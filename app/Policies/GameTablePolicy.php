<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameTable;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

final class GameTablePolicy
{
    use HandlesAuthorization;

    /**
     * Admins tienen acceso total a cualquier acción.
     */
    public function before(?User $user, string $ability): ?Response
    {
        if (!$user) {
            return null;
        }

        // Usa el gate "admin" o métodos equivalentes
        if (Gate::forUser($user)->allows('admin')) {
            return Response::allow();
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return Response::allow();
        }

        if (($user->role ?? null) === 'admin') {
            return Response::allow();
        }

        return null;
    }

    /**
     * Ver listado de mesas (público).
     */
    public function viewAny(?User $user): Response
    {
        return Response::allow();
    }

    /**
     * Ver una mesa específica.
     */
    public function view(?User $user, GameTable $mesa): Response
    {
        return Response::allow();
    }

    /**
     * Crear mesas (requiere sesión iniciada).
     */
    public function create(?User $user): Response
    {
        return $user
            ? Response::allow()
            : Response::deny('Debes iniciar sesión para crear una mesa.');
    }

    /**
     * Actualizar mesa: creador, manager o admin.
     */
    public function update(?User $user, GameTable $mesa): Response
    {
        return $this->checkOwnershipOrDeny($user, $mesa, 'editar esta mesa');
    }

    /**
     * Eliminar mesa.
     */
    public function delete(?User $user, GameTable $mesa): Response
    {
        return $this->checkOwnershipOrDeny($user, $mesa, 'eliminar esta mesa');
    }

    /**
     * Abrir mesa (solo dueño o encargado).
     */
    public function open(?User $user, GameTable $mesa): Response
    {
        return $this->checkOwnershipOrDeny($user, $mesa, 'abrir esta mesa');
    }

    /**
     * Cerrar mesa (solo dueño o encargado).
     */
    public function close(?User $user, GameTable $mesa): Response
    {
        return $this->checkOwnershipOrDeny($user, $mesa, 'cerrar esta mesa');
    }

    public function restore(?User $user, GameTable $mesa): Response
    {
        return $this->checkOwnershipOrDeny($user, $mesa, 'restaurar esta mesa');
    }

    public function forceDelete(?User $user, GameTable $mesa): Response
    {
        return $this->checkOwnershipOrDeny($user, $mesa, 'eliminar permanentemente esta mesa');
    }

    /* ========================= Helpers ========================= */

    private function checkOwnershipOrDeny(?User $user, GameTable $mesa, string $action): Response
    {
        if (!$user) {
            return Response::deny("Debes iniciar sesión para poder {$action}.");
        }

        $uid = (int) $user->getAuthIdentifier();
        $isOwner = (int) ($mesa->created_by ?? 0) === $uid;
        $isManager = (int) ($mesa->manager_id ?? 0) === $uid;

        if ($isOwner || $isManager) {
            return Response::allow();
        }

        return Response::deny("Solo el creador o el encargado pueden {$action}.");
    }
}
