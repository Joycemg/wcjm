<?php declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * VoteHistory
 * - Ligero y compatible con hosting compartido.
 * - Soporta esquemas “flexibles” (con o sin columnas kind/closed_at).
 *
 * Campos habituales:
 *  - user_id (int)
 *  - game_table_id (int)
 *  - game_title (string)
 *  - kind (string|null)        // opcional
 *  - happened_at (datetime|null)
 *  - closed_at (datetime|null) // opcional
 *  - created_at/updated_at (timestamps opcionales)
 *
 * Accessors:
 *  - event_time: CarbonInterface|null (preferencia: happened_at > closed_at > created_at)
 */
class VoteHistory extends Model
{
    use HasFactory;

    /** @var array<int,string> */
    protected $fillable = [
        'user_id',
        'game_table_id',
        'game_title',
        'kind',
        'happened_at',
        'closed_at',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'happened_at' => 'immutable_datetime',
        'closed_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /** @var array<int,string> */
    protected $appends = ['event_time'];

    /* =========================
     * Relaciones
     * ========================= */

    /** @return BelongsTo<GameTable,VoteHistory> */
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(GameTable::class, 'game_table_id');
    }

    /** @return BelongsTo<User,VoteHistory> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* =========================
     * Accessors
     * ========================= */

    /**
     * event_time: fecha “del evento” agnóstica de esquema.
     * Usa happened_at; si no existe/está null, fallback a closed_at, luego created_at.
     */
    protected function eventTime(): Attribute
    {
        return Attribute::get(function () {
            /** @var \Carbon\CarbonInterface|null $h */
            $h = $this->getAttribute('happened_at');
            if ($h)
                return $h;

            /** @var \Carbon\CarbonInterface|null $c */
            $c = $this->getAttribute('closed_at');
            if ($c)
                return $c;

            /** @var \Carbon\CarbonInterface|null $cr */
            $cr = $this->getAttribute('created_at');
            return $cr;
        });
    }

    /* =========================
     * Scopes
     * ========================= */

    /** @param Builder<VoteHistory> $q */
    public function scopeByUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /** @param Builder<VoteHistory> $q */
    public function scopeByMesa(Builder $q, int $mesaId): Builder
    {
        return $q->where('game_table_id', $mesaId);
    }

    /**
     * Filtra por kind='close' sólo si la columna existe (compatibilidad hacia atrás).
     * @param Builder<VoteHistory> $q
     */
    public function scopeKindClose(Builder $q): Builder
    {
        return self::hasKindColumn()
            ? $q->where('kind', 'close')
            : $q;
    }

    /**
     * Ordena por “fecha de evento” y limita resultados (usa columna disponible).
     * @param Builder<VoteHistory> $q
     */
    public function scopeRecent(Builder $q, int $limit = 30): Builder
    {
        return $q->orderByDesc(self::eventColumn())->limit(max(1, $limit));
    }

    /* =========================
     * Eloquent
     * ========================= */

    protected function serializeDate(DateTimeInterface $date): string
    {
        // ISO 8601 (consistente en API/JSON)
        return $date->format(DATE_ATOM);
    }

    /* =========================
     * Helpers internos
     * ========================= */

    private static ?bool $memoHasKind = null;
    private static ?string $memoEventCol = null;

    private static function hasKindColumn(): bool
    {
        if (self::$memoHasKind !== null) {
            return self::$memoHasKind;
        }
        // Evita errores en instalaciones donde aún no existe la columna
        return self::$memoHasKind = Schema::hasColumn('vote_histories', 'kind');
    }

    /**
     * Devuelve la mejor columna de orden para “fecha de evento”.
     * Prioridad: happened_at > closed_at > created_at
     */
    private static function eventColumn(): string
    {
        if (self::$memoEventCol !== null) {
            return self::$memoEventCol;
        }

        $table = 'vote_histories';
        if (Schema::hasColumn($table, 'happened_at')) {
            return self::$memoEventCol = 'happened_at';
        }
        if (Schema::hasColumn($table, 'closed_at')) {
            return self::$memoEventCol = 'closed_at';
        }
        // created_at es estándar
        return self::$memoEventCol = 'created_at';
    }
}
