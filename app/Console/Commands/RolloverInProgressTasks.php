<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tarea;
use Carbon\Carbon;

class RolloverInProgressTasks extends Command
{
    protected $signature = 'agenda:rollover-inprogress';
    protected $description = 'Reinicia tareas EN_CURSO vencidas: las pasa a PENDIENTE y a HOY';

    public function handle(): int
    {
        $hoy = Carbon::now()->startOfDay()->toDateString();

        $tareas = Tarea::where('estado', 'en_curso')
            ->whereDate('fecha', '<', $hoy)
            ->orderBy('fecha', 'asc')
            ->get();

        $count = 0;
        foreach ($tareas as $t) {
            $t->estado = 'pendiente';
            $t->fecha  = $hoy;
            $t->save();
            $count++;
        }

        $this->info("Rollover en_curso: {$count} tarea(s) reiniciadas y movidas a {$hoy}");
        return self::SUCCESS;
    }
}
