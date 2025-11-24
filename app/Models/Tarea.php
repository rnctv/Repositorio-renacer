<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = [
        'fecha',
        'estado',
        'tipo',
        'plan',
        'cliente_id',
        'user_ppp_hotspot',
        'precinto',
        'titulo',
        'notas',
        'contacto_nombre',
        'contacto_direccion',
        'contacto_telefono',
        'coord_lat',
        'coord_lng',
    ];

    protected $casts = [
        'fecha' => 'date',
        'coord_lat' => 'decimal:7',
        'coord_lng' => 'decimal:7',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    // ===== Normalización en MAYÚSCULAS =====
    protected function titulo(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v !== null ? Str::upper($v) : null
        );
    }

    protected function plan(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v !== null ? Str::upper($v) : null
        );
    }

    protected function tipo(): Attribute
    {
        return Attribute::make(
            set: function ($v) {
                if ($v === null) return null;
                $t = Str::upper($v);
                return $t === 'OTRO' ? 'OTROS' : $t;
            }
        );
    }

    protected function contactoNombre(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v !== null ? Str::upper($v) : null
        );
    }

    protected function contactoDireccion(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v !== null ? Str::upper($v) : null
        );
    }

    // teléfono sin formateo (solo dígitos)
    protected function contactoTelefono(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => $v !== null ? preg_replace('/\D+/', '', $v) : null
        );
    }

    // ===== Scopes útiles =====
    public function scopeDelDia($q, $fecha)
    {
        return $q->whereDate('fecha', $fecha);
    }
}
