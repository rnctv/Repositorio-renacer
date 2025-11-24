@extends('layouts.app')

@section('content')
@php
  $periodValue = $period ?? now()->format('Y-m');
  $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int) $month, (int) $year);
  $firstOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1);
  $firstDayOfWeek = (int) $firstOfMonth->dayOfWeekIso; // 1 = lunes, 7 = domingo
  $monthTitle = $firstOfMonth->translatedFormat('F Y');
@endphp

<style>
  .juan-day-btn.is-selected {
    background: #10b981 !important; /* verde */
    color: #ffffff !important;
    border-color: #059669 !important;
  }

  .juan-calendar-note {
    font-size: 12px;
    color: #6b7280;
  }
  .juan-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1050;
  }
  .juan-modal-backdrop.is-visible {
    display: flex;
  }
  .juan-modal {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.35);
    max-width: 480px;
    width: 100%;
    padding: 14px 16px 12px;
  }
  .juan-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
  }
  .juan-modal-title {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
  }
  .juan-modal-close {
    border: none;
    background: transparent;
    font-size: 18px;
    line-height: 1;
    padding: 0 2px;
    cursor: pointer;
    color: #6b7280;
  }
  .juan-modal-body {
    font-size: 12px;
    color: #374151;
    margin-bottom: 8px;
  }
  .juan-calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 4px;
  }
  .juan-calendar-weekdays div {
    text-align: center;
    font-weight: 600;
    font-size: 11px;
    color: #6b7280;
  }
  .juan-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
  }
  .juan-day-btn {
    width: 100%;
    padding: 4px 0;
    font-size: 11px;
    border-radius: 8px;
  }
  .juan-day-empty {
    height: 26px;
  }
  .juan-modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
    font-size: 12px;
  }
  .juan-days-label-inline {
    font-size: 11px;
    color: #4b5563;
  }
</style>

<div class="container-fluid px-3 py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-1">Sueldos</h1>
      <p class="text-muted mb-0">
        Configuración de sueldos por subcategoría de la categoría SUELDOS (egresos) para el mes seleccionado.
      </p>
    </div>
    <form method="GET" action="{{ route('finanzas.salaries.index') }}" class="d-flex align-items-center gap-2">
      <label for="period" class="me-2 mb-0 small text-muted">Mes</label>
      <input
        type="month"
        id="period"
        name="period"
        class="form-control form-control-sm"
        value="{{ $periodValue }}"
      >
      <button type="submit" class="btn btn-sm btn-outline-primary ms-2">
        Cambiar
      </button>
    </form>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      @if(session('success'))
        <div class="alert alert-success small py-2">
          {{ session('success') }}
        </div>
      @endif

      @if(session('error'))
        <div class="alert alert-danger small py-2">
          {{ session('error') }}
        </div>
      @endif

      <form method="POST" action="{{ route('finanzas.salaries.store') }}">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="table-responsive">
          <table class="table table-sm align-middle mb-2">
            <thead class="table-light">
              <tr>
                <th style="width: 30%;">Trabajador (Subcategoría)</th>
                <th style="width: 20%;" class="text-end">Pagado mes</th>
                <th style="width: 25%;" class="text-end">Sueldo tope</th>
                <th style="width: 15%;" class="text-end">Falta</th>
                <th style="width: 10%;" class="text-end">% cubierto</th>
              </tr>
            </thead>
            <tbody>
              @php
                // valor por día para Juan
                $juanRate = 13000;
              @endphp

              @forelse($workers as $worker)
                @php
                  $isJuan = mb_strtoupper($worker->label, 'UTF-8') === 'JUAN';
                  $baseAmount = $worker->defined > 0 ? $worker->defined : $worker->total;
                  $juanInitialDays = $isJuan && $baseAmount > 0 ? (int) round($baseAmount / $juanRate) : 0;
                @endphp
                <tr>
                  <td>
                    {{ $worker->label }}
                    <input type="hidden" name="salaries[{{ $worker->sub_id }}][worker_name]" value="{{ $worker->worker_name }}">
                  </td>
                  <td class="text-end">
                    ${{ number_format($worker->paid, 0, ',', '.') }}
                  </td>
                  <td class="text-end">
                    <input
                      type="number"
                      min="0"
                      step="1000"
                      name="salaries[{{ $worker->sub_id }}][amount]"
                      class="form-control form-control-sm text-end"
                      value="{{ $worker->defined > 0 ? $worker->defined : '' }}"
                      placeholder="Ej: 800000"
                      @if($isJuan) id="juan-salary-input" @endif
                    >
                    @if($isJuan)
                      <input type="hidden" id="juan-initial-days" value="{{ $juanInitialDays }}">
                      <div class="juan-days-label-inline mt-1" id="juan-days-label">
                        Días seleccionados: {{ $juanInitialDays }}
                      </div>
                      <button
                        type="button"
                        class="btn btn-link btn-sm p-0 mt-1"
                        id="juan-open-calendar"
                      >
                        Configurar días
                      </button>
                    @endif
                  </td>
                  <td class="text-end">
                    @if($worker->total > 0)
                      ${{ number_format($worker->remaining, 0, ',', '.') }}
                    @else
                      —
                    @endif
                  </td>
                  <td class="text-end">
                    @if($worker->total > 0)
                      {{ $worker->percent }}%
                    @else
                      —
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-center text-muted small py-3">
                    No se encontraron subcategorías bajo la categoría de SUELDOS.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="juan-calendar-note mb-2">
          Nota: Para <strong>Juan</strong> el sueldo se calcula como <strong>$13.000 por día trabajado</strong>,
          usando el calendario mensual.
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success btn-sm">
            Guardar sueldos del mes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Modal calendario de Juan --}}
<div
  id="juan-calendar-modal"
  class="juan-modal-backdrop"
  data-rate="13000"
  data-days="{{ $daysInMonth }}"
  data-first-dow="{{ $firstDayOfWeek }}"
>
  <div class="juan-modal">
    <div class="juan-modal-header">
      <div class="juan-modal-title">
        Días trabajados de Juan <span class="text-muted">({{ $monthTitle }})</span>
      </div>
      <button type="button" class="juan-modal-close" aria-label="Cerrar">&times;</button>
    </div>
    <div class="juan-modal-body">
      <div class="mb-1">
        Selecciona los días trabajados. El sueldo de Juan se calculará como
        <strong>$13.000 x día</strong> al aplicar los cambios.
      </div>
      <div class="juan-calendar-weekdays">
        <div>Lu</div>
        <div>Ma</div>
        <div>Mi</div>
        <div>Ju</div>
        <div>Vi</div>
        <div>Sa</div>
        <div>Do</div>
      </div>
      <div class="juan-calendar-grid" id="juan-calendar-grid">
        {{-- El JavaScript rellenará los días aquí --}}
      </div>
    </div>
    <div class="juan-modal-footer">
      <div id="juan-modal-days-text">Días seleccionados: 0</div>
      <div>
        <button type="button" class="btn btn-sm btn-outline-secondary me-1 juan-modal-cancel">
          Cerrar
        </button>
        <button type="button" class="btn btn-sm btn-primary juan-modal-apply">
          Aplicar
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var openBtn = document.getElementById('juan-open-calendar');
  var modal = document.getElementById('juan-calendar-modal');
  var salaryInput = document.getElementById('juan-salary-input');
  var inlineLabel = document.getElementById('juan-days-label');
  var initialDaysInput = document.getElementById('juan-initial-days');

  if (!openBtn || !modal || !salaryInput || !initialDaysInput) {
    return;
  }

  var rate = parseInt(modal.getAttribute('data-rate') || '13000', 10);
  var daysInMonth = parseInt(modal.getAttribute('data-days') || '30', 10);
  var firstDow = parseInt(modal.getAttribute('data-first-dow') || '1', 10); // 1 = lunes
  var grid = document.getElementById('juan-calendar-grid');
  var modalDaysText = document.getElementById('juan-modal-days-text');

  var selected = new Set();
  var initialized = false;

  function openModal() {
    if (!initialized) {
      buildCalendar();
      initialized = true;
    }
    modal.classList.add('is-visible');
  }

  function closeModal() {
    modal.classList.remove('is-visible');
  }

  function buildCalendar() {
    if (!grid) return;

    grid.innerHTML = '';

    var initialDays = parseInt(initialDaysInput.value || '0', 10);
    selected = new Set();

    // dayOfWeekIso: 1 (lunes) ... 7 (domingo)
    for (var i = 1; i < firstDow; i++) {
      var emptyCell = document.createElement('div');
      emptyCell.className = 'juan-day-empty';
      grid.appendChild(emptyCell);
    }

    for (var day = 1; day <= daysInMonth; day++) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'juan-day-btn btn btn-light';
      btn.textContent = String(day);
      btn.dataset.day = String(day);

      if (day <= initialDays) {
        btn.classList.add('is-selected');
        btn.classList.remove('btn-light');
        btn.classList.add('btn-primary');
        selected.add(day);
      }

      btn.addEventListener('click', function (ev) {
        var b = ev.currentTarget;
        var d = parseInt(b.dataset.day || '0', 10);
        if (b.classList.contains('is-selected')) {
          b.classList.remove('is-selected');
          b.classList.remove('btn-primary');
          b.classList.add('btn-light');
          selected.delete(d);
        } else {
          b.classList.add('is-selected');
          b.classList.remove('btn-light');
          b.classList.add('btn-primary');
          selected.add(d);
        }
        updateModalDaysText();
      });

      grid.appendChild(btn);
    }

    updateModalDaysText();
  }

  function updateModalDaysText() {
    var count = selected.size;
    if (modalDaysText) {
      modalDaysText.textContent = 'Días seleccionados: ' + count;
    }
  }

  function applySelection() {
    var count = selected.size;
    var amount = count * rate;
    if (salaryInput) {
      salaryInput.value = amount > 0 ? amount : '';
    }
    if (inlineLabel) {
      inlineLabel.textContent = 'Días seleccionados: ' + count;
    }
    // actualizar valor base para próximas aperturas
    initialDaysInput.value = String(count);
    closeModal();
  }

  openBtn.addEventListener('click', function () {
    openModal();
  });

  var closeBtn = modal.querySelector('.juan-modal-close');
  var cancelBtn = modal.querySelector('.juan-modal-cancel');
  var applyBtn = modal.querySelector('.juan-modal-apply');

  if (closeBtn) {
    closeBtn.addEventListener('click', closeModal);
  }
  if (cancelBtn) {
    cancelBtn.addEventListener('click', closeModal);
  }
  if (applyBtn) {
    applyBtn.addEventListener('click', applySelection);
  }

  // cerrar si clic fuera del contenido
  modal.addEventListener('click', function (ev) {
    if (ev.target === modal) {
      closeModal();
    }
  });
});
</script>
@endpush
