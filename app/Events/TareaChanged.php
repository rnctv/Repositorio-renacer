<?php

namespace App\Events;

use App\Models\Tarea;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TareaChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $action;
    public Tarea $tarea;
    public ?string $oldDate;

    /**
     * @param string $action  'created' | 'moved'
     * @param Tarea  $tarea
     * @param ?string $oldDate YYYY-MM-DD de la fecha anterior (en 'moved')
     */
    public function __construct(string $action, Tarea $tarea, ?string $oldDate = null)
    {
        $this->action = $action;
        $this->tarea  = $tarea->fresh(['cliente:id,id_externo,nombre,direccion,telefono,movil,coordenadas']);
        $this->oldDate = $oldDate;
    }

    public function broadcastOn(): array
    {
        $channels = [];

        // Canal nuevo (fecha actual de la tarea)
        $dateNew = optional($this->tarea->fecha)->toDateString();
        if ($dateNew) {
            $channels[] = new Channel('tareas-' . $dateNew);
        }

        // Canal antiguo (si hubo cambio de fecha)
        if (!empty($this->oldDate) && $this->oldDate !== $dateNew) {
            $channels[] = new Channel('tareas-' . $this->oldDate);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        // Nombre simple y estable
        return 'tarea.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action'   => $this->action,
            'old_date' => $this->oldDate,
            'tarea'    => [
                'id'          => $this->tarea->id,
                'titulo'      => $this->tarea->titulo,
                'tipo'        => $this->tarea->tipo,
                'plan'        => $this->tarea->plan,
                'estado'      => $this->tarea->estado,
                'fecha'       => $this->tarea->fecha ? $this->tarea->fecha->toDateString() : null,
                'notas'       => $this->tarea->notas,
                'cliente'     => $this->tarea->cliente ? [
                    'id'          => $this->tarea->cliente->id,
                    'id_externo'  => $this->tarea->cliente->id_externo,
                    'nombre'      => $this->tarea->cliente->nombre,
                    'direccion'   => $this->tarea->cliente->direccion,
                    'telefono'    => $this->tarea->cliente->telefono ?: $this->tarea->cliente->movil,
                    'coordenadas' => $this->tarea->cliente->coordenadas,
                ] : null,
                'dias_desde_creacion' => $this->tarea->created_at ? $this->tarea->created_at->diffInDays(now()) : 0,
            ],
        ];
    }
}
