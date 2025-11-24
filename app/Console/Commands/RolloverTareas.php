<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tarea;
use App\Events\TareaChanged;

class RolloverTareas extends Command
{
    protected $signature = 'agenda:rollover {--silent}';
    protected $description = 'Mueve a HOY las tareas pendiente/en_curso con fecha pasada y emite eventos';

    public function handle(): int
    {
        $tz  = config('app.timezone', 'America/Santiago');
        $hoy = now()->timezone($tz)->toDateString();

        $tareas = Tarea::whereIn('estado', ['pendiente', 'en_curso'])
            ->whereDate('fecha', '<', $hoy)
            ->get();

        if ($tareas->isEmpty()) {
            if (!$this->option('silent')) {
                $this->info('Sin tareas que mover.');
            }
            return self::SUCCESS;
        }

        $n = 0;
        foreach ($tareas as $t) {
            $oldDate = optional($t->fecha)->toDateString();
            $t->fecha = $hoy;
            $t->save();
            event(new TareaChanged('moved', $t, $oldDate));
            $n++;
        }

        if (!$this->option('silent')) {
            $this->info("Tareas movidas a HOY: {$n}");
        }
        return self::SUCCESS;
    }
}
