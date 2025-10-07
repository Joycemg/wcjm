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
use Throwable;

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
        $this->configurePagination();
        $this->enforceHttpsWhenRequested();
        $this->configureCarbonLocale();
        $this->configureEloquentStrictness();
        $this->configureLegacyStringLength();
        $this->registerBladeConditionals();
    }

    private function configurePagination(): void
    {
        Paginator::useTailwind();
    }

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
        } catch (Throwable $e) {
            // Evitar que falle el arranque si la extensión de locale no está disponible.
        }

        if (method_exists(Carbon::class, 'setUtf8')) {
            try {
                Carbon::setUtf8(true);
            } catch (Throwable $e) {
            }
        }
    }

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

        Blade::if('feature', function (string $key): bool {
            return (bool) data_get(config('features', []), $key, false);
        });
    }

    private function shouldUseStrictEloquentMode(): bool
    {
        return app()->environment(['local', 'testing']);
    }
}
