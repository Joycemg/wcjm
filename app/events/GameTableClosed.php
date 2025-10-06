<?php declare(strict_types=1);

// app/Events/GameTableClosed.php
namespace App\Events;

use App\Models\GameTable;
use Illuminate\Foundation\Events\Dispatchable;

final class GameTableClosed
{
    use Dispatchable;

    /** ID de la mesa cerrada */
    public readonly int $tableId;

    /** ISO-8601 (UTC) del cierre, o null si no está seteado */
    public readonly ?string $closedAtIso;

    /** Epoch ms del cierre (UTC), o null */
    public readonly ?int $closedAtMs;

    /** true si es el primer cierre efectivo (idempotencia) */
    public readonly bool $firstClose;

    /** contador de inscriptos (si lo tenés precargado) */
    public readonly ?int $signupsCount;

    /** versión/revisión opcional para invalidar cachés */
    public readonly ?int $rev;

    private function __construct(
        int $tableId,
        ?string $closedAtIso,
        ?int $closedAtMs,
        bool $firstClose,
        ?int $signupsCount,
        ?int $rev
    ) {
        $this->tableId = $tableId;
        $this->closedAtIso = $closedAtIso;
        $this->closedAtMs = $closedAtMs;
        $this->firstClose = $firstClose;
        $this->signupsCount = $signupsCount;
        $this->rev = $rev;
    }

    /**
     * Crea el evento desde el modelo SIN serializarlo (ideal hosting compartido).
     * Pasá $signupsCount / $rev si ya vienen en el query para evitar hits extra.
     */
    public static function fromModel(
        GameTable $table,
        bool $firstClose = true,
        ?int $signupsCount = null,
        ?int $rev = null
    ): self {
        $closed = $table->closed_at?->clone()->utc();

        return new self(
            $table->getKey(),
            $closed?->toIso8601String(),
            $closed ? (int) ($closed->getTimestamp() * 1000) : null,
            $firstClose,
            $signupsCount,
            $rev
        );
    }

    public function toArray(): array
    {
        return [
            'table_id' => $this->tableId,
            'closed_at_iso' => $this->closedAtIso,
            'closed_at_ms' => $this->closedAtMs,
            'first_close' => $this->firstClose,
            'signups_count' => $this->signupsCount,
            'rev' => $this->rev,
        ];
    }
}
