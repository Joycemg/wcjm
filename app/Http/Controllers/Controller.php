<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Controller base con utilidades para:
 *  - TZ/fechas (CarbonImmutable)
 *  - Validación tipada
 *  - JSON helpers (ok/fail/paginated)
 *  - Rate-limit (RFC 9298) + ETag/Cache
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected const DEFAULT_PER_PAGE = 15;
    protected const MAX_PER_PAGE = 100;

    /** Flags JSON por defecto (UTF-8 seguro) */
    protected int $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    private ?string $memoTz = null;

    /* ========================= TZ/Fechas ========================= */

    protected function tz(): string
    {
        if ($this->memoTz !== null)
            return $this->memoTz;
        return $this->memoTz = (string) config('app.display_timezone', config('app.timezone', 'UTC'));
    }

    protected function nowTz(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tz());
    }

    protected function toIsoUtc(CarbonImmutable $dt): string
    {
        return $dt->utc()->toIso8601String();
    }

    protected function toEpochMs(CarbonImmutable $dt): int
    {
        return (int) $dt->utc()->valueOf();
    }

    /* ========================= Validación/Input ========================= */

    protected function validateInput(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        /** @var array $validated */
        $validated = $request->validate($rules, $messages, $attributes);
        return $validated;
    }

    protected function perPage(Request $request, ?int $default = null, ?int $max = null): int
    {
        $default ??= self::DEFAULT_PER_PAGE;
        $max ??= self::MAX_PER_PAGE;

        $raw = $request->query('per_page');

        if (is_array($raw))
            return $default;
        if (is_string($raw) && strtolower($raw) === 'all')
            return $max;

        $pp = filter_var($raw, FILTER_VALIDATE_INT);
        if ($pp === false || $pp === null)
            $pp = $default;
        if ($pp < 1)
            $pp = 1;
        if ($pp > $max)
            $pp = $max;

        return $pp;
    }

    /* ========================= Auth helpers ========================= */

    protected function optionalUser(Request $request): ?User
    {
        $u = $request->user();
        return $u instanceof User ? $u : null;
    }

    protected function requireUser(Request $request): User
    {
        $u = $this->optionalUser($request);
        abort_unless($u instanceof User, 403, 'Necesitás iniciar sesión.');
        return $u;
    }

    /* ========================= JSON helpers ========================= */

    protected function currentJsonFlags(): int
    {
        $flags = $this->jsonFlags;
        if (config('app.debug') && request()->boolean('pretty', false)) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return $flags;
    }

    protected function json(mixed $payload, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json($payload, $status, $headers, $this->currentJsonFlags());
    }

    protected function jsonOk(array $data = [], array $meta = [], int $status = 200): JsonResponse
    {
        return $this->json(['ok' => true, 'data' => $data, 'meta' => $meta], $status);
    }

    protected function jsonCreated(array $data = [], array $meta = [], ?string $location = null): JsonResponse
    {
        $res = $this->jsonOk($data, $meta, 201);
        if ($location)
            $res->header('Location', $location);
        return $res;
    }

    protected function jsonNoContent(): HttpResponse
    {
        return response()->noContent();
    }

    protected function jsonFail(string $message, int $status = 400, array $errors = [], array $meta = []): JsonResponse
    {
        return $this->json(['ok' => false, 'message' => $message, 'errors' => $errors, 'meta' => $meta], $status);
    }

    protected function jsonPaginated(LengthAwarePaginator $paginator, array $meta = []): JsonResponse
    {
        $links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];

        $pagination = [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];

        return $this->jsonOk(
            data: $paginator->items(),
            meta: array_merge($meta, compact('links', 'pagination'))
        );
    }

    /* ========================= Rate-limit (RFC 9298) ========================= */

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

        RateLimiter::hit($key, $decaySeconds);
        return null;
    }

    protected function withRateHeaders(
        HttpResponse $response,
        Request $request,
        ?string $abilityKey,
        int $maxAttempts,
        int $decaySeconds,
        string $by = 'auto'
    ): HttpResponse {
        $key = $this->rateKey($request, $abilityKey, $by);
        $response->headers->add($this->rateHeaders($key, $maxAttempts, $decaySeconds, false));
        return $response;
    }

    protected function rateKey(Request $request, ?string $abilityKey, string $by = 'auto'): string
    {
        $ability = $abilityKey ?: (strtoupper($request->method()) . ':' . trim($request->path(), '/'));
        return "rate:{$ability}:" . $this->clientFingerprint($request, $by);
    }

    protected function clientFingerprint(Request $request, string $by = 'auto'): string
    {
        if ($by === 'user' && $request->user()) {
            return 'u:' . $request->user()->getAuthIdentifier();
        }
        if ($by === 'ip') {
            return 'ip:' . $request->ip();
        }
        if ($request->user()) {
            return 'u:' . $request->user()->getAuthIdentifier();
        }
        $ua = (string) $request->userAgent();
        return 'ipua:' . $request->ip() . ':' . substr(sha1($ua), 0, 12);
    }

    protected function rateHeaders(string $key, int $max, int $decay, bool $exceeded = false): array
    {
        $remaining = max(0, RateLimiter::remaining($key, $max));
        $resetIn = RateLimiter::availableIn($key);

        $std = [
            'RateLimit-Limit' => (string) $max,
            'RateLimit-Remaining' => (string) $remaining,
            'RateLimit-Reset' => (string) $resetIn,
        ];

        $legacy = [
            'X-RateLimit-Limit' => (string) $max,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) (time() + $resetIn),
        ];

        if ($exceeded) {
            $std['Retry-After'] = (string) $resetIn;
        }

        return $std + $legacy;
    }

    /* ========================= ETag / Cache ========================= */

    protected function makeEtag(mixed $seed, bool $weak = true): string
    {
        if (is_array($seed) || is_object($seed)) {
            $seed = json_encode($seed, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            $seed = (string) $seed;
        }
        $hash = sha1($seed);
        return ($weak ? 'W/' : '') . '"' . $hash . '"';
    }

    protected function maybeNotModified(Request $request, string $etag, int $maxAge, bool $privateForAuth = true): ?HttpResponse
    {
        $raw = (string) $request->header('If-None-Match', '');
        $clientEtgs = array_filter(array_map('trim', $raw === '' ? [] : explode(',', $raw)));

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => $this->cacheDirective($request, $maxAge, $privateForAuth),
            'Vary' => $this->varyFor($request, $privateForAuth),
        ];

        foreach ($clientEtgs as $candidate) {
            if ($candidate === '*' || $this->etagEqualsWeak($candidate, $etag)) {
                return response('', 304, $headers);
            }
        }
        return null;
    }

    protected function withCacheHeaders(
        HttpResponse $response,
        Request $request,
        string $etag,
        int $maxAge,
        bool $privateForAuth = true
    ): HttpResponse {
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', $this->cacheDirective($request, $maxAge, $privateForAuth));
        $response->headers->set('Vary', $this->varyFor($request, $privateForAuth));
        return $response;
    }

    protected function cacheDirective(Request $request, int $maxAge, bool $privateForAuth): string
    {
        if ($privateForAuth && $request->user()) {
            return 'private, no-store';
        }
        return "public, max-age={$maxAge}";
    }

    protected function varyFor(Request $request, bool $privateForAuth): string
    {
        return $privateForAuth && $request->user()
            ? 'Accept, Accept-Encoding, Authorization, Cookie'
            : 'Accept, Accept-Encoding';
    }

    private function etagEqualsWeak(string $a, string $b): bool
    {
        $norm = static fn(string $t) => trim(str_ireplace('W/', '', $t), " \t\n\r\0\x0B\"");
        return $norm($a) !== '' && $norm($a) === $norm($b);
    }
}
