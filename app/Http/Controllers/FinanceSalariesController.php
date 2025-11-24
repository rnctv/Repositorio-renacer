<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FinanceBudget;
use App\Models\FinanceTransaction;
use App\Models\FinanceCategory;
use App\Models\FinanceSubcategory;

class FinanceSalariesController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period');
        $year = null;
        $month = null;

        if ($period && preg_match('/^(\\d{4})-(\\d{2})$/', $period, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
        } else {
            $year = (int) ($request->input('year') ?: now()->year);
            $month = (int) ($request->input('month') ?: now()->month);
            $period = sprintf('%04d-%02d', $year, $month);
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-d', strtotime($from . ' +1 month'));

        // Categoría(s) de sueldos: egresos cuyo nombre contiene SUELD
        $salaryCategoryIds = FinanceCategory::query()
            ->where('type', 'egreso')
            ->whereRaw("UPPER(name) LIKE '%SUELD%'")
            ->pluck('id');

        $workers = collect();

        if ($salaryCategoryIds->isNotEmpty()) {
            // Subcategorías (trabajadores) bajo la(s) categoría(s) de sueldos
            $subcategories = FinanceSubcategory::query()
                ->whereIn('category_id', $salaryCategoryIds)
                ->orderBy('name')
                ->get();

            // Ocultar RRLL (CESAR) de la tabla de sueldos
            $subcategories = $subcategories->filter(function ($sub) {
                $name = mb_strtoupper(trim($sub->name), 'UTF-8');
                return $name !== 'CESAR' && $name !== 'CÉSAR';
            })->values();


            // Egresos/gastos de sueldos del periodo, para sumar lo pagado por subcategoría
            $salaryTx = FinanceTransaction::query()
                ->whereBetween('date', [$from, $to])
                ->whereIn('type', ['egreso', 'gasto'])
                ->whereIn('category_id', $salaryCategoryIds)
                ->get();

            $paidBySubId = [];
            foreach ($salaryTx as $tx) {
                $subId = (int) ($tx->subcategory_id ?? 0);
                if ($subId <= 0) {
                    continue;
                }
                if (!isset($paidBySubId[$subId])) {
                    $paidBySubId[$subId] = 0;
                }
                $paidBySubId[$subId] += (int) $tx->amount;
            }

            // Presupuestos de sueldos existentes para el periodo actual
            $salaryBudgets = FinanceBudget::query()
                ->where('year', $year)
                ->where('month', $month)
                ->whereIn('category_id', $salaryCategoryIds)
                ->get();

            // Mapear subcategorías por nombre en mayúsculas para cruzar con FinanceBudget.worker_name
            $subsByUpperName = [];
            foreach ($subcategories as $sub) {
                $key = mb_strtoupper(trim($sub->name), 'UTF-8');
                $subsByUpperName[$key] = $sub;
            }

            // Inicializar workers en base a subcategorías
            $workersArr = [];
            foreach ($subcategories as $sub) {
                $paid = (int) ($paidBySubId[$sub->id] ?? 0);
                $key  = mb_strtoupper(trim($sub->name), 'UTF-8');

                $workersArr[$sub->id] = [
                    'sub_id'    => $sub->id,
                    'label'     => $sub->name,
                    'name_key'  => $key,
                    'paid'      => $paid,
                    'defined'   => 0,
                ];
            }

            // Asignar sueldos definidos desde FinanceBudget
            foreach ($salaryBudgets as $budget) {
                $label = trim((string) ($budget->worker_name ?? ''));
                if ($label === '') {
                    continue;
                }
                $key = mb_strtoupper($label, 'UTF-8');

                if (!isset($subsByUpperName[$key])) {
                    continue;
                }

                $sub = $subsByUpperName[$key];
                if (!isset($workersArr[$sub->id])) {
                    continue;
                }

                $workersArr[$sub->id]['defined'] = (int) $workersArr[$sub->id]['defined'] + (int) $budget->amount;
            }

            // Construir colección final de workers con totales y porcentajes
            $workers = collect($workersArr)
                ->map(function (array $row) {
                    $defined   = (int) $row['defined'];
                    $paid      = (int) $row['paid'];
                    $total     = $defined > 0 ? $defined : $paid;
                    $remaining = max(0, $total - $paid);
                    $percent   = $total > 0
                        ? min(100, (int) round($paid * 100 / $total))
                        : 0;

                    return (object) [
                        'sub_id'     => $row['sub_id'],
                        'label'      => $row['label'],
                        'worker_name'=> $row['label'], // se usa como worker_name en FinanceBudget
                        'defined'    => $defined,
                        'paid'       => $paid,
                        'total'      => $total,
                        'remaining'  => $remaining,
                        'percent'    => $percent,
                    ];
                })
                ->sortBy('label')
                ->values();
        }

        return view('finanzas.sueldos', [
            'period'  => $period,
            'year'    => $year,
            'month'   => $month,
            'workers' => $workers,
        ]);
    }

    public function store(Request $request)
    {
        $year  = (int) $request->input('year');
        $month = (int) $request->input('month');

        if (!$year || !$month) {
            return redirect()
                ->route('finanzas.salaries.index')
                ->with('error', 'Período inválido para sueldos.');
        }

        // Categoría de sueldos (primer egreso encontrado)
        $salaryCategoryId = FinanceCategory::query()
            ->where('type', 'egreso')
            ->whereRaw("UPPER(name) LIKE '%SUELD%'")
            ->value('id');

        if (!$salaryCategoryId) {
            return redirect()
                ->route('finanzas.salaries.index', ['period' => sprintf('%04d-%02d', $year, $month)])
                ->with('error', 'No se encontró categoría de sueldos.');
        }

        $rows = $request->input('salaries', []);

        foreach ($rows as $subId => $row) {
            $workerName = trim((string) ($row['worker_name'] ?? ''));
            $amount     = (int) ($row['amount'] ?? 0);

            if ($workerName === '' || $amount <= 0) {
                continue;
            }

            FinanceBudget::updateOrCreate(
                [
                    'year'        => $year,
                    'month'       => $month,
                    'category_id' => $salaryCategoryId,
                    'worker_name' => $workerName,
                ],
                [
                    'amount' => $amount,
                ]
            );
        }

        return redirect()
            ->route('finanzas.salaries.index', ['period' => sprintf('%04d-%02d', $year, $month)])
            ->with('success', 'Sueldos guardados correctamente.');
    }
}
