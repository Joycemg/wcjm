<?php declare(strict_types=1);

namespace App\Services;

use App\Models\GameTable;
use App\Models\HonorEvent;
use App\Models\Signup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de honor (idempotentes vía slug).
 * Optimizado para hosting compartido: cargas mínimas y sin N+1.
 *
 * Notas:
 * - Mantiene firmas públicas para compatibilidad.
 * - Usa transacciones cortas con reintento ante deadlocks/timeouts.
 * - Intenta serializar cambios por usuario con lock condicional (si el driver lo soporta).
 */
final class HonorRules
{
    // ====== Puntos por regla (centralizados) ======
    private const P_ATTEND_OK = 10;
    private const P_NO_SHOW = -20;
    private const P_BEHAV_GOOD = 10;
    private const P_BEHAV_BAD = -10;

    // ====== SQLSTATE típicos para reintentar (MySQL/Postgres) ======
    private const SQLSTATE_RETRYABLE = [
        '40001', // Serialization failure
        '40P01', // Deadlock detected (PG)
        '55P03', // Lock not available (PG)
        'HY000', // MySQL genérico (a veces deadlock)
    ];
    private const MYSQL_RETRYABLE_ERRNO = [1205, 1213]; // lock wait timeout / deadlock

    /**
     * Confirmar asistencia: +10 (slug único por mesa+signup).
     */
    public function confirmAttendance(Signup $signup, User $manager): HonorEvent
    {
        [$user, $mesa] = $this->loadSignupMin($signup);

        $slug = $this->slugAttend($mesa->id, $signup->id);

        return $this->txWithRetry(function () use ($user, $mesa, $signup, $manager, $slug) {
            $this->lockUserIfSupported($user->getKey());

            $event = $user->addHonor(
                self::P_ATTEND_OK,
                HonorEvent::R_ATTEND_OK,
                $this->meta($mesa->id, $signup->id, $manager->id),
                $slug
            );

            $changed = (bool) $event->wasRecentlyCreated;

            // Quita side-effects incompatibles
            $changed = $this->removeHonorBySlug($user, $this->slugNoShow($mesa->id, $signup->id)) || $changed;
            $changed = $this->removeHonorBySlug($user, $slug . ':undo') || $changed;

            $this->refreshHonorAggregateIfNeeded($user, $changed);

            return $event;
        });
    }

    /**
     * No show: -20 (slug único por mesa+signup).
     */
    public function noShow(Signup $signup, User $manager): HonorEvent
    {
        [$user, $mesa] = $this->loadSignupMin($signup);

        $slug = $this->slugNoShow($mesa->id, $signup->id);

        return $this->txWithRetry(function () use ($user, $mesa, $signup, $manager, $slug) {
            $this->lockUserIfSupported($user->getKey());

            $event = $user->addHonor(
                self::P_NO_SHOW,
                HonorEvent::R_NO_SHOW,
                $this->meta($mesa->id, $signup->id, $manager->id),
                $slug
            );

            $this->refreshHonorAggregateIfNeeded($user, (bool) $event->wasRecentlyCreated);

            return $event;
        });
    }

    /**
     * Comportamiento: good => +10, bad => -10.
     * Slug permite 1 registro por (mesa, signup, tipo, manager).
     *
     * @param 'good'|'bad' $type
     */
    public function behavior(Signup $signup, User $manager, string $type): HonorEvent
    {
        [$user, $mesa] = $this->loadSignupMin($signup);

        $points = match ($type) {
            'good' => self::P_BEHAV_GOOD,
            'bad' => self::P_BEHAV_BAD,
            default => throw new \InvalidArgumentException('Tipo de comportamiento inválido.'),
        };

        $reason = $type === 'good'
            ? HonorEvent::R_BEHAV_GOOD
            : HonorEvent::R_BEHAV_BAD;

        $slug = $this->slugBehavior($mesa->id, $signup->id, $type, $manager->id);

        return $this->txWithRetry(function () use ($user, $mesa, $signup, $manager, $points, $reason, $slug, $type) {
            $this->lockUserIfSupported($user->getKey());

            $event = $user->addHonor(
                $points,
                $reason,
                $this->meta($mesa->id, $signup->id, $manager->id),
                $slug
            );

            $changed = (bool) $event->wasRecentlyCreated;

            // Limpia “undos” del mismo tipo si existieran
            $undoSlug = $this->slugBehaviorUndo($mesa->id, $signup->id, $type);
            $changed = $this->removeHonorBySlug($user, $undoSlug) || $changed;

            $this->refreshHonorAggregateIfNeeded($user, $changed);

            return $event;
        });
    }

    /* ========================= Helpers ========================= */

    /**
     * Carga mínima y segura de relaciones requeridas del Signup:
     * - user (id suficiente)
     * - mesa / gameTable (id suficiente, soporta alias "mesa" o "gameTable")
     *
     * @return array{0: User, 1: GameTable}
     */
    private function loadSignupMin(Signup $signup): array
    {
        $mesaRel = method_exists($signup, 'mesa') ? 'mesa'
            : (method_exists($signup, 'gameTable') ? 'gameTable' : null);

        $relations = [];
        if (!$signup->relationLoaded('user')) {
            $relations['user'] = static fn($q) => $q->select('id');
        }
        if ($mesaRel && !$signup->relationLoaded($mesaRel)) {
            $relations[$mesaRel] = static fn($q) => $q->select('id');
        }

        if ($relations) {
            $signup->load($relations);
        }

        /** @var User|null $user */
        $user = $signup->getRelationValue('user');
        if (!$user instanceof User) {
            throw new \RuntimeException('El signup no tiene usuario asociado.');
        }

        /** @var GameTable|null $mesa */
        $mesa = $mesaRel ? $signup->getRelationValue($mesaRel) : null;
        if (!$mesa instanceof GameTable) {
            // Intento final mínimo
            $rel = $mesaRel ?? 'gameTable';
            $mesa = method_exists($signup, $rel)
                ? $signup->{$rel}()->select('id')->first()
                : null;
        }
        if (!$mesa instanceof GameTable) {
            throw new \RuntimeException('El signup no tiene mesa asociada.');
        }

        return [$user, $mesa];
    }

    /** Devuelve el payload meta común. */
    private function meta(int $mesaId, int $signupId, int $by): array
    {
        return ['mesa_id' => $mesaId, 'signup_id' => $signupId, 'by' => $by];
    }

    /** Slug: asistencia OK. */
    private function slugAttend(int $mesaId, int $signupId): string
    {
        return "mesa:{$mesaId}:signup:{$signupId}:attended";
    }

    /** Slug: no show. */
    private function slugNoShow(int $mesaId, int $signupId): string
    {
        return "mesa:{$mesaId}:signup:{$signupId}:no_show";
    }

    /** Slug: comportamiento (por tipo + manager). */
    private function slugBehavior(int $mesaId, int $signupId, string $type, int $by): string
    {
        return "mesa:{$mesaId}:signup:{$signupId}:behavior:{$type}:by:{$by}";
    }

    /** Slug: undo de comportamiento (histórico/compat). */
    private function slugBehaviorUndo(int $mesaId, int $signupId, string $type): string
    {
        return "mesa:{$mesaId}:signup:{$signupId}:behavior:undo:{$type}";
    }

    private function refreshHonorAggregateIfNeeded(User $user, bool $changed): void
    {
        if ($changed && method_exists($user, 'refreshHonorAggregate')) {
            // true := forzar recálculo si tu implementación lo admite
            $user->refreshHonorAggregate(true);
        }
    }

    /**
     * Borra un evento de honor por slug.
     * Devuelve true si se borró algo, false si no.
     */
    private function removeHonorBySlug(User $user, string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        if (method_exists($user, 'removeHonorEventBySlug')) {
            return $user->removeHonorEventBySlug($slug);
        }

        return HonorEvent::query()
            ->where('user_id', $user->getKey())
            ->where('slug', $slug)
            ->limit(1)
            ->delete() > 0;
    }

    /* ========================= Concurrencia / Transacciones ========================= */

    /**
     * Lock condicional del usuario para serializar actualizaciones de honor.
     * Ignora en drivers que no soportan FOR UPDATE (sqlite/sqlsrv).
     */
    private function lockUserIfSupported(int $userId): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['sqlite', 'sqlsrv'], true)) {
            return;
        }

        // Lee y bloquea sólo la PK (mínimo ancho).
        DB::table((new User())->getTable())
            ->select('id')
            ->where('id', $userId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Transacción corta con reintento ante deadlocks / timeouts.
     * (Si ya tenés App\Support\DatabaseUtils::transactionWithRetry, podés reemplazar
     *  el cuerpo por: return DatabaseUtils::transactionWithRetry($callback);)
     *
     * @template TReturn
     * @param  \Closure():TReturn $callback
     * @param  int                $maxAttempts
     * @param  int                $baseBackoffMs
     * @return TReturn
     * @throws \Throwable
     */
    private function txWithRetry(\Closure $callback, int $maxAttempts = 3, int $baseBackoffMs = 100)
    {
        $attempt = 0;

        beginning:
        $attempt++;

        try {
            return DB::transaction($callback);
        } catch (QueryException $e) {
            if (!$this->isRetryable($e) || $attempt >= $maxAttempts) {
                throw $e;
            }

            // Backoff exponencial con jitter ±20%
            $exp = $baseBackoffMs * (2 ** ($attempt - 1));
            $jitter = (int) round($exp * (0.2 * (mt_rand(-100, 100) / 100)));
            $sleepMs = max(10, $exp + $jitter);
            usleep($sleepMs * 1000);

            goto beginning;
        }
    }

    /** ¿La excepción es candidata a reintento? */
    private function isRetryable(QueryException $e): bool
    {
        $state = strtoupper((string) ($e->errorInfo[0] ?? ''));
        $errno = (int) ($e->errorInfo[1] ?? 0);

        return in_array($state, self::SQLSTATE_RETRYABLE, true)
            || in_array($errno, self::MYSQL_RETRYABLE_ERRNO, true);
    }
}
