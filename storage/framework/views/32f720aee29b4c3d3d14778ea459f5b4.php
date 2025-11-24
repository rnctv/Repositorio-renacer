<?php $__env->startSection('content'); ?>
<?php
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
?>

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
  <?php if(session('status')): ?>
  <div class="alert alert-success fin-status-alert" style="margin-bottom:.75rem;"><?php echo e(session('status')); ?></div>
  <?php endif; ?>
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
                 value="<?php echo e($periodValue); ?>"
                 min="<?php echo e($minPeriod); ?>"
                 onchange="this.form.submit()">
        </div>
      </form>
    </h1>
  </div>


  
  <div class="fin-summary-row">
    <?php
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
?>
<div class="fin-summary-card green">
      <div class="fin-summary-inner">
        <div class="d-flex align-items-center mb-1">
          <span class="fin-summary-icon">💰</span>
          <span class="fin-summary-title">Total ingresos</span>
        </div>
        <div class="fin-summary-main">
          <?php echo e(number_format(($mt['ingresos_total'] + ($openingDbSum ?? 0)),0,',','.')); ?> CLP
        </div>
        <div class="fin-summary-sub">
          <div>Efectivo: <?php echo e(number_format($mt['ingresos_efectivo'],0,',','.')); ?></div>
          <div>Transferencias: <?php echo e(number_format($mt['ingresos_transferencias'],0,',','.')); ?></div>
          <div>Tarjeta: <?php echo e(number_format($mt['ingresos_tarjeta'],0,',','.')); ?></div>
          <div>Mercado Pago: <?php echo e(number_format($mt['ingresos_mercadopago'],0,',','.')); ?></div>
          <div><strong>Saldo inicial:</strong> <?php if(isset($openingList)): ?><span class="text-muted small fin-opening-debug">(debug: <?php echo e($year); ?>-<?php echo e($month); ?>, registros: <?php echo e($openingList->count()); ?>)</span><?php endif; ?> <?php echo e(number_format($openingDbSum,0,',','.')); ?></div>
          <div class="mt-1">
            <label for="finOpeningToggle" class="link-edit-opening">Editar saldo inicial</label>
          </div>
        </div>
        <?php if(isset($openingList) && $openingList->count()): ?>
        <div class="fin-summary-sub" style="margin-top:.25rem; font-size:.8rem; color:#e6f2ff;">
          <?php $__currentLoopData = $openingList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $op): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div>- <?php echo e(strtoupper($op->paymentMethod?->name ?? 'SIN MEDIO')); ?>: <?php echo e(number_format((int)$op->amount,0,',','.')); ?></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="fin-summary-card red">
      <div class="fin-summary-inner">
        <div class="d-flex align-items-center mb-1">
          <span class="fin-summary-icon">💸</span>
          <span class="fin-summary-title">Total egresos</span>
        </div>
        <div class="fin-summary-main">
          <?php echo e(number_format($mt['egresos_total'],0,',','.')); ?> CLP
        </div>
        <div class="fin-summary-sub">
          <div>Efectivo: <?php echo e(number_format($mt['egresos_efectivo'],0,',','.')); ?></div>
          <div>Cuenta: <?php echo e(number_format($mt['egresos_cuenta'],0,',','.')); ?></div>
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
          <?php echo e(number_format(($mt['saldo_general'] + ($openingDbSum ?? 0)),0,',','.')); ?> CLP
        </div>
        <div class="fin-summary-sub">
          <div>Saldo efectivo: <?php echo e(number_format($mt['saldo_efectivo'] + ($openingCash ?? 0),0,',','.')); ?></div>
          <div>Saldo cuenta: <?php echo e(number_format($mt['saldo_cuenta'] + ($openingAccount ?? 0),0,',','.')); ?></div>
        </div>
      </div>
    </div>
  </div>

  
  <div class="fin-main-row">
    <div class="fin-main-col form-col">
      <div class="fin-card">
        <div class="border-bottom px-3 py-2 fin-section-title">
          Registrar movimiento
        </div>
        <div class="p-3 fin-form">
          <form method="POST" action="<?php echo e(route('finanzas.store')); ?>">
            <?php echo csrf_field(); ?>
            <?php if(isset($editTransaction)): ?>
              <input type="hidden" name="id" value="<?php echo e($editTransaction->id); ?>">
            <?php endif; ?>


            
            <div class="fin-form-row">
              <div class="fin-form-col">
                <label class="form-label">Fecha</label>
                <input type="date"
                       name="date"
                       id="tx-date"
                       class="form-control"
                       value="<?php echo e(old('date', isset($editTransaction) && $editTransaction->date ? $editTransaction->date->format('Y-m-d') : now()->toDateString())); ?>"
                       required
                       onkeydown="return false"
                       onpaste="return false"
                       inputmode="none"
                       onclick="this.showPicker && this.showPicker()">
              </div>
              <div class="fin-form-col">
                <label class="form-label">Tipo</label>
                <?php
                  $currentType = old('type', isset($editTransaction) ? $editTransaction->type : 'ingreso');
                ?>
                <select name="type" id="tx-type" class="form-select" required>
                  <option value="ingreso" <?php echo e($currentType === 'ingreso' ? 'selected' : ''); ?>>Ingreso</option>
                  <option value="gasto" <?php echo e($currentType !== 'ingreso' ? 'selected' : ''); ?>>Egreso</option>
                </select>
              </div>
            </div>

            
            <div class="fin-form-row">
              <div class="fin-form-col">
                <label class="form-label">Categoría</label>
                <select name="category_id" id="tx-category" class="form-select" required>
                  <option value="">Seleccione una categoría</option>
                  <?php
                    $currentCategoryId = old('category_id', isset($editTransaction) ? $editTransaction->category_id : null);
                  ?>
                  <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($c->id); ?>"
                            data-type="<?php echo e($c->type); ?>"
                            <?php if($currentCategoryId == $c->id): ?> selected <?php endif; ?>>
                      <?php echo e($c->name); ?>

                    </option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
              </div>
              <div class="fin-form-col">
                <label class="form-label">Subcategoría / Ítem</label>
                <select name="subcategory_id" id="tx-subcategory" class="form-select" disabled>
                  <option value="">Opcional</option>
                  <?php
                    $currentSubcategoryId = old('subcategory_id', isset($editTransaction) ? $editTransaction->subcategory_id : null);
                  ?>
                  <?php $__currentLoopData = $allSubcategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($s->id); ?>"
                            data-category="<?php echo e($s->category_id); ?>"
                            <?php if($currentSubcategoryId == $s->id): ?> selected <?php endif; ?>>
                      <?php echo e($s->name); ?>

                    </option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
              </div>
            </div>

            
            <div class="fin-form-row">
              <div class="fin-form-col">
                <label class="form-label" id="payment-group-label">Medio de pago</label>
                <?php
                  $currentPaymentGroup = old('payment_group');
                  if (!$currentPaymentGroup && isset($editTransaction) && $editTransaction->paymentMethod) {
                    $currentPaymentGroup = $editTransaction->paymentMethod->is_cash ? 'cash' : 'account';
                  }
                ?>
                <select name="payment_group" id="payment-group" class="form-select" disabled>
                  <option value="">Seleccione medio</option>
                  <option value="cash" <?php echo e($currentPaymentGroup === 'cash' ? 'selected' : ''); ?>>Efectivo</option>
                  <option value="account" <?php echo e($currentPaymentGroup === 'account' ? 'selected' : ''); ?>>Cuenta empresa</option>
                </select>
              </div>
              <div class="fin-form-col">
                <label class="form-label">Monto (CLP)</label>
                <input type="number" name="amount" class="form-control" min="0" step="1" required value="<?php echo e(old('amount', isset($editTransaction) ? $editTransaction->amount : '')); ?>">
              </div>
            </div>

            
            <div class="mb-3">
              <label class="form-label">Descripción</label>
              <textarea name="description" class="form-control" placeholder="Detalle del movimiento"><?php echo e(old('description', isset($editTransaction) ? $editTransaction->description : '')); ?></textarea>
            </div>

            <div class="text-end">
              <button type="submit" class="btn btn-primary">
                <?php echo e(isset($editTransaction) ? 'Actualizar movimiento' : 'Registrar Gasto / Ingreso'); ?>

              </button>
              <?php if(isset($editTransaction)): ?>
                <a href="<?php echo e(route('finanzas.index', ['period' => $period ?? null])); ?>" class="btn btn-outline-secondary ms-2">
                  Cancelar edición
                </a>
              <?php endif; ?>
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
          <?php
            $gastosPorCategoria = [];
            $totalGastosVista = 0;
            foreach ($transactions as $t) {
                if (in_array($t->type, ['gasto','egreso'])) {
                    $catName = $t->category?->name ?? 'Sin categoría';
                    $gastosPorCategoria[$catName] = ($gastosPorCategoria[$catName] ?? 0) + (int)$t->amount;
                    $totalGastosVista += (int)$t->amount;
                }
            }
          ?>

          <?php if($totalGastosVista <= 0): ?>
            <p class="text-muted mb-0">No hay gastos registrados en este periodo.</p>
          <?php else: ?>
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
                  <?php $__currentLoopData = $gastosPorCategoria; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat => $monto): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $pct = round(($monto / max($totalGastosVista,1)) * 100); ?>
                    <tr>
                      <td><?php echo e($cat); ?></td>
                      <td class="text-end"><?php echo e(number_format($monto,0,',','.')); ?></td>
                      <td>
                        <div class="mb-1" style="background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden;">
                          <div style="background:#ef4444;height:8px;width: <?php echo e($pct); ?>%;"></div>
                        </div>
                        <span class="small text-muted"><?php echo e($pct); ?>%</span>
                      </td>
                    </tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>


  
  <div class="fin-card mb-2">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
      <span class="fin-mini-header">Últimos 5 movimientos del mes</span>
      <a href="<?php echo e(route('finanzas.historial')); ?>" class="btn btn-sm btn-outline-secondary fin-latest-more-btn">
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
          <?php
            $rows = isset($latestTransactions) && $latestTransactions->count() ? $latestTransactions : $transactions;
          ?>
          <?php
            $openingRows = \App\Models\FinanceOpeningBalance::with('paymentMethod')->where(['year'=>$year,'month'=>$month])->get();
          ?>
          <?php if(($openingRows->sum('amount') ?? 0) > 0): ?>
            <?php $__currentLoopData = $openingRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $op): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr class="table-info">
                <td><?php echo e(sprintf('%04d-%02d-01', $year, $month)); ?></td>
                <td><span class="badge bg-success">INGRESO</span></td>
                <td>Saldo inicial</td>
                <td>SISTEMA</td>
                <td>SALDO INICIAL</td>
                <td><?php echo e($op->paymentMethod->name ?? '—'); ?></td>
                <td class="text-end"><?php echo e(number_format((int)$op->amount,0,',','.')); ?></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php endif; ?>
          
          <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><?php echo e($t->date->format('Y-m-d')); ?></td>
              <td>
                <span class="badge <?php echo e($t->type === 'ingreso' ? 'bg-success' : 'bg-danger'); ?>">
                  <?php echo e(strtoupper($t->type)); ?>

                </span>
              </td>
              <td><?php echo e($t->description ?? '—'); ?></td>
              <td><?php echo e($t->category?->name ?? '—'); ?></td>
              <td><?php echo e($t->subcategory?->name ?? '—'); ?></td>
              <td><?php echo e($t->paymentMethod->name ?? '—'); ?></td>
              <td class="text-end"><?php echo e(number_format($t->amount,0,',','.')); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">
                No hay movimientos para este periodo.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  

<?php if(isset($salaryBars) && $salaryBars->isNotEmpty()): ?>
  <div class="fin-card mb-2">
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
      <span class="fin-mini-header">Sueldos del personal (mes)</span>
      <a href="<?php echo e(route('finanzas.salaries.index')); ?>?period=<?php echo e($periodValue); ?>" class="fin-salary-manage-link">
        Ajustar sueldos del mes
      </a>
    </div>
    <div class="fin-salary-wrapper">
      <div class="fin-salary-grid">
        <?php $__currentLoopData = $salaryBars; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bar): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="fin-salary-card">
            <div class="fin-salary-header-row">
              <div class="fin-salary-name">
                <?php echo e($bar->label); ?>

              </div>
              <div class="fin-salary-amounts">
                $<?php echo e(number_format($bar->paid, 0, ',', '.')); ?>

                <span class="fin-salary-separator">/</span>
                $<?php echo e(number_format($bar->total, 0, ',', '.')); ?>

              </div>
            </div>
            <div class="fin-salary-progress">
              <div class="fin-salary-progress-fill" style="width: <?php echo e($bar->percent); ?>%;"></div>
            </div>
            <div class="fin-salary-footer">
              <span class="fin-salary-paid">
                Pagado: $<?php echo e(number_format($bar->paid, 0, ',', '.')); ?>

              </span>
              <span class="fin-salary-remaining">
                Falta: $<?php echo e(number_format($bar->remaining, 0, ',', '.')); ?>

              </span>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>
<?php endif; ?>

</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>

<!-- Modal: Saldo inicial del mes -->
<input type="checkbox" id="finOpeningToggle" style="position:fixed;opacity:0;pointer-events:none;width:0;height:0" aria-hidden="true" />
<div id="modalOpening" class="modal-backdrop">
  <div class="modal-card">
    <form method="POST" action="<?php echo e(route('finanzas.monthOpening.store')); ?>" class="modal-content">
      <?php echo csrf_field(); ?>
      <div class="modal-head">
        <h5 class="modal-title">Saldo inicial del mes</h5>
        <label for="finOpeningToggle" class="modal-close" aria-label="Close" style="cursor:pointer;">&times;</label>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Año</label>
            <input name="year" type="number" class="form-control" value="<?php echo e($year); ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label">Mes</label>
            <input name="month" type="number" class="form-control" value="<?php echo e($month); ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Medio</label>
            <select name="payment_method_id" class="form-select" required>
              <?php $__currentLoopData = $methods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($m->id); ?>" <?php echo e(!$m->is_cash ? 'selected' : ''); ?>><?php echo e($m->name); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Monto (CLP)</label>
            <input name="amount" type="number" min="0" class="form-control" placeholder="0" value="<?php echo e($openingDbSum); ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label">Contraseña de edición</label>
            <input name="edit_pass" type="password" class="form-control" placeholder="Ingresa la contraseña" required>
            <?php $__errorArgs = ['edit_pass'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger small mt-1"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
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
    var shouldOpen = <?php echo json_encode(session('open_modal') === 'opening' || $errors->has('edit_pass'), 15, 512) ?>;
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
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/laravel/resources/views/finanzas/index.blade.php ENDPATH**/ ?>