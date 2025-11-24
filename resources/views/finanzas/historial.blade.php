@extends('layouts.app')

@section('content')
      <h1 class="h3 mb-4">Historial de movimientos</h1>

      {{-- Filtros --}}
      <div class="card mb-3">
        <div class="card-body">
          
<div class="row g-2 align-items-end flex-wrap fin-filters">
  <div class="col-auto">
    <label class="form-label">Mes</label>
    <input type="month" id="filter-period" class="form-control" value="{{ $currentPeriod ?? now()->format('Y-m') }}">
  </div>
  <div class="col-auto">
    <label class="form-label">Tipo</label>
    <select id="filter-type" class="form-select">
      <option value="todos">Todos</option>
      <option value="ingreso">Ingresos</option>
      <option value="egreso">Egresos</option>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Medio de pago</label>
    <select id="filter-payment" class="form-select">
      <option value="">Todos</option>
      @foreach(($paymentMethods ?? []) as $pm)
        <option value="{{ $pm->id }}">{{ $pm->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Categoría</label>
    <select id="filter-category" class="form-select">
      <option value="">Todas</option>
      @foreach(($categories ?? []) as $c)
        <option value="{{ $c->id }}">{{ $c->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-auto d-none">
    <label class="form-label">Subcategoría / ítem</label>
    <select id="filter-subcategory" class="form-select">
      <option value="">Todas</option>
      @foreach(($subcategories ?? []) as $s)
        <option value="{{ $s->id }}" data-category="{{ $s->category_id }}">{{ $s->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Buscar</label>
    <input type="text" id="filter-q" class="form-control fin-filter-search" placeholder="Buscar en todas las columnas">
  </div>
  <div class="col-auto">
    <button type="button" class="btn btn-outline-secondary" id="btn-clear">Limpiar</button>
  </div>
</div>
<div class="card">
        <div style="padding:12px 16px;border-bottom:1px solid #e5e7eb;font-weight:500;display:flex;justify-content:space-between;align-items:center;">
          <span>Resultados</span>
          <span id="result-count" class="text-muted" style="font-size:12px;"></span>
        </div>

        <div class="table-responsive" style="max-height:calc(100vh - 320px);overflow:auto;">
          <table class="table table-sm mb-0 fin-history-table">
            <thead style="position:sticky;top:0;background:#f9fafb;z-index:1;">
              <tr>
                <th class="fin-th-sort" data-sort="date">Fecha <span class="fin-sort" data-field="date"></span></th>
                <th class="fin-th-sort" data-sort="type">Tipo <span class="fin-sort" data-field="type"></span></th>
                <th class="fin-th-sort" data-sort="description">Descripción <span class="fin-sort" data-field="description"></span></th>
                <th class="fin-th-sort" data-sort="category">Categoría <span class="fin-sort" data-field="category"></span></th>
                <th class="fin-th-sort" data-sort="subcategory">Subcategoría / ítem <span class="fin-sort" data-field="subcategory"></span></th>
                <th class="fin-th-sort" data-sort="payment">Medio <span class="fin-sort" data-field="payment_method"></span></th>
                <th class="text-end fin-th-sort" data-sort="amount">Monto <span class="fin-sort" data-field="amount"></span></th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody id="history-body">
              <tr>
                <td colspan="8" class="text-center text-muted py-3">
                  Cargando movimientos...
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        {{-- Paginación --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center px-3 py-2 gap-2 border-top" id="history-pagination">
          <div class="text-muted" style="font-size:12px;">
            <span id="history-page-info"></span>
          </div>
          <div class="d-flex align-items-center gap-2" style="font-size:12px;">
            <span>Mostrar</span>
            <select id="page-size" class="form-select form-select-sm" style="width:auto;">
              <option value="10" selected>10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
            <span>por página</span>
          </div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Paginación">
            <button type="button" class="btn btn-outline-secondary" id="page-first">&laquo;</button>
            <button type="button" class="btn btn-outline-secondary" id="page-prev">&lsaquo;</button>
            <button type="button" class="btn btn-outline-secondary" id="page-next">&rsaquo;</button>
            <button type="button" class="btn btn-outline-secondary" id="page-last">&raquo;</button>
          </div>
        </div>
{{-- Panel flotante edición --}}
<div id="fin-edit-modal" class="fin-edit-overlay" aria-hidden="true">
  <div class="fin-edit-panel">
    <div class="fin-edit-header">
      <span class="fin-edit-title">Editar movimiento</span>
      <button type="button" class="fin-edit-close" id="fin-edit-close-btn">&times;</button>
    </div>
    <div class="fin-edit-body">
      <form id="fin-edit-form">
        <input type="hidden" id="fin-edit-id">

        <div class="fin-edit-row">
          <div class="fin-edit-col">
            <label class="form-label">Fecha</label>
            <input type="date" id="fin-edit-date" class="form-control" required>
          </div>
          <div class="fin-edit-col">
            <label class="form-label">Tipo</label>
            <select id="fin-edit-type" class="form-select" required>
              <option value="ingreso">Ingreso</option>
              <option value="egreso">Egreso</option>
            </select>
          </div>
        </div>

        <div class="fin-edit-row">
          <div class="fin-edit-col">
            <label class="form-label">Categoría</label>
            <select id="fin-edit-category" class="form-select" required>
              <option value="">Seleccione una categoría</option>
              @foreach(($categories ?? []) as $c)
                <option value="{{ $c->id }}">{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="fin-edit-col">
            <label class="form-label">Subcategoría / ítem</label>
            <select id="fin-edit-subcategory" class="form-select">
              <option value="">Opcional</option>
              @foreach(($subcategories ?? []) as $s)
                <option value="{{ $s->id }}" data-category="{{ $s->category_id }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="fin-edit-row">
          <div class="fin-edit-col">
            <label class="form-label">Monto (CLP)</label>
            <input type="number" id="fin-edit-amount" class="form-control" min="0" step="1" required>
          </div>
        </div>

        <div class="mb-0">
          <label class="form-label">Descripción</label>
          <textarea id="fin-edit-description" class="form-control" rows="2" placeholder="Detalle del movimiento"></textarea>
        </div>
      </form>
    </div>
    <div class="fin-edit-footer">
      <button type="button" class="btn btn-outline-secondary" id="fin-edit-cancel">Cancelar</button>
      <button type="button" class="btn btn-primary" id="fin-edit-save">Guardar cambios</button>
    </div>
  </div>
</div>
@endsection

<style>
  .fin-history-table thead th,
  .fin-history-table tbody td {
    padding-top: 0.15rem;
    padding-bottom: 0.15rem;
    line-height: 1.1;
  }

  .fin-th-sort {
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
  }

  .fin-th-sort .fin-sort {
    font-size: 11px;
    opacity: .45;
    margin-left: 4px;
  }

  .fin-th-sort.is-sorted-asc .fin-sort::before {
    content: '▲';
  }

  .fin-th-sort.is-sorted-desc .fin-sort::before {
    content: '▼';
  }

  .fin-edit-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1050;
  }

  .fin-edit-overlay.is-open {
    display: flex;
  }

  .fin-edit-panel {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 18px 45px rgba(15,23,42,0.35);
    max-width: 640px;
    width: 100%;
    padding: 16px 18px 14px;
  }

  .fin-edit-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  .fin-edit-title {
    font-weight: 600;
    font-size: 17px;
  }

  .fin-edit-close {
    border: none;
    background: transparent;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
  }

  .fin-edit-body {
    margin-bottom: 10px;
  }

  .fin-edit-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
  }

  .fin-edit-row {
    display: flex;
    gap: 12px;
    margin-bottom: 10px;
    flex-wrap: wrap;
  }

  .fin-edit-col {
    flex: 1;
    min-width: 180px;
  }

/* Compact horizontal filters */
.fin-filters .form-label{margin-bottom:.25rem;}
.fin-filter-search{min-width:260px}

</style>


<script>
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(() => {
    if (typeof filterTopSubcategories === 'function') {
      filterTopSubcategories();
    }
  }, 50);

  const editUrlBase    = @json(route('finanzas.index'));
  const destroyUrlBase = @json(url('finanzas'));
  const dataUrl        = @json(route('finanzas.historial.data'));
  const csrfToken      = @json(csrf_token());
  const currentPeriod  = @json($currentPeriod ?? now()->format('Y-m'));

  const periodEl   = document.getElementById('filter-period');
  const typeEl     = document.getElementById('filter-type');
  const payEl      = document.getElementById('filter-payment');
  const catEl      = document.getElementById('filter-category');
  const subcatEl   = document.getElementById('filter-subcategory');
  const qEl        = document.getElementById('filter-q');
  const clearBtn   = document.getElementById('btn-clear');
  // Mapas (id -> nombre) para coincidencia por texto cuando el backend no envía IDs
  const catNameById = {};
  if (catEl) {
    Array.prototype.slice.call(catEl.options).forEach(function (opt) {
      if (opt.value) catNameById[String(opt.value)] = opt.text.trim();
    });
  }
  const subcatNameById = {}
  
  function getOptionText(sel) {
    try { return (sel && sel.options && sel.selectedIndex >= 0) ? sel.options[sel.selectedIndex].text.trim() : ''; }
    catch(e){ return ''; }
  }
  function norm(v){ return (v==null?'':String(v)).toLowerCase(); }
const payNameById = {};
  if (payEl) {
    Array.prototype.slice.call(payEl.options).forEach(function (opt) {
      if (opt.value) payNameById[String(opt.value)] = opt.text.trim();
    });
  }
;
  if (subcatEl) {
    Array.prototype.slice.call(subcatEl.options).forEach(function (opt) {
      if (opt.value) subcatNameById[String(opt.value)] = opt.text.trim();
    });
  }

  const tbody      = document.getElementById('history-body');
  const countLabel = document.getElementById('result-count');

  const pageInfoEl    = document.getElementById('history-page-info');
  const pageSizeEl    = document.getElementById('page-size');
  const pageFirstBtn  = document.getElementById('page-first');
  const pagePrevBtn   = document.getElementById('page-prev');
  const pageNextBtn   = document.getElementById('page-next');
  const pageLastBtn   = document.getElementById('page-last');
  const sortableHeaders = document.querySelectorAll('.fin-th-sort');

  const editModalEl     = document.getElementById('fin-edit-modal');
  const editIdInput     = document.getElementById('fin-edit-id');
  const editDateInput   = document.getElementById('fin-edit-date');
  const editTypeSelect  = document.getElementById('fin-edit-type');
  const editCatSelect   = document.getElementById('fin-edit-category');
  const editSubSelect   = document.getElementById('fin-edit-subcategory');
  const editAmountInput = document.getElementById('fin-edit-amount');
  const editDescInput   = document.getElementById('fin-edit-description');

  let filterSubcategoryOptions = [];
  if (subcatEl) {
    filterSubcategoryOptions = Array.prototype.slice.call(subcatEl.options);
  }
  const editSaveBtn     = document.getElementById('fin-edit-save');

  let editSubcategoryOptions = [];
  let rowsCache = {};
  let timer = null;

  let allRows = [];
  let currentPage = 1;
  let pageSize = 10;
  let sortField = 'date';
  let sortDir = 'desc';

  function buildQuery() {
    const params = new URLSearchParams();
    if (periodEl && periodEl.value)   params.append('period', periodEl.value);
    if (typeEl && typeEl.value && typeEl.value !== 'todos') params.append('type', typeEl.value);
    if (payEl && payEl.value)         params.append('payment_method_id', payEl.value);
    if (catEl && catEl.value)         params.append('category_id', catEl.value);
    /* subcategory filter is client-side */
    if (qEl && qEl.value.trim())      params.append('q', qEl.value.trim());
    return params.toString();
  }

  function renderLoading() {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Cargando movimientos...</td></tr>';
    if (countLabel) countLabel.textContent = '';
    if (pageInfoEl) pageInfoEl.textContent = '';
  }

  function renderEmpty() {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No se encontraron movimientos para los filtros seleccionados.</td></tr>';
    if (countLabel) countLabel.textContent = '0 resultados';
    if (pageInfoEl) pageInfoEl.textContent = '';
  }

  function applySortIndicators() {
    sortableHeaders.forEach(function (th) {
      th.classList.remove('is-sorted-asc', 'is-sorted-desc');
      const field = th.getAttribute('data-sort');
      if (field === sortField) {
        th.classList.add(sortDir === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
      }
    });
  }

  function renderTable() {
    if (!allRows.length) {
      renderEmpty();
      return;
    }

    let rows = allRows.slice();

    // Filtro de texto en todas las columnas (búsqueda en tiempo real)
    const term = (qEl && qEl.value) ? qEl.value.trim().toLowerCase() : '';
    if (term) {
      rows = rows.filter(function (r) {
        const fields = [
          r.date,
          r.type_label,
          r.description,
          r.category,
          r.subcategory,
          r.payment_method,
          r.amount
        ];
        return fields.some(function (val) {
          if (val === null || val === undefined) return false;
          return val.toString().toLowerCase().includes(term);
        });
      });
    }

    rows.sort(function (a, b) {
      let va, vb;
      switch (sortField) {
        case 'amount':
          va = a.amount || 0;
          vb = b.amount || 0;
          break;
        case 'type':
          va = (a.type_label || '').toString().toLowerCase();
          vb = (b.type_label || '').toString().toLowerCase();
          break;
        case 'description':
          va = (a.description || '').toString().toLowerCase();
          vb = (b.description || '').toString().toLowerCase();
          break;
        case 'category':
          va = (a.category || '').toString().toLowerCase();
          vb = (b.category || '').toString().toLowerCase();
          break;
        case 'subcategory':
          va = (a.subcategory || '').toString().toLowerCase();
          vb = (b.subcategory || '').toString().toLowerCase();
          break;
        case 'payment':
        case 'payment_method':
          va = (a.payment_method || '').toString().toLowerCase();
          vb = (b.payment_method || '').toString().toLowerCase();
          break;
        case 'date':
        default:
          va = a.date || '';
          vb = b.date || '';
          break;
      }
      if (va < vb) return sortDir === 'asc' ? -1 : 1;
      if (va > vb) return sortDir === 'asc' ? 1 : -1;
      return 0;
    });

    const total = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * pageSize;
    const end   = start + pageSize;
    const pageRows = rows.slice(start, end);

    let html = '';
    rowsCache = {};
    pageRows.forEach(function (r) {
      rowsCache[String(r.id)] = r;
      html += '<tr>' +
        '<td>' + (r.date || '') + '</td>' +
        '<td>' + (r.type_label || '') + '</td>' +
        '<td>' + (r.description || '—') + '</td>' +
        '<td>' + (r.category || '—') + '</td>' +
        '<td>' + (r.subcategory || '—') + '</td>' +
        '<td>' + (r.payment_method || '—') + '</td>' +
        '<td class="text-end">' + (r.amount_formatted || '') + '</td>' +
        '<td class="text-end">' +
          '<button type="button" class="btn btn-sm btn-outline-primary fin-btn-edit" data-id="' + r.id + '">Editar</button> ' + '<form action="' + destroyUrlBase + '/' + r.id + '" method="POST" class="d-inline fin-del-form">' + '<input type="hidden" name="_token" value="' + csrfToken + '">' + '<input type="hidden" name="_method" value="DELETE">' + '<button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>' + '</form>' + '</td>' +
      '</tr>';
    });

    tbody.innerHTML = html;

    if (countLabel) {
      countLabel.textContent = total + ' resultado' + (total === 1 ? '' : 's');
    }
    if (pageInfoEl) {
      const from = total ? (start + 1) : 0;
      const to   = Math.min(total, end);
      pageInfoEl.textContent = total
        ? ('Mostrando ' + from + '–' + to + ' de ' + total + ' (Página ' + currentPage + ' de ' + totalPages + ')')
        : 'Mostrando 0 de 0';
    }

    const editButtons = tbody.querySelectorAll('.fin-btn-edit');
    editButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const id = this.getAttribute('data-id');
        const row = rowsCache[String(id)];
        if (row) openEditModal(row);
      });
    });

    applySortIndicators();
  }
function loadData() {
    renderLoading();
    const qs = buildQuery();
    fetch(dataUrl + (qs ? ('?' + qs) : ''))
      .then(function (resp) { return resp.json(); })
      .then(function (payload) {
        const rows = payload && (payload.data || payload.rows || payload) ? (payload.data || payload.rows || payload) : [];
        allRows = Array.isArray(rows) ? rows : [];
        currentPage = 1;
        if (!allRows.length) {
          renderEmpty();
          return;
        }
        renderTable();
      })
      .catch(function () {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Error al cargar los movimientos.</td></tr>';
        if (countLabel) countLabel.textContent = '';
        if (pageInfoEl) pageInfoEl.textContent = '';
      });
  }

  function goToPage(page) {
    if (!allRows.length) return;
    const totalPages = Math.max(1, Math.ceil(allRows.length / pageSize));
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    currentPage = page;
    renderTable();
  }

  if (sortableHeaders && sortableHeaders.length) {
    sortableHeaders.forEach(function (th) {
      th.addEventListener('click', function () {
        const field = th.getAttribute('data-sort');
        if (!field) return;
        if (sortField === field) {
          sortDir = (sortDir === 'asc') ? 'desc' : 'asc';
        } else {
          sortField = field;
          sortDir = (field === 'amount' || field === 'date') ? 'desc' : 'asc';
        }
        renderTable();
      });
    });
  }

  if (pageFirstBtn) pageFirstBtn.addEventListener('click', function () { goToPage(1); });
  if (pagePrevBtn)  pagePrevBtn.addEventListener('click', function () { goToPage(currentPage - 1); });
  if (pageNextBtn)  pageNextBtn.addEventListener('click', function () { goToPage(currentPage + 1); });
  if (pageLastBtn)  pageLastBtn.addEventListener('click', function () {
    if (!allRows.length) return;
    const totalPages = Math.max(1, Math.ceil(allRows.length / pageSize));
    goToPage(totalPages);
  });

  if (pageSizeEl) {
    pageSizeEl.addEventListener('change', function () {
      const val = parseInt(pageSizeEl.value, 10);
      pageSize = isNaN(val) || val <= 0 ? 10 : val;
      currentPage = 1;
      renderTable();
    });
  }

  function filterEditSubcategories() {
    if (!editSubSelect || !editSubcategoryOptions.length) return;
    const catId = editCatSelect ? editCatSelect.value : '';
    editSubSelect.value = '';
    editSubcategoryOptions.forEach(function (opt) {
      if (!opt.value) {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      const ok = !catId || opt.getAttribute('data-category') === catId;
      opt.hidden = !ok;
      opt.disabled = !ok;
    });
  }

  function openEditModal(row) {
    if (!editModalEl) return;

    if (!editSubcategoryOptions.length) {
      editSubcategoryOptions = Array.from(document.querySelectorAll('#fin-edit-subcategory option'));
      if (editCatSelect) {
        editCatSelect.addEventListener('change', filterEditSubcategories);
      }
    }

    editIdInput.value     = row.id || '';
    editDateInput.value   = row.date || '';
    editTypeSelect.value  = (row.type === 'egreso' || row.type === 'gasto') ? 'egreso' : 'ingreso';
    editAmountInput.value = row.amount || 0;
    editDescInput.value   = row.description || '';

    if (editCatSelect) {
      editCatSelect.value = row.category_id ? String(row.category_id) : '';
    }
    filterEditSubcategories();
    if (editSubSelect) {
      editSubSelect.value = row.subcategory_id ? String(row.subcategory_id) : '';
    }

    editModalEl.classList.add('is-open');
  }

  function closeEditModal() {
    if (editModalEl) editModalEl.classList.remove('is-open');
  }

  const closeBtn  = document.getElementById('fin-edit-close-btn');
  const cancelBtn = document.getElementById('fin-edit-cancel');
  if (closeBtn)  closeBtn.addEventListener('click', closeEditModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeEditModal);

  if (editSaveBtn) {
    editSaveBtn.addEventListener('click', function () {
      if (!editIdInput || !editDateInput || !editTypeSelect || !editCatSelect || !editAmountInput) return;

      const formData = new FormData();
      formData.append('_token', csrfToken);
      formData.append('id', editIdInput.value);
      formData.append('date', editDateInput.value);
      formData.append('type', editTypeSelect.value || 'ingreso');
      formData.append('amount', editAmountInput.value || 0);
      formData.append('category_id', editCatSelect.value || '');
      formData.append('subcategory_id', editSubSelect && editSubSelect.value ? editSubSelect.value : '');
      formData.append('description', editDescInput ? (editDescInput.value || '') : '');

      fetch(editUrlBase, {
        method: 'POST',
        body: formData
      })
        .then(function (resp) {
          if (!resp.ok) throw new Error('HTTP ' + resp.status);
          return resp.text();
        })
        .then(function () {
          closeEditModal();
          loadData();
        })
        .catch(function (err) {
          console.error('Error guardando movimiento', err);
          alert('No se pudo guardar el movimiento. Revisa los datos.');
        });
    });
  }

  
  function filterTopSubcategories() {
    if (!subcatEl || !filterSubcategoryOptions.length) return;
    const catId = catEl ? catEl.value : '';
    if (subcatEl && subcatEl.value) {
      const current = subcatEl.value;
      let stillThere = false;
      filterSubcategoryOptions.forEach(function(opt){ if(opt.value===current && !opt.hidden) stillThere=true; });
      if(!stillThere) subcatEl.value='';
    }
    filterSubcategoryOptions.forEach(function (opt) {
      if (!opt.value) {
        opt.hidden = false;
        opt.disabled = false;
        return;
      }
      const optCat = opt.getAttribute('data-category');
      const match = !catId || optCat === catId;
      opt.hidden = !match;
      opt.disabled = !match;
    });
  }
function scheduleLoad() {
    if (timer) window.clearTimeout(timer);
    timer = window.setTimeout(loadData, 250);
  }

  if (periodEl) periodEl.addEventListener('change', scheduleLoad);
  if (typeEl)   typeEl.addEventListener('change', scheduleLoad);
  if (payEl)    payEl.addEventListener('change', scheduleLoad);
  if (catEl)    catEl.addEventListener('change', function () {
    filterTopSubcategories();
    scheduleLoad();
  });
  if (subcatEl) subcatEl.addEventListener('change', function(){ currentPage = 1; renderTable(); });
  if (qEl)      qEl.addEventListener('input', function () {
    currentPage = 1;
    renderTable();
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (periodEl) periodEl.value = currentPeriod;
      if (typeEl)   typeEl.value   = 'todos';
      if (payEl)    payEl.value    = '';
      if (catEl)    catEl.value    = '';
      if (subcatEl) if (subcatEl && subcatEl.value) {
      const current = subcatEl.value;
      let stillThere = false;
      filterSubcategoryOptions.forEach(function(opt){ if(opt.value===current && !opt.hidden) stillThere=true; });
      if(!stillThere) subcatEl.value='';
    }
      if (qEl)      qEl.value      = '';
      filterTopSubcategories();
      scheduleLoad();
    });
  }

  loadData();
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('submit', function(e) {
    var form = e.target && e.target.closest ? e.target.closest('.fin-del-form') : null;
    if (!form) return;
    if (!confirm('¿Eliminar este movimiento?')) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  }, true);
});
</script>
