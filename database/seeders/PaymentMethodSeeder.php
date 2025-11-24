<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinancePaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'EFECTIVO', 'is_cash' => true],
            ['name' => 'TARJETA', 'is_cash' => false],
            ['name' => 'CUENTA EMPRESA', 'is_cash' => false],
            ['name' => 'TRANSFERENCIA', 'is_cash' => false],
        ];
        foreach ($items as $i) {
            FinancePaymentMethod::firstOrCreate(['name' => $i['name']], $i);
        }
    }
}
