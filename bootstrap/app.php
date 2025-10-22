<?php

@file_put_contents('C:\Temp\APP_BOOT.txt', 'boot '.date('c').PHP_EOL, FILE_APPEND);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; 

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
        ->withMiddleware(fn (Middleware $middleware) => null)
        ->withExceptions(fn (Exceptions $exceptions) => null)
        ->withSchedule(function (Schedule $schedule) {

        // Tu job existente
        $schedule->command('imports:ingest')
            ->hourly()
            ->timezone('America/Costa_Rica');

        // Genera feriados del próximo año el 15 de diciembre
        $schedule->call(function () {
            $nextYear = now('America/Costa_Rica')->addYear()->year;
            \Artisan::call('holidays:generate-default', [
                'year' => $nextYear,
                '--include-sundays' => '1',
                '--reset' => '0',
            ]);
            \Log::info("[Holidays] Generados por defecto para {$nextYear} (scheduler dic).");
        })->yearlyOn(12, 15, '02:10')->timezone('America/Costa_Rica');

        // Autocuración: si en enero faltan feriados default, generarlos
        $schedule->call(function () {
            $year = now('America/Costa_Rica')->year;
            $exists = \App\Models\Holiday::whereYear('date', $year)->where('origin','default')->exists();
            if (!$exists) {
                \Artisan::call('holidays:generate-default', [
                    'year' => $year,
                    '--include-sundays' => '1',
                    '--reset' => '0',
                ]);
                \Log::warning("[Holidays] Autocuración ejecutada para {$year}.");
            }
        })->dailyAt('02:20')->when(fn () => now('America/Costa_Rica')->month === 1)
          ->timezone('America/Costa_Rica');
    })
    
    ->create();
