<?php declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
     protected function commands(): void
     {
          $this->load(__DIR__ . '/Commands');
          require base_path('routes/console.php');
     }

     protected function schedule(Schedule $schedule): void
     {
          $schedule->command('honor:decay-inactivity')
               ->monthlyOn(1, '03:00')
               ->timezone(config('app.timezone', 'America/Argentina/La_Rioja'))
               ->withoutOverlapping()
               ->onOneServer();
     }
}
