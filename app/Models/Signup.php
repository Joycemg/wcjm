<?php declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Modelo: Signup (inscripción a mesa)
 * - Optimizado para hosting compartido (queries minimalistas, sin features DB exóticas).
 * - Compatible con MesaHonorController (confirm/no-show).
 *
 * @property int $id
 * @property int $game_table_id
 * @property int $user_id
 * @property bool $is_counted
 * @property bool $is_manager
 * @property bool|null $attended                 // derivado de attended | attendance_confirmed_at | no_show_at
 * @property string|null $behavior               // 'good'|'regular'|'bad'|null
 *
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $attendance_confirmed_at
 * @property int|null $attendance_confirmed_by
 * @property \Illuminate\Support\Carbon|null $no_show_at
 * @property int|null $no_show_by
 *
 * ==== Accessors computados (viajan en JSON) ====
 * @property-read int|null $position             // 1-based entre contados
 * @property-read bool|null $is_player           // dentro de capacidad
 * @property-read bool|null $is_waitlist         // fuera de capacidad
 * @property-read string $user_avatar_url        // cascada avatar
 * @property-read string|null $created_ago       // "hace 5 min"
 * @property-read string|null $user_display_name // name|username|local-part
 *
 * @mixin \Eloquent
 */
class Signup extends Model
{
    use HasFactory;

    /** Valores válidos para behavior */
    public const BEHAV_GOOD = 'good';
    public const BEHAV_REGULAR = 'regular';
    public const BEHAV_BAD = 'bad';

    /** @var array<int,string> */
    protected $fillable = [
        'game_table_id',
        'user_id',
        'is_counted',
        'is_manager',
        'attended', // opcional si tenés columna booleana
        'behavior',

        // compatibles con MesaHonorController:
        'attendance_confirmed_at',
        'attendance_confirmed_by',
        'no_show_at',
        'no_show_by',
    ];

    /** Viajan en JSON para UI */
    /** @var array<int,string> */
    protected $appends = [
        'position',
        'is_player',
        'is_waitlist',
        'user_avatar_url',
        'created_ago',
        'user_display_name',
    ];

    /** Tipos/casts simples y sin features raras de DB */
    /** @var array<string,string> */
    protected $casts = [
        'id' => 'integer',
        'game_table_id' => 'integer',
        'user_id' => 'integer',
        'is_counted' => 'boolean',
        'is_manager' => 'boolean',
        'attended' => 'boolean', // si existe la columna real
        'behavior' => 'string',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        'attendance_confirmed_at' => 'datetime',
        'attendance_confirmed_by' => 'integer',
        'no_show_at' => 'datetime',
        'no_show_by' => 'integer',
    ];

    /** Tocar mesa en cambios (para orden por actividad sin triggers) */
    protected $touches = ['gameTable'];

    /** Cache simple de capacidad por instancia */
    private ?int $memoTableCapacity = null;
    private ?int $memoCapacityTableId = null;

    /* =========================
     * Relaciones
     * ========================= */

    /** @return BelongsTo<GameTable,Signup> */
    public function gameTable(): BelongsTo
    {
        return $this->belongsTo(GameTable::class, 'game_table_id');
    }

    /** @return BelongsTo<User,Signup> */
    public function user(): BelongsTo
    {
        // withDefault evita nulls en blades/accessors
        return $this->belongsTo(User::class)->withDefault([
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
        // orden estable por fecha y ID (tabla FIFO)
        return $q->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /** @param Builder<Signup> $q */
    public function scopeCounted(Builder $q): Builder
    {
        return $q->where('is_counted', 1);
    }

    /** @param Builder<Signup> $q */
    public function scopeManagers(Builder $q): Builder
    {
        return $q->where('is_manager', 1);
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

    /** @param Builder<Signup> $q  — solo los que están dentro de capacidad (jugadores) */
    public function scopePlayers(Builder $q, int $tableId, int $capacity): Builder
    {
        // Sin window functions para compatibilidad: usar created_at asc y cortar en aplicación
        return $q->forTable($tableId)->counted()->ordered()->limit(max(0, $capacity));
    }

    /** @param Builder<Signup> $q  — fuera de capacidad (waitlist) */
    public function scopeWaitlist(Builder $q, int $tableId, int $capacity): Builder
    {
        // Estrategia compatible: agarrar más y filtrar en colecciones
        return $q->forTable($tableId)->ordered();
    }

    /** Precarga liviana de usuario (id, name, username, email, avatar_path) */
    public function scopeWithUserLite(Builder $q): Builder
    {
        return $q->with(['user:id,name,username,email,avatar_path']);
    }

    /* =========================
     * Mutators / Normalizaciones
     * ========================= */

    /** Normaliza behavior a uno de (good|regular|bad|null) */
    protected function behavior(): Attribute
    {
        return Attribute::set(function ($value) {
            if ($value === null || $value === '') {
                return null;
            }
            $v = Str::lower(trim((string) $value));
            return in_array($v, [self::BEHAV_GOOD, self::BEHAV_REGULAR, self::BEHAV_BAD], true)
                ? $v
                : self::BEHAV_REGULAR;
        });
    }

    /**
     * Getter “virtual” para attended:
     * - Si existe columna real `attended`, respeta su valor.
     * - Si no, deriva de `attendance_confirmed_at` / `no_show_at`.
     */
    public function getAttendedAttribute($value): ?bool
    {
        if (array_key_exists('attended', $this->getAttributes())) {
            return $value === null ? null : (bool) $value;
        }

        $confirmed = $this->getAttributes()['attendance_confirmed_at'] ?? null;
        if ($confirmed !== null) {
            return true;
        }

        $noShow = $this->getAttributes()['no_show_at'] ?? null;
        if ($noShow !== null) {
            return false;
        }

        return null;
    }

    /* =========================
     * Atributos calculados
     * ========================= */

    /**
     * Posición (1-based) entre inscriptos contados.
     * Usa relación precargada si está disponible; si no, COUNT eficiente con tie-break por id.
     */
    public function getPositionAttribute(): ?int
    {
        if (!$this->game_table_id || !$this->exists) {
            return null;
        }

        // En memoria si la mesa y sus signups están cargados
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

        // Query estable (sin window functions)
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
                            $q2->where('created_at', '=', $createdAt)
                                ->where('id', '<=', $id);
                        });
                } else {
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

        $cap = $this->tableCapacity();
        if ($cap <= 0) {
            return false;
        }

        $pos = $this->position;
        return $pos ? $pos <= $cap : null;
    }

    /** ¿Está en lista de espera (más allá de capacidad)? */
    public function getIsWaitlistAttribute(): ?bool
    {
        if (!$this->game_table_id) {
            return null;
        }

        $cap = $this->tableCapacity();
        if ($cap <= 0) {
            return false;
        }

        $pos = $this->position;
        return $pos ? $pos > $cap : null;
    }

    /** URL de avatar del usuario (cascada robusta) */
    protected function userAvatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $u = $this->relationLoaded('user') ? $this->user : null;

            if ($u) {
                // Accessor avatar_url del User si existe
                try {
                    if (isset($u->avatar_url) && (string) $u->avatar_url !== '') {
                        return (string) $u->avatar_url;
                    }
                } catch (\Throwable) {
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

    /** “hace 3 min”, “hace 1 hora”, etc. */
    protected function createdAgo(): Attribute
    {
        return Attribute::get(
            fn(): ?string => $this->created_at ? $this->created_at->diffForHumans() : null
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
        static::saving(function (Signup $s): void {
            $s->game_table_id = (int) $s->game_table_id;
            $s->user_id = (int) $s->user_id;
        });
    }

    /* =========================
     * Helpers capacidad / consultas
     * ========================= */

    private function tableCapacity(): int
    {
        $tableId = $this->game_table_id ? (int) $this->game_table_id : null;
        if ($tableId === null) {
            return 0;
        }

        if ($this->memoTableCapacity !== null && $this->memoCapacityTableId === $tableId) {
            return $this->memoTableCapacity;
        }

        $cap = null;

        if ($this->relationLoaded('gameTable')) {
            $mesa = $this->getRelation('gameTable');
            if ($mesa) {
                $cap = $mesa->capacity;
            }
        }

        if ($cap === null) {
            $cap = GameTable::query()->whereKey($tableId)->value('capacity');
        }

        $capInt = (int) ($cap ?? 0);
        $this->memoCapacityTableId = $tableId;

        return $this->memoTableCapacity = max(0, $capInt);
    }

    /* =========================
     * Utils (asset/strings/SVG)
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
  <text x="50%" y="50%" dy="0.35em" text-anchor="middle"
        font-family="ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu"
        font-size="{$fontSize}" font-weight="700" fill="{$fg}">{$text}</text>
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
        // ISO 8601 consistente (APIs/JSON)
        return $date->format(DATE_ATOM);
    }
}
