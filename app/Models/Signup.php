<?php declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Signup (inscripción a mesa)
 * - Optimizado para hosting compartido: evita N+1 y hace queries minimalistas.
 * - Compatible con los campos usados por MesaHonorController (confirm/no-show).
 *
 * @property-read int|null $position
 * @property-read bool|null $is_player
 * @property-read bool|null $is_waitlist
 * @property-read string $user_avatar_url
 * @property-read string|null $created_ago
 * @property-read string|null $user_display_name
 */
class Signup extends Model
{
    /** Columnas asignables */
    protected $fillable = [
        'game_table_id',
        'user_id',
        'is_counted',
        'is_manager',
        'attended',
        'behavior',

        // soportadas por MesaHonorController:
        'attendance_confirmed_at',
        'attendance_confirmed_by',
        'no_show_at',
        'no_show_by',
    ];

    /** Atributos computados que viajan en JSON */
    protected $appends = [
        'position',
        'is_player',
        'is_waitlist',
        'user_avatar_url',
        'created_ago',
        'user_display_name',
    ];

    /** Casts (usa datetime mutable para diffs humanos habituales) */
    protected $casts = [
        'id' => 'integer',
        'game_table_id' => 'integer',
        'user_id' => 'integer',
        'is_counted' => 'boolean',
        'is_manager' => 'boolean',
        'attended' => 'boolean',
        'behavior' => 'string',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        'attendance_confirmed_at' => 'datetime',
        'no_show_at' => 'datetime',
        'attendance_confirmed_by' => 'integer',
        'no_show_by' => 'integer',
    ];

    /** Tocar la mesa al cambiar un signup (para ordenar por actividad) */
    protected $touches = ['gameTable'];

    /* =========================
     * Relaciones
     * ========================= */

    /** @return BelongsTo<GameTable, Signup> */
    public function gameTable(): BelongsTo
    {
        return $this->belongsTo(GameTable::class, 'game_table_id');
    }

    /** @return BelongsTo<User, Signup> */
    public function user(): BelongsTo
    {
        // withDefault evita nulls en vistas y accessors
        return $this->belongsTo(User::class)
            ->withDefault([
                'name' => null,
                'username' => null,
                'email' => null,
                'avatar_path' => null,
            ]);
    }

    /* =========================
     * Scopes utilitarios
     * ========================= */

    /** @param Builder<Signup> $q */
    public function scopeForTable(Builder $q, int $tableId): Builder
    {
        return $q->where('game_table_id', $tableId);
    }

    /** @param Builder<Signup> $q */
    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /** @param Builder<Signup> $q */
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /** @param Builder<Signup> $q */
    public function scopeForTableAndUser(Builder $q, int $tableId, int $userId): Builder
    {
        return $q->forTable($tableId)->forUser($userId);
    }

    /** @param Builder<Signup> $q */
    public function scopeRecentForTable(Builder $q, int $tableId, int $limit = 8): Builder
    {
        return $q->forTable($tableId)->orderByDesc('created_at')->limit($limit);
    }

    /* =========================
     * Atributos calculados
     * ========================= */

    /**
     * Posición (1-based) entre inscriptos contados, estable por (created_at, id).
     * Usa relación precargada si existe; si no, una COUNT en BD (eficiente).
     */
    public function getPositionAttribute(): ?int
    {
        if (!$this->game_table_id || !$this->exists) {
            return null;
        }

        // Si el modelo de mesa y sus signups están cargados, calcular en memoria
        if ($this->relationLoaded('gameTable')) {
            $mesa = $this->getRelation('gameTable');
            if ($mesa && $mesa->relationLoaded('signups')) {
                /** @var Collection<int, self> $sorted */
                $sorted = $mesa->signups
                    ->where('is_counted', 1)
                    ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                    ->values();

                $idx = $sorted->search(fn($s) => (int) $s->id === (int) $this->id);
                return $idx === false ? null : ($idx + 1);
            }
        }

        // Query estable por tie-break de id
        $createdAt = $this->created_at instanceof Carbon ? $this->created_at : null;
        $id = (int) $this->id;
        $tableId = (int) $this->game_table_id;

        return static::query()
            ->where('game_table_id', $tableId)
            ->where('is_counted', 1)
            ->where(function (Builder $q) use ($createdAt, $id) {
                if ($createdAt) {
                    $q->where('created_at', '<', $createdAt)
                        ->orWhere(function (Builder $q2) use ($createdAt, $id) {
                            $q2->where('created_at', '=', $createdAt)->where('id', '<=', $id);
                        });
                } else {
                    // Fallback cuando no hay created_at (poco probable)
                    $q->where('id', '<=', $id);
                }
            })
            ->count();
    }

    /** ¿Es jugador (dentro de capacidad)? */
    public function getIsPlayerAttribute(): ?bool
    {
        if (!$this->game_table_id) {
            return null;
        }

        $cap = null;
        if ($this->relationLoaded('gameTable')) {
            $cap = optional($this->getRelation('gameTable'))->capacity;
        }
        if ($cap === null) {
            $cap = (int) GameTable::query()->whereKey($this->game_table_id)->value('capacity');
        }
        if ((int) $cap <= 0) {
            return false;
        }

        $pos = $this->position;
        return $pos ? $pos <= (int) $cap : null;
    }

    /** ¿Está en lista de espera (más allá de capacidad)? */
    public function getIsWaitlistAttribute(): ?bool
    {
        if (!$this->game_table_id) {
            return null;
        }

        $cap = null;
        if ($this->relationLoaded('gameTable')) {
            $cap = optional($this->getRelation('gameTable'))->capacity;
        }
        if ($cap === null) {
            $cap = (int) GameTable::query()->whereKey($this->game_table_id)->value('capacity');
        }
        if ((int) $cap <= 0) {
            return false;
        }

        $pos = $this->position;
        return $pos ? $pos > (int) $cap : null;
    }

    /**
     * URL del avatar del usuario (cascade: user->avatar_url > storage > gravatar > default > SVG iniciales).
     */
    protected function userAvatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $u = $this->relationLoaded('user') ? $this->user : null;

            if ($u) {
                try {
                    // Si el User ya expone avatar_url (accessor), úsalo
                    if (isset($u->avatar_url) && (string) $u->avatar_url !== '') {
                        return (string) $u->avatar_url;
                    }
                } catch (\Throwable) {
                    // ignorar y seguir con cascada
                }

                $path = (string) ($u->avatar_path ?? '');
                if ($path !== '') {
                    if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
                        return $path;
                    }
                    try {
                        return (string) Storage::url($path);
                    } catch (\Throwable) {
                        return $this->assetSafe('storage/' . ltrim($path, '/'));
                    }
                }

                if ((bool) config('auth.avatars.use_gravatar', false) && !empty($u->email)) {
                    $email = $this->lowerNoMb((string) $u->email);
                    $hash = md5(trim($email));
                    $size = (int) config('auth.avatars.gravatar_size', 96);
                    $default = urlencode((string) config('auth.avatars.gravatar_default', 'identicon'));
                    return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}";
                }
            }

            $defaultPath = (string) config('auth.avatars.default', 'images/avatar-default.svg');
            if ($defaultPath !== '') {
                if (Str::startsWith($defaultPath, ['http://', 'https://', '//'])) {
                    return $defaultPath;
                }
                return $this->assetSafe($defaultPath);
            }

            $seed = $u ? ((string) ($u->name ?? $u->username ?? $u->email ?? 'U')) : 'U';
            return $this->initialsDataUri($seed);
        });
    }

    /** Hace “3 min” / “hace 1 hora”, etc. */
    protected function createdAgo(): Attribute
    {
        return Attribute::get(
            fn(): ?string =>
            $this->created_at ? $this->created_at->diffForHumans() : null
        );
    }

    /** Nombre visible del usuario */
    protected function userDisplayName(): Attribute
    {
        return Attribute::get(function (): ?string {
            $u = $this->relationLoaded('user') ? $this->user : null;
            if (!$u)
                return null;
            if (!empty($u->name))
                return (string) $u->name;
            if (!empty($u->username))
                return (string) $u->username;
            if (!empty($u->email))
                return Str::before((string) $u->email, '@');
            return null;
        });
    }

    /* =========================
     * Eloquent hooks
     * ========================= */

    protected static function booted(): void
    {
        static::saving(function (Signup $s) {
            $s->game_table_id = (int) $s->game_table_id;
            $s->user_id = (int) $s->user_id;
        });
    }

    /* =========================
     * Utils (SVG/strings)
     * ========================= */

    private function assetSafe(string $path): string
    {
        return function_exists('asset') ? asset($path) : '/' . ltrim($path, '/');
    }

    private function lowerNoMb(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }

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
        if (str_contains($v, '@')) {
            $v = explode('@', $v)[0];
        }
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
        $palette = ['#1F77B4', '#2CA02C', '#D62728', '#9467BD', '#8C564B', '#E377C2', '#7F7F7F', '#BCBD22', '#17BECF', '#FF7F0E'];
        $hash = crc32($value);
        return $palette[$hash % count($palette)];
    }

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

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }
}
