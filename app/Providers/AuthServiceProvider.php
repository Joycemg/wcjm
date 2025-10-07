<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de Policies (completá según tus modelos).
     *
     * Ejemplo:
     * protected $policies = [
     *     \App\Models\GameTable::class => \App\Policies\GameTablePolicy::class,
     * ];
     */
    protected $policies = [
        \App\Models\GameTable::class => \App\Policies\GameTablePolicy::class,

    ];

    public function boot(): void
    {
        $this->registerPolicies();

        $adminRoles = (array) config('auth.admin_roles', ['admin']);
        $superAbility = (string) config('auth.super_ability', 'superadmin');

        $this->configureGateOverrides($adminRoles, $superAbility);
        $this->defineAdminGate($adminRoles);
        $this->defineManageTablesGate();
        $this->logDeniedGateDecisions();
    }

    private function configureGateOverrides(array $adminRoles, string $superAbility): void
    {
        Gate::before(function (?User $user) use ($adminRoles, $superAbility) {
            if (!$user) {
                return null;
            }

            if ($superAbility && $this->userHasAbility($user, $superAbility)) {
                return true;
            }

            if ($this->userIsAdmin($user, $adminRoles)) {
                return true;
            }

            return null;
        });
    }

    private function defineAdminGate(array $adminRoles): void
    {
        Gate::define('admin', function (?User $user) use ($adminRoles) {
            if (!$user) {
                return Response::deny('Necesitás iniciar sesión.');
            }

            return $this->userIsAdmin($user, $adminRoles)
                ? Response::allow()
                : Response::deny('Solo administradores.');
        });
    }

    private function defineManageTablesGate(): void
    {
        Gate::define('manage-tables', function (?User $user) {
            if (!$user) {
                return Response::deny('Necesitás iniciar sesión.');
            }

            if (Gate::forUser($user)->allows('admin')) {
                return Response::allow();
            }

            if (
                (method_exists($user, 'can') && $user->can('manage tables'))
                || (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('manage tables'))
            ) {
                return Response::allow();
            }

            return Response::deny('No tenés permisos para administrar mesas.');
        });
    }

    private function logDeniedGateDecisions(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            return;
        }

        Gate::after(function (?User $user, string $ability, ?bool $result, array $arguments = []) {
            if ($result === false) {
                Log::info('Gate denegada', [
                    'user_id' => $user?->id,
                    'ability' => $ability,
                    'args' => $arguments,
                    'user_role' => $user->role ?? null,
                ]);
            }

            return null;
        });
    }

    private function userIsAdmin(User $user, array $adminRoles): bool
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($adminRoles)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($adminRoles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return isset($user->role) && in_array($user->role, $adminRoles, true);
    }

    private function userHasAbility(User $user, string $superAbility): bool
    {
        if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($superAbility)) {
            return true;
        }

        return (
            (property_exists($user, 'is_superadmin') && $user->is_superadmin)
            || (isset($user->is_superadmin) && (bool) $user->is_superadmin)
        );
    }
}
