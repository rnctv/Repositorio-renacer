@extends('layouts.app')
@section('title','Técnico — Hoy')

@push('head')
<style>
  /* --- Ocultar menús/headers globales sólo en esta vista --- */
  header, nav, .navbar, .topbar, .sidebar, .app-sidebar, .main-menu, .menu, .layout-sidebar, .layout-topbar {
    display: none !important;
  }
  .layout-content, main, .container, .content, .app-content { margin: 0 !important; padding-top: 0 !important; }

  /* --- Estilos de la vista técnico --- */
  .tec-wrap{ display:grid; grid-template-columns:1fr; gap:1rem }
  .tec-col{ background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden }
  .tec-hd{ padding:.7rem .9rem; border-bottom:1px solid var(--border); font-weight:600; display:flex; justify-content:space-between; align-items:center }
  .tec-bd{ padding:.7rem .9rem }
  .tec-item{ padding:.55rem .6rem; border:1px solid var(--border); border-radius:10px; margin-bottom:.5rem; cursor:pointer }
  .tec-item h4{ margin:0 0 .25rem 0; font-size:.95rem }
  .muted{ color:#6b7280; font-size:.85rem }
  .count{ color:#6b7280; font-weight:500; font-size:.85rem }
  .map-link{ display:inline-block; margin-top:.25rem; font-size:.82rem; text-decoration:none }
  .map-link:hover{ text-decoration:underline }

  /* Modal */
  .mask{ position:fixed; inset:0; background:rgba(15,23,42,.45); display:none; align-items:center; justify-content:center; padding:12px; z-index:6000 }
  .mask.show{ display:flex }
  .dialog{ width:min(720px,96vw); background:#fff; border:1px solid var(--border); border-radius:14px; box-shadow:0 20px 50px rgba(0,0,0,.25); overflow:hidden }
  .dlg-hd{ display:flex; justify-content:space-between; align-items:center; padding:.8rem 1rem; border-bottom:1px solid var(--border) }
  .dlg-ttl{ font-weight:700 }
  .dlg-x{ background:none; border:0; font-size:1.35rem; cursor:pointer }
  .dlg-bd{ padding:1rem }
  .g{ display:grid; grid-template-columns:1fr 1fr; gap:.75rem }
  .g-1{ grid-column:1/-1 }
  .lbl{ display:block; font-size:.85rem; color:#6b7280; margin-bottom:.25rem }
  .inp, .txt{ width:100%; padding:.55rem .7rem; border:1px solid var(--border); border-radius:10px; background:#fff }
  .txt{ min-height:90px; resize:vertical }
</style>
@endpush

@section('content')
<div id="tecBoard"
     data-selected="{{ $selected }}"
     data-endpoint-list="{{ route('agenda.events') }}"
     data-pusher-key="{{ $pusherKey ?? '' }}"
     data-pusher-cluster="{{ $pusherCluster ?? 'mt1' }}">
  <div class="card">
    <div class="card-body">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.75rem;">
        <div>
          <h3 style="margin:0;">Agenda de hoy</h3>
          <div class="muted">Día: <span id="tecDay">{{ $selected }}</span></div>
        </div>
        <button class="btn" id="btnRefTec">Refrescar</button>
      </div>

      <div class="tec-wrap">
        {{-- En curso ARRIBA --}}
        <section class="tec-col" data-col="en_curso">
          <div class="tec-hd">En curso <span class="count" id="cCurso">0</span></div>
          <div class="tec-bd" id="listCurso"></div>
        </section>

        <section class="tec-col" data-col="pendiente">
          <div class="tec-hd">Pendiente <span class="count" id="cPend">0</span></div>
          <div class="tec-bd" id="listPend"></div>
        </section>
      </div>
    </div>
  </div>
</div>

{{-- Modal Detalle Técnico --}}
<div class="mask" id="tecViewMask" aria-hidden="true">
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="tecViewTitle">
    <div class="dlg-hd">
      <div class="dlg-ttl" id="tecViewTitle">Detalle</div>
      <button class="dlg-x" type="button" data-close>✕</button>
    </div>
    <div class="dlg-bd">
      <div class="g">
        <div class="g-1">
          <label class="lbl">Título</label>
          <input class="inp" id="tv_titulo" readonly>
        </div>
        <div>
          <label class="lbl">Estado</label>
          <input class="inp" id="tv_estado" readonly>
        </div>
        <div>
          <label class="lbl">Tipo</label>
          <input class="inp" id="tv_tipo" readonly>
        </div>
        <div>
          <label class="lbl">Plan</label>
          <input class="inp" id="tv_plan" readonly>
        </div>
        <div>
          <label class="lbl">Fecha</label>
          <input class="inp" id="tv_fecha" readonly>
        </div>
        <div>
          <label class="lbl">Cliente</label>
          <input class="inp" id="tv_cliente" readonly>
        </div>
        <div class="g-1">
          <label class="lbl">Dirección</label>
          <input class="inp" id="tv_direccion" readonly>
        </div>
        <div>
          <label class="lbl">Teléfono</label>
          <input class="inp" id="tv_telefono" readonly>
        </div>
        <div class="g-1">
          <label class="lbl">Descripción</label>
          <textarea class="txt" id="tv_desc" readonly></textarea>
        </div>
        <div class="g-1" id="tv_map_wrap" style="display:none;">
          <a id="tv_map" class="map-link" href="#" target="_blank" rel="noopener">🗺️ Ver en Google Maps</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="{{ asset('js/tecnico.js') }}?v={{ $assetManifest['js/tecnico.js'] ?? '' }}"></script>
@endsection
