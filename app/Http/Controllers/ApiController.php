<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ApiController extends Controller
{
    /* ======================================================
     *  RATE LIMIT
     * ====================================================== */

    /**
     * Aplica rate-limit. Si excede, responde 429 con headers.
     * Si NO excede, incrementa el contador y retorna null.
     *
     * $abilityKey: identifica la acción (p.ej. "ranking:honor:index").
     * $by: "auto" | "user" | "ip". En "auto" prioriza user, si no IP+UA.
     */
    protected function enforceRateLimit(
        Request $request,
        ?string $abilityKey = null,
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        string $by = 'auto'
    ): ?HttpResponse {
        $key = $this->rateKey($request, $abilityKey, $by);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            /** @var JsonResponse $resp */
            $resp = response()->json([
                'ok' => false,
                'error' => 'Too Many Requests',
                'status' => 429,
                'retry_after' => $retryAfter,
            ], 429);

            $resp->headers->add($this->rateHeaders($key, $maxAttempts, $decaySeconds, true));
            return $resp;
        }

        // Cuenta este intento (TTL = $decaySeconds)
        RateLimiter::hit($key, $decaySeconds);
        return null;
    }

    /**
     * Adjunta headers de rate-limit a una respuesta 2xx.
     */
    protected function withRateHeaders(HttpResponse $response, Request $request, ?string $abilityKey, int $maxAttempts, int $decaySeconds, string $by = 'auto'): HttpResponse
    {
        $key = $this->rateKey($request, $abilityKey, $by);
        $response->headers->add($this->rateHeaders($key, $maxAttempts, $decaySeconds, false));
        return $response;
    }

    /**
     * Clave: ability + fingerprint cliente.
     */
    protected function rateKey(Request $request, ?string $abilityKey, string $by = 'auto'): string
    {
        $ability = $abilityKey ?: (strtoupper($request->method()) . ':' . trim($request->path(), '/'));
        return "rate:{$ability}:" . $this->clientFingerprint($request, $by);
    }

    /**
     * Fingerprint del cliente: user id | ip | ip+ua (para NAT/CDN).
     */
    protected function clientFingerprint(Request $request, string $by = 'auto'): string
    {
        if ($by === 'user' && $request->user()) {
            return 'u:' . $request->user()->getAuthIdentifier();
        }
        if ($by === 'ip') {
            return 'ip:' . $request->ip();
        }
        // auto: prioriza user; si no, IP + hash de UA (barato en hosting compartido)
        if ($request->user()) {
            return 'u:' . $request->user()->getAuthIdentifier();
        }
        $ua = (string) $request->userAgent();
        return 'ipua:' . $request->ip() . ':' . substr(sha1($ua), 0, 12);
    }

    /**
     * Headers estándar (RFC 9298) + legacy X-RateLimit-*.
     */
    protected function rateHeaders(string $key, int $max, int $decay, bool $exceeded = false): array
    {
        $remaining = max(0, RateLimiter::remaining($key, $max));
        $resetIn = RateLimiter::availableIn($key);

        $std = [
            'RateLimit-Limit' => (string) $max,
            'RateLimit-Remaining' => (string) $remaining,
            'RateLimit-Reset' => (string) $resetIn,               // seg hasta reset
        ];

        $legacy = [
            'X-RateLimit-Limit' => (string) $max,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) (time() + $resetIn),  // epoch seg
        ];

        if ($exceeded) {
            $std['Retry-After'] = (string) $resetIn;
        }

        return $std + $legacy;
    }

    /* ======================================================
     *  ETag / CACHE
     * ====================================================== */

    /**
     * Genera un ETag (débil por defecto) a partir de un seed liviano
     * (p.ej., max(updated_at), total filas, o un timestamp).
     */
    protected function makeEtag(mixed $seed, bool $weak = true): string
    {
        if (is_array($seed) || is_object($seed)) {
            $seed = json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            $seed = (string) $seed;
        }
        $hash = sha1($seed); // barato y suficiente para shared hosting
        return ($weak ? 'W/' : '') . '"' . $hash . '"';
    }

    /**
     * Si If-None-Match coincide (comparación débil), devuelve 304 con headers.
     * Si no coincide, retorna null y el caller debe responder 200 con el mismo ETag.
     * Para usuarios autenticados aplica cache privada/no-store (no compartida).
     */
    protected function maybeNotModified(Request $request, string $etag, int $maxAge, bool $privateForAuth = true): ?HttpResponse
    {
        $raw = (string) $request->header('If-None-Match', '');
        $clientEtags = array_filter(array_map('trim', $raw === '' ? [] : explode(',', $raw)));

        $cacheControl = $this->cacheDirective($request, $maxAge, $privateForAuth);

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => $cacheControl,
            'Vary' => $this->varyFor($request, $privateForAuth),
        ];

        foreach ($clientEtags as $candidate) {
            if ($candidate === '*' || $this->etagEqualsWeak($candidate, $etag)) {
                return response('', 304, $headers);
            }
        }
        return null;
    }

    /**
     * Agrega ETag y Cache-Control a una respuesta 200.
     */
    protected function withCacheHeaders(HttpResponse $response, Request $request, string $etag, int $maxAge, bool $privateForAuth = true): HttpResponse
    {
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', $this->cacheDirective($request, $maxAge, $privateForAuth));
        $response->headers->set('Vary', $this->varyFor($request, $privateForAuth));
        return $response;
    }

    protected function cacheDirective(Request $request, int $maxAge, bool $privateForAuth): string
    {
        if ($privateForAuth && $request->user()) {
            // Evita que CDNs/proxies compartan contenido personalizado
            return 'private, no-store';
        }
        return "public, max-age={$maxAge}";
    }

    protected function varyFor(Request $request, bool $privateForAuth): string
    {
        // Básicos para contenido estático/JSON; ampliamos si hay sesión.
        return $privateForAuth && $request->user()
            ? 'Accept, Accept-Encoding, Authorization, Cookie'
            : 'Accept, Accept-Encoding';
    }

    /**
     * Comparación débil de ETags (ignora W/ y comillas).
     */
    private function etagEqualsWeak(string $a, string $b): bool
    {
        $norm = static fn(string $t) => trim(str_ireplace('W/', '', $t), " \t\n\r\0\x0B\"");
        return $norm($a) !== '' && $norm($a) === $norm(b: $b);
    }
}
