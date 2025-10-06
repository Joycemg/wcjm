<?php declare(strict_types=1);

namespace App\Services;

use App\Models\GameTable;
use App\Models\HonorEvent;
use App\Models\Signup;
use App\Models\User;

/**
 * Reglas de honor (idempotentes vía slug).
 * Optimizado para hosting compartido: cargas mínimas y sin N+1.
 */
final class HonorRules
{
    /**
     * Confirmar asistencia: +10 (slug único por mesa+signup).
     */
    public function confirmAttendance(Signup $signup, User $manager): HonorEvent
    {
        [$user, $mesa] = $this->loadSignupMin($signup);

        $slug = "mesa:{$mesa->id}:signup:{$signup->id}:attended";

        return $user->addHonor(
            10,
            HonorEvent::R_ATTEND_OK,
            ['mesa_id' => (int) $mesa->id, 'signup_id' => (int) $signup->id, 'by' => (int) $manager->id],
            $slug
        );
    }

    /**
     * No show: -20 (slug único por mesa+signup).
     */
    public function noShow(Signup $signup, User $manager): HonorEvent
    {
        [$user, $mesa] = $this->loadSignupMin($signup);

        $slug = "mesa:{$mesa->id}:signup:{$signup->id}:no_show";

        return $user->addHonor(
            -20,
            HonorEvent::R_NO_SHOW,
            ['mesa_id' => (int) $mesa->id, 'signup_id' => (int) $signup->id, 'by' => (int) $manager->id],
            $slug
        );
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
            'good' => 10,
            'bad' => -10,
            default => throw new \InvalidArgumentException('Tipo de comportamiento inválido.'),
        };

        $reason = $type === 'good'
            ? HonorEvent::R_BEHAV_GOOD
            : HonorEvent::R_BEHAV_BAD;

        // Un encargado (manager) puede votar una sola vez por tipo para ese signup
        $slug = "mesa:{$mesa->id}:signup:{$signup->id}:behavior:{$type}:by:{$manager->id}";

        return $user->addHonor(
            $points,
            $reason,
            ['mesa_id' => (int) $mesa->id, 'signup_id' => (int) $signup->id, 'by' => (int) $manager->id],
            $slug
        );
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
        // Detecta el nombre de la relación a GameTable (alias compatible)
        $mesaRel = method_exists($signup, 'mesa') ? 'mesa'
            : (method_exists($signup, 'gameTable') ? 'gameTable' : null);

        // Cargar mínimamente si faltan
        $relations = [];
        if (!$signup->relationLoaded('user')) {
            $relations['user'] = fn($q) => $q->select('id');
        }
        if ($mesaRel && !$signup->relationLoaded($mesaRel)) {
            $relations[$mesaRel] = fn($q) => $q->select('id');
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
            // Último intento directo, muy raro:
            $mesa = $signup->{$mesaRel ?? 'gameTable'}()->select('id')->first();
        }
        if (!$mesa instanceof GameTable) {
            throw new \RuntimeException('El signup no tiene mesa asociada.');
        }

        return [$user, $mesa];
    }
}
