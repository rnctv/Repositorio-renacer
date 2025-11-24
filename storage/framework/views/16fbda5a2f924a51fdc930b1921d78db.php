<?php $__env->startSection('title','Agenda'); ?>

<?php
  // === Clave de Google Maps ===
  // Preferimos services.google.maps_browser_key; si no existe, cae a env('GOOGLE_MAPS_BROWSER_KEY','')
  $gmapsKey = $gmapsKey ?? (config('services.google.maps_browser_key') ?? env('GOOGLE_MAPS_BROWSER_KEY',''));

  // === Día seleccionado (fallback si el Controller no lo envía) ===
  $selected = $selected ?? now()->format('Y-m-d');

  // === Defaults de mapa (Longaví por defecto) ===
  // 1) Si tienes en config/app.php:
  //    'maps_default_lat' => env('GMAPS_DEFAULT_LAT', -36.012889),
  //    'maps_default_lng' => env('GMAPS_DEFAULT_LNG', -71.653852),
  //    usarán esos valores. Si no, caerán directo al .env.
  $defaultLat = config('app.maps_default_lat', env('GMAPS_DEFAULT_LAT', -36.012889));
  $defaultLng = config('app.maps_default_lng', env('GMAPS_DEFAULT_LNG', -71.653852));
?>

<?php $__env->startPush('head'); ?>
<style>
  /* ====== Estilos específicos de Kanban ====== */
  .kanban-header{display:flex;gap:.5rem;align-items:center;margin-bottom:.75rem}
  .kanban-header .btn{padding:.45rem .8rem;border:1px solid var(--border);border-radius:10px;background:#fff}
  .days{display:flex;gap:.35rem;overflow:auto;padding:.25rem .15rem}
  .day-pill{min-width:64px;border:1px solid var(--border);background:#fff;border-radius:12px;padding:.35rem .5rem;display:flex;flex-direction:column;align-items:center;gap:.2rem;cursor:pointer;position:relative}
  .day-pill.active{outline:2px solid var(--accent);border-color:var(--accent)}
  .pill-badge{display:none;min-width:18px;height:18px;padding:0 6px;border-radius:9px;align-items:center;justify-content:center;font-size:.72rem;color:#fff}
  .badge-pend{background:#ef4444}
  .badge-done{background:#16a34a}
  .has-pending .badge-pend{display:flex}
  .has-done .badge-done{display:flex}

  .columns{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem}
  @media (max-width:1000px){ .columns{grid-template-columns:1fr 1fr} }
  @media (max-width:720px){ .columns{grid-template-columns:1fr} }

  .column{background:var(--card);border:1px solid var(--border);border-radius:14px}
  .column>h3{margin:0;padding:.8rem 1rem;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
  .dropzone{min-height:120px;padding:.6rem}
  .dropzone.over{outline:2px dashed var(--accent)}

  .card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:.8rem;margin:.5rem;cursor:grab}
  .card.locked{cursor:default;opacity:.7}
  .card h4{margin:.1rem 0 .3rem 0}
  .muted{color:var(--muted);font-size:.9rem}
  .card .btn{padding:.35rem .6rem;border-radius:10px}

  /* ====== Modales ====== */
  .mask{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;padding:12px;z-index:5000}
  .mask.show{display:flex}
  .dialog{width:min(880px,96vw);background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden}
  .dlg-hd{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;border-bottom:1px solid var(--border)}
  .dlg-ttl{font-weight:700}
  .dlg-x{appearance:none;border:0;background:transparent;font-size:1.35rem;line-height:1;cursor:pointer}
  .dlg-bd{padding:1rem}
  .g{display:grid;grid-template-columns:1fr 1fr;gap:.6rem}
  .g-1{grid-column:1/-1}
  .lbl{display:block;font-size:.85rem;color:var(--muted);margin-bottom:.25rem}
  .inp,.txt,select{width:100%;padding:.55rem .7rem;border:1px solid var(--border);border-radius:10px;background:#fff}
  .txt{min-height:130px;resize:vertical}
  .actions .btn{padding:.55rem .9rem;border:1px solid var(--border);border-radius:10px;background:#fff}
  .actions .primary{background:var(--accent);color:#fff;border-color:var(--accent)}

  /* Autocomplete */
  .sug{position:relative}
  .sug-list{position:absolute;inset:auto 0 0 0;transform:translateY(100%);background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.12);display:none;z-index:10}
  .sug.show .sug-list{display:block}
  .sug-item{padding:.5rem .7rem;cursor:pointer}
  .sug-item:hover{background:#f2f6ff}

  /* Ver detalle */
  #v_map{display:inline-flex;align-items:center;gap:.4rem}

  /* Mapa */
  #mapCanvas{width:min(840px,96vw);height:min(70vh,520px);border:1px solid var(--border);border-radius:12px}
</style>


<link rel="stylesheet" href="<?php echo e(asset('css/kanban-badges.css')); ?>?v=<?php echo e($assetManifest['css/kanban-badges.css'] ?? ''); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>

<div class="kanban-header">
  <button class="btn" id="prevDays">‹</button>
  <button class="btn" id="btnToday">Hoy</button>
  <button class="btn" id="nextDays">›</button>
  <div id="daysScroll" class="days" style="flex:1"></div>
  <button class="btn" id="btnPend">Pendientes</button>
  <button class="btn primary" id="openNew">Nueva</button>
</div>

<div id="kanbanBoard"
     data-endpoint-list="<?php echo e(route('agenda.list')); ?>"
     data-endpoint-move="<?php echo e(url('/agenda')); ?>"
     data-endpoint-counts="<?php echo e(route('agenda.counts')); ?>"
     data-endpoint-pendientes="<?php echo e(route('agenda.pendientes')); ?>"
     data-gmaps-key="<?php echo e($gmapsKey); ?>"
     data-selected="<?php echo e($selected); ?>"
     
     data-default-lat="<?php echo e($defaultLat); ?>"
     data-default-lng="<?php echo e($defaultLng); ?>">
  <div class="columns">
    <section class="column" data-col="pendiente">
      <h3>Pendiente <span class="muted" data-count>0</span></h3>
      <div class="dropzone" data-status="pendiente"></div>
    </section>
    <section class="column" data-col="en_curso">
      <h3>En curso <span class="muted" data-count>0</span></h3>
      <div class="dropzone" data-status="en_curso"></div>
    </section>
    <section class="column" data-col="completado">
      <h3>Completado <span class="muted" data-count>0</span></h3>
      <div class="dropzone" data-status="completado"></div>
    </section>
  </div>
</div>


<script>
  (function () {
    const b = document.getElementById('kanbanBoard');
    const lat = parseFloat(b?.dataset.defaultLat || 'NaN');
    const lng = parseFloat(b?.dataset.defaultLng || 'NaN');
    window.KANBAN_DEFAULTS = {
      lat: !Number.isNaN(lat) ? lat : null,
      lng: !Number.isNaN(lng) ? lng : null
    };
    console.log('[KANBAN] DEFAULTS (Blade):', window.KANBAN_DEFAULTS);
  })();
</script>


<div class="mask" id="taskMask" aria-hidden="true">
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="newTitle">
    <div class="dlg-hd">
      <div class="dlg-ttl" id="newTitle">Nueva actividad</div>
      <button class="dlg-x" type="button" data-close>✕</button>
    </div>
    <div class="dlg-bd">
      <form id="taskForm">
        <div class="g">
          <div>
            <label class="lbl">Fecha</label>
            <input class="inp" id="fecha" name="fecha" type="date" required />
          </div>
          <div>
            <label class="lbl">Tipo</label>
            <select class="inp" name="tipo" id="tipo">
              <option value="INSTALACION">INSTALACION</option>
              <option value="ASISTENCIA">ASISTENCIA</option>
              <option value="OTRO">OTRO</option>
            </select>
            <div id="tipoOtroWrap" class="g-1" style="display:none;margin-top:.5rem">
              <label class="lbl">Motivo (si eliges OTRO)</label>
              <input class="inp" id="tipoOtro" name="tipo_otro" placeholder="Escribe el motivo..." />
            </div>
          </div>
          <div>
            <label class="lbl">Plan</label>
            <select class="inp" name="plan" id="plan">
              <optgroup label="DUO">
                <option value="DUO 200">DUO 200</option>
                <option value="DUO 400">DUO 400</option>
                <option value="DUO 600">DUO 600</option>
                <option value="DUO 800">DUO 800</option>
              </optgroup>
              <optgroup label="INTERNET">
                <option value="INTERNET 200">INTERNET 200</option>
                <option value="INTERNET 400">INTERNET 400</option>
                <option value="INTERNET 600">INTERNET 600</option>
                <option value="INTERNET 800">INTERNET 800</option>
              </optgroup>
              <optgroup label="TELEVISION">
                <option value="TELEVISION">TELEVISION</option>
              </optgroup>
            </select>
          </div>

          <div class="g-1 sug">
            <label class="lbl">Buscar cliente</label>
            <input class="inp" id="buscaCliente" placeholder="Nombre, ID externo, etc.">
            <div class="sug-list" id="sugList"></div>
          </div>

          <div class="g-1">
            <small id="coordHint" style="display:block;font-size:.8rem;color:#666;margin-top:-.3rem;"></small>
          </div>

          <div>
            <label class="lbl">Nombre</label>
            <input class="inp" id="cliNombre" name="cli_nombre" />
          </div>
          <div>
            <label class="lbl">Teléfono</label>
            <input class="inp" id="cliTel" name="cli_telefono" />
          </div>
          <div class="g-1">
            <label class="lbl">Dirección</label>
            <input class="inp" id="cliDir" name="cli_direccion" />
          </div>

          <div class="g-1">
            <label class="lbl">Descripción</label>
            <textarea class="txt" name="descripcion" placeholder="Notas o detalles…"></textarea>
          </div>

          
          <input type="hidden" id="cliente_id" name="cliente_id">
          <input type="hidden" id="dbLat">
          <input type="hidden" id="dbLng">
          <input type="hidden" id="cliLat" name="lat">
          <input type="hidden" id="cliLng" name="lng">
          <input type="hidden" id="cliPPP" name="user_ppp_hotspot">
          <input type="hidden" id="cliPrecinto" name="precinto">
        </div>

        <div class="actions">
          <button class="btn" type="button" data-close>Cancelar</button>
          <button class="btn primary" type="submit">Guardar</button>
          <button class="btn" type="button" id="openMap">Mapa</button>
          <button class="btn" type="button" id="btnPreviewCoords">Ver ubicación</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Detalle -->
<div class="mask" id="viewMask" aria-hidden="true">
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="viewTitle" aria-describedby="viewDesc" tabindex="-1">
    <div class="dlg-hd">
      <div class="dlg-ttl" id="viewTitle">Detalle de la actividad</div>
      <button class="dlg-x" type="button" data-close-view aria-label="Cerrar">✕</button>
    </div>

    <div class="dlg-bd">
      <p id="viewDesc" class="sr-only" aria-hidden="true">Ventana con la información completa de la actividad seleccionada.</p>

      <div class="g">
        <div><label class="lbl">Nombre</label><input class="inp" id="v_cliente" readonly></div>
        <div><label class="lbl">Teléfono</label><input class="inp" id="v_telefono" readonly></div>
        <div class="g-1"><label class="lbl">Dirección</label><input class="inp" id="v_direccion" readonly></div>
        <div><label class="lbl">Tipo</label><input class="inp" id="v_tipo" readonly></div>
        <div><label class="lbl">Plan</label><input class="inp" id="v_plan" readonly></div>
        <div><label class="lbl">Creada</label><input class="inp" id="v_fecha" readonly></div>
        <div><label class="lbl">Días transcurridos</label><input class="inp" id="v_estado" readonly></div>
        <div class="g-1"><label class="lbl">Descripción</label><textarea class="txt" id="v_desc" readonly></textarea></div>
      </div>

      <p id="v_map_wrap" style="margin-top:.5rem;display:none">
        <a id="v_map" href="#" target="_blank" rel="noopener">🗺️ Ver en Google Maps</a>
      </p>

      <div class="actions">
        <button class="btn" type="button" data-close-view>Cerrar</button>
      </div>
    </div>
  </div>
</div>


<div class="mask" id="pendMask" aria-hidden="true">
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="pendTitle">
    <div class="dlg-hd">
      <div class="dlg-ttl" id="pendTitle">Actividades pendientes</div>
      <button class="dlg-x" type="button" data-close-pend>✕</button>
    </div>
    <div class="dlg-bd">
      <div id="pendList"></div>
      <div class="actions" style="margin-top:.75rem">
        <button class="btn" type="button" data-close-pend>Cerrar</button>
      </div>
    </div>
  </div>
</div>


<div class="mask" id="mapMask" aria-hidden="true">
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="mapTitle">
    <div class="dlg-hd">
      <div class="dlg-ttl" id="mapTitle">Ubicación del cliente</div>
      <button class="dlg-x" type="button" data-close-map>✕</button>
    </div>
    <div class="dlg-bd">
      <div id="mapCanvas"></div>
      <p class="muted" style="margin:.6rem 0 0">Lat: <span id="latVal">—</span> &nbsp; Lng: <span id="lngVal">—</span></p>
      <div class="actions" style="margin-top:.75rem">
        <button class="btn" type="button" data-close-map>Cancelar</button>
        <button class="btn primary" type="button" id="save-coords-btn">Guardar ubicación</button>
      </div>
    </div>
  </div>
</div>


<div class="mask" id="coordsMask" aria-hidden="true">
  <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="coordsTitle">
    <div class="dlg-hd">
      <div class="dlg-ttl" id="coordsTitle">Ubicación del cliente</div>
      <button class="dlg-x" type="button" data-close>✕</button>
    </div>
    <div class="dlg-bd" style="height: 420px;">
      <iframe id="coordsFrame"
              src=""
              style="border:0;width:100%;height:100%;"
              loading="lazy"></iframe>
    </div>
  </div>
</div>


<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
  <script>
    window.GMAPS_KEY = <?php echo json_encode($gmapsKey, 15, 512) ?>;
    window.DEFAULT_RECIPIENTS = <?php echo json_encode($recipients ?? [], 15, 512) ?>;
  </script>

  <script src="<?php echo e(asset('js/kanban.js')); ?>?v=<?php echo e($assetManifest['js/kanban.js'] ?? ''); ?>"></script>
  <script src="<?php echo e(asset('js/kanban.openSend.polyfill.js')); ?>?v=<?php echo e($assetManifest['js/kanban.openSend.polyfill.js'] ?? ''); ?>"></script>   
  <script src="<?php echo e(asset('js/kanban.override.js')); ?>?v=<?php echo e($assetManifest['js/kanban.override.js'] ?? ''); ?>"></script>
  <script src="<?php echo e(asset('js/kanban-badges.js')); ?>?v=<?php echo e($assetManifest['js/kanban-badges.js'] ?? ''); ?>" defer></script>
<?php $__env->stopPush(); ?>




<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/laravel/resources/views/agenda/kanban.blade.php ENDPATH**/ ?>