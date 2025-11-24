@extends('layouts.app')
@section('title','Agenda')

@push('head')
  <!-- FullCalendar (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

  <style>
    .toolbar-agenda{ display:flex; gap:.5rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap }
    .btn.primary{ background:var(--accent); color:#fff; border-color:var(--accent) }
    /* Modal simple para nueva actividad (reutilizamos estilo del proyecto) */
    .a-mask{position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center; padding:12px; z-index:6000}
    .a-mask.show{display:flex}
    .a-modal{width:min(560px,94vw); background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 20px 50px rgba(0,0,0,.25); overflow:hidden}
    .a-hd{display:flex; align-items:center; justify-content:space-between; padding:.9rem 1rem; border-bottom:1px solid var(--border)}
    .a-bd{padding:1rem}
    .a-row{display:flex; gap:.5rem; margin-bottom:.7rem; flex-wrap:wrap}
    .a-row .col{flex:1}
    .a-row label{display:block; font-size:.85rem; color:var(--muted); margin-bottom:.25rem}
    .a-input,.a-select,.a-text{width:100%; padding:.6rem .7rem; border:1px solid var(--border); border-radius:10px; background:#fff}
    .a-text{min-height:84px}
    .a-x{background:transparent; border:0; font-size:1.3rem; cursor:pointer}
    /* Autocomplete clientes */
    .ac-wrap{position:relative}
    .ac-list{position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 12px 30px rgba(0,0,0,.08); z-index:7000; max-height:220px; overflow:auto; display:none}
    .ac-list.show{display:block}
    .ac-item{padding:.55rem .7rem; cursor:pointer}
    .ac-item:hover{background:#f3f4f6}
  </style>
@endpush

@section('content')
<div class="card">
  <div class="card-body">
    <div class="toolbar-agenda">
      <button class="btn primary" id="btn-nuevo">+ Nueva actividad</button>
    </div>
    <div id="calendar"></div>
  </div>
</div>

{{-- Modal Nueva Actividad --}}
<div class="a-mask" id="modal-nuevo" aria-hidden="true">
  <div class="a-modal" role="dialog" aria-modal="true">
    <div class="a-hd">
      <strong>Nueva actividad</strong>
      <button class="a-x" data-close>✕</button>
    </div>
    <div class="a-bd">
      <form id="form-actividad">
        @csrf
        <div class="a-row">
          <div class="col">
            <label>Tipo</label>
            <select class="a-select" name="tipo" required>
              @foreach($tipos as $t)
                <option value="{{ $t }}">{{ $t }}</option>
              @endforeach
            </select>
          </div>
          <div class="col">
            <label>Fecha</label>
            <input class="a-input" type="date" name="fecha" required>
          </div>
        </div>

        <div class="a-row">
          <div class="col">
            <label>Hora inicio</label>
            <input class="a-input" type="time" name="hora_inicio" required>
          </div>
          <div class="col">
            <label>Hora fin (opcional)</label>
            <input class="a-input" type="time" name="hora_fin">
          </div>
        </div>

        <div class="a-row">
          <div class="col ac-wrap">
            <label>Cliente (opcional)</label>
            <input class="a-input" type="text" id="ac-buscar" placeholder="Escribe nombre, ID externo o móvil">
            <input type="hidden" name="cliente_id" id="ac-id">
            <div class="ac-list" id="ac-list"></div>
          </div>
        </div>

        <div class="a-row">
          <div class="col">
            <label>Título/Detalle (opcional)</label>
            <input class="a-input" type="text" name="titulo" maxlength="180" placeholder="Ej: Instalar router / Revisar señal...">
          </div>
        </div>

        <div class="a-row">
          <div class="col">
            <label>Notas (opcional)</label>
            <textarea class="a-text" name="notas" placeholder="Detalles adicionales..."></textarea>
          </div>
        </div>

        <div class="a-row" style="justify-content:flex-end">
          <button class="btn" type="button" data-close>Cancelar</button>
          <button class="btn primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="{{ asset('js/agenda.js') }}?v={{ $assetManifest['js/agenda.js'] ?? '' }}"></script>
@endsection
