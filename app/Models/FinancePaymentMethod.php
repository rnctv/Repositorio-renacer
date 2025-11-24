<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancePaymentMethod extends Model
{
    protected $fillable = ['name','is_cash'];

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'payment_method_id');
    }
}
