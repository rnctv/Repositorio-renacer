<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TareaLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tarea_id',
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'fecha_anterior',
        'fecha_nueva',
        'user_id'
    ];

    public function tarea()
    {
        return $this->belongsTo(Tarea::class);
    }
}
