<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Acá podés bindear singletons/clients si los necesitás.
        // Mantengo limpio por defecto.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /** ------------------------------
         *  UI / Navegación
         * ------------------------------ */
        // Paginación con Tailwind (Breeze/Jetstream/Blade por defecto)
        Paginator::useTailwind();

        // Forzar HTTPS en prod si lo configurás
        // APP_FORCE_HTTPS=true o config('app.force_https')=true
        if (config('app.force_https', (bool) env('APP_FORCE_HTTPS', false))) {
            URL::forceScheme('https');
        }

        /** ------------------------------
         *  Fechas / Locale
         * ------------------------------ */
        try {
            Carbon::setLocale(config('app.locale', 'es'));
        } catch (\Throwable $e) {
            // no romper si el locale no existe
        }
        if (method_exists(Carbon::class, 'setUtf8')) {
            try {
                Carbon::setUtf8(true);
            } catch (\Throwable $e) {
            }
        }

        /** ------------------------------
         *  Eloquent "strict mode" en dev/test
         *  (detecta N+1, atributos faltantes, etc.)
         * ------------------------------ */
        $strict = app()->environment(['local', 'testing']);

        if (method_exists(Model::class, 'shouldBeStrict')) {
            // Laravel 10/11+: activa todo el modo estricto
            Model::shouldBeStrict($strict);
        } else {
            // Versiones previas: activar de a uno si existen
            if (method_exists(Model::class, 'preventLazyLoading')) {
                Model::preventLazyLoading($strict);
            }
            if (method_exists(Model::class, 'preventSilentlyDiscardingAttributes')) {
                Model::preventSilentlyDiscardingAttributes($strict);
            }
            if (method_exists(Model::class, 'preventAccessingMissingAttributes')) {
                Model::preventAccessingMissingAttributes($strict);
            }
        }

        // Log útil si ocurre lazy loading (te señala el modelo y la relación)
        if ($strict && method_exists(Model::class, 'handleLazyLoadingViolationUsing')) {
            Model::handleLazyLoadingViolationUsing(function ($model, string $relation): void {
                Log::warning('Lazy loading detectado', [
                    'model' => is_object($model) ? get_class($model) : (string) $model,
                    'relation' => $relation,
                    'hint' => 'Usá ->with() en la query o ->load() antes de acceder.',
                ]);
            });
        }

        /** ------------------------------
         *  Compatibilidad MySQL antiguo (opcional)
         *  Seteá DB_DEFAULT_STRING_LENGTH=191 para esquemas legacy
         * ------------------------------ */
        if (($len = (int) env('DB_DEFAULT_STRING_LENGTH', 0)) > 0) {
            Schema::defaultStringLength($len);
        }

        /** ------------------------------
         *  Blade helpers
         * ------------------------------ */
        // @admin('rol') => por defecto 'admin'
        Blade::if('admin', function (string $role = 'admin'): bool {
            $user = auth()->user();
            if (!$user)
                return false;

            // Compatibilidad con paquetes de roles
            if (method_exists($user, 'hasRole')) {
                return (bool) $user->hasRole($role);
            }
            if (method_exists($user, 'hasAnyRole')) {
                return (bool) $user->hasAnyRole([$role]);
            }
            return isset($user->role) && $user->role === $role;
        });

        // @feature('clave') -> lee config('features.clave', false)
        Blade::if('feature', function (string $key): bool {
            return (bool) data_get(config('features', []), $key, false);
        });
    }
}
