<?php declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Username permitido: empieza/termina alfanum; internos . _ - ; 3..32 */
    private const USERNAME_REGEX = '/^[a-z0-9](?:[a-z0-9._-]{1,30}[a-z0-9])?$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza antes de validar (menos sorpresas en hosting compartido).
     */
    protected function prepareForValidation(): void
    {
        $login = trim((string) $this->input('email', ''));
        $pass = (string) $this->input('password', '');
        $remember = $this->boolean('remember');

        // Para login por email, conviene forzar lowercase
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $login = Str::lower($login);
        }

        $this->merge([
            'email' => $login,
            'password' => $pass,
            'remember' => $remember,
        ]);
    }

    /**
     * Por defecto pide email válido.
     * Si allow_username=true, acepta "email O username".
     */
    public function rules(): array
    {
        $rulesForLogin = ['required', 'string', 'max:254'];

        if ($this->allowUsername()) {
            $rulesForLogin[] = function (string $attr, $value, $fail) {
                $v = (string) $value;
                $isEmail = filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
                $isUsername = (bool) preg_match(self::USERNAME_REGEX, Str::lower($v));
                if (!($isEmail || $isUsername)) {
                    $fail('Ingresá un email válido o un usuario válido.');
                }
            };
        } else {
            $rulesForLogin[] = 'email';
        }

        return [
            'email' => $rulesForLogin,   // campo "email" puede contener username si allow_username=true
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Ingresá tu email o usuario.',
            'email.email' => 'Ingresá un email válido.',
            'password.required' => 'Ingresá tu contraseña.',
        ];
    }

    /**
     * Intenta autenticar con rate-limit dual (login+IP e IP global).
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        [$credentials, $remember] = $this->credentials();

        if (!Auth::attempt($credentials, $remember)) {
            // Golpear ambos contadores
            RateLimiter::hit($this->throttleKey(), $this->decaySeconds());
            RateLimiter::hit($this->ipThrottleKey(), $this->ipDecaySeconds());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Éxito: limpiar contadores y regenerar sesión (fixation)
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipThrottleKey());
        $this->session()->regenerate();
    }

    /**
     * Bloquea si cualquiera de los dos límites se excede.
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $tooLoginIp = RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxAttempts());
        $tooIp = RateLimiter::tooManyAttempts($this->ipThrottleKey(), $this->ipMaxAttempts());

        if (!$tooLoginIp && !$tooIp) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey())
        );

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Crea credenciales según configuración:
     * - Por defecto: email + password
     * - Si allow_username=true y no es email: username + password
     * @return array{0: array<string,string>, 1: bool}
     */
    private function credentials(): array
    {
        $rawLogin = (string) $this->input('email', '');
        $remember = (bool) $this->boolean('remember');

        if ($this->allowUsername() && !filter_var($rawLogin, FILTER_VALIDATE_EMAIL)) {
            // login por username (normalizado)
            $username = $this->normalizeUsername($rawLogin);
            return [['username' => $username, 'password' => (string) $this->input('password')], $remember];
        }

        // login por email (lowercase)
        $email = Str::lower(trim($rawLogin));
        return [['email' => $email, 'password' => (string) $this->input('password')], $remember];
    }

    /** Clave rate-limit por login+IP (hash para no guardar el login en texto plano) */
    public function throttleKey(): string
    {
        $login = Str::lower(trim((string) $this->input('email', '')));
        return 'login:' . sha1($login) . '|' . $this->ip();
    }

    /** Clave rate-limit por IP (global) */
    public function ipThrottleKey(): string
    {
        return 'login:ip:' . $this->ip();
    }

    /** Getters desde config para evitar tocar código si querés cambiar límites */
    private function allowUsername(): bool
    {
        return (bool) config('auth.login.allow_username', false);
    }
    private function maxAttempts(): int
    {
        return (int) config('auth.login.max_attempts', 5);
    }
    private function decaySeconds(): int
    {
        return (int) config('auth.login.decay_seconds', 60);
    }
    private function ipMaxAttempts(): int
    {
        return (int) config('auth.login.ip_max_attempts', 50);
    }
    private function ipDecaySeconds(): int
    {
        return (int) config('auth.login.ip_decay_seconds', 300);
    }

    /** Normaliza username (ascii, lower, sin símbolos fuera de . _ - ; colapsa repeticiones) */
    private function normalizeUsername(string $value): string
    {
        $u = Str::of($value)->trim()->lower();
        $u = Str::of(Str::ascii($u));
        $u = $u->replaceMatches('/\s+/', '_')
            ->replaceMatches('/[^a-z0-9._-]/', '')
            ->replaceMatches('/([._-])\1+/', '$1')
            ->replaceMatches('/^[._-]+|[._-]+$/', '');
        $u = (string) $u;

        // Enforce regex y largo mínimo (3) por si quedó corto
        if ($u === '' || !preg_match(self::USERNAME_REGEX, $u)) {
            $u = 'user';
        }
        return $u;
    }
}
