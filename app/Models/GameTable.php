<?php declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Modelo de Mesa (GameTable)
 * - Optimizado para hosting compartido.
 *
 * @property int                        $id
 * @property string                     $title
 * @property string|null                $description
 * @property int                        $capacity
 * @property string|null                $image_path
 * @property string|null                $image_url
 * @property bool                       $is_open
 * @property \Carbon\CarbonImmutable|null $opens_at
 * @property \Carbon\CarbonImmutable|null $closed_at
 * @property int|null                   $created_by
 * @property int|null                   $manager_id
 * @property bool                       $manager_counts_as_player
 * @property string|null                $join_url
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 *
 * @property-read bool                  $is_open_now
 * @property-read bool                  $is_full
 * @property-read int                   $seats_taken
 * @property-read int                   $seats_left
 * @property-read int                   $occupancy_percent
 * @property-read string|null           $image_url_resolved
 */
class GameTable extends Model
{
    use HasFactory;

    public const RECENT_SIGNUPS_LIMIT = 8;

    protected $perPage = 12;
    private static ?string $memoTz = null;

    /** @var array<int,string> */
    protected $fillable = [
        'title',
        'description',
        'capacity',
        'image_path',
        'image_url',
        'is_open',
        'opens_at',
        'created_by',
        'closed_at',
        'join_url',
        'manager_id',
        'manager_counts_as_player',
        'manager_note',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'capacity' => 'int',
        'is_open' => 'bool',
        'created_by' => 'int',
        'manager_id' => 'int',
        'manager_counts_as_player' => 'bool',
        'opens_at' => 'immutable_datetime',
        'closed_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /** @var array<string,mixed> */
    protected $attributes = [
        'is_open' => false,
        'manager_counts_as_player' => true,
    ];

    /** @var array<int,string> */
    protected $appends = [
        'is_open_now',
        'is_full',
        'image_url_resolved',
        'seats_taken',
        'seats_left',
        'occupancy_percent',
    ];

    /* =========================
     * Relaciones
     * ========================= */

    /** @return HasMany<Signup> */
    public function signups(): HasMany
    {
        return $this->hasMany(Signup::class, 'game_table_id')->orderBy('created_at', 'asc');
    }

    /** @return HasMany<Signup> */
    public function recentSignups(): HasMany
    {
        return $this->hasMany(Signup::class, 'game_table_id')
            ->where('is_counted', 1)
            ->orderBy('created_at', 'desc')
            ->limit(self::RECENT_SIGNUPS_LIMIT);
    }

    /** @return BelongsTo<User,GameTable> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')
            ->withDefault(['name' => null, 'username' => null, 'email' => null, 'avatar_path' => null]);
    }

    /** @return BelongsTo<User,GameTable> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id')
            ->withDefault(['name' => null, 'username' => null, 'email' => null, 'avatar_path' => null]);
    }

    /* =========================
     * Scopes
     * ========================= */

    /** Búsqueda por título */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '')
            return $q;
        $like = '%' . addcslashes($term, "%_\\") . '%';
        return $q->where('title', 'LIKE', $like);
    }

    /** Campo SQL computado is_open_now */
    public function scopeSelectIsOpenNow(Builder $query): Builder
    {
        $t = $query->getModel()->getTable();
        $now = CarbonImmutable::now(self::appTz())->toDateTimeString();
        return $query->select("$t.*")->selectRaw(
            "CASE WHEN {$t}.is_open = 1 AND ({$t}.opens_at IS NULL OR {$t}.opens_at <= ?) THEN 1 ELSE 0 END AS is_open_now",
            [$now]
        );
    }

    /** Listado para tarjetas */
    public function scopeForCards(Builder $query): Builder
    {
        $t = $query->getModel()->getTable();
        return $query->selectIsOpenNow()
            ->withCount(['signups as signups_count' => fn($q) => $q->where('is_counted', 1)])
            ->with([
                'recentSignups' => fn($q) => $q->where('is_counted', 1),
                'recentSignups.user:id,username,name,email,avatar_path,updated_at',
            ])
            ->orderByDesc("$t.created_at");
    }

    /** Mesas abiertas ahora */
    public function scopeOpenNow(Builder $query): Builder
    {
        $now = CarbonImmutable::now(self::appTz())->toDateTimeString();
        return $query->where('is_open', true)
            ->where(fn($w) => $w->whereNull('opens_at')->orWhere('opens_at', '<=', $now));
    }

    /** Mesas cerradas ahora */
    public function scopeClosedNow(Builder $query): Builder
    {
        $now = CarbonImmutable::now(self::appTz())->toDateTimeString();
        return $query->where(fn($w) => $w
            ->where('is_open', false)
            ->orWhere(fn($w2) => $w2->where('is_open', true)->where('opens_at', '>', $now)));
    }

    /* =========================
     * Accessors
     * ========================= */

    protected function imageUrlResolved(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = (string) ($this->image_path ?? '');

            // 1) Path absoluto o data-uri
            if ($path !== '' && Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
                return $path;
            }

            // 2) Storage público / S3 seguro
            if ($path !== '') {
                $normalized = ltrim($path, '/');
                try {
                    $disk = Storage::disk(config('mesas.image_disk', config('filesystems.default', 'public')));
                    if (is_object($disk) && method_exists($disk, 'url')) {
                        $url = $disk->url($normalized);
                        if (is_string($url) && $url !== '') {
                            return $url;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::debug('Error resolviendo URL de imagen', [
                        'path' => $path,
                        'msg' => $e->getMessage(),
                    ]);
                }

                // 3) Fallback local (hosting compartido)
                return function_exists('asset')
                    ? asset('storage/' . $normalized)
                    : '/storage/' . $normalized;
            }

            // 4) URL directa si no hay archivo
            $url = (string) ($this->image_url ?? '');
            return $url !== '' ? $url : null;
        });
    }

    public function getIsOpenNowAttribute(): bool
    {
        if (!$this->is_open)
            return false;
        $tz = self::appTz();
        $now = CarbonImmutable::now($tz);
        return !$this->opens_at || $now->greaterThanOrEqualTo($this->opens_at->timezone($tz));
    }

    public function getSeatsTakenAttribute(): int
    {
        if (($c = $this->getAttribute('signups_count')) !== null)
            return (int) $c;
        if ($this->relationLoaded('signups'))
            return $this->signups->where('is_counted', 1)->count();
        return (int) $this->signups()->where('is_counted', 1)->count();
    }

    public function getIsFullAttribute(): bool
    {
        return $this->getSeatsTakenAttribute() >= (int) $this->capacity;
    }

    public function getSeatsLeftAttribute(): int
    {
        return max(0, (int) $this->capacity - $this->getSeatsTakenAttribute());
    }

    public function getOccupancyPercentAttribute(): int
    {
        $cap = max(1, (int) $this->capacity);
        return min(100, max(0, (int) round(($this->getSeatsTakenAttribute() / $cap) * 100)));
    }

    /* =========================
     * Hooks y utilitarios
     * ========================= */

    protected static function booted(): void
    {
        static::saving(function (GameTable $t) {
            $t->capacity = max(1, (int) ($t->capacity ?? 1));
            if ($t->opens_at instanceof CarbonInterface && $t->opens_at->isFuture() && !$t->isDirty('is_open')) {
                $t->is_open = true;
            }
        });
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }

    private static function appTz(): string
    {
        if (self::$memoTz)
            return self::$memoTz;
        $tz = config('app.display_timezone') ?: config('app.timezone', 'UTC');
        return self::$memoTz = is_string($tz) && $tz !== '' ? $tz : 'UTC';
    }
}
