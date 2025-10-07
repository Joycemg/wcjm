<?php declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\HonorEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Mixin para modelos que acumulan "honor" (puntos).
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasHonor
{
    /** Cache por-request del total ya calculado. */
    private ?int $__honor_cached = null;

    /** Cache por-request para evitar múltiples hasColumn() lentos. */
    private static ?bool $__users_has_honor_column = null;

    /** Flag por-request de existencia de tabla honor_events. */
    private static ?bool $__has_honor_events_table = null;

    /**
     * Relación 1:N con eventos de honor.
     *
     * @return HasMany<HonorEvent>
     */
    public function honorEvents(): HasMany
    {
        /** @var Model $this */
        return $this->hasMany(HonorEvent::class);
    }

    // ===================== SCOPES =====================

    /**
     * Proyecta el total de honor como columna "honor_total" sin N+1.
     *
     * @param  Builder<Model&self>  $query
     * @return Builder<Model&self>
     */
    public function scopeWithHonor(Builder $query): Builder
    {
        if (!$this->hasHonorEventsTable()) {
            return $query;
        }

        // Laravel 9+ soporta alias en withSum('relation as alias', 'column')
        try {
            // @phpstan-ignore-next-line
            return $query->withSum('honorEvents as honor_total', 'points');
        } catch (\Throwable) {
            /** @var Model $this */
            $he = (new HonorEvent())->getTable();
            $pk = $this->getKeyName();
            $table = $this->getTable();

            $sub = HonorEvent::query()
                ->selectRaw('COALESCE(SUM(points),0)')
                ->whereColumn("{$he}.user_id", "{$table}.{$pk}");

            return $query->select("{$table}.*")->selectSub($sub, 'honor_total');
        }
    }

    /**
     * Ordena por el total de honor (desc por defecto).
     *
     * @param  Builder<Model&self>  $query
     */
    public function scopeOrderByHonor(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        // Asegurar proyección
        if (!array_key_exists('honor_total', $query->getQuery()->columns ?? [])) {
            $query = $this->scopeWithHonor($query);
        }

        return $query->orderByRaw('COALESCE(honor_total, 0) ' . $direction);
    }

    /**
     * Filtra por honor >= $min.
     *
     * @param  Builder<Model&self>  $query
     */
    public function scopeWhereHonorAtLeast(Builder $query, int $min): Builder
    {
        $query = $this->scopeWithHonor($query);
        return $query->havingRaw('COALESCE(honor_total, 0) >= ?', [$min]);
    }

    /**
     * Filtra por honor <= $max.
     *
     * @param  Builder<Model&self>  $query
     */
    public function scopeWhereHonorAtMost(Builder $query, int $max): Builder
    {
        $query = $this->scopeWithHonor($query);
        return $query->havingRaw('COALESCE(honor_total, 0) <= ?', [$max]);
    }

    // ===================== API principal =====================

    public function addHonor(int $points, ?string $reason = null, array $meta = [], ?string $slug = null): HonorEvent
    {
        /** @var Model $this */
        $uid = (int) $this->getKey();

        $reason = $reason !== null ? trim($reason) : null;
        $slug = $this->normalizeSlug($slug);

        if ($slug !== null) {
            try {
                $event = HonorEvent::firstOrCreate(
                    ['user_id' => $uid, 'slug' => $slug],
                    ['points' => $points, 'reason' => $reason, 'meta' => $meta]
                );
            } catch (QueryException $e) {
                if (!$this->isIntegrityConstraintViolation($e)) {
                    throw $e;
                }
                $event = HonorEvent::query()
                    ->where('user_id', $uid)
                    ->where('slug', $slug)
                    ->firstOrFail();
            }

            $this->refreshHonorAggregate(true);
            return $event;
        }

        $event = HonorEvent::create([
            'user_id' => $uid,
            'points' => $points,
            'reason' => $reason,
            'meta' => $meta,
            'slug' => null,
        ]);

        $this->refreshHonorAggregate(true);
        return $event;
    }

    public function addOrUpdateHonorBySlug(string $slug, int $points, ?string $reason = null, array $meta = []): HonorEvent
    {
        /** @var Model $this */
        $uid = (int) $this->getKey();
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return $this->addHonor($points, $reason, $meta, null);
        }

        $attrs = ['points' => $points, 'reason' => $reason, 'meta' => $meta];

        try {
            /** @var HonorEvent $event */
            $event = HonorEvent::updateOrCreate(['user_id' => $uid, 'slug' => $slug], $attrs);
        } catch (QueryException $e) {
            if (!$this->isTableMissing($e)) {
                throw $e;
            }
            /** @var HonorEvent $event */
            $event = new HonorEvent(['user_id' => $uid] + $attrs + ['slug' => $slug]);
        }

        $this->refreshHonorAggregate(true);
        return $event;
    }

    public function removeHonorEventBySlug(string $slug): bool
    {
        /** @var Model $this */
        $slug = $this->normalizeSlug($slug);
        if ($slug === null) {
            return false;
        }

        try {
            $deleted = HonorEvent::query()
                ->where('user_id', $this->getKey())
                ->where('slug', $slug)
                ->limit(1)
                ->delete() > 0;

            if ($deleted) {
                $this->refreshHonorAggregate(true);
            }

            return $deleted;
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Total calculado (con cache por-request).
     */
    public function getHonorAttribute(): int
    {
        /** @var Model $this */

        if ($this->__honor_cached !== null) {
            return $this->__honor_cached;
        }

        $projected = $this->getAttributes()['honor_total'] ?? null;
        if ($projected !== null) {
            return $this->__honor_cached = (int) $projected;
        }

        if ($this->relationLoaded('honorEvents')) {
            /** @var \Illuminate\Support\Collection $rel */
            $rel = $this->getRelation('honorEvents');
            return $this->__honor_cached = (int) $rel->sum('points');
        }

        try {
            $sum = (int) HonorEvent::query()
                ->where('user_id', $this->getKey())
                ->sum('points');

            return $this->__honor_cached = $sum;
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                return $this->__honor_cached = 0;
            }
            throw $e;
        }
    }

    /**
     * Accessor ergonomico: devuelve la proyección `honor_total` si existe,
     * y si no, cae al cálculo normal (`honor`).
     */
    public function getHonorTotalAttribute(): int
    {
        $projected = $this->getAttributes()['honor_total'] ?? null;
        return $projected !== null ? (int) $projected : $this->honor;
    }

    /**
     * Recalcula y opcionalmente persiste en `users.honor` si existe.
     */
    public function refreshHonorAggregate(bool $persist = false): int
    {
        /** @var Model $this */

        try {
            $total = (int) HonorEvent::query()
                ->where('user_id', $this->getKey())
                ->sum('points');
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                $this->__honor_cached = 0;
                return 0;
            }
            throw $e;
        }

        $this->__honor_cached = $total;

        if ($persist && $this->usersHasHonorColumn()) {
            $this->forceFill(['honor' => $total])->saveQuietly();
        }

        return $total;
    }

    // ===================== Helpers internos =====================

    private function normalizeSlug(?string $slug): ?string
    {
        if ($slug === null)
            return null;
        $slug = trim($slug);
        if ($slug === '')
            return null;
        if (\strlen($slug) > 191) {
            $slug = \substr($slug, 0, 191);
        }
        return $slug;
    }

    private function usersHasHonorColumn(): bool
    {
        if (self::$__users_has_honor_column !== null) {
            return self::$__users_has_honor_column;
        }

        /** @var Model $this */
        $table = $this->getTable();

        return self::$__users_has_honor_column = Schema::hasColumn($table, 'honor');
    }

    private function hasHonorEventsTable(): bool
    {
        if (self::$__has_honor_events_table !== null) {
            return self::$__has_honor_events_table;
        }

        try {
            self::$__has_honor_events_table = Schema::hasTable((new HonorEvent())->getTable());
        } catch (\Throwable) {
            self::$__has_honor_events_table = false;
        }

        return self::$__has_honor_events_table;
    }

    private function isIntegrityConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $code = (string) $e->getCode();
        $msg = strtolower($e->getMessage());

        if ($sqlState === '23000' || $code === '23000')
            return true; // ANSI
        if ($sqlState === '23505' || $code === '23505')
            return true; // PostgreSQL unique_violation

        return str_contains($msg, 'unique') || str_contains($msg, 'duplicate');
    }

    private function isTableMissing(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverErr = (string) ($e->errorInfo[1] ?? '');
        $code = (string) $e->getCode();
        $msg = strtolower($e->getMessage());

        if ($sqlState === '42S02' || $driverErr === '1146')
            return true; // MySQL/MariaDB
        if ($driverErr === '1' && str_contains($msg, 'no such table'))
            return true; // SQLite
        if ($sqlState === '42P01' || $code === '42P01')
            return true; // PostgreSQL undefined_table

        return str_contains($msg, 'honor_events') &&
            (str_contains($msg, 'does not exist') ||
                str_contains($msg, "doesn't exist") ||
                str_contains($msg, 'not found'));
    }
}
