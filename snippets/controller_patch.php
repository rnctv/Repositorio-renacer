use App\Models\FinanceMonthOpening;
use App\Models\FinancePaymentMethod;

// === Saldos iniciales desde BD (finance_month_openings) ===
$openingDbSum = (int) round((float)
    FinanceMonthOpening::where(['year'=>$year,'month'=>$month])->sum('amount')
);

// Por medio: id => total
$openingByMethod = FinanceMonthOpening::where(['year'=>$year,'month'=>$month])
    ->selectRaw('COALESCE(payment_method_id, 0) as pm, SUM(amount) as total')
    ->groupBy('pm')->pluck('total','pm');

// Para la vista
$monthTotals['ingresos_saldo_inicial'] = $openingDbSum;

// === Ajuste: sumar saldo inicial por medio al detalle "Saldo cuenta"
$idCuenta = optional(FinancePaymentMethod::where('name','CUENTA EMPRESA')->first())->id
    ?? optional(FinancePaymentMethod::where('name','like','%CUENTA%')->first())->id
    ?? 0;

if (isset($mt['saldo_cuenta'])) {
    $mt['saldo_cuenta'] = (int) $mt['saldo_cuenta']
        + (int) round((float) ($openingByMethod[$idCuenta] ?? 0));
}

// (Opcional) Si tu saldo general debe incluir el saldo inicial:
if (isset($mt['saldo_general'])) {
    $mt['saldo_general'] = (int) $mt['saldo_general'] + (int) $openingDbSum;
}
