<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Actividad extends Model
{
    use HasFactory;

    protected $table = 'actividades';

    protected $fillable = [
        'cliente_id','tipo','titulo','fecha_inicio','fecha_fin','estado','notas'
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin'    => 'datetime',
    ];

    public function cliente() {
        return $this->belongsTo(\App\Models\Cliente::class);
    }
}
