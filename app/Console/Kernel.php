<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Si tu proyecto no autodistribuye comandos, puedes listarlos aquí:
     */
    protected $commands = [
        \App\Console\Commands\RolloverTareas::class,
    ];

    /**
     * Define el schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Ejecuta el rollover cada noche a las 00:05 en la TZ de la app.
        $schedule->command('agenda:rollover --silent')
            ->dailyAt('00:05')
            ->timezone(config('app.timezone', 'America/Santiago'))
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Registra los comandos para la consola.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
