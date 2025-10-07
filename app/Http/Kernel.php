<?php declare(strict_types=1);

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

final class Kernel extends HttpKernel
{
    /**
     * Global middleware stack (para TODAS las requests).
     * Mantener liviano en hosting compartido.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // Seguridad/host/proxies
        \App\Http\Middleware\TrustHosts::class,     // si no existe, podés quitarlo
        \App\Http\Middleware\TrustProxies::class,

        // CORS y mantenimiento
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,

        // Tamaño de POST y sanitización
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * Grupos de middleware para WEB y API.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class, // desactivado por costo en shared hosting
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // Rate limiter definido en RouteServiceProvider → 'throttle:api'
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // Para SPA con cookies y Sanctum, descomentar:
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ],
    ];

    /**
     * Alias por ruta (Laravel 10).
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // Custom
        'admin' => \App\Http\Middleware\AdminOnly::class,
        'admin.only' => \App\Http\Middleware\AdminOnly::class,
    ];

    /**
     * Alias por ruta (Laravel 11+). Mantenemos ambos por compatibilidad.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        // Custom
        'admin' => \App\Http\Middleware\AdminOnly::class,
        'admin.only' => \App\Http\Middleware\AdminOnly::class,
    ];

    /**
     * (Opcional) Prioridad de middleware cuando existan dependencias de ejecución.
     * Mantener vacío salvo que detectes problemas de orden con paquetes externos.
     *
     * @var array<int, class-string|string>
     */
    protected $middlewarePriority = [
        // Ejemplo (si tuvieras problemas de orden):
        // \Illuminate\Session\Middleware\StartSession::class,
        // \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        // \App\Http\Middleware\Authenticate::class,
    ];
}
