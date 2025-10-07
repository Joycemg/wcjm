<?php declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class HonorDecayInactivity extends Command
{
    /**
     * Uso:
     *  php artisan honor:decay-inactivity
     *  php artisan honor:decay-inactivity --dry
     *  php artisan honor:decay-inactivity --period=2025-09
     *  php artisan honor:decay-inactivity --year=2025 --month=9
     *  php artisan honor:decay-inactivity --points=-10
     *  php artisan honor:decay-inactivity --tz=America/Argentina/Buenos_Aires
     */
    protected $signature = 'honor:decay-inactivity
        {--dry : Simular sin escribir}
        {--period= : Periodo YYYY-MM (prioridad sobre year/month)}
        {--year= : Año a procesar (YYYY)}
        {--month= : Mes a procesar (1-12)}
        {--points=-10 : Puntos a aplicar (negativos)}
        {--tz= : Timezone a usar para los límites del mes (por defecto: config("app.timezone"))}';

    protected $description = 'Penaliza usuarios sin actividad (signups is_counted=1) en el mes indicado. Inserción masiva e idempotente por slug.';

    public function handle(): int
    {
        // --- 0) Guardas de esquema (no rompas si faltan tablas en entornos incompletos) ---
        if (!Schema::hasTable('users')) {
            $this->error('La tabla "users" no existe. Abortando.');
            return self::INVALID;
        }
        if (!Schema::hasTable('signups')) {
            $this->error('La tabla "signups" no existe. Abortando.');
            return self::INVALID;
        }
        if (!Schema::hasTable('honor_events')) {
            $this->error('La tabla "honor_events" no existe. Abortando.');
            return self::INVALID;
        }

        // --- 1) Resolver TZ y validar ---
        $appTz = (string) ($this->option('tz') ?: config('app.timezone', 'UTC'));
        // Carbon lanza en usos posteriores si el TZ no es válido; avisamos temprano:
        try {
            CarbonImmutable::now($appTz);
        } catch (\Throwable $e) {
            $this->error("Timezone inválido para --tz: {$appTz}");
            return self::INVALID;
        }

        // --- 2) Resolver período (YYYY-MM) ---
        [$year, $month] = $this->resolvePeriod(
            (string) $this->option('period'),
            (string) $this->option('year'),
            (string) $this->option('month'),
            $appTz
        );

        // [from, to) en TZ local, luego normalizamos a UTC (típico en Laravel)
        $fromLocal = CarbonImmutable::createFromFormat('Y-n-j H:i:s', sprintf('%04d-%d-1 00:00:00', $year, $month), $appTz);
        $toLocal = $fromLocal->addMonth(); // mes calendario siguiente (semi-abierto)
        $fromUtc = $fromLocal->utc();
        $toUtc = $toLocal->utc();

        // --- 3) Normalizar puntos y slug/razón ---
        $points = (int) $this->option('points');
        if ($points >= 0) {
            $this->warn("Se recomienda que --points sea negativo para decaimiento. Valor recibido: {$points}");
        }

        $slug = sprintf('decay:inactivity:%04d-%02d', $year, $month);

        // Razón (si existe constante, usala)
        $reasonConst = 'App\Models\HonorEvent::R_DECAY';
        $reason = \defined($reasonConst) ? \constant($reasonConst) : 'decay:inactivity';

        $meta = ['year' => $year, 'month' => $month];
        $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $dry = (bool) $this->option('dry');

        $this->info(sprintf(
            'Periodo %04d-%02d (tz=%s) → [%s … %s) UTC | slug=%s%s',
            $year,
            $month,
            $appTz,
            $fromUtc->toDateTimeString(),
            $toUtc->toDateTimeString(),
            $slug,
            $dry ? ' [DRY]' : ''
        ));

        // --- 4) Subconsulta de “usuarios activos” en el mes (signups contados) ---
        // Compatibilidad: si falta alguna columna, ajustá acá.
        $activeSub = DB::table('signups')
            ->select('user_id')
            ->where('is_counted', 1)
            ->where('created_at', '>=', $fromUtc)
            ->where('created_at', '<', $toUtc)
            ->distinct();

        // --- 5) Candidatos inactivos sin evento previo para este slug (idempotencia) ---
        $candidates = DB::table('users as u')
            ->leftJoinSub($activeSub, 's', 's.user_id', '=', 'u.id')
            ->leftJoin('honor_events as he', function ($j) use ($slug) {
                $j->on('he.user_id', '=', 'u.id')->where('he.slug', '=', $slug);
            })
            // Usuarios que existían antes de que terminara el mes
            ->where('u.created_at', '<', $toUtc)
            // Sin actividad en el mes
            ->whereNull('s.user_id')
            // Sin honor_event previo con este slug (idempotente)
            ->whereNull('he.user_id');

        // --- 6) DRY-RUN: conteo + muestra (hasta 50 IDs) ---
        if ($dry) {
            $count = (clone $candidates)->count('u.id');
            $this->line('Usuarios inactivos a penalizar: ' . $count);

            if ($count > 0) {
                $sample = (clone $candidates)->orderBy('u.id')->limit(50)->pluck('u.id')->all();
                $rows = array_map(static fn($id) => ['user_id' => $id], $sample);
                $this->table(['user_id (muestra máx 50)'], $rows);
            }

            return self::SUCCESS;
        }

        // --- 7) Inserción MASIVA idempotente (INSERT … SELECT) ---
        // Recomendado: índice único en honor_events (user_id, slug).
        $select = (clone $candidates)
            ->selectRaw('u.id as user_id')
            ->selectRaw('? as points', [$points])
            ->selectRaw('? as reason', [$reason])
            ->selectRaw('? as meta', [$metaJson])
            ->selectRaw('? as slug', [$slug])
            ->selectRaw('CURRENT_TIMESTAMP as created_at')
            ->selectRaw('CURRENT_TIMESTAMP as updated_at');

        // Guardamos IDs planeados (puede ayudar a refrescar agregados)
        $candidateIds = (clone $candidates)
            ->select('u.id as candidate_id')
            ->orderBy('u.id')
            ->pluck('candidate_id')
            ->map(static fn($id) => (int) $id)
            ->all();

        if ($candidateIds === []) {
            $this->info('No hay usuarios a penalizar. Listo.');
            return self::SUCCESS;
        }

        DB::transaction(static function () use ($select) {
            DB::table('honor_events')->insertUsing(
                ['user_id', 'points', 'reason', 'meta', 'slug', 'created_at', 'updated_at'],
                $select
            );
        });

        // --- 8) Refrescar agregado users.honor (si existe) por lotes ---
        $this->refreshUsersHonorAggregate($candidateIds);

        $this->info('Penalizaciones aplicadas nuevas (planificadas): ' . count($candidateIds));
        return self::SUCCESS;
    }

    /**
     * Resuelve (año, mes) con prioridad:
     *  --period (YYYY-MM) > --year/--month > mes anterior en TZ.
     */
    private function resolvePeriod(string $periodOpt, string $yearOpt, string $monthOpt, string $tz): array
    {
        // --period=YYYY-MM
        if ($periodOpt !== '') {
            try {
                $p = CarbonImmutable::createFromFormat('Y-m', $periodOpt, $tz)->startOfMonth();
                return [$p->year, (int) $p->format('n')];
            } catch (InvalidFormatException) {
                $this->warn("Formato inválido en --period='{$periodOpt}', se usa fallback.");
            } catch (\Throwable) {
                $this->warn("No se pudo parsear --period='{$periodOpt}', se usa fallback.");
            }
        }

        // --year + --month (validación básica)
        if ($yearOpt !== '' && $monthOpt !== '') {
            $y = (int) $yearOpt;
            $m = (int) $monthOpt;
            if ($y >= 1970 && $m >= 1 && $m <= 12) {
                return [$y, $m];
            }
            $this->warn("Parámetros --year/--month inválidos (year={$yearOpt}, month={$monthOpt}), se usa fallback.");
        }

        // Fallback: mes anterior al actual (TZ)
        $now = CarbonImmutable::now($tz)->startOfMonth()->subMonthNoOverflow();
        return [$now->year, (int) $now->format('n')];
    }

    /**
     * Si existe users.honor, refresca con la suma de honor_events.points (por lotes).
     */
    private function refreshUsersHonorAggregate(array $userIds): void
    {
        if ($userIds === [] || !Schema::hasColumn('users', 'honor')) {
            return;
        }

        $now = now();
        // Subquery independiente del driver
        $sub = 'SELECT COALESCE(SUM(points), 0) FROM honor_events WHERE honor_events.user_id = users.id';

        foreach (array_chunk($userIds, 500) as $chunk) {
            if ($chunk === []) {
                continue;
            }

            DB::table('users')
                ->whereIn('id', $chunk)
                ->update([
                    'honor' => DB::raw("({$sub})"),
                    'updated_at' => $now,
                ]);
        }
    }
}
