@extends('layouts.app')

@section('content')
@php
  $periodValue = isset($period)
    ? $period
    : sprintf('%04d-%02d', $year ?? now()->year, $month ?? now()->month);
  $minPeriod = $currentPeriod ?? now()->format('Y-m');
  $allSubcategories = isset($subcategories) ? $subcategories : collect();

  // Totales calculados en base a las transacciones del periodo
  $ingresos_total          = 0;
  $ingresos_efectivo       = 0;
  $ingresos_transferencias = 0;
  $ingresos_tarjeta        = 0;
  $ingresos_mercadopago    = 0;

  $egresos_total    = 0;
  $egresos_efectivo = 0;
  $egresos_cuenta   = 0;

  foreach ($transactions as $tx) {
      $monto = (int)($tx->amount ?? 0);
      $tipo  = strtolower($tx->type ?? '');

      if ($tipo === 'ingreso') {
          $ingresos_total += $monto;

          $subNombre = strtoupper(trim(optional($tx->subcategory)->name ?? ''));
          $subPlano  = str_replace(' ', '', $subNombre);

          if (str_contains($subNombre, 'EFECTIVO')) {
              $ingresos_efectivo += $monto;
          } elseif (str_contains($subNombre, 'TRANSFER')) {
              $ingresos_transferencias += $monto;
          } elseif (str_contains($subNombre, 'TARJETA')) {
              $ingresos_tarjeta += $monto;
          } elseif (str_contains($subNombre, 'MERCADO PAGO') || str_contains($subPlano, 'MERCADOPAGO')) {
              $ingresos_mercadopago += $monto;
          }
      } elseif (in_array($tipo, ['gasto', 'egreso'])) {
          $egresos_total += $monto;

          $pmNombre = strtoupper(trim(optional($tx->paymentMethod)->name ?? ''));
          if (str_contains($pmNombre, 'EFECTIVO')) {
              $egresos_efectivo += $monto;
          } elseif ($pmNombre !== '') {
              // Cualquier medio distinto de efectivo lo consideramos como "cuenta"
              $egresos_cuenta += $monto;
          }
      }
  }

  $saldo_general   = $ingresos_total - $egresos_total;
  $saldo_efectivo  = $ingresos_efectivo - $egresos_efectivo;
  $ingresos_no_efectivo = $ingresos_total - $ingresos_efectivo;
  $saldo_cuenta    = $ingresos_no_efectivo - $egresos_cuenta;

  $mt = [
    'ingresos_total'          => $ingresos_total,
    'ingresos_efectivo'       => $ingresos_efectivo,
    'ingresos_transferencias' => $ingresos_transferencias,
    'ingresos_tarjeta'        => $ingresos_tarjeta,
    'ingresos_mercadopago'    => $ingresos_mercadopago,
    'egresos_total'           => $egresos_total,
    'egresos_efectivo'        => $egresos_efectivo,
    'egresos_cuenta'          => $egresos_cuenta,
    'saldo_general'           => $saldo_general,
    'saldo_efectivo'          => $saldo_efectivo,
    'saldo_cuenta'            => $saldo_cuenta,
  ];
@endphp

<style>
  body {
    background: #f8fafc;
  }

  .container.fin-container {
    max-width: 1400px;
  }
  .fin-container {
    padding-top: 24px;
    padding-bottom: 32px;
  }

  .fin-card {
    border-radius: 14px;
    border: none;
    box-shadow: 0 4px 10px rgba(15,23,42,.06);
    background: #ffffff;
  }

  .fin-summary-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 22px;
  }

  .fin-summary-card {
    flex: 1;
    min-width: 230px;
    border-radius: 18px;
    padding: 16px 18px;
    color: #fff;
    box-shadow: 0 6px 14px rgba(15,23,42,.18);
    position: relative;
    overflow: hidden;
  }

  .fin-summary-card::after {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(255,255,255,0.2), transparent 60%);
    opacity: 0.9;
    pointer-events: none;
  }

  .fin-summary-inner {
    position: relative;
    z-index: 1;
  }

  .fin-summary-card.green {
    background: linear-gradient(135deg,#10b981,#059669);
  }
  .fin-summary-card.red {
    background: linear-gradient(135deg,#ef4444,#b91c1c);
  }
  .fin-summary-card.blue {
    background: linear-gradient(135deg,#3b82f6,#1d4ed8);
  }

  .fin-summary-icon {
    font-size: 20px;
    opacity: .95;
    margin-right: 8px;
  }

  .fin-summary-title {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    opacity: .9;
  }
  .fin-summary-main {
    font-size: 24px;
    font-weight: 800;
    margin-top: 8px;
    margin-bottom: 6px;
  }
  .fin-summary-sub {
    font-size: 12px;
    line-height: 1.4;
    opacity: .95;
  }

  .fin-form .form-label {
    font-size: 13px;
    font-weight: 500;
    color: #374151;
  }
  .fin-form .form-control,
  .fin-form .form-select {
    font-size: 14px;
    border-radius: 10px;
    border-color: #d1d5db;
    padding: 7px 11px;
  }
  .fin-form .form-control:focus,
  .fin-form .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 .15rem rgba(59,130,246,.35);
  }
  .fin-form textarea.form-control {
    min-height: 80px;
    resize: vertical;
  }
  .fin-form button[type="submit"] {
    border-radius: 999px;
    padding: 7px 22px;
    font-weight: 600;
    background: linear-gradient(135deg,#3b82f6,#2563eb);
    border: none;
  }
  .fin-form button[type="submit"]:hover {
    filter: brightness(1.05);
    box-shadow: 0 6px 15px rgba(37,99,235,.35);
  }

  .fin-section-title {
    font-weight: 600;
    font-size: 14px;
  }

.fin-card .table th,
  .fin-card .table td {
    padding-top: 0.45rem;
    padding-bottom: 0.45rem;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
    font-size: 12px;
  }

  .fin-latest-more-btn {
    border-radius: 999px;
    padding: 4px 14px;
    font-size: 11px;
  }

  .fin-stats-body {
    max-height: 260px;
    overflow-y: auto;
    padding-right: 4px;
  }

  .fin-stats-body::-webkit-scrollbar {
    width: 6px;
  }
  .fin-stats-body::-webkit-scrollbar-track {
    background: transparent;
  }
  .fin-stats-body::-webkit-scrollbar-thumb {
    background: rgba(148,163,184,.8);
    border-radius: 999px;
  }

  /* Barras de sueldos resumen */
  .fin-salary-wrapper {
    padding: 6px 10px 8px;
  }
  .fin-salary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 8px 10px;
  }
  .fin-salary-card {
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    padding: 8px 10px 7px;
    box-shadow: 0 3px 10px rgba(15,23,42,0.04);
    font-size: 12px;
  }
  .fin-salary-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
  }
  .fin-salary-name {
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .fin-salary-amounts {
    font-weight: 500;
    color: #111827;
    white-space: nowrap;
  }
  .fin-salary-separator {
    margin: 0 2px;
    color: #9ca3af;
  }
  .fin-salary-progress {
    position: relative;
    width: 100%;
    height: 7px;
    border-radius: 999px;
    background: #e5e7eb;
    overflow: hidden;
  }
  .fin-salary-progress-fill {
    position: absolute;
    inset: 0;
    width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg,#b91c1c,#f97316);
    transition: width 0.4s ease-out;
  }
  .fin-salary-footer {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-top: 2px;
    font-size: 11px;
    color: #4b5563;
  }
  .fin-salary-paid {
    font-weight: 500;
  }
  .fin-salary-remaining {
    color: #b45309;
  }
  .fin-salary-link {
    text-align: right;
    padding: 0 12px 7px;
  }
  .fin-salary-manage-link {
    font-size: 11px;
    text-decoration: none;
    color: #2563eb;
  }
  .fin-salary-manage-link:hover {
    text-decoration: underline;
  }

  .fin-table-header {
    font-size: 13px;
  }

  .fin-main-row {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin-bottom: 14px;
    align-items: stretch;
  }

  .fin-main-col {
    min-width: 260px;
    display: flex;
  }

  .fin-main-col > .fin-card {
    flex: 1 1 auto;
  }

  .fin-main-col.form-col {
    flex: 1.1 1 0;
  }
  .fin-main-col.stats-col {
    flex: 0.9 1 0;
  }

  .fin-mini-header {
    font-size: 13px;
    font-weight: 600;
  }

  /* filas horizontales en formulario */
  .fin-form-row {
    display: flex;
    gap: 12px;
    margin-bottom: 10px;
    flex-wrap: wrap;
  }
  .fin-form-col {
    flex: 1;
    min-width: 0;
  }

  @media (max-width: 768px) {
    .fin-main-col.form-col,
    .fin-main-col.stats-col {
      flex: 1 1 100%;
    }
    .fin-container {
      padding-top: 16px;
    }
    .fin-form-row {
      flex-direction: column;
    }
  }

  /* Header Finanzas */
  .fin-header-row {
    margin-bottom: 16px;
  }
  .fin-header-row h1 {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
  }
  .fin-period-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    border-radius: 999px;
    background: #ffffff;
    box-shadow: 0 4px 10px rgba(15,23,42,.08);
    border: 1px solid rgba(148,163,184,.4);
    cursor: pointer;
  }
  .fin-period-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #6b7280;
  }
  .fin-period-input {
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    color: #111827;
    padding: 0;
    min-width: 140px;
  }
  .fin-period-input:focus {
    outline: none;
    box-shadow: none;
  }

</style>

<div class="container fin-container">
  @if(session('status'))
  <div class="alert alert-success fin-status-alert" style="margin-bottom:.75rem;">{{ session('status') }}</div>
  @endif
  <div class="fin-header-row d-flex align-items-center gap-3 mb-3">
    <h1 class="mb-0 d-flex align-items-center gap-3">
      Finanzas
      <form method="GET" id="fin-period-form" class="m-0 p-0">
        <div class="fin-period-wrapper"
             onclick="(function(){var el=document.getElementById('fin-period-input');if(el){if(el.showPicker){el.showPicker();}else{el.focus();}}})();">
          <span class="fin-period-label">Mes</span>
          <input type="month"
                 name="period"
                 id="fin-period-input"
                 class="fin-period-input"
                 value="{{ $periodValue }}"
                 min="{{ $minPeriod }}"
                 onchange="this.form.submit()">
        </div>
      </form>
    </h1>
  </div>


  {{-- CARDS RESUMEN (HORIZONTALES) --}}
  <div class="fin-summary-row">
    @php
  // Saldo inicial (apertura de mes) — total y desglose por efectivo/cuenta
  $openingRecords = \App\Models\FinanceOpeningBalance::with('paymentMethod')
      ->where(['year'=>$year,'month'=>$month])
      ->get();

  $openingDbSum = (int) round((float) $openingRecords->sum('amount'));

  // Determinar efectivo vs cuenta
  $openingCash = 0;
  $openingAccount = 0;
  foreach ($openingRecords as $op) {
      $amt = (int) ($op->amount ?? 0);
      $pm  = $op->paymentMethod;
      $pmName = strtoupper(trim($pm->name ?? ''));
      $isCash = ($pm && property_exists($pm, 'is_cash')) ? (bool)$pm->is_cash : false;
      // Fallback textual si no existe is_cash
      if ($isCash or str_contains($pmName, 'EFECTIVO')) {
          $openingCash += $amt;
      } else {
          $openingAccount += $amt;
      }
  }
@endphp
<div class="fin-summary-card green">
      <div class="fin-summary-inner">
        <div class="d-flex align-items-center mb-1">
          <span class="fin-summary-icon">💰</span>
          <span class="fin-summary-title">Total ingresos</span>
        </div>
        <div class="fin-summary-main">
          {{ number_format(($mt['ingresos_total'] + ($openingDbSum ?? 0)),0,',','.') }} CLP
        </div>
        <div class="fin-summary-sub">
          <div>Efectivo: {{ number_format($mt['ingresos_efectivo'],0,',','.') }}</div>
          <div>Transferencias: {{ number_format($mt['ingresos_transferencias'],0,',','.') }}</div>
          <div>Tarjeta: {{ number_format($mt['ingresos_tarjeta'],0,',','.') }}</div>
          <div>Mercado Pago: {{ number_format($mt['ingresos_mercadopago'],0,',','.') }}</div>
          <div><strong>Saldo inicial:</strong> @if(isset($openingList))<span class="text-muted small fin-opening-debug">(debug: {{ $year }}-{{ $month }}, registros: {{ $openingList->count() }})</span>@endif {{ number_format($openingDbSum,0,',','.') }}</div>
          <div class="mt-1">
            <label for="finOpeningToggle" class="link-edit-opening">Editar saldo inicial</label>
          </div>
        </div>
        @if(isset($openingList) && $openingList->count())
        <div class="fin-summary-sub" style="margin-top:.25rem; font-size:.8rem; color:#e6f2ff;">
          @foreach($openingList as $op)
            <div>- {{ strtoupper($op->paymentMethod?->name ?? 'SIN MEDIO') }}: {{ number_format((int)$op->amount,0,',','.') }}</div>
          @endforeach
        </div>
        @endif
      </div>
    </div>

    <div class="fin-summary-card red">
      <div class="fin-summary-inner">
        <div class="d-flex align-items-center mb-1">
          <span class="fin-summary-icon">💸</span>
          <span class="fin-summary-title">Total egresos</span>
        </div>
        <div class="fin-summary-main">
          {{ number_format($mt['egresos_total'],0,',','.') }} CLP
        </div>
        <div class="fin-summary-sub">
          <div>Efectivo: {{ number_format($mt['egresos_efectivo'],0,',','.') }}</div>
          <div>Cuenta: {{ number_format($mt['egresos_cuenta'],0,',','.') }}</div>
        </div>
      </div>
    </div>

    <div class="fin-summary-card blue">
      <div class="fin-summary-inner">
        <div class="d-flex align-items-center mb-1">
          <span class="fin-summary-icon">📊</span>
          <span class="fin-summary-title">Saldo neto</span>
        </div>
        <div class="fin-summary-main">
          {{ number_format(($mt['saldo_general'] + ($openingDbSum ?? 0)),0,',','.') }} CLP
        </div>
        <div class="fin-summary-sub">
          <div>Saldo efectivo: {{ number_format($mt['saldo_efectivo'] + ($openingCash ?? 0),0,',','.') }}</div>
          <div>Saldo cuenta: {{ number_format($mt['saldo_cuenta'] + ($openingAccount ?? 0),0,',','.') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- FORM + DISTRIBUCIÓN (2 COLUMNAS) --}}
  <div class="fin-main-row">
    <div class="fin-main-col form-col">
      <div class="fin-card">
        <div class="border-bottom px-3 py-2 fin-section-title">
          Registrar movimiento
        </div>
        <div class="p-3 fin-form">
          <form method="POST" action="{{ route('finanzas.store') }}">
            @csrf
            @if(isset($editTransaction))
              <input type="hidden" name="id" value="{{ $editTransaction->id }}">
            @endif


            {{-- FILA 1: Fecha - Tipo --}}
            <div class="fin-form-row">
              <div class="fin-form-col">
                <label class="form-label">Fecha</label>
                <input type="date"
                       name="date"
                       id="tx-date"
                       class="form-control"
                       value="{{ old('date', isset($editTransaction) && $editTransaction->date ? $editTransaction->date->format('Y-m-d') : now()->toDateString()) }}"
                       required
                       onkeydown="return false"
                       onpaste="return false"
                       inputmode="none"
                       onclick="this.showPicker && this.showPicker()">
              </div>
              <div class="fin-form-col">
                <label class="form-label">Tipo</label>
                @php
                  $currentType = old('type', isset($editTransaction) ? $editTransaction->type : 'ingreso');
                @endphp
                <select name="type" id="tx-type" class="form-select" required>
                  <option value="ingreso" {{ $currentType === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                  <option value="gasto" {{ $currentType !== 'ingreso' ? 'selected' : '' }}>Egreso</option>
                </select>
              </div>
            </div>

            {{-- FILA 2: Categoría - Subcategoría --}}
            <div class="fin-form-row">
              <div class="fin-form-col">
                <label class="form-label">Categoría</label>
                <select name="category_id" id="tx-category" class="form-select" required>
                  <option value="">Seleccione una categoría</option>
                  @php
                    $currentCategoryId = old('category_id', isset($editTransaction) ? $editTransaction->category_id : null);
                  @endphp
                  @foreach($categories as $c)
                    <option value="{{ $c->id }}"
                            data-type="{{ $c->type }}"
                            @if($currentCategoryId == $c->id) selected @endif>
                      {{ $c->name }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="fin-form-col">
                <label class="form-label">Subcategoría / Ítem</label>
                <select name="subcategory_id" id="tx-subcategory" class="form-select" disabled>
                  <option value="">Opcional</option>
                  @php
                    $currentSubcategoryId = old('subcategory_id', isset($editTransaction) ? $editTransaction->subcategory_id : null);
                  @endphp
                  @foreach($allSubcategories as $s)
                    <option value="{{ $s->id }}"
                            data-category="{{ $s->category_id }}"
                            @if($currentSubcategoryId == $s->id) selected @endif>
                      {{ $s->name }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            {{-- FILA 3: Medio de pago - Monto --}}
            <div class="fin-form-row">
              <div class="fin-form-col">
                <label class="form-label" id="payment-group-label">Medio de pago</label>
                @php
                  $currentPaymentGroup = old('payment_group');
                  if (!$currentPaymentGroup && isset($editTransaction) && $editTransaction->paymentMethod) {
                    $currentPaymentGroup = $editTransaction->paymentMethod->is_cash ? 'cash' : 'account';
                  }
                @endphp
                <select name="payment_group" id="payment-group" class="form-select" disabled>
                  <option value="">Seleccione medio</option>
                  <option value="cash" {{ $currentPaymentGroup === 'cash' ? 'selected' : '' }}>Efectivo</option>
                  <option value="account" {{ $currentPaymentGroup === 'account' ? 'selected' : '' }}>Cuenta empresa</option>
                </select>
              </div>
              <div class="fin-form-col">
                <label class="form-label">Monto (CLP)</label>
                <input type="number" name="amount" class="form-control" min="0" step="1" required value="{{ old('amount', isset($editTransaction) ? $editTransaction->amount : '') }}">
              </div>
            </div>

            {{-- FILA 4: Descripción --}}
            <div class="mb-3">
              <label class="form-label">Descripción</label>
              <textarea name="description" class="form-control" placeholder="Detalle del movimiento">{{ old('description', isset($editTransaction) ? $editTransaction->description : '') }}</textarea>
            </div>

            <div class="text-end">
              <button type="submit" class="btn btn-primary">
                {{ isset($editTransaction) ? 'Actualizar movimiento' : 'Registrar Gasto / Ingreso' }}
              </button>
              @if(isset($editTransaction))
                <a href="{{ route('finanzas.index', ['period' => $period ?? null]) }}" class="btn btn-outline-secondary ms-2">
                  Cancelar edición
                </a>
              @endif
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="fin-main-col stats-col">
      <div class="fin-card">
        <div class="border-bottom px-3 py-2 fin-section-title">
          Distribución de gastos por categoría (mes)
        </div>
        <div class="p-3">
          @php
            $gastosPorCategoria = [];
            $totalGastosVista = 0;
            foreach ($transactions as $t) {
                if (in_array($t->type, ['gasto','egreso'])) {
                    $catName = $t->category?->name ?? 'Sin categoría';
                    $gastosPorCategoria[$catName] = ($gastosPorCategoria[$catName] ?? 0) + (int)$t->amount;
                    $totalGastosVista += (int)$t->amount;
                }
            }
          @endphp

          @if($totalGastosVista <= 0)
            <p class="text-muted mb-0">No hay gastos registrados en este periodo.</p>
          @else
            <div class="fin-stats-body">
              <table class="table table-sm align-middle mb-0">
                <thead class="fin-table-header">
                  <tr>
                    <th>Categoría</th>
                    <th class="text-end">Monto</th>
                    <th style="width:180px;">%</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($gastosPorCategoria as $cat => $monto)
                    @php $pct = round(($monto / max($totalGastosVista,1)) * 100); @endphp
                    <tr>
                      <td>{{ $cat }}</td>
                      <td class="text-end">{{ number_format($monto,0,',','.') }}</td>
                      <td>
                        <div class="mb-1" style="background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden;">
                          <div style="background:#ef4444;height:8px;width: {{ $pct }}%;"></div>
                        </div>
                        <span class="small text-muted">{{ $pct }}%</span>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>


  {{-- TABLA MOVIMIENTOS: ÚLTIMOS 5 (ANCHO COMPLETO) --}}
  <div class="fin-card mb-2">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
      <span class="fin-mini-header">Últimos 5 movimientos del mes</span>
      <a href="{{ route('finanzas.historial') }}" class="btn btn-sm btn-outline-secondary fin-latest-more-btn">
        Ver todos
      </a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="bg-light fin-table-header">
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Subcategoría / Ítem</th>
            <th>Medio</th>
            <th class="text-end">Monto</th>
          </tr>
        </thead>
        <tbody>
          @php
            $rows = isset($latestTransactions) && $latestTransactions->count() ? $latestTransactions : $transactions;
          @endphp
          @php
            $openingRows = \App\Models\FinanceOpeningBalance::with('paymentMethod')->where(['year'=>$year,'month'=>$month])->get();
          @endphp
          @if(($openingRows->sum('amount') ?? 0) > 0)
            @foreach($openingRows as $op)
              <tr class="table-info">
                <td>{{ sprintf('%04d-%02d-01', $year, $month) }}</td>
                <td><span class="badge bg-success">INGRESO</span></td>
                <td>Saldo inicial</td>
                <td>SISTEMA</td>
                <td>SALDO INICIAL</td>
                <td>{{ $op->paymentMethod->name ?? '—' }}</td>
                <td class="text-end">{{ number_format((int)$op->amount,0,',','.') }}</td>
              </tr>
            @endforeach
          @endif
          
          @forelse($rows as $t)
            <tr>
              <td>{{ $t->date->format('Y-m-d') }}</td>
              <td>
                <span class="badge {{ $t->type === 'ingreso' ? 'bg-success' : 'bg-danger' }}">
                  {{ strtoupper($t->type) }}
                </span>
              </td>
              <td>{{ $t->description ?? '—' }}</td>
              <td>{{ $t->category?->name ?? '—' }}</td>
              <td>{{ $t->subcategory?->name ?? '—' }}</td>
              <td>{{ $t->paymentMethod->name ?? '—' }}</td>
              <td class="text-end">{{ number_format($t->amount,0,',','.') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-3">
                No hay movimientos para este periodo.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  

@if(isset($salaryBars) && $salaryBars->isNotEmpty())
  <div class="fin-card mb-2">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
      <span class="fin-mini-header">Sueldos del personal (mes)</span>
      <a href="{{ route('finanzas.salaries.index') }}?period={{ $periodValue }}" class="fin-salary-manage-link">
        Ajustar sueldos del mes
      </a>
    </div>
    <div class="fin-salary-wrapper">
      <div class="fin-salary-grid">
        @foreach($salaryBars as $bar)
          <div class="fin-salary-card">
            <div class="fin-salary-header-row">
              <div class="fin-salary-name">
                {{ $bar->label }}
              </div>
              <div class="fin-salary-amounts">
                ${{ number_format($bar->paid, 0, ',', '.') }}
                <span class="fin-salary-separator">/</span>
                ${{ number_format($bar->total, 0, ',', '.') }}
              </div>
            </div>
            <div class="fin-salary-progress">
              <div class="fin-salary-progress-fill" style="width: {{ $bar->percent }}%;"></div>
            </div>
            <div class="fin-salary-footer">
              <span class="fin-salary-paid">
                Pagado: ${{ number_format($bar->paid, 0, ',', '.') }}
              </span>
              <span class="fin-salary-remaining">
                Falta: ${{ number_format($bar->remaining, 0, ',', '.') }}
              </span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
@endif

</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const typeSelect = document.getElementById('tx-type');
  const categorySelect = document.getElementById('tx-category');
  const subcategorySelect = document.getElementById('tx-subcategory');
  const paymentGroup = document.getElementById('payment-group');
  const paymentGroupLabel = document.getElementById('payment-group-label');

  if (!typeSelect || !categorySelect || !subcategorySelect || !paymentGroup) return;

  const categoryOptions = Array.from(categorySelect.options);
  const subcategoryOptions = Array.from(subcategorySelect.options);

  function updatePaymentGroup() {
    const type = typeSelect.value === 'ingreso' ? 'ingreso' : 'egreso';
    if (type === 'ingreso') {
      paymentGroup.value = '';
      paymentGroup.disabled = true;
      if (paymentGroupLabel) {
        paymentGroupLabel.textContent = 'Medio (solo para egresos)';
      }
    } else {
      paymentGroup.disabled = false;
      if (paymentGroupLabel) {
        paymentGroupLabel.textContent = 'Medio de pago';
      }
    }
  }

  function filterCategories() {
    const type = typeSelect.value === 'ingreso' ? 'ingreso' : 'egreso';
    categorySelect.value = '';
    subcategorySelect.value = '';
    subcategorySelect.disabled = true;

    categoryOptions.forEach(function (opt) {
      if (!opt.value) {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      const optType = opt.getAttribute('data-type');
      const ok = !optType || optType === type;
      opt.hidden = !ok;
      opt.disabled = !ok;
    });
  }

  function filterSubcategories() {
    const catId = categorySelect.value;
    subcategorySelect.disabled = !catId;

    subcategoryOptions.forEach(function (opt) {
      if (!opt.value) {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      const ok = opt.getAttribute('data-category') === catId;
      opt.hidden = !ok;
      opt.disabled = !ok;
    });
  }

  typeSelect.addEventListener('change', function () {
    filterCategories();
    filterSubcategories();
    updatePaymentGroup();
  });
  categorySelect.addEventListener('change', filterSubcategories);

  filterCategories();
  filterSubcategories();
  updatePaymentGroup();
});
</script>
@endpush

<!-- Modal: Saldo inicial del mes -->
<input type="checkbox" id="finOpeningToggle" style="position:fixed;opacity:0;pointer-events:none;width:0;height:0" aria-hidden="true" />
<div id="modalOpening" class="modal-backdrop">
  <div class="modal-card">
    <form method="POST" action="{{ route('finanzas.monthOpening.store') }}" class="modal-content">
      @csrf
      <div class="modal-head">
        <h5 class="modal-title">Saldo inicial del mes</h5>
        <label for="finOpeningToggle" class="modal-close" aria-label="Close" style="cursor:pointer;">&times;</label>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Año</label>
            <input name="year" type="number" class="form-control" value="{{ $year }}" required>
          </div>
          <div class="col-6">
            <label class="form-label">Mes</label>
            <input name="month" type="number" class="form-control" value="{{ $month }}" required>
          </div>
          <div class="col-12">
            <label class="form-label">Medio</label>
            <select name="payment_method_id" class="form-select" required>
              @foreach($methods as $m)
                <option value="{{ $m->id }}" {{ !$m->is_cash ? 'selected' : '' }}>{{ $m->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Monto (CLP)</label>
            <input name="amount" type="number" min="0" class="form-control" placeholder="0" value="{{ $openingDbSum }}" required>
          </div>
          <div class="col-12">
            <label class="form-label">Contraseña de edición</label>
            <input name="edit_pass" type="password" class="form-control" placeholder="Ingresa la contraseña" required>
            @error('edit_pass')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label class="form-label">Origen</label>
            <select name="source" class="form-select">
              <option value="manual" selected>Ingreso manual</option>
              <option value="carryover">Saldo sobrante del mes anterior</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <label for="finOpeningToggle" class="btn btn-secondary" style="cursor:pointer;">Cancelar</label>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<style>
  /* Toggle modal only with CSS */
  #finOpeningToggle:checked ~ #modalOpening { display:flex; }
</style>

<style>
  .link-edit-opening{
    background:transparent; border:0; padding:0;
    color:#e6f2ff; text-decoration:underline; cursor:pointer;
    font-size:.9rem; font-weight:600;
  }
  #finOpeningToggle{
    position:fixed; opacity:0; pointer-events:none; width:0; height:0;
  }
</style>

<script>
  (function(){
    var shouldOpen = @json(session('open_modal') === 'opening' || $errors->has('edit_pass'));
    if(shouldOpen){
      var cb = document.getElementById('finOpeningToggle');
      if(cb){ cb.checked = true; }
    }
  })();
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
  // Para cada formulario con 'type', conectamos lógica local
  document.querySelectorAll('select#tx-type').forEach(function(typeSelect) {
    const form = typeSelect.closest('form') || document;
    const categorySelect = form.querySelector('#tx-category');
    const subcategorySelect = form.querySelector('#tx-subcategory');
    const boxPg = form.querySelector('.box-payment-group');
    const selPg = form.querySelector('select[name="payment_group"]');
    const boxPm = form.querySelector('.box-payment-method-id');

    // Cacheamos opciones originales
    const allCatOptions = categorySelect ? Array.from(categorySelect.options) : [];
    const allSubOptions = subcategorySelect ? Array.from(subcategorySelect.options) : [];

    function togglePaymentUI() {
      const isIngreso = (typeSelect.value === 'ingreso');
      if (isIngreso) {
        if (boxPg) boxPg.style.display = 'none';
        if (selPg) { selPg.disabled = true; selPg.value = ''; }
        if (boxPm) boxPm.style.display = 'none';
      } else {
        if (boxPg) boxPg.style.display = '';
        if (selPg) { selPg.disabled = false; if (!selPg.value) selPg.value = 'account'; }
        if (boxPm) boxPm.style.display = 'none';
      }
    }

    function filterCategories() {
      if (!categorySelect) return;
      const type = (typeSelect && typeSelect.value === 'ingreso') ? 'ingreso' : 'egreso';
      // Mostrar todas y luego filtrar por data-type
      allCatOptions.forEach(function(opt) {
        if (!opt.value) { opt.hidden = false; opt.disabled = false; return; }
        const ok = (opt.getAttribute('data-type') === type);
        opt.hidden = !ok;
        opt.disabled = !ok;
      });
      // Si la actual no calza con el tipo, reseteamos
      const currentOpt = categorySelect.selectedOptions[0];
      if (currentOpt && currentOpt.hidden) {
        categorySelect.value = '';
      }
      filterSubcategories(); // Reaplicar subcategorías
    }

    function filterSubcategories() {
      if (!subcategorySelect) return;
      const catId = categorySelect ? categorySelect.value : '';
      subcategorySelect.disabled = !catId;
      allSubOptions.forEach(function(opt) {
        if (!opt.value) { opt.hidden = false; opt.disabled = false; return; }
        const ok = (opt.getAttribute('data-category') === catId);
        opt.hidden = !ok;
        opt.disabled = !ok;
      });
      // Si la seleccionada ya no aplica, limpiamos
      const scur = subcategorySelect.selectedOptions[0];
      if (scur && scur.hidden) {
        subcategorySelect.value = '';
      }
    }

    if (typeSelect) {
      typeSelect.addEventListener('change', function() {
        togglePaymentUI();
        filterCategories();
      });
    }
    if (categorySelect) {
      categorySelect.addEventListener('change', filterSubcategories);
    }

    // Inicialización
    togglePaymentUI();
    filterCategories();
  });
});
</script>