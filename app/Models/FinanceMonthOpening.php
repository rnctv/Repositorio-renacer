<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceMonthOpening extends Model
{
    protected $table = 'finance_month_openings';
    protected $fillable = ['year','month','payment_method_id','amount','source'];
    public $timestamps = true;

    public function paymentMethod()
    {
        return $this->belongsTo(FinancePaymentMethod::class, 'payment_method_id');
    }
}
