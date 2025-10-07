<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasHonor;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $username
 * @property string|null $email
 * @property string|null $password
 * @property string|null $role
 * @property string|null $avatar_path
 * @property string|null $bio
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @property-read string $profile_param
 * @property-read string $handle
 * @property-read string $avatar_url
 * @property-read string|null $display_name
 * @property-read string $initials
 *
 * @method static Builder|self search(?string $term)
 * @method static Builder|self admins()
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
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'email_verified_at' => 'immutable_datetime',
        'last_login_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'password' => 'hashed',
    ];

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

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '')
            return $q;

        $like = '%' . addcslashes($term, "%_\\") . '%';

        return $q->where(function (Builder $w) use ($like) {
            $w->where('name', 'LIKE', $like)
                ->orWhere('username', 'LIKE', $like)
                ->orWhere('email', 'LIKE', $like);
        });
    }

    public function scopeAdmins(Builder $q): Builder
    {
        return $q->where('role', 'admin');
    }

    /* =========================
     * Mutators & Normalizaciones
     * ========================= */

    protected function name(): Attribute
    {
        return Attribute::set(
            fn(?string $value) =>
            $value ? Str::limit(Str::of($value)->squish()->trim(), 120, '') : null
        );
    }

    protected function username(): Attribute
    {
        return Attribute::set(function ($value) {
            if ($value === null)
                return null;

            $u = Str::of((string) $value)->trim()->lower();
            $u = Str::of(Str::ascii($u))
                ->replaceMatches('/\s+/', '_')
                ->replaceMatches('/[^a-z0-9._-]/', '')
                ->replaceMatches('/([._-])\1+/', '$1')
                ->replaceMatches('/^[._-]+|[._-]+$/', '');

            return $u !== '' ? (string) $u : null;
        });
    }

    protected function email(): Attribute
    {
        return Attribute::set(
            fn(?string $value) =>
            $value ? Str::lower(trim($value)) : null
        );
    }

    protected function bio(): Attribute
    {
        return Attribute::set(function ($value) {
            if ($value === null)
                return null;

            $clean = strip_tags((string) $value);
            $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
            $clean = trim($clean);
            return $clean !== '' ? Str::limit($clean, 2000, '') : null;
        });
    }

    /* =========================
     * Accessors
     * ========================= */

    protected function profileParam(): Attribute
    {
        return Attribute::get(
            fn(): string =>
            $this->username ? '@' . $this->username . '_' . $this->id : (string) $this->id
        );
    }

    protected function handle(): Attribute
    {
        return Attribute::get(
            fn(): string =>
            ($this->username ?: $this->name ?: 'usuario') . '#' . $this->id
        );
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $path = (string) ($this->avatar_path ?? '');

            if ($path !== '' && Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
                return $path;
            }

            if ($path !== '') {
                try {
                    return (string) Storage::url($path);
                } catch (\Throwable) {
                    return function_exists('asset')
                        ? asset('storage/' . ltrim($path, '/'))
                        : '/storage/' . ltrim($path, '/');
                }
            }

            if ((bool) config('auth.avatars.use_gravatar', false) && !empty($this->email)) {
                $hash = md5(strtolower(trim((string) $this->email)));
                $size = (int) config('auth.avatars.gravatar_size', 256);
                $default = urlencode((string) config('auth.avatars.gravatar_default', 'identicon'));
                return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}";
            }

            $default = (string) config('auth.avatars.default', 'images/avatar-default.svg');
            if ($default !== '') {
                return Str::startsWith($default, ['http://', 'https://', '//'])
                    ? $default
                    : (function_exists('asset') ? asset($default) : '/' . ltrim($default, '/'));
            }

            return $this->initialsDataUri($this->display_name ?? 'U', 128);
        });
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->name)
                return (string) $this->name;
            if ($this->username)
                return (string) $this->username;
            if ($this->email)
                return Str::before((string) $this->email, '@');
            return null;
        });
    }

    protected function initials(): Attribute
    {
        return Attribute::get(
            fn(): string =>
            $this->initialsFrom($this->display_name ?? (string) ($this->email ?? 'U'))
        );
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
  <text x="50%" y="50%" dy="0.35em" text-anchor="middle" font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu" font-size="{$fontSize}" font-weight="700" fill="{$fg}">{$text}</text>
</svg>
SVG;

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    private function initialsFrom(string $value): string
    {
        $v = trim($value);
        if (str_contains($v, '@'))
            $v = explode('@', $v)[0];
        $parts = preg_split('/[\s._-]+/u', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = [];
        foreach ($parts as $p) {
            $letters[] = $this->u_upper($this->u_substr($p, 0, 1));
            if (count($letters) === 2)
                break;
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

    private function u_substr(string $s, int $start, int $len = 1): string
    {
        return function_exists('mb_substr')
            ? mb_substr($s, $start, $len, 'UTF-8')
            : substr($s, $start, $len);
    }

    private function u_upper(string $s): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($s, 'UTF-8')
            : strtoupper($s);
    }

    /* =========================
     * Serialización
     * ========================= */

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }

    /* =========================
     * Utils
     * ========================= */

    public function isAdmin(): bool
    {
        return ($this->role ?? null) === 'admin';
    }

    public static function findByProfileParam(string $param): ?self
    {
        $param = trim($param);
        if ($param !== '' && ctype_digit($param))
            return static::find((int) $param);

        if (Str::startsWith($param, '@'))
            $param = ltrim($param, '@');
        $parts = explode('_', $param);
        $id = (int) array_pop($parts);
        return $id > 0 ? static::find($id) : null;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null)
            return parent::resolveRouteBinding($value, $field);

        return static::findByProfileParam((string) $value)
            ?? parent::resolveRouteBinding($value, $field);
    }
}
