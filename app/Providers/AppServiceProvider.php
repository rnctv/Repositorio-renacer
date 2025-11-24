<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use App\Models\FinanceMonthOpening;
use App\Models\FinancePaymentMethod;
use App\Models\FinanceOpeningBalance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            // Solo en vistas bajo URL que contenga 'finanzas'
            $path = request()->path();
            if (!Str::contains($path, 'finanzas')) {
                return;
            }

            $data  = $view->getData();
            $year  = isset($data['year'])  ? (int)$data['year']  : (int)date('Y');
            $month = isset($data['month']) ? (int)$data['month'] : (int)date('n');

            // Total saldo inicial del mes
            $openingDbSum = (int) round((float)
                FinanceOpeningBalance::where(['year'=>$year,'month'=>$month])->sum('amount')
            );

            // Por medio
            $openingByMethod = FinanceOpeningBalance::where(['year'=>$year,'month'=>$month])
                ->selectRaw('COALESCE(payment_method_id, 0) as pm, SUM(amount) as total')
                ->groupBy('pm')->pluck('total','pm');

            // Medio cuenta (CUENTA EMPRESA o que contenga CUENTA)
            $idCuenta = optional(FinancePaymentMethod::where('name','CUENTA EMPRESA')->first())->id
                ?? optional(FinancePaymentMethod::where('name','like','%CUENTA%')->first())->id
                ?? 0;

            // Variables existentes
            $mt          = $data['mt']          ?? ($data['monthTotals'] ?? []);
            $monthTotals = $data['monthTotals'] ?? ($data['mt']          ?? []);

            // Inyecta saldo inicial para la card verde
            $mt['ingresos_saldo_inicial'] = $openingDbSum;
            $monthTotals['ingresos_saldo_inicial'] = $openingDbSum;

            // Ajusta card azul
            $mt['saldo_general'] = (int)($mt['saldo_general'] ?? 0) + (int)$openingDbSum;
            $mt['saldo_cuenta']  = (int)($mt['saldo_cuenta']  ?? 0) + (int) round((float) ($openingByMethod[$idCuenta] ?? 0));

            // Comparte con la vista
            $view->with('mt', $mt);
            $view->with('monthTotals', $monthTotals);
            $view->with('openingDbSum', $openingDbSum);
        });
    }
}
