<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasHonor;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use DateTimeInterface;

/**
 * @property-read string      $profile_param
 * @property-read string      $handle
 * @property-read string      $avatar_url
 * @property-read string|null $display_name
 * @property-read string      $initials
 */
class User extends Authenticatable // implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasHonor;

    /* =========================
     * Configuración
     * ========================= */

    /** @var array<int,string> */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'avatar_path',
        'bio',
        'email_verified_at',
    ];

    /** @var array<int,string> */
    protected $hidden = [
        'password',
        'remember_token',
        // Jetstream/2FA (si existieran)
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // Laravel 10+: hashea automáticamente al asignar
        'password' => 'hashed',
        // Si no existe la columna, no afecta
        'last_login_at' => 'datetime',
    ];

    /** Atributos calculados que viajan en JSON */
    /** @var array<int,string> */
    protected $appends = [
        'profile_param',
        'handle',
        'avatar_url',
        'display_name',
        'initials',
    ];

    /* =========================
     * Relaciones
     * ========================= */

    /** @return HasMany<GameTable> */
    public function tablesCreated(): HasMany
    {
        return $this->hasMany(GameTable::class, 'created_by');
    }

    /** @return HasMany<Signup> */
    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class);
    }

    /* =========================
     * Scopes
     * ========================= */

    /** Búsqueda por nombre, username o email (LIKE con escape de % y _) */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        $like = '%' . addcslashes($term, "%_\\") . '%';

        return $q->where(function ($w) use ($like) {
            $w->where('name', 'LIKE', $like)
                ->orWhere('username', 'LIKE', $like)
                ->orWhere('email', 'LIKE', $like);
        });
    }

    /** Usuarios admins (si usás string/enum role) */
    public function scopeAdmins(Builder $q): Builder
    {
        return $q->where('role', 'admin');
    }

    /* =========================
     * Mutators (setters) & Normalizaciones
     * ========================= */

    /** name: trim + squish + límite */
    protected function name(): Attribute
    {
        return Attribute::set(function (?string $value) {
            $name = Str::of((string) $value)->squish()->trim();
            return (string) Str::limit($name, 120, '');
        });
    }

    /**
     * username: normaliza (ascii, lowercase, espacios->_, sólo [a-z0-9._-],
     * colapsa duplicados, quita bordes). Si queda vacío => null.
     */
    protected function username(): Attribute
    {
        return Attribute::set(function ($value) {
            if ($value === null) {
                return null;
            }

            $u = Str::of((string) $value)->trim()->lower();
            $u = Str::of(Str::ascii($u));
            $u = $u->replaceMatches('/\s+/', '_')
                ->replaceMatches('/[^a-z0-9._-]/', '')
                ->replaceMatches('/([._-])\1+/', '$1')
                ->replaceMatches('/^[._-]+|[._-]+$/', '');
            $u = (string) $u;

            return $u !== '' ? $u : null;
        });
    }

    /** email: trim + lowercase */
    protected function email(): Attribute
    {
        return Attribute::set(function (?string $value) {
            return $value === null ? null : Str::lower(trim($value));
        });
    }

    /** bio: sin HTML, espacios normalizados, tope 2000 */
    protected function bio(): Attribute
    {
        return Attribute::set(function ($value) {
            if ($value === null) {
                return null;
            }
            $clean = strip_tags((string) $value);
            $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
            $clean = trim($clean);
            return $clean !== '' ? Str::limit($clean, 2000, '') : null;
        });
    }

    /* =========================
     * Accessors (getters) para UI/API
     * ========================= */

    /**
     * Parámetro para la URL del perfil:
     * - sin username  => "2"
     * - con username  => "@usuario_2"
     *
     * Uso: route('profile.show', $user->profile_param)
     */
    protected function profileParam(): Attribute
    {
        return Attribute::get(function (): string {
            return $this->username
                ? '@' . $this->username . '_' . $this->id
                : (string) $this->id;
        });
    }

    /** Handle visible "usuario#2" (o name si no hay username) */
    protected function handle(): Attribute
    {
        return Attribute::get(function (): string {
            $name = $this->username ?: ($this->name ?: 'usuario');
            return "{$name}#{$this->id}";
        });
    }

    /**
     * URL del avatar con estrategia en cascada:
     * - avatar_path absoluto → tal cual
     * - Storage::url(avatar_path) (local/S3)
     * - Gravatar (si está habilitado en config)
     * - Imagen local por defecto (config)
     * - Fallback SVG con iniciales
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $path = (string) ($this->avatar_path ?? '');

            // 1) Path absoluto / data URI
            if ($path !== '' && Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
                return $path;
            }

            // 2) Storage público / S3
            if ($path !== '') {
                try {
                    return (string) Storage::url($path);
                } catch (\Throwable) {
                    // 3) Fallback local si no hay disk configurado
                    return function_exists('asset')
                        ? asset('storage/' . ltrim($path, '/'))
                        : '/storage/' . ltrim($path, '/');
                }
            }

            // 4) Gravatar (opcional)
            if ((bool) config('auth.avatars.use_gravatar', false) && !empty($this->email)) {
                $hash = md5(strtolower(trim((string) $this->email)));
                $size = (int) config('auth.avatars.gravatar_size', 256);
                $default = urlencode((string) config('auth.avatars.gravatar_default', 'identicon'));
                return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}";
            }

            // 5) Imagen por defecto (configurable)
            $default = (string) config('auth.avatars.default', 'images/avatar-default.svg');
            if ($default !== '') {
                return Str::startsWith($default, ['http://', 'https://', '//'])
                    ? $default
                    : (function_exists('asset') ? asset($default) : '/' . ltrim($default, '/'));
            }

            // 6) Fallback SVG con iniciales
            return $this->initialsDataUri($this->display_name ?? 'U', 128);
        });
    }

    /** Nombre visible (name > username > email local-part) */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (!empty($this->name)) {
                return (string) $this->name;
            }
            if (!empty($this->username)) {
                return (string) $this->username;
            }
            if (!empty($this->email)) {
                return Str::before((string) $this->email, '@');
            }
            return null;
        });
    }

    /** Iniciales (para placeholders, badges, etc.) */
    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $seed = $this->display_name ?? (string) ($this->email ?? 'U');
            return $this->initialsFrom($seed);
        });
    }

    /* =========================
     * Helpers UI (SVG, Iniciales)
     * ========================= */

    public function initialsDataUri(string $source, int $size = 96): string
    {
        $text = $this->initialsFrom($source);
        $bg = $this->colorFromString($source);
        $fg = '#FFFFFF';
        $rx = $size / 6;
        $fontSize = $size * 0.42;

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}" role="img" aria-label="{$text}">
  <rect width="100%" height="100%" rx="{$rx}" ry="{$rx}" fill="{$bg}"/>
  <text x="50%" y="50%" dy="0.35em" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu" font-size="{$fontSize}" font-weight="700" fill="{$fg}">
    {$text}
  </text>
</svg>
SVG;

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    private function initialsFrom(string $value): string
    {
        $v = trim($value);
        if (str_contains($v, '@')) {
            $v = explode('@', $v)[0];
        }
        $parts = preg_split('/[\s._-]+/u', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = [];
        foreach ($parts as $p) {
            $letters[] = $this->u_upper($this->u_substr($p, 0, 1));
            if (count($letters) === 2) {
                break;
            }
        }
        return $letters ? implode('', $letters) : 'U';
    }

    private function colorFromString(string $value): string
    {
        $palette = [
            '#1F77B4',
            '#2CA02C',
            '#D62728',
            '#9467BD',
            '#8C564B',
            '#E377C2',
            '#7F7F7F',
            '#BCBD22',
            '#17BECF',
            '#FF7F0E',
        ];
        $hash = crc32($value);
        return $palette[$hash % count($palette)];
    }

    /** Unicode helpers sin mbstring (funcionan igual si tenés mbstring) */
    private function u_substr(string $s, int $start, int $len = 1): string
    {
        return function_exists('mb_substr') ? mb_substr($s, $start, $len, 'UTF-8') : substr($s, $start, $len);
    }

    private function u_upper(string $s): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
    }

    /* =========================
     * Serialización
     * ========================= */

    /** Fechas JSON consistentes (ISO 8601) */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }

    /* =========================
     * Utils
     * ========================= */

    /** Syntactic sugar de rol admin (evita múltiples comparaciones) */
    public function isAdmin(): bool
    {
        return ($this->role ?? null) === 'admin';
    }

    /**
     * Helper para parsear un profile_param y encontrar un usuario.
     * Soporta:
     *   - "15"
     *   - "@usuario_15"
     */
    public static function findByProfileParam(string $param): ?self
    {
        $param = trim($param);

        // Caso simple: es un ID puro
        if (ctype_digit($param)) {
            return static::find((int) $param);
        }

        // Formato @username_id
        if (Str::startsWith($param, '@')) {
            $param = ltrim($param, '@');
        }

        $parts = explode('_', $param);
        $id = (int) array_pop($parts); // último segmento
        if ($id > 0) {
            return static::find($id);
        }

        return null;
    }
}
