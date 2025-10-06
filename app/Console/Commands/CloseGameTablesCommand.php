<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameTable;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CloseGameTablesCommand extends Command
{
    /**
     * --force    : Ejecuta aunque no sea sábado 15:00
     * --dry      : Simulación (no escribe cambios)
     * --ids=     : Coma-separados de IDs a cerrar (omite filtro por fecha)
     */
    protected $signature = 'mesas:auto-close
                            {--force : Ejecuta aunque no sea sábado 15:00}
                            {--dry : Modo simulación, no escribe cambios}
                            {--ids= : IDs (coma-separados) a cerrar explícitamente}';

    protected $description = 'Cierra mesas abiertas (is_open) cada sábado a las 15:00 y registra historial para jugadores (no reserva).';

    public function handle(): int
    {
        $tz = config('app.display_timezone', config('app.timezone', 'UTC'));
        $now = CarbonImmutable::now($tz)->second(0);

        // Solo corre los sábados 15:00 salvo --force
        $isSaturdayAt1500 = $now->isSaturday() && $now->hour === 15 && $now->minute === 0;
        if (!$isSaturdayAt1500 && !$this->option('force')) {
            $this->info("No es sábado 15:00 en {$tz} ({$now->format('Y-m-d H:i')}). Usa --force para forzar.");
            return self::SUCCESS;
        }

        // Candado para evitar solapamiento
        $lock = Cache::lock('mesas:auto-close-running', 300);
        if (!$lock->get()) {
            $this->warn('Ya hay otro proceso de cierre en ejecución. Salgo.');
            return self::SUCCESS;
        }

        try {
            // Query base de mesas a cerrar
            $q = GameTable::query()->where('is_open', true);

            // Si pasan IDs, cerramos esas; si no, cerramos las “abiertas ahora”
            $idsOpt = trim((string) $this->option('ids'));
            if ($idsOpt !== '') {
                $ids = collect(explode(',', $idsOpt))
                    ->map(fn($v) => (int) trim($v))
                    ->filter(fn($v) => $v > 0)
                    ->values()
                    ->all();

                if (empty($ids)) {
                    $this->error('Parámetro --ids= inválido. Debe ser una lista de enteros separada por comas.');
                    return self::INVALID;
                }

                $q->whereIn('id', $ids);
            } else {
                $q->where(function ($w) use ($now) {
                    $w->whereNull('opens_at')
                        ->orWhere('opens_at', '<=', $now->toDateTimeString());
                });
            }

            $total = (clone $q)->count();
            if ($total === 0) {
                $this->info('No hay mesas para cerrar.');
                return self::SUCCESS;
            }

            if ($this->output->isVerbose()) {
                $preview = (clone $q)->orderBy('id')->limit(50)->pluck('id')->all();
                $this->line('Candidatas (hasta 50): ' . implode(', ', $preview) . ($total > 50 ? ' …' : ''));
            }

            if ($this->option('dry')) {
                $this->line("DRY-RUN: se cerrarían {$total} mesa(s). No se escriben cambios.");
                return self::SUCCESS;
            }

            // 🔑 CIERRE REAL: SIEMPRE con closeNow() para guardar historial y closed_at
            $this->info("Cerrando {$total} mesa(s) y registrando historial…");
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $cerradas = 0;

            (clone $q)->orderBy('id')->chunkById(300, function ($chunk) use (&$cerradas, $bar) {
                /** @var \App\Models\GameTable $mesa */
                foreach ($chunk as $mesa) {
                    try {
                        // closeNow() fija closed_at, pone is_open=false y persiste historiales (jugadores no reserva)
                        $mesa->closeNow();
                        $cerradas++;
                    } catch (\Throwable $e) {
                        // No detener el lote por una mesa que falla; loguear y seguir.
                        \Log::error('Fallo al cerrar mesa', [
                            'mesa_id' => $mesa->id,
                            'msg' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info("Hecho: {$cerradas} mesa(s) cerradas y con historial registrado.");

            return self::SUCCESS;

        } finally {
            optional($lock)->release();
        }
    }
}
