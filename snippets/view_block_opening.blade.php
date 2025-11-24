@php
  // Total del saldo inicial (por mes) desde BD
  $openingDbSum = (int) round((float)
    \App\Models\FinanceMonthOpening::where(['year'=>$year,'month'=>$month])->sum('amount')
  );

  // Suma por medio (payment_method_id => total)
  $openingByMethod = \App\Models\FinanceMonthOpening::where(['year'=>$year,'month'=>$month])
      ->selectRaw('COALESCE(payment_method_id, 0) as pm, SUM(amount) as total')
      ->groupBy('pm')->pluck('total','pm');

  // ID del medio "Cuenta"
  $pmCuenta = optional(\App\Models\FinancePaymentMethod::where('name','CUENTA EMPRESA')->first())->id
      ?? optional(\App\Models\FinancePaymentMethod::where('name','like','%CUENTA%')->first())->id
      ?? 0;

  // Saldo cuenta ajustado (usa este en la línea "Saldo cuenta:")
  $saldoCuentaAdj = (int) ($mt['saldo_cuenta'] ?? 0) + (int) round((float) ($openingByMethod[$pmCuenta] ?? 0));

  // Ayudas para render:
  // - "Saldo inicial:" -> {{ number_format($openingDbSum ?? 0,0,',','.') }}
  // - Nro grande card azul -> {{ number_format(($mt['saldo_general'] + ($openingDbSum ?? 0)),0,',','.') }} CLP
  // - "Saldo cuenta:" -> {{ number_format($saldoCuentaAdj,0,',','.') }}
@endphp
