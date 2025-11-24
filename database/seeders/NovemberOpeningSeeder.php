<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{FinancePaymentMethod, FinanceOpeningBalance};

class NovemberOpeningSeeder extends Seeder
{
    public function run(): void
    {
        // Medio: CUENTA EMPRESA
        $method = FinancePaymentMethod::firstOrCreate(
            ['name' => 'CUENTA EMPRESA'],
            ['is_cash' => false]
        );

        // Apertura noviembre 2025: 500000 CLP
        FinanceOpeningBalance::updateOrCreate(
            ['year' => 2025, 'month' => 11, 'payment_method_id' => $method->id],
            ['amount' => 500000, 'source' => 'manual']
        );

        $this->command->info('Apertura de noviembre 2025 (CUENTA EMPRESA) establecida en $500.000 CLP.');
    }
}
