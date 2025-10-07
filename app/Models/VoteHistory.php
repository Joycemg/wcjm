<?php declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Class VoteHistory
 *
 * Representa un registro histórico de votaciones en mesas de juego.
 * Es liviano, compatible con hosting compartido y tolerante a esquemas
 * con columnas opcionales (kind, closed_at).
 *
 * Campos comunes:
 * - user_id (int)
 * - game_table_id (int)
 * - game_title (string)
 * - kind (string|null)
 * - happened_at (datetime|null)
 * - closed_at (datetime|null)
 * - created_at, updated_at (opcional)
 *
 * Propiedades dinámicas:
 * @property int                         $id
 * @property int                         $user_id
 * @property int                         $game_table_id
 * @property string                      $game_title
 * @property string|null                 $kind
 * @property CarbonImmutable|null        $happened_at
 * @property CarbonImmutable|null        $closed_at
 * @property CarbonImmutable|null        $created_at
 * @property CarbonImmutable|null        $updated_at
 * @property-read CarbonImmutable|null   $event_time
 *
 * Relaciones:
 * @property-read User                   $user
 * @property-read GameTable              $mesa
 *
 * Scopes disponibles:
 * @method static Builder|self byUser(int $userId)
 * @method static Builder|self byMesa(int $mesaId)
 * @method static Builder|self kindClose()
 * @method static Builder|self recent(int $limit = 30)
 * @method static Builder|self between(?string $fromIso, ?string $toIso)
 * @method static Builder|self searchTitle(?string $query)
 * @method static Builder|self forPair(int $userId, int $mesaId)
 * @method static Builder|self selectLight()
 */
final class VoteHistory extends Model
{
    use HasFactory;

    public const TABLE = 'vote_histories';

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

    /** @return BelongsTo<GameTable,self> */
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(GameTable::class, 'game_table_id');
    }

    /** @return BelongsTo<User,self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* =========================
     * Accessors
     * ========================= */

    /**
     * event_time: devuelve la fecha más representativa del evento.
     * Prioridad: happened_at → closed_at → created_at.
     */
    protected function eventTime(): Attribute
    {
        return Attribute::get(
            fn() =>
            $this->getAttribute('happened_at')
            ?? $this->getAttribute('closed_at')
            ?? $this->getAttribute('created_at')
        );
    }

    /* =========================
     * Hooks de modelo
     * ========================= */

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            // Completa automáticamente game_title si está vacío.
            if (trim((string) $m->game_title) !== '') {
                return;
            }

            $mesa = $m->getRelationValue('mesa');
            if ($mesa && isset($mesa->title)) {
                $m->game_title = Str::limit((string) $mesa->title, 180, '');
            }
        });
    }

    /* =========================
     * Scopes
     * ========================= */

    /** @param Builder<self> $q */
    public function scopeByUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /** @param Builder<self> $q */
    public function scopeByMesa(Builder $q, int $mesaId): Builder
    {
        return $q->where('game_table_id', $mesaId);
    }

    /** @param Builder<self> $q */
    public function scopeKindClose(Builder $q): Builder
    {
        return self::hasKindColumn()
            ? $q->where('kind', 'close')
            : $q;
    }

    /** @param Builder<self> $q */
    public function scopeRecent(Builder $q, int $limit = 30): Builder
    {
        $limit = max(1, min(200, $limit)); // límite defensivo
        return $q->orderByDesc(self::eventColumn())->limit($limit);
    }

    /** @param Builder<self> $q */
    public function scopeBetween(Builder $q, ?string $fromIso, ?string $toIso): Builder
    {
        $col = self::eventColumn();
        if ($fromIso) {
            $q->where($col, '>=', $fromIso);
        }
        if ($toIso) {
            $q->where($col, '<=', $toIso);
        }
        return $q;
    }

    /** @param Builder<self> $q */
    public function scopeSearchTitle(Builder $q, ?string $query): Builder
    {
        $query = trim((string) $query);
        if ($query === '') {
            return $q;
        }
        $safe = str_replace(['%', '_'], ['\%', '\_'], $query);
        return $q->where('game_title', 'like', '%' . $safe . '%');
    }

    /** @param Builder<self> $q */
    public function scopeForPair(Builder $q, int $userId, int $mesaId): Builder
    {
        return $q->where('user_id', $userId)
            ->where('game_table_id', $mesaId);
    }

    /** @param Builder<self> $q */
    public function scopeSelectLight(Builder $q): Builder
    {
        return $q->select([
            'id',
            'user_id',
            'game_table_id',
            'game_title',
            'happened_at',
            'closed_at',
            'created_at',
        ]);
    }

    /* =========================
     * Eloquent
     * ========================= */

    protected function serializeDate(DateTimeInterface $date): string
    {
        // ISO 8601 consistente en JSON/API
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
        return self::$memoHasKind = Schema::hasColumn(self::TABLE, 'kind');
    }

    /**
     * Determina la mejor columna de orden para “fecha de evento”.
     * Prioridad: happened_at > closed_at > created_at.
     */
    private static function eventColumn(): string
    {
        if (self::$memoEventCol !== null) {
            return self::$memoEventCol;
        }

        $t = self::TABLE;
        if (Schema::hasColumn($t, 'happened_at')) {
            return self::$memoEventCol = 'happened_at';
        }
        if (Schema::hasColumn($t, 'closed_at')) {
            return self::$memoEventCol = 'closed_at';
        }
        return self::$memoEventCol = 'created_at';
    }
}
