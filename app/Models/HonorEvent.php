<?php declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo: HonorEvent
 * ------------------------------------------------------------
 * Registra los eventos de honor de un usuario, con puntos positivos o negativos,
 * motivo (reason) y metadatos opcionales. Incluye slug para evitar duplicados
 * (idempotencia lógica).
 *
 * Estructura esperada:
 * - id (int)
 * - user_id (int)
 * - points (int)
 * - reason (string)
 * - meta (json|null)
 * - slug (string|null, único)
 * - created_at / updated_at (CarbonImmutable)
 *
 * Propiedades útiles:
 * @property int                          $id
 * @property int                          $user_id
 * @property int                          $points
 * @property string                       $reason
 * @property array|null                   $meta
 * @property string|null                  $slug
 * @property CarbonImmutable|null         $created_at
 * @property CarbonImmutable|null         $updated_at
 *
 * Relaciones:
 * @property-read \App\Models\User        $user
 *
 * Scopes:
 * @method static Builder|self byUser(int $userId)
 * @method static Builder|self recent(int $limit = 50)
 * @method static Builder|self withSlug(string $slug)
 * @method static Builder|self reason(string $reason)
 */
final class HonorEvent extends Model
{
    use HasFactory;

    /* =========================================================
     * Constantes de dominio
     * ========================================================= */

    /** Decaimiento mensual por inactividad */
    public const R_DECAY = 'decay:inactivity';

    /** Asistencia */
    public const R_ATTEND_OK = 'attendance:confirmed';
    public const R_ATTEND_UNDO = 'attendance:undo';

    /** No show */
    public const R_NO_SHOW = 'no_show';

    /** Comportamiento */
    public const R_BEHAV_GOOD = 'behavior:good';
    public const R_BEHAV_BAD = 'behavior:bad';
    public const R_BEHAV_UNDO_GOOD = 'behavior:undo_good';
    public const R_BEHAV_UNDO_BAD = 'behavior:undo_bad';

    /* =========================================================
     * Configuración Eloquent
     * ========================================================= */

    /** @var array<int,string> */
    protected $fillable = [
        'user_id',
        'points',
        'reason',
        'meta',
        'slug',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'points' => 'integer',
        'meta' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /* =========================================================
     * Relaciones
     * ========================================================= */

    /** @return BelongsTo<User, HonorEvent> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /* =========================================================
     * Scopes
     * ========================================================= */

    /** @param Builder<self> $q */
    public function scopeByUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    /** @param Builder<self> $q */
    public function scopeRecent(Builder $q, int $limit = 50): Builder
    {
        return $q->orderByDesc('id')->limit(max(1, $limit));
    }

    /** @param Builder<self> $q */
    public function scopeWithSlug(Builder $q, string $slug): Builder
    {
        return $q->where('slug', $slug);
    }

    /** @param Builder<self> $q */
    public function scopeReason(Builder $q, string $reason): Builder
    {
        return $q->where('reason', $reason);
    }

    /* =========================================================
     * Métodos de dominio / utilitarios
     * ========================================================= */

    /** Devuelve true si el evento otorga puntos. */
    public function isPositive(): bool
    {
        return $this->points > 0;
    }

    /** Devuelve true si el evento resta puntos. */
    public function isNegative(): bool
    {
        return $this->points < 0;
    }

    /** Devuelve true si el evento es neutro. */
    public function isNeutral(): bool
    {
        return $this->points === 0;
    }

    /**
     * Crea o recupera un evento según slug (idempotencia).
     * Si no hay slug, se crea siempre un nuevo registro.
     *
     * @param array<string,mixed>|null $meta
     */
    public static function firstOrCreateBySlug(
        int $userId,
        int $points,
        string $reason,
        ?array $meta = null,
        ?string $slug = null
    ): self {
        $payload = [
            'user_id' => $userId,
            'points' => $points,
            'reason' => $reason,
            'meta' => $meta,
            'slug' => $slug ?: null,
        ];

        if ($slug && $slug !== '') {
            return static::firstOrCreate(
                ['user_id' => $userId, 'slug' => $slug],
                $payload
            );
        }

        return static::create($payload);
    }

    /**
     * Borra un evento específico (si existe) según slug.
     */
    public static function removeBySlug(int $userId, string $slug): bool
    {
        if (trim($slug) === '') {
            return false;
        }

        return (bool) static::query()
            ->where('user_id', $userId)
            ->where('slug', $slug)
            ->limit(1)
            ->delete();
    }

    /* =========================================================
     * Serialización consistente
     * ========================================================= */

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format(DATE_ATOM);
    }
}
