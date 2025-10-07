<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Base genérico para endpoints JSON simples.
 * Reutiliza los helpers del Controller padre para rate-limit y ETag.
 */
final class ApiController extends Controller
{
    /**
     * Ejemplo de endpoint “ping” con rate-limit y ETag.
     * Útil como plantilla para tus APIs públicas.
     */
    public function ping(Request $request): HttpResponse|JsonResponse
    {
        // 1) Rate-limit por IP+UA (anon) o user (auth)
        if ($tooMany = $this->enforceRateLimit($request, abilityKey: 'api:ping', maxAttempts: 30, decaySeconds: 60)) {
            return $tooMany; // 429 con headers RFC 9298
        }

        // 2) ETag débil basado en minuto actual (respuestas idénticas en la ventana)
        $etag = $this->makeEtag(date('Y-m-d H:i'));

        if ($notMod = $this->maybeNotModified($request, $etag, maxAge: 30)) {
            return $notMod; // 304 con ETag/Cache-Control/Vary
        }

        // 3) Respuesta OK + rate headers
        $resp = $this->jsonOk(['pong' => true, 'now' => $this->nowTz()->toIso8601String()]);
        $resp = $this->withRateHeaders($resp, $request, 'api:ping', 30, 60);
        return $this->withCacheHeaders($resp, $request, $etag, 30);
    }
}
