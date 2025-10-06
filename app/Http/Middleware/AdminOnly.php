<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Middleware de acceso por rol/ability.
 *
 * Uso básico (igual que tu AdminOnly clásico):
 *   ->middleware('admin.only')
 *
 * Con roles explícitos:
 *   ->middleware('admin.only:admin,moderator,staff')
 *
 * Con abilities/policies:
 *   ->middleware('admin.only:can:manage-tables')
 *   ->middleware('admin.only:can:update,can:delete') // varias
 *
 * Requerir rol Y ability (no OR):
 *   ->middleware('admin.only:mode:all,admin,can:manage-tables')
 *
 * Notas:
 * - Si no pasás parámetros, toma roles de config('auth.admin_roles', ['admin']).
 * - Si el cliente espera JSON, devuelve JSON (401/403). Si no, redirige (login o back).
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next, ...$params): Response
    {
        $user = $request->user();

        // 1) Usuario no autenticado => 401
        if (!$user) {
            return $this->deny($request, 401, 'Debes iniciar sesión para acceder.');
        }

        // 2) Parseo de parámetros (roles / abilities / modo)
        $requireAll = $this->extractRequireAll($params);           // mode:all => true
        [$roles, $abilities] = $this->extractRolesAndAbilities($params);

        // Si no se pasaron roles ni abilities, caemos a config por defecto
        if (empty($roles) && empty($abilities)) {
            $roles = config('auth.admin_roles', ['admin']);
        }

        // 3) Chequeos
        $okRole = !empty($roles) ? $this->userHasAnyRole($user, $roles) : false;
        $okAbility = !empty($abilities) ? $this->userHasAnyAbility($user, $abilities) : false;

        $authorized = $requireAll
            ? ($this->boolOrNull($okRole, !empty($roles)) && $this->boolOrNull($okAbility, !empty($abilities)))
            : ($okRole || $okAbility || (empty($roles) && empty($abilities))); // por si quedaron ambos vacíos

        if (!$authorized) {
            $this->logDenied($request, $user, $roles, $abilities, $requireAll);
            return $this->deny($request, 403, 'Solo administradores.');
        }

        return $next($request);
    }

    /* =========================
     * Helpers de autorización
     * ========================= */

    private function extractRequireAll(array $params): bool
    {
        foreach ($params as $p) {
            if (is_string($p) && str_starts_with($p, 'mode:')) {
                return trim(substr($p, strlen('mode:'))) === 'all';
            }
        }
        return false;
    }

    /**
     * Separa parámetros en listas de roles y abilities.
     * Sintaxis de ability: "can:abilityName"
     */
    private function extractRolesAndAbilities(array $params): array
    {
        $roles = [];
        $abilities = [];

        foreach ($params as $p) {
            if (!is_string($p) || $p === '')
                continue;

            if (str_starts_with($p, 'mode:')) {
                // ya procesado en extractRequireAll
                continue;
            }

            if (str_starts_with($p, 'can:')) {
                $ability = trim(substr($p, strlen('can:')));
                if ($ability !== '') {
                    $abilities[] = $ability;
                }
                continue;
            }

            // Es un rol
            $roles[] = trim($p);
        }

        // Normaliza (quita vacíos y duplica)
        $roles = array_values(array_unique(array_filter($roles, fn($r) => $r !== '')));
        $abilities = array_values(array_unique(array_filter($abilities, fn($a) => $a !== '')));

        return [$roles, $abilities];
    }

    private function userHasAnyRole($user, array $roles): bool
    {
        // 1) Spatie/otros: métodos helper si existen
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }
        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $r) {
                if ($user->hasRole($r))
                    return true;
            }
        }

        // 2) Campo 'role' plano (string)
        if (isset($user->role) && is_string($user->role)) {
            return in_array($user->role, $roles, true);
        }

        // 3) Relación/campo 'roles' (array/collection)
        if (isset($user->roles)) {
            $userRoles = $user->roles;
            // Soporta array de strings o Collection de modelos con ->name
            if (is_array($userRoles)) {
                return count(array_intersect($roles, $userRoles)) > 0;
            }
            if ($userRoles instanceof \Illuminate\Support\Collection) {
                $names = $userRoles->map(function ($r) {
                    return is_string($r) ? $r : ($r->name ?? null);
                })->filter()->values()->all();
                return count(array_intersect($roles, $names)) > 0;
            }
        }

        return false;
    }

    private function userHasAnyAbility($user, array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($user->can($ability)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Si una sección (roles/abilities) está presente, su booleano debe respetarse.
     * Si NO está presente, lo tratamos como "no aplica" y devolvemos true para el AND.
     */
    private function boolOrNull(bool $value, bool $present): bool
    {
        return $present ? $value : true;
    }

    /* =========================
     * Helpers de respuesta y logging
     * ========================= */

    private function deny(Request $request, int $status, string $message): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        // 401 => a login si existe ruta; 403 => back con error
        if ($status === 401 && Route::has('login')) {
            return redirect()->guest(route('login'))->withErrors(['auth' => $message]);
        }

        // Redirige al back con flash (si hay referrer) o muestra 403.
        if ($status === 403 && url()->previous() !== url()->current()) {
            return redirect()->back()->with('err', $message);
        }

        // Fallback controlado (sin abort()) para mantener consistencia.
        return response()->view('errors.generic', [
            'code' => $status,
            'message' => $message,
        ], $status);
    }

    private function logDenied(Request $request, $user, array $roles, array $abilities, bool $requireAll): void
    {
        try {
            Log::warning('Acceso denegado por AdminOnly', [
                'user_id' => $user->id ?? null,
                'user_role' => $user->role ?? null,
                'required' => [
                    'mode' => $requireAll ? 'all' : 'any',
                    'roles' => $roles,
                    'abilities' => $abilities,
                ],
                'route' => optional($request->route())->getName(),
                'uri' => $request->getRequestUri(),
                'ip' => $request->ip(),
                'agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // No romper flujo si logging falla
        }
    }
}
