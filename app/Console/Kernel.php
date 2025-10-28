<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Registrar comandos Artisan personalizados.
     */
    protected $commands = [
        \App\Console\Commands\ImportsIngestCommand::class,
        \App\Console\Commands\GenerateDefaultHolidays::class,
    ];

    /**
     * Definir la programación de comandos (si quieres).
     */
    protected function schedule(Schedule $schedule): void
    {
        // 1) Prueba con escritura directa desde PHP (sin logger)
        $schedule->call(function () {
            @file_put_contents('C:\Temp\cronphp.txt', date('c') . PHP_EOL, FILE_APPEND);
        })->everyMinute()->timezone('America/Costa_Rica');

        // 2) Prueba con comando del sistema (sin PHP/Laravel)
        $schedule->exec('cmd /c echo %DATE% %TIME% >> C:\Temp\cronexec.txt')
                ->everyMinute()->timezone('America/Costa_Rica');

        $schedule->command('imports:ingest')->hourly();
        $schedule->call(function () {
            Log::info('[Scheduler] Heartbeat 1min: '.now()->toDateTimeString());
        })->everyMinute()->timezone('America/Costa_Rica');

        $schedule->call(function () {
            try {
                app(\App\Services\HolidaySetupService::class)->ensure();
            } catch (\Throwable $e) {
                \Log::warning('[Holidays ensure via scheduler] '.$e->getMessage());
            }
        })->dailyAt('02:15')->timezone('America/Costa_Rica');
    }

    /**
     * Registrar los comandos de la carpeta Commands y routes/console.php.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        if (file_exists(base_path('routes/console.php'))) {
            require base_path('routes/console.php');
        }
    }
}
