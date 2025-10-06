<?php declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\HonorEvent;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * Mixin para modelos que acumulan "honor" (puntos).
 * - Idempotencia vía slug (unique por user_id + slug).
 * - Cálculo de total eficiente (usa withCount/withSum si está disponible).
 * - Seguro para hosting compartido (sin operaciones pesadas por defecto).
 *
 * Úsalo en App\Models\User:
 *   use HasHonor;
 */
trait HasHonor
{
    /**
     * Relación 1:N con eventos de honor.
     *
     * @return HasMany<HonorEvent>
     */
    public function honorEvents(): HasMany
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        return $this->hasMany(HonorEvent::class);
    }

    /**
     * Registra un evento de honor.
     * - Si $slug viene, es idempotente (firstOrCreate): no duplica si ya existe.
     * - $reason puede ser null (razón opcional).
     * - $meta se guarda tal cual (debe castear a JSON en HonorEvent).
     */
    public function addHonor(int $points, ?string $reason = null, array $meta = [], ?string $slug = null): HonorEvent
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        $uid = (int) $this->getKey();

        // Normalizar razón y slug para evitar sorpresas (índices VARCHAR(191))
        $reason = $reason !== null ? trim($reason) : null;
        $slug = $slug !== null ? trim($slug) : null;
        if ($slug !== null && $slug !== '' && \strlen($slug) > 191) {
            $slug = \substr($slug, 0, 191);
        }
        if ($slug === '') {
            $slug = null;
        }

        if ($slug !== null) {
            try {
                return HonorEvent::firstOrCreate(
                    ['user_id' => $uid, 'slug' => $slug],
                    ['points' => $points, 'reason' => $reason, 'meta' => $meta]
                );
            } catch (QueryException $e) {
                $errorCode = (string) ($e->getCode() ?? '');
                $sqlState = (string) ($e->errorInfo[0] ?? '');

                if ($errorCode !== '23000' && $sqlState !== '23000') {
                    throw $e;
                }

                // Otro proceso lo insertó: devolvemos el existente (idempotencia).
                return HonorEvent::query()
                    ->where('user_id', $uid)
                    ->where('slug', $slug)
                    ->firstOrFail();
            }
        }

        return HonorEvent::create([
            'user_id' => $uid,
            'points' => $points,
            'reason' => $reason,
            'meta' => $meta,
            'slug' => null,
        ]);
    }

    /**
     * Total de honor del usuario actual.
     * Estrategia:
     * 1) Si existe atributo "honor_total" (por withSum/SELECT ... AS honor_total), úsalo.
     * 2) Si la relación honorEvents está cargada, sumar en memoria.
     * 3) Consulta directa SUM(points) (barata).
     *
     * Además, cachea en el atributo dinámico "honor" para reusos en la misma request.
     */
    public function getHonorAttribute(): int
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        // Reusar si ya lo calculamos en esta request
        $already = $this->getAttributes()['honor'] ?? null;
        if ($already !== null) {
            return (int) $already;
        }

        // 1) Atributo proyectado (e.g., selectRaw('SUM(...) AS honor_total'))
        $projected = $this->getAttributes()['honor_total'] ?? null;
        if ($projected !== null) {
            $val = (int) $projected;
            $this->setAttribute('honor', $val);
            return $val;
        }

        // 2) Relación cargada
        if ($this->relationLoaded('honorEvents')) {
            /** @var \Illuminate\Support\Collection $rel */
            $rel = $this->getRelation('honorEvents');
            $val = (int) $rel->sum('points');
            $this->setAttribute('honor', $val);
            return $val;
        }

        // 3) Consulta directa
        $val = (int) HonorEvent::query()
            ->where('user_id', $this->getKey())
            ->sum('points');

        $this->setAttribute('honor', $val);
        return $val;
    }

    /**
     * Recalcula el total y, si existe la columna "honor" en la tabla del usuario,
     * lo persiste como agregado (útil para rankings rápidos en hosting compartido).
     *
     * @param bool $persist Si true y hay columna users.honor, persiste el agregado.
     * @return int nuevo total calculado
     */
    public function refreshHonorAggregate(bool $persist = false): int
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        $total = (int) HonorEvent::query()
            ->where('user_id', $this->getKey())
            ->sum('points');

        // Cache en el modelo para esta request
        $this->setAttribute('honor', $total);

        if ($persist && Schema::hasColumn($this->getTable(), 'honor')) {
            // Guardado silencioso para no disparar observers costosos
            $this->forceFill(['honor' => $total])->saveQuietly();
        }

        return $total;
    }
}
