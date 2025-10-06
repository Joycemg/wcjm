<?php
declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

/**
 * Modelo de Mesa (GameTable)
 * - Optimizado para hosting compartido: queries mínimas, cálculos en BD cuando conviene.
 * - Accesors no disparan N+1 (usan consultas cuando la relación no está cargada).
 */
class GameTable extends Model
{
    public const RECENT_SIGNUPS_LIMIT = 8;

    /** Paginación default */
    protected $perPage = 12;

    /** Cache simple del huso para evitar repetir config() */
    private static ?string $memoTz = null;

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

    protected $attributes = [
        'is_open' => false,
        'manager_counts_as_player' => true,
    ];

    /** Atributos calculados que se devuelven en toArray()/JSON */
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
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User,GameTable> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /* =========================
     * Scopes
     * ========================= */

    /**
     * Agrega columna computada is_open_now en SQL (evita calcular en PHP para listados).
     */
    public function scopeSelectIsOpenNow(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $now = CarbonImmutable::now(self::appTz())->toDateTimeString();

        return $query->select("$table.*")->selectRaw(
            'CASE WHEN ' . $table . '.is_open = 1 AND (' . $table . ".opens_at IS NULL OR " . $table . '.opens_at <= ?) THEN 1 ELSE 0 END AS is_open_now',
            [$now]
        );
    }

    /**
     * Listado para tarjetas: open_now + counts + últimos signups con user básico.
     */
    public function scopeForCards(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->selectIsOpenNow()
            ->withCount(['signups as signups_count' => fn($q) => $q->where('is_counted', 1)])
            ->with([
                'recentSignups' => fn($q) => $q->where('is_counted', 1),
                'recentSignups.user:id,username,name,email,avatar_path,updated_at',
            ])
            ->orderByDesc("$table.created_at");
    }

    /**
     * Mesas abiertas ahora (server-side).
     */
    public function scopeOpenNow(Builder $query): Builder
    {
        $now = CarbonImmutable::now(self::appTz())->toDateTimeString();
        return $query->where('is_open', true)
            ->where(function (Builder $w) use ($now) {
                $w->whereNull('opens_at')->orWhere('opens_at', '<=', $now);
            });
    }

    /**
     * Mesas cerradas ahora (server-side).
     */
    public function scopeClosedNow(Builder $query): Builder
    {
        $now = CarbonImmutable::now(self::appTz())->toDateTimeString();
        return $query->where(function (Builder $w) use ($now) {
            $w->where('is_open', false)
                ->orWhere(function (Builder $w2) use ($now) {
                    $w2->where('is_open', true)->where('opens_at', '>', $now);
                });
        });
    }

    /* =========================
     * Accessors/Computed
     * ========================= */

    public function getIsOpenNowAttribute(): bool
    {
        if (!$this->is_open) {
            return false;
        }
        $tz = self::appTz();
        $now = CarbonImmutable::now($tz);
        $open = $this->opens_at;

        if ($open instanceof CarbonInterface) {
            return $now->greaterThanOrEqualTo($open->timezone($tz));
        }

        return true;
    }

    public function getSeatsTakenAttribute(): int
    {
        // Usa withCount si vino disponible
        $countAttr = $this->getAttribute('signups_count');
        if ($countAttr !== null) {
            return (int) $countAttr;
        }

        // Si la relación está cargada, calcular en memoria
        if ($this->relationLoaded('signups')) {
            return $this->signups->where('is_counted', 1)->count();
        }

        // Caso general: contar en BD
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
        $pct = (int) round(($this->getSeatsTakenAttribute() / $cap) * 100);
        return max(0, min(100, $pct));
    }

    /**
     * URL de imagen resolviendo disco público/S3; prioriza archivo sobre URL.
     */
    protected function imageUrlResolved(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = (string) ($this->image_path ?? '');

            // 1) Path absoluto o data-uri
            if ($path !== '' && Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
                return $path;
            }

            // 2) Storage público / S3
            if ($path !== '') {
                $normalized = ltrim($path, '/');
                try {
                    $disk = Storage::disk((string) config('mesas.image_disk', config('filesystems.default', 'public')));
                    if (method_exists($disk, 'url')) {
                        return $disk->url($normalized);
                    }
                } catch (\Throwable) {
                    // ignore
                }

                // 3) Fallback local
                return function_exists('asset')
                    ? asset('storage/' . $normalized)
                    : '/storage/' . $normalized;
            }

            // 4) URL directa si no hay archivo
            $url = (string) ($this->image_url ?? '');
            return $url !== '' ? $url : null;
        });
    }

    /**
     * Jugadores confirmados (is_counted=1) hasta la capacidad.
     * Si la relación no está cargada, hace una consulta limitada (no carga toda la lista).
     *
     * @return Collection<int, Signup>
     */
    public function getPlayersAttribute(): Collection
    {
        $cap = max(0, (int) $this->capacity);

        if ($this->relationLoaded('signups')) {
            /** @var Collection<int, Signup> $all */
            $all = $this->signups;
            return $all->where('is_counted', 1)->take($cap)->values();
        }

        /** @var Collection<int, Signup> $rows */
        $rows = $this->signups()
            ->where('is_counted', 1)
            ->limit($cap)
            ->get();

        return $rows->values();
    }

    /**
     * Lista de espera (is_counted=1) a partir de la capacidad.
     * Si no está cargada la relación, consulta con offset en BD (eficiente).
     *
     * @return Collection<int, Signup>
     */
    public function getWaitlistAttribute(): Collection
    {
        $cap = max(0, (int) $this->capacity);

        if ($this->relationLoaded('signups')) {
            /** @var Collection<int, Signup> $all */
            $all = $this->signups;
            return $all->where('is_counted', 1)->slice($cap)->values();
        }

        /** @var Collection<int, Signup> $rows */
        $rows = $this->signups()
            ->where('is_counted', 1)
            ->offset($cap)
            ->limit((int) config('mesas.waitlist_max', 10000))
            ->get();

        return $rows->values();
    }

    /* =========================
     * Cierre e historial
     * ========================= */

    /**
     * IDs de jugadores finales a la hora de cerrar (capacidad, por orden de signup).
     *
     * @return list<int>
     */
    protected function finalPlayersUserIds(): array
    {
        $cap = max(1, (int) ($this->capacity ?? 1));

        $ids = DB::table('signups')
            ->where('game_table_id', $this->id)
            ->where('is_counted', 1)
            ->orderBy('created_at', 'asc')
            ->limit($cap)
            ->pluck('user_id')
            ->all();

        return array_values(array_unique(array_map('intval', $ids)));
    }

    protected function recordCloseHistoryForFinalPlayers(): void
    {
        if (!Schema::hasTable('vote_histories')) {
            return;
        }

        $hasKind = Schema::hasColumn('vote_histories', 'kind');
        $hasHappened = Schema::hasColumn('vote_histories', 'happened_at');
        $hasClosed = Schema::hasColumn('vote_histories', 'closed_at');
        $hasCreated = Schema::hasColumn('vote_histories', 'created_at');
        $hasUpdated = Schema::hasColumn('vote_histories', 'updated_at');

        $title = (string) ($this->title ?? 'Mesa');
        $when = $this->closed_at ?? CarbonImmutable::now(self::appTz());
        $uids = $this->finalPlayersUserIds();

        if (empty($uids)) {
            return;
        }

        $nowTs = now();

        foreach ($uids as $uid) {
            $match = ['user_id' => (int) $uid, 'game_table_id' => (int) $this->id];
            if ($hasKind) {
                $match['kind'] = 'close';
            }

            $data = ['game_title' => $title];
            if ($hasHappened) {
                $data['happened_at'] = $when;
            } elseif ($hasClosed) {
                $data['closed_at'] = $when;
            }
            if ($hasCreated) {
                $data['created_at'] = $nowTs;
            }
            if ($hasUpdated) {
                $data['updated_at'] = $nowTs;
            }

            try {
                DB::table('vote_histories')->updateOrInsert($match, $data);
            } catch (\Throwable $e) {
                try {
                    DB::table('vote_histories')->insert($match + $data);
                } catch (\Throwable $e2) {
                    \Log::warning('vote_histories upsert/insert falló', [
                        'mesa_id' => $this->id,
                        'user_id' => $uid,
                        'e1' => $e->getMessage(),
                        'e2' => $e2->getMessage(),
                    ]);
                }
            }
        }
    }

    /* =========================
     * Eloquent hooks
     * ========================= */

    protected static function booted(): void
    {
        // Normalizaciones y defaults antes de guardar
        static::saving(function (GameTable $table) {
            $table->title = Str::limit(Str::of((string) ($table->title ?? ''))->squish()->trim(), 120, '');

            if ($table->description !== null) {
                $desc = Str::of((string) $table->description)->trim();
                $table->description = Str::limit((string) $desc, 2000, '');
            }

            $table->capacity = max(1, (int) ($table->capacity ?? 1));

            if ($table->opens_at) {
                $tz = self::appTz();
                $dt = $table->opens_at instanceof CarbonInterface
                    ? CarbonImmutable::instance($table->opens_at)
                    : CarbonImmutable::parse((string) $table->opens_at, $tz);

                // normalizar a precisión de minuto en TZ de app
                $table->opens_at = CarbonImmutable::createFromFormat('Y-m-d H:i', $dt->format('Y-m-d H:i'), $tz);
            }

            // Si se programa a futuro y no se tocó is_open explícitamente, abrirla
            if ($table->opens_at instanceof CarbonInterface && $table->opens_at->isFuture() && !$table->isDirty('is_open')) {
                $table->is_open = true;
            }
        });

        // Sincroniza signup del encargado post-save
        static::saved(function (GameTable $table) {
            if (!$table->manager_id) {
                return;
            }

            try {
                $signup = Signup::firstOrCreate(
                    ['game_table_id' => $table->id, 'user_id' => $table->manager_id],
                    ['is_manager' => 1, 'is_counted' => $table->manager_counts_as_player ? 1 : 0]
                );

                // Ajustar flags siempre por si cambió manager_counts_as_player
                $signup->is_manager = 1;
                $signup->is_counted = $table->manager_counts_as_player ? 1 : 0;
                $signup->saveQuietly();
            } catch (\Throwable $e) {
                \Log::warning('No se pudo sincronizar signup del manager', [
                    'mesa_id' => $table->id,
                    'manager_id' => $table->manager_id,
                    'msg' => $e->getMessage(),
                ]);
            }
        });

        // Al cerrar la mesa, fijar closed_at (si falta) y registrar historial
        static::updated(function (GameTable $table) {
            if ($table->wasChanged('is_open') && $table->is_open === false) {
                if (!$table->closed_at) {
                    $tz = self::appTz();
                    $now = CarbonImmutable::now($tz);
                    $table->closed_at = CarbonImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d H:i'), $tz);
                    $table->saveQuietly();
                }
                $table->recordCloseHistoryForFinalPlayers();
            }
        });

        // Eliminar imagen local al borrar (si es de nuestra carpeta)
        static::deleting(function (GameTable $table) {
            $path = (string) ($table->image_path ?? '');
            if ($path !== '' && Str::startsWith($path, 'mesas/')) {
                try {
                    Storage::disk((string) config('mesas.image_disk', config('filesystems.default', 'public')))->delete($path);
                } catch (\Throwable) {
                    // no romper en hosting compartido
                }
            }
        });
    }

    /* =========================
     * Serialización
     * ========================= */

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }

    /* =========================
     * Helpers
     * ========================= */

    private static function appTz(): string
    {
        if (self::$memoTz !== null) {
            return self::$memoTz;
        }
        $tz = config('app.display_timezone') ?: config('app.timezone', 'UTC');
        return self::$memoTz = (is_string($tz) && $tz !== '') ? $tz : 'UTC';
    }
}
