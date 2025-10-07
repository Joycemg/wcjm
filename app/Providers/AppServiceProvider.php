<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AdminOnly;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class AppServiceProvider extends ServiceProvider
{
    /** Register any application services. */
    public function register(): void
    {
        // Limpio por defecto. Ideal para hosting compartido.
        // Aquí podrías bindear singletons (clients, etc.) si los necesitás.
    }

    /** Bootstrap any application services. */
    public function boot(): void
    {
        $this->ensureSessionDriverIsAvailable();
        $this->configurePagination();
        $this->enforceHttpsWhenRequested();
        $this->configureCarbonLocale();
        $this->configureEloquentStrictness();
        $this->configureLegacyStringLength();
        $this->registerBladeConditionals();
        $this->registerRouteMiddlewareAliases();
    }

    /**
     * Evita errores 500 cuando SESSION_DRIVER=database pero la tabla aún no existe.
     * Cae a 'file' en forma silenciosa para mantener la app utilizable.
     */
    private function ensureSessionDriverIsAvailable(): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        try {
            if (Schema::hasTable($table)) {
                return;
            }
        } catch (Throwable $e) {
            // Si no se puede comprobar (por ejemplo, DB sin migrar), continuamos con el fallback.
            Log::warning('No se pudo verificar la tabla de sesiones, usando driver file.', [
                'error' => $e->getMessage(),
            ]);
        }

        config()->set('session.driver', 'file');

        Log::warning('Falta la tabla de sesiones; se fuerza SESSION_DRIVER=file como fallback.', [
            'expected_table' => $table,
        ]);
    }

    private function configurePagination(): void
    {
        // Usa vistas Tailwind por defecto (las trae Laravel).
        Paginator::useTailwind();
    }

    /**
     * Respeta APP_FORCE_HTTPS / config('app.force_https') para proxies (ej. Hostinger/Cloudflare).
     */
    private function enforceHttpsWhenRequested(): void
    {
        if (config('app.force_https', (bool) env('APP_FORCE_HTTPS', false))) {
            URL::forceScheme('https');
        }
    }

    private function configureCarbonLocale(): void
    {
        try {
            Carbon::setLocale(config('app.locale', 'es'));
        } catch (Throwable) {
            // Evita crash al boot si la locale del SO no está instalada.
        }

        if (method_exists(Carbon::class, 'setUtf8')) {
            try {
                Carbon::setUtf8(true);
            } catch (Throwable) {
                // Silente: mejora rendering de fechas en algunos entornos.
            }
        }
    }

    /**
     * Modo estricto de Eloquent sólo en local/testing (útil para detectar lazy loading).
     * En producción (hosting compartido) evita overhead innecesario.
     */
    private function configureEloquentStrictness(): void
    {
        $strict = $this->shouldUseStrictEloquentMode();

        if (method_exists(Model::class, 'shouldBeStrict')) {
            Model::shouldBeStrict($strict);
        } else {
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

        if ($strict && method_exists(Model::class, 'handleLazyLoadingViolationUsing')) {
            Model::handleLazyLoadingViolationUsing(function ($model, string $relation): void {
                Log::warning('Lazy loading detectado', [
                    'model' => is_object($model) ? get_class($model) : (string) $model,
                    'relation' => $relation,
                    'hint' => 'Usá ->with() en la query o ->load() antes de acceder.',
                ]);
            });
        }
    }

    /**
     * Evita problemas de índice/charset en MySQL viejos (ej. shared hosting).
     * Configurable vía env DB_DEFAULT_STRING_LENGTH.
     */
    private function configureLegacyStringLength(): void
    {
        if (($len = (int) env('DB_DEFAULT_STRING_LENGTH', 0)) > 0) {
            Schema::defaultStringLength($len);
        }
    }

    private function registerBladeConditionals(): void
    {
        Blade::if('admin', function (string $role = 'admin'): bool {
            $user = auth()->user();
            if (!$user) {
                return false;
            }

            if (method_exists($user, 'hasRole')) {
                return (bool) $user->hasRole($role);
            }
            if (method_exists($user, 'hasAnyRole')) {
                return (bool) $user->hasAnyRole([$role]);
            }

            return isset($user->role) && $user->role === $role;
        });

        Blade::if('feature', fn(string $key): bool => (bool) data_get(config('features', []), $key, false));
    }

    private function registerRouteMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('admin', AdminOnly::class);
        $router->aliasMiddleware('admin.only', AdminOnly::class);
    }

    private function shouldUseStrictEloquentMode(): bool
    {
        return app()->environment(['local', 'testing']);
    }
}
