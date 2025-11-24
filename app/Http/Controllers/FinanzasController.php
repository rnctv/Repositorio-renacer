<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\{
    FinanceCategory,
    FinanceSubcategory,
    FinanceTransaction,
    FinancePaymentMethod,
    FinanceOpeningBalance,
    FinanceBudget
};

class FinanzasController extends Controller
{
    public function index(Request $request)
    {
        $year  = (int)($request->input('year') ?? date('Y'));
        $month = (int)($request->input('month') ?? date('n'));

        // Selector de mes como YYYY-MM
        $periodInput = $request->input('period');
        if ($periodInput && preg_match('/^(\d{4})-(\d{2})$/', $periodInput, $m)) {
            $year  = (int)$m[1];
            $month = (int)$m[2];
            $period = $periodInput;
        } else {
            $period = sprintf('%04d-%02d', $year, $month);
        }
        $currentPeriod = date('Y-m'); // para min en input month de la vista

        $pmId = $request->input('payment_method_id');

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-d', strtotime("$from +1 month"));

        $methods       = FinancePaymentMethod::orderBy('name')->get();
        $categories    = FinanceCategory::orderBy('name')->get();
        $subcategories = FinanceSubcategory::orderBy('name')->get();

        // Saldo inicial por medio
        $defaultPmId = $pmId ?? ($methods->first()->id ?? null);
        $opening = $defaultPmId
            ? FinanceOpeningBalance::firstOrCreate(
                ['year' => $year, 'month' => $month, 'payment_method_id' => $defaultPmId],
                ['amount' => 0, 'source' => 'manual']
            )
            : null;

        // Auto carryover: si no hay saldos iniciales definidos para este mes,
        // usar el saldo sobrante del mes anterior y registrarlo como 'carryover'.
        $hasAnyOpening = FinanceOpeningBalance::where(['year' => $year, 'month' => $month])->exists();
        if (!$hasAnyOpening) {
            $prevYear  = (int)date('Y', strtotime("$from -1 month"));
            $prevMonth = (int)date('n', strtotime("$from -1 month"));
            $prevFrom  = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
            $prevTo    = date('Y-m-d', strtotime("$prevFrom +1 month"));
            $prevIngresos = (int) FinanceTransaction::whereBetween('date', [$prevFrom, $prevTo])->where('type','ingreso')->sum('amount');
            $prevEgresos  = (int) FinanceTransaction::whereBetween('date', [$prevFrom, $prevTo])->whereIn('type',['gasto','egreso'])->sum('amount');
            $prevOpening  = (int) FinanceOpeningBalance::where(['year'=>$prevYear,'month'=>$prevMonth])->sum('amount');
            $prevEnding   = $prevOpening + $prevIngresos - $prevEgresos;
            if ($prevEnding > 0) {
                $accountPm = FinancePaymentMethod::where('is_cash', false)->orderBy('id')->first() ?? FinancePaymentMethod::orderBy('id')->first();
                if ($accountPm) {
                    FinanceOpeningBalance::updateOrCreate(
                        ['year'=>$year,'month'=>$month,'payment_method_id'=>$accountPm->id],
                        ['amount'=>$prevEnding,'source'=>'carryover']
                    );
                }
            }
        }


        // Query base para movimientos del mes (tabla + distribución)
        $txQuery = FinanceTransaction::whereBetween('date', [$from, $to]);

        if ($pmId) {
            $txQuery->where('payment_method_id', $pmId);
        }

        foreach (['type', 'category_id', 'modality', 'worker_name', 'vehicle'] as $f) {
            if ($val = $request->input($f)) {
                $txQuery->where($f, $val);
            }
        }

        // Todos los movimientos del periodo (para distribución)
        $transactions = $txQuery->orderBy('date', 'desc')->get();

        // Últimos 5 movimientos (para tabla del home)
        $latestTransactions = $transactions->take(5);

        // Totales filtrados (tabla)
        $ingresos = (int) (clone $txQuery)->where('type', 'ingreso')->sum('amount');
        $egresos  = (int) (clone $txQuery)->whereIn('type', ['gasto', 'egreso'])->sum('amount');

        $saldoInicial = $opening ? (int)$opening->amount : 0;
        $saldoActual  = $saldoInicial + $ingresos - $egresos;

        // Suma total de saldos iniciales del mes (todos los medios)
        $openingSum = (int) round((float) FinanceOpeningBalance::where(['year'=>$year,'month'=>$month])->sum('amount'));
        $openingList = FinanceOpeningBalance::with('paymentMethod')
            ->where(['year'=>$year,'month'=>$month])->get();// Presupuestos del mes
        $budgets = FinanceBudget::with('category')
            ->where(['year' => $year, 'month' => $month])
            ->orderBy('category_id')
            ->get()
            ->map(function (FinanceBudget $b) use ($from, $to) {
                $spentQ = FinanceTransaction::where('category_id', $b->category_id)
                    ->whereBetween('date', [$from, $to])
                    ->whereIn('type', ['gasto', 'egreso']);

                if ($b->worker_name) {
                    $spentQ->where('worker_name', $b->worker_name);
                }

                $spent = (int)$spentQ->sum('amount');
                $remaining = (int)$b->amount - $spent;

                return [
                    'id'          => $b->id,
                    'category'    => $b->category?->name,
                    'worker_name' => $b->worker_name,
                    'amount'      => (int)$b->amount,
                    'spent'       => $spent,
                    'remaining'   => $remaining,
                ];
            });

        // ===============================
        //  TOTALES DEL MES (3 CARDS)
        // ===============================
        $methodsById = $methods->keyBy('id');

        $monthTx = FinanceTransaction::whereBetween('date', [$from, $to])->get();

        $ingresoEfectivo       = 0;
        $ingresoTransferencias = 0;
        $ingresoTarjeta        = 0;
        $ingresoMercadoPago    = 0;
        $ingresoOtrosCuenta    = 0;

        $egresoEfectivo = 0;
        $egresoCuenta   = 0;

        foreach ($monthTx as $tx) {
            $amount = (int)$tx->amount;
            $type   = $tx->type;

            $pm = $tx->payment_method_id
                ? $methodsById->get($tx->payment_method_id)
                : null;

        // Auto carryover: si no hay saldos iniciales definidos para este mes,
        // usar el saldo sobrante del mes anterior y registrarlo como 'carryover'.
        $hasAnyOpening = FinanceOpeningBalance::where(['year' => $year, 'month' => $month])->exists();
        if (!$hasAnyOpening) {
            $prevYear  = (int)date('Y', strtotime("$from -1 month"));
            $prevMonth = (int)date('n', strtotime("$from -1 month"));
            $prevFrom  = sprintf('%04d-%02d-01', $prevYear, $prevMonth);
            $prevTo    = date('Y-m-d', strtotime("$prevFrom +1 month"));
            $prevIngresos = (int) FinanceTransaction::whereBetween('date', [$prevFrom, $prevTo])->where('type','ingreso')->sum('amount');
            $prevEgresos  = (int) FinanceTransaction::whereBetween('date', [$prevFrom, $prevTo])->whereIn('type',['gasto','egreso'])->sum('amount');
            $prevOpening  = (int) FinanceOpeningBalance::where(['year'=>$prevYear,'month'=>$prevMonth])->sum('amount');
            $prevEnding   = $prevOpening + $prevIngresos - $prevEgresos;
            if ($prevEnding > 0) {
                $accountPm = FinancePaymentMethod::where('is_cash', false)->orderBy('id')->first() ?? FinancePaymentMethod::orderBy('id')->first();
                if ($accountPm) {
                    FinanceOpeningBalance::updateOrCreate(
                        ['year'=>$year,'month'=>$month,'payment_method_id'=>$accountPm->id],
                        ['amount'=>$prevEnding,'source'=>'carryover']
                    );
                }
            }
        }


            $name   = $pm ? strtoupper($pm->name) : '';
            $isCash = $pm && (bool)$pm->is_cash;

            if ($type === 'ingreso') {
                if ($isCash) {
                    $ingresoEfectivo += $amount;
                } elseif (str_contains($name, 'MERCADO')) {
                    $ingresoMercadoPago += $amount;
                } elseif (str_contains($name, 'TARJETA')) {
                    $ingresoTarjeta += $amount;
                } elseif (str_contains($name, 'TRANSF')) {
                    $ingresoTransferencias += $amount;
                } else {
                    $ingresoOtrosCuenta += $amount;
                }
            } elseif ($type === 'egreso' || $type === 'gasto') {
                if ($isCash) {
                    $egresoEfectivo += $amount;
                } else {
                    $egresoCuenta += $amount;
                }
            }
        }

        $ingresosTotalMes = $ingresoEfectivo + $ingresoTransferencias + $ingresoTarjeta + $ingresoMercadoPago + $ingresoOtrosCuenta;
        $egresosTotalMes  = $egresoEfectivo + $egresoCuenta;

        // Comisión 2,19% + IVA aplicada a ingresos con Tarjeta y Mercado Pago
        $commissionBase = 0.0219;
        $commissionIva  = 0.19;
        $commissionRate = $commissionBase * (1 + $commissionIva);
        $commissionTotal = (int) round(($ingresoTarjeta + $ingresoMercadoPago) * $commissionRate);

        $egresosTotalConComision = $egresosTotalMes + $commissionTotal;

        // Monto neto que realmente llega por Tarjeta + Mercado Pago
        $saldoTarjetaMpNeto = ($ingresoTarjeta + $ingresoMercadoPago) - $commissionTotal;

        $saldoGeneralMes  = $ingresosTotalMes - $egresosTotalConComision;
        $saldoEfectivoMes = $ingresoEfectivo - $egresoEfectivo;
        $saldoCuentaMes   = ($ingresoTransferencias + $saldoTarjetaMpNeto) - $egresoCuenta;

        $monthTotals = [
            'ingresos_saldo_inicial' => $openingSum,
            'ingresos_total'         => $ingresosTotalMes,
            'ingresos_efectivo'      => $ingresoEfectivo,
            'ingresos_transferencias'=> $ingresoTransferencias,
            'ingresos_tarjeta'       => $ingresoTarjeta,
            'ingresos_mercadopago'   => $ingresoMercadoPago,
            'egresos_total'          => $egresosTotalConComision,
            'egresos_efectivo'       => $egresoEfectivo,
            'egresos_cuenta'         => $egresoCuenta,
            'egresos_comision'       => $commissionTotal,
            'saldo_general'          => $saldoGeneralMes,
            'saldo_efectivo'         => $saldoEfectivoMes,
            'saldo_cuenta'           => $saldoCuentaMes,
            'saldo_tarjeta_mp_neto'  => $saldoTarjetaMpNeto,
        ];
        // ===============================
        //  SUELDOS - BARRAS POR TRABAJADOR
        // ===============================
        $salaryBars = collect();

        $salaryCategoryIds = FinanceCategory::query()
            ->where('type', 'egreso')
            ->whereRaw("UPPER(name) LIKE '%SUELD%'")
            ->pluck('id');

        if ($salaryCategoryIds->isNotEmpty()) {
            // Subcategorías (trabajadores) bajo la(s) categoría(s) de sueldos
            $salarySubcategories = FinanceSubcategory::query()
                ->whereIn('category_id', $salaryCategoryIds)
                ->orderBy('name')
                ->get();

            // Ocultar RRLL (CESAR) de las barras de sueldos
            $salarySubcategories = $salarySubcategories->filter(function ($sub) {
                $name = mb_strtoupper(trim($sub->name), 'UTF-8');
                return $name !== 'CESAR' && $name !== 'CÉSAR';
            })->values();

            // Egresos/gastos de sueldos del mes (a partir de $monthTx ya cargado)
            $salaryTx = $monthTx->filter(function (FinanceTransaction $tx) use ($salaryCategoryIds) {
                return in_array($tx->type, ['egreso', 'gasto'], true)
                    && $tx->category_id
                    && $salaryCategoryIds->contains($tx->category_id);
            });

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
            foreach ($salarySubcategories as $sub) {
                $key = mb_strtoupper(trim($sub->name), 'UTF-8');
                $subsByUpperName[$key] = $sub;
            }

            // Monto definido por trabajador (por nombre), sumando posibles registros
            $definedByWorker = [];
            foreach ($salaryBudgets as $budget) {
                $label = trim((string) ($budget->worker_name ?? ''));
                if ($label === '') {
                    continue;
                }
                $key = mb_strtoupper($label, 'UTF-8');
                if (!isset($definedByWorker[$key])) {
                    $definedByWorker[$key] = 0;
                }
                $definedByWorker[$key] += (int) $budget->amount;
            }

            foreach ($salarySubcategories as $sub) {
                $label = $sub->name;
                $key   = mb_strtoupper(trim($label), 'UTF-8');

                $paid    = (int) ($paidBySubId[$sub->id] ?? 0);
                $defined = (int) ($definedByWorker[$key] ?? 0);

                // Si no hay ni sueldo definido ni pagos, no mostramos barra
                if ($paid === 0 && $defined === 0) {
                    continue;
                }

                $total     = $defined > 0 ? $defined : $paid;
                $remaining = max(0, $total - $paid);
                $percent   = $total > 0
                    ? min(100, (int) round($paid * 100 / $total))
                    : 0;

                $salaryBars->push((object) [
                    'sub_id'    => $sub->id,
                    'label'     => $label,
                    'defined'   => $defined,
                    'paid'      => $paid,
                    'total'     => $total,
                    'remaining' => $remaining,
                    'percent'   => $percent,
                ]);
            }

            $salaryBars = $salaryBars->sortBy('label')->values();
        }



        $editId = $request->input('edit');
        $editTransaction = null;
        if ($editId) {
            $editTransaction = FinanceTransaction::with(['category','subcategory','paymentMethod'])->find((int)$editId);
        }

        return view('finanzas.index', compact(
            'year',
            'month',
            'period',
            'currentPeriod',
            'pmId',
            'methods',
            'categories',
            'subcategories',
            'transactions',
            'latestTransactions',
            'saldoInicial',
            'saldoActual',
            'ingresos',
            'egresos',
            'budgets',
            'monthTotals',
            'editTransaction',
            'salaryBars'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:finance_transactions,id'],
            'date' => ['required', 'date'],
            'type' => ['required', 'in:ingreso,gasto,egreso'],
            'amount' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:finance_categories,id'],
            'subcategory_id' => ['nullable', 'exists:finance_subcategories,id'],
            'payment_group' => ['nullable', 'in:cash,account'],
            'reference' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'modality' => ['nullable', 'string'],
            'worker_name' => ['nullable', 'string'],
            'vehicle' => ['nullable', 'string'],
        ]);

        $paymentGroup = $data['payment_group'] ?? null;

if ($paymentGroup) {
    $method = null;
    if ($paymentGroup === 'cash') {
        $method = FinancePaymentMethod::where('is_cash', 1)->orderBy('id')->first();
    } elseif ($paymentGroup === 'account') {
        // Preferir explícitamente CUENTA (empresa). Si no existe, tomar el primer no-efectivo.
        $method = FinancePaymentMethod::where('is_cash', 0)
            ->where('name', 'like', '%CUENTA%')
            ->orderBy('id')->first();
        if (!$method) {
            $method = FinancePaymentMethod::where('is_cash', 0)->orderBy('id')->first();
        }
    }
    $data['payment_method_id'] = $method ? $method->id : null;
}

        $id = $data['id'] ?? null;
        unset($data['id']);

        if ($id) {
            $transaction = FinanceTransaction::find($id);
            if ($transaction) {
                $transaction->update($data);
                $message = 'Movimiento actualizado';
            } else {
                FinanceTransaction::create($data);
                $message = 'Movimiento registrado';
            }
        } else {
            FinanceTransaction::create($data);
            $message = 'Movimiento registrado';
        }

        return back()->with('status', $message);
    }

    public function destroy(FinanceTransaction $transaccion)
    {
        $transaccion->delete();
        return back()->with('status', 'Movimiento eliminado');
    }

    public function exportCsv(Request $request)
    {
        $year  = (int)($request->input('year') ?? date('Y'));
        $month = (int)($request->input('month') ?? date('n'));
        $pmId  = $request->input('payment_method_id');

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-d', strtotime("$from +1 month"));

        $txQuery = FinanceTransaction::whereBetween('date', [$from, $to]);
        if ($pmId) {
            $txQuery->where('payment_method_id', $pmId);
        }

        foreach (['type', 'category_id', 'modality', 'worker_name', 'vehicle'] as $f) {
            if ($val = $request->input($f)) {
                $txQuery->where($f, $val);
            }
        }

        $rows = $txQuery->orderBy('date')->with(['category','paymentMethod'])->get();

        $headers = [
            'fecha',
            'tipo',
            'monto',
            'categoria',
            'medio',
            'modalidad',
            'trabajador',
            'vehiculo',
            'referencia',
            'descripcion',
        ];

// Mapear payment_group -> payment_method_id (solo egresos)
if (($data['type'] ?? null) === 'egreso') {
    $pg = $data['payment_group'] ?? null;
    if ($pg === 'cash') {
        $m = \App\Models\FinancePaymentMethod::where('is_cash', 1)->first();
        if ($m) { $data['payment_method_id'] = $m->id; }
    } elseif ($pg === 'account') {
        // Preferimos "cuenta" explícita; si no, el primer método no-efectivo
        $m = \App\Models\FinancePaymentMethod::where('is_cash', 0)
            ->where('name', 'like', '%CUENTA%')
            ->first();
        if (!$m) {
            $m = \App\Models\FinancePaymentMethod::where('is_cash', 0)->first();
        }
        if ($m) { $data['payment_method_id'] = $m->id; }
    }
}


        $out = implode(',', $headers) . "\n";

        foreach ($rows as $t) {
            $line = [
                $t->date?->format('Y-m-d'),
                $t->type,
                $t->amount,
                $t->category?->name,
                $t->paymentMethod->name ?? null,
                $t->modality,
                $t->worker_name,
                $t->vehicle,
                $t->reference,
                $t->description,
            ];
            $csvRow = [];
            foreach ($line as $val) {
                $val = $val === null ? '' : (string)$val;
                $val = '"' . str_replace('"', '""', $val) . '"';
                $csvRow[] = $val;
            }
            $out .= implode(',', $csvRow) . "\n";
        }

        return Response::make($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=finanzas_' . $year . '_' . $month . '.csv',
        ]);
    }

    public function historial(Request $request)
    {
        $methods       = FinancePaymentMethod::orderBy('name')->get();
        $categories    = FinanceCategory::orderBy('name')->get();
        $subcategories = FinanceSubcategory::orderBy('name')->get();
        $currentPeriod = date('Y-m');

        return view('finanzas.historial', [
            'methods'       => $methods,
            'categories'    => $categories,
            'subcategories' => $subcategories,
            'currentPeriod' => $currentPeriod,
        ]);
    }

    public function historialData(Request $request)
    {
        $year  = (int)($request->input('year') ?? date('Y'));
        $month = (int)($request->input('month') ?? date('n'));

        $periodInput = $request->input('period');
        if ($periodInput && preg_match('/^(\d{4})-(\d{2})$/', $periodInput, $m)) {
            $year  = (int)$m[1];
            $month = (int)$m[2];
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to   = date('Y-m-d', strtotime("$from +1 month"));

        $query = FinanceTransaction::with(['category','subcategory','paymentMethod'])
            ->whereBetween('date', [$from, $to]);

        if ($type = $request->input('type')) {
            if ($type === 'ingreso') {
                $query->where('type', 'ingreso');
            } elseif ($type === 'egreso') {
                $query->whereIn('type', ['egreso','gasto']);
            }
        }

        if ($pmId = $request->input('payment_method_id')) {
            $query->where('payment_method_id', $pmId);
        }

        if ($catId = $request->input('category_id')) {
            $query->where('category_id', $catId);
        }

        if ($q = trim((string)$request->input('q'))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('description', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderBy('date','desc')->limit(200)->get();

        $data = $rows->map(function (FinanceTransaction $t) {
            return [
                'id'               => $t->id,
                'date'             => $t->date ? $t->date->format('Y-m-d') : null,
                'type'             => $t->type,
                'type_label'       => strtoupper($t->type),
                'description'      => $t->description,
                'category'         => $t->category?->name,
                'category_id'      => $t->category_id,
                'subcategory'      => $t->subcategory?->name,
                'subcategory_id'   => $t->subcategory_id,
                'payment_method'   => $t->paymentMethod?->name ?? null,
                'payment_method_id'=> $t->payment_method_id,
                'payment_is_cash'  => $t->paymentMethod?->is_cash,
                'amount'           => (int)$t->amount,
                'amount_formatted' => number_format((int)$t->amount, 0, ',', '.'),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    
    
    public function updateOpeningBalance(Request $request)
    {
        $data = $request->validate([
            'year' => ['required','integer','min:2000','max:2100'],
            'month'=> ['required','integer','min:1','max:12'],
            'payment_method_id' => ['required','exists:finance_payment_methods,id'],
            'amount' => ['required','numeric','min:0'],
            'source' => ['nullable','in:manual,carryover'],
            'edit_pass' => ['required','string'],
        ]);

        $pass = trim((string)$data['edit_pass']);
        $expected = trim((string)(config('app.fin_opening_pass') ?? env('FIN_OPENING_PASS', '09Nov1985.Ell0nga')));

        if (!hash_equals($expected, $pass)) {
            return back()
                ->withInput()
                ->with('open_modal', 'opening')
                ->withErrors(['edit_pass' => 'Contraseña incorrecta.']);
        }

        $data['source'] = $data['source'] ?? 'manual';

        FinanceOpeningBalance::updateOrCreate(
            [
                'year' => (int)$data['year'],
                'month'=> (int)$data['month'],
                'payment_method_id' => (int)$data['payment_method_id'],
            ],
            [
                'amount' => (float)$data['amount'],
                'source' => $data['source'],
            ]
        );

        return redirect()->route('finanzas.index', ['period' => sprintf('%04d-%02d', $data['year'], $data['month'])])
            ->with('status','Saldo inicial actualizado');
    }


}
