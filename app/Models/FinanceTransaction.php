<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FinancePaymentMethod;
use App\Models\FinanceSubcategory;

class FinanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'date','type','amount','category_id','subcategory_id','payment_method_id','cliente_id',
        'reference','description','modality','worker_name','vehicle','meta'
    ];

    protected $casts = [
        'date' => 'date',
        'meta' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(FinancePaymentMethod::class, 'payment_method_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(FinanceSubcategory::class, 'subcategory_id');
    }


/**
 * Normaliza el tipo antes de persistir.
 * 'gasto' -> 'egreso', y sanea variantes comunes.
 */
public function setTypeAttribute($value)
{
    $v = strtolower((string) $value);
    if ($v === 'gasto') {
        $v = 'egreso';
    }
    if ($v === 'ingresos') {
        $v = 'ingreso';
    }
    $this->attributes['type'] = $v;
}

}
