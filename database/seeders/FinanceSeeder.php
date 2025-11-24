<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinanceCategory;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Ventas', 'type' => 'ingreso', 'color' => '#059669'],
            ['name' => 'Transferencias', 'type' => 'ingreso', 'color' => '#2563eb'],
            ['name' => 'Servicios', 'type' => 'ingreso', 'color' => '#7c3aed'],
            ['name' => 'Sueldos', 'type' => 'egreso', 'color' => '#ef4444'],
            ['name' => 'Insumos', 'type' => 'egreso', 'color' => '#f59e0b'],
            ['name' => 'Gastos fijos', 'type' => 'egreso', 'color' => '#374151'],
        ];
        foreach ($defaults as $d) {
            FinanceCategory::firstOrCreate(['name'=>$d['name'],'type'=>$d['type']], $d);
        }
    }
}
