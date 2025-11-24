<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tarea;
use Carbon\Carbon;

class RolloverPendingTasks extends Command
{
    protected $signature = 'agenda:rollover';
    protected $description = 'Mueve tareas PENDIENTES en fechas pasadas hacia el día actual';

    public function handle(): int
    {
        $hoy = Carbon::now()->startOfDay()->toDateString();

        $tareas = Tarea::where('estado', 'pendiente')
            ->whereDate('fecha', '<', $hoy)
            ->orderBy('fecha', 'asc')
            ->get();

        $count = 0;
        foreach ($tareas as $t) {
            $t->fecha = $hoy;
            $t->save();
            $count++;
        }

        $this->info("Rollover pendientes: {$count} tarea(s) movidas a {$hoy}");
        return self::SUCCESS;
    }
}
