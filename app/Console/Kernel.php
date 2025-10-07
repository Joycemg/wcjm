<?php declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final class Kernel extends ConsoleKernel
{
     /**
      * Registrar comandos de consola (autodiscovery por carpeta + routes/console.php).
      */
     protected function commands(): void
     {
          $this->load(__DIR__ . '/Commands');
          require base_path('routes/console.php');
     }

     /**
      * Agenda de tareas recurrentes.
      * Pensada para hosting compartido: trabajos cortos, sin solapamiento y con TZ configurable.
      */
     protected function schedule(Schedule $schedule): void
     {
          $appTz = (string) config('app.timezone', 'UTC');
          $displayTz = (string) (config('app.display_timezone') ?: $appTz);

          // --- Honor: decaimiento por inactividad (día 1 a las 03:00 locales) ---
          $schedule->command('honor:decay-inactivity')
               ->monthlyOn(1, '03:00')
               ->timezone($appTz)
               ->withoutOverlapping()
               ->onOneServer()
               ->runInBackground()
               ->evenInMaintenanceMode();

          // --- Mesas: cierre automático los sábados 15:00 (si existe el comando) ---
          if ($this->commandExists('mesas:auto-close')) {
               $schedule->command('mesas:auto-close')
                    ->weeklyOn(6, '15:00')              // 6 = Saturday
                    ->timezone($displayTz)              // coincide con lo que ve la gente
                    ->withoutOverlapping()
                    ->onOneServer()
                    ->runInBackground()
                    ->evenInMaintenanceMode();
          }

          // --- Mantenimiento liviano (seguro para shared hosting) ---
          // Prune de batches antiguos (si usás Bus::batch)
          $schedule->command('queue:prune-batches', ['--hours' => 48])
               ->dailyAt('02:20')
               ->timezone($appTz)
               ->onOneServer()
               ->runInBackground();

          // Reiniciar workers (si usás queue:work) para aplicar cambios de código/ENV
          $schedule->command('queue:restart')
               ->dailyAt('04:10')
               ->timezone($appTz)
               ->onOneServer()
               ->runInBackground();

          // Cache prune (solo si usás taggable stores que lo soporten)
          if ($this->commandExists('cache:prune-stale-tags')) {
               $schedule->command('cache:prune-stale-tags')
                    ->dailyAt('03:45')
                    ->timezone($appTz)
                    ->onOneServer()
                    ->runInBackground();
          }
     }

     /**
      * Pequeño helper para verificar si un comando existe (evita errores en entornos recortados).
      */
     private function commandExists(string $name): bool
     {
          try {
               return (bool) app('Illuminate\Contracts\Console\Kernel')
                    ->all()[$name] ?? false;
          } catch (\Throwable) {
               return false;
          }
     }
}
