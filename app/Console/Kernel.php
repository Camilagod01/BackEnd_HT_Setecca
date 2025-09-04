<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Registrar comandos Artisan personalizados.
     */
    protected $commands = [
        \App\Console\Commands\ImportsIngestCommand::class,
    ];

    /**
     * Definir la programación de comandos (si quieres).
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('imports:ingest')->hourly();
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
