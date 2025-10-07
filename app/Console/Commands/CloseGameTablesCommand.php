<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameTable;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class CloseGameTablesCommand extends Command
{
    /**
     * --force    : Ejecuta aunque no sea sábado 15:00
     * --dry      : Simulación (no escribe cambios)
     * --ids=     : Coma-separados de IDs a cerrar (omite filtro por fecha)
     * --at=      : Fecha/hora de referencia (TZ app) p.ej. "2025-01-18 15:00"
     * --since=   : Ventana inferior de opens_at (incl.)
     * --until=   : Ventana superior de opens_at (incl.)
     */
    protected $signature = 'mesas:auto-close
                            {--force : Ejecuta aunque no sea sábado 15:00}
                            {--dry : Modo simulación, no escribe cambios}
                            {--ids= : IDs (coma-separados) a cerrar explícitamente}
                            {--at= : Fecha/hora de referencia (YYYY-MM-DD HH:MM)}
                            {--since= : Cerrar mesas con opens_at >= este instante (YYYY-MM-DD HH:MM)}
                            {--until= : Cerrar mesas con opens_at <= este instante (YYYY-MM-DD HH:MM)}';

    protected $description = 'Cierra mesas abiertas y registra historial (por defecto, sábados 15:00 TZ app). Soporta --at, --since/--until e IDs.';

    public function handle(): int
    {
        $tz = config('app.display_timezone', config('app.timezone', 'UTC'));

        // === Resolver fecha/hora de referencia (--at o now) ===
        $ref = $this->parseOptionDateTime('at', $tz) ?? CarbonImmutable::now($tz)->second(0);

        // === Chequeo “sábado 15:00”, a menos que --force ===
        $isSaturdayAt1500 = $ref->isSaturday() && $ref->format('H:i') === '15:00';
        if (!$isSaturdayAt1500 && !$this->option('force')) {
            $this->info("No es sábado 15:00 en {$tz} (ref={$ref->format('Y-m-d H:i')}). Usá --force o --at para forzar.");
            // No devolvemos INVALID porque es una condición operativa normal
            return self::SUCCESS;
        }

        // === Candado anti-solapamiento ===
        $lock = Cache::lock('mesas:auto-close-running', 300); // 5 minutos
        if (!$lock->get()) {
            $this->warn('Ya hay otro proceso de cierre en ejecución. Salgo.');
            return self::SUCCESS;
        }

        try {
            // === Construcción de la query base ===
            $q = GameTable::query()
                ->select(['id', 'is_open', 'opens_at', 'capacity', 'title', 'closed_at'])
                ->where('is_open', true);

            $idsOpt = trim((string) $this->option('ids'));
            $sinceOpt = trim((string) $this->option('since'));
            $untilOpt = trim((string) $this->option('until'));

            $since = $sinceOpt !== '' ? $this->parseOptionDateTime('since', $tz) : null;
            $until = $untilOpt !== '' ? $this->parseOptionDateTime('until', $tz) : null;

            // === Prioridad 1: --ids ===
            if ($idsOpt !== '') {
                $ids = collect(explode(',', $idsOpt))
                    ->map(static fn($v) => (int) trim($v))
                    ->filter(static fn($v) => $v > 0)
                    ->values()
                    ->all();

                if (empty($ids)) {
                    $this->error('Parámetro --ids= inválido. Debe ser lista de enteros separada por comas.');
                    return self::INVALID;
                }

                $q->whereIn('id', $ids);

                // === Prioridad 2: Ventana --since/--until (si se pasó alguna) ===
            } elseif ($since || $until) {
                // Cerrar abiertas cuya opens_at esté en ventana;
                // Si opens_at es NULL (abiertas sin programar), también se cierran: para incluirlas explícitamente,
                // mantenemos la semántica de “mesas abiertas actualmente”.
                $q->where(function ($w) use ($since, $until) {
                    $w->whereNull('opens_at');

                    if ($since && $until) {
                        $w->orWhereBetween('opens_at', [$since->toDateTimeString(), $until->toDateTimeString()]);
                    } elseif ($since) {
                        $w->orWhere('opens_at', '>=', $since->toDateTimeString());
                    } elseif ($until) {
                        $w->orWhere('opens_at', '<=', $until->toDateTimeString());
                    }
                });

                // === Prioridad 3: Heurística por defecto (abiertas y no futuras respecto a ref) ===
            } else {
                $q->where(function ($w) use ($ref) {
                    $w->whereNull('opens_at')
                        ->orWhere('opens_at', '<=', $ref->toDateTimeString());
                });
            }

            $total = (clone $q)->count();
            if ($total === 0) {
                $this->info('No hay mesas para cerrar según los criterios.');
                return self::SUCCESS;
            }

            if ($this->output->isVerbose()) {
                $preview = (clone $q)->orderBy('id')->limit(50)->pluck('id')->all();
                $this->line('Candidatas (hasta 50): ' . implode(', ', $preview) . ($total > 50 ? ' …' : ''));
            }

            if ($this->option('dry')) {
                $this->line("DRY-RUN: se cerrarían {$total} mesa(s). Ref={$ref->format('Y-m-d H:i')} TZ={$tz}.");
                return self::SUCCESS;
            }

            // === Cierre real ===
            $this->info("Cerrando {$total} mesa(s) y registrando historial…");
            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $cerradas = 0;

            (clone $q)->orderBy('id')->chunkById(300, function ($chunk) use (&$cerradas, $bar) {
                /** @var GameTable $mesa */
                foreach ($chunk as $mesa) {
                    try {
                        if (method_exists($mesa, 'closeNow')) {
                            // Tu modelo puede ya fijar closed_at + historial
                            $mesa->closeNow();
                        } else {
                            // Fallback: disparar updated() del modelo (que ya registra historial)
                            $mesa->is_open = false;
                            if (!$mesa->closed_at) {
                                $mesa->closed_at = now(); // el modelo normaliza TZ/precisión
                            }
                            $mesa->save();
                        }

                        $cerradas++;
                    } catch (\Throwable $e) {
                        Log::error('Fallo al cerrar mesa', [
                            'mesa_id' => $mesa->id,
                            'msg' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                    } finally {
                        $bar->advance();
                    }
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info("Hecho: {$cerradas} mesa(s) cerradas y con historial registrado.");

            return self::SUCCESS;

        } finally {
            $lock?->release();
        }
    }

    /**
     * Parsea una opción de fecha/hora (YYYY-MM-DD HH:MM) en el TZ dado.
     * Devuelve null si la opción no está o está vacía.
     * Lanza error con mensaje legible si el formato es inválido.
     */
    private function parseOptionDateTime(string $key, string $tz): ?CarbonImmutable
    {
        $raw = trim((string) $this->option($key));
        if ($raw === '') {
            return null;
        }

        // Aceptamos "YYYY-MM-DD HH:MM" o ISO; Carbon es flexible, pero validamos que tenga minuto.
        try {
            $dt = CarbonImmutable::parse($raw, $tz)->second(0);
            // Normalizamos a precisión de minuto
            return CarbonImmutable::createFromFormat('Y-m-d H:i', $dt->format('Y-m-d H:i'), $tz);
        } catch (\Throwable $e) {
            $this->error("Formato inválido para --{$key}. Usá 'YYYY-MM-DD HH:MM' (ej: 2025-01-18 15:00). Valor recibido: {$raw}");
            throw $e; // deja traza útil si se ejecuta vía CI
        }
    }
}
