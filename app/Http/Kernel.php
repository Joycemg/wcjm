<?php declare(strict_types=1);

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

/**
 * Http Kernel optimizado para hosting compartido (Hostinger)
 * - Minimalista, sin grupos "lean"/polling ni extras pesados
 * - Compatible con Laravel 10 y 11 (define $routeMiddleware y $middlewareAliases)
 */
class Kernel extends HttpKernel
{
    /** @var array<int, class-string|string> */
    protected $middleware = [
        // Seguridad/host/proxies primero
        \App\Http\Middleware\TrustHosts::class,         // si no existe en tu app, quítalo
        \App\Http\Middleware\TrustProxies::class,

        // CORS y maintenance
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,

        // Subidas y sanitización
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /** @var array<string, array<int, class-string|string>> */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class, // evitar sobrecosto en shared hosting
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            // Usa el RateLimiter configurado en RouteServiceProvider (ligero y flexible)
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // Si usás Sanctum y necesitás cookies de SPA, agregá:
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ],
    ];

    /**
     * Laravel 10: $routeMiddleware
     * Laravel 11: $middlewareAliases
     * Definimos ambos para máxima compatibilidad en entornos compartidos.
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
    ];

    /** @var array<string, class-string|string> */
    protected $middlewareAliases = [
        // Mismos alias que $routeMiddleware para Laravel 11+
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
    ];
}
