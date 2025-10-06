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
        // Registra las policies declaradas arriba
        $this->registerPolicies();

        // Roles administradores y “super habilidad” desde config (con defaults)
        $adminRoles = (array) config('auth.admin_roles', ['admin']);
        $superAbility = (string) config('auth.super_ability', 'superadmin'); // permiso que otorga TODO

        /**
         * Gate::before => atajo: si el usuario es superadmin o admin, autoriza todo.
         * IMPORTANTE: evitar $user->can($superAbility) para no provocar recursión.
         */
        Gate::before(function (?User $user, string $ability) use ($adminRoles, $superAbility) {
            if (!$user) {
                return null; // invitado => seguir flujo normal
            }

            // 1) Super habilidad sin pasar por Gate (Spatie o flag propio)
            if ($superAbility) {
                // Spatie Permissions (si lo usás)
                if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($superAbility)) {
                    return true;
                }
                // Alternativa si NO usás Spatie: booleano/atributo en users
                if (
                    (property_exists($user, 'is_superadmin') && $user->is_superadmin)
                    || (isset($user->is_superadmin) && (bool) $user->is_superadmin)
                ) {
                    return true;
                }
            }

            // 2) Roles admin (Spatie)
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

            // 3) Campo plano 'role' en users
            if (isset($user->role) && in_array($user->role, $adminRoles, true)) {
                return true;
            }

            return null; // seguimos con la evaluación normal
        });

        /**
         * Gate: 'admin' => acceso de administración.
         * Devuelve Response con mensaje para un 403 más claro.
         */
        Gate::define('admin', function (?User $user) use ($adminRoles) {
            if (!$user) {
                return Response::deny('Necesitás iniciar sesión.');
            }

            // Spatie (roles)
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($adminRoles)) {
                return Response::allow();
            }
            if (method_exists($user, 'hasRole')) {
                foreach ($adminRoles as $role) {
                    if ($user->hasRole($role)) {
                        return Response::allow();
                    }
                }
            }

            // Campo 'role' plano
            if (isset($user->role) && in_array($user->role, $adminRoles, true)) {
                return Response::allow();
            }

            return Response::deny('Solo administradores.');
        });

        /**
         * Ejemplo de gate granular (ajustá/duplicá según tu dominio).
         * Útil si querés chequear permisos sin escribir una Policy completa.
         */
        Gate::define('manage-tables', function (?User $user) {
            if (!$user) {
                return Response::deny('Necesitás iniciar sesión.');
            }

            // Los admins pasan directo (Gate::before ya los autoriza, pero esto lo hace explícito)
            if (Gate::forUser($user)->allows('admin')) {
                return Response::allow();
            }

            // Si usás Spatie/permissions (permiso nominal)
            if (
                (method_exists($user, 'can') && $user->can('manage tables'))
                || (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('manage tables'))
            ) {
                return Response::allow();
            }

            return Response::deny('No tenés permisos para administrar mesas.');
        });

        /**
         * Logging de decisiones en entornos de desarrollo/test
         * (ayuda a entender por qué se denegó algo).
         */
        if (app()->environment(['local', 'testing'])) {
            Gate::after(function (?User $user, string $ability, ?bool $result, array $arguments = []) {
                if ($result === false) {
                    Log::info('Gate denegada', [
                        'user_id' => $user?->id,
                        'ability' => $ability,
                        'args' => $arguments,
                        'user_role' => $user->role ?? null,
                    ]);
                }
                return null; // no sobreescribe el resultado
            });
        }
    }
}
