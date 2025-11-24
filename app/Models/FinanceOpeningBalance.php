<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceOpeningBalance extends Model
{
    protected $fillable = ['year','month','payment_method_id','amount','source'];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(FinancePaymentMethod::class, 'payment_method_id');
    }
}
