<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\GameTable;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GameTablePolicy
{
    use HandlesAuthorization;

    /**
     * Puede gestionar (editar/abrir/cerrar/eliminar) la mesa si:
     * - Es su creador, o
     * - Es el manager asignado, o
     * - Es admin (role = 'admin' o método isAdmin()).
     */
    public function manage(?User $user, GameTable $mesa): bool
    {
        if (!$user) {
            return false;
        }

        $uid = (int) $user->getAuthIdentifier();
        $isOwner = (int) ($mesa->created_by ?? 0) === $uid;
        $isManager = (int) ($mesa->manager_id ?? 0) === $uid;

        // Soporta tanto método isAdmin() como propiedad role='admin'
        $isAdmin = \method_exists($user, 'isAdmin')
            ? $user->isAdmin()
            : (($user->role ?? null) === 'admin');

        return $isOwner || $isManager || $isAdmin;
    }

    /** Alias comunes que delegan en manage() */
    public function view(?User $user, GameTable $mesa): bool
    {
        // Ver generalmente es público; ajusta si tu app requiere restricción.
        return true;
    }

    public function create(?User $user): bool
    {
        return (bool) $user;
    }

    public function update(?User $user, GameTable $mesa): bool
    {
        return $this->manage($user, $mesa);
    }

    public function delete(?User $user, GameTable $mesa): bool
    {
        return $this->manage($user, $mesa);
    }

    public function open(?User $user, GameTable $mesa): bool
    {
        return $this->manage($user, $mesa);
    }

    public function close(?User $user, GameTable $mesa): bool
    {
        return $this->manage($user, $mesa);
    }
}
