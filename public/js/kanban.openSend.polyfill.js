/* =========================================================================
   kanban.openSend.polyfill.js  (v3)
   - Define window.openSendModal si no existe (múltiples destinatarios)
   - Muestra TODOS los DEFAULT_RECIPIENTS excepto "Cliente" y "Soporte"
     - Daniel/Juan: checked por defecto (puedes desmarcarlos)
     - Harry/Cesar: visibles NO marcados
   - Oculta "Cliente" y "Otro número"
   - Envía POST a /agenda/tareas/{id}/notificar con { recipients[], message }.
   ======================================================================= */
(function () {
  'use strict';

  if (typeof window.openSendModal === 'function') {
    // Ya existe en tu app; no lo pisamos.
    return;
  }

  var DEFAULTS = Array.isArray(window.DEFAULT_RECIPIENTS) ? window.DEFAULT_RECIPIENTS : [];
  function U(s){ return String(s || '').toUpperCase(); }

  // --- Lista visible: TODOS salvo "Cliente" y "Soporte" (por nombre) ---
  function visibleRecipients() {
    var out = [];
    for (var i = 0; i < DEFAULTS.length; i++) {
      var r = DEFAULTS[i] || {};
      var name = String(r.name || '');
      // Filtramos "Cliente" y cualquier variante de "Soporte"
      if (/^cliente$/i.test(name)) continue;
      if (/soporte/i.test(name)) continue;
      if (!r.number) continue;
      out.push({
        name: name,
        number: String(r.number),
        selected: !!r.selected  // Daniel/Juan vendrán true y saldrán pre-marcados
      });
    }
    return out;
  }

  function buildRecipientsList() {
    var list = visibleRecipients();
    if (!list.length) {
      return '<div class="muted">No hay destinatarios configurados.</div>';
    }
    var html = '';
    for (var i = 0; i < list.length; i++) {
      var r = list[i];
      var safeName = String(r.name || '').replace(/"/g,'&quot;');
      var safeNum  = String(r.number || '').replace(/"/g,'&quot;');
      var checked  = r.selected ? 'checked' : '';
      html += ''+
        '<label class="chk" style="display:block;margin:.25rem 0">'+
        '  <input type="checkbox" class="rec-item" data-name="'+safeName+'" data-number="'+safeNum+'" '+checked+'>'+
        '  <span>'+safeName+' — '+safeNum+'</span>'+
        '</label>';
    }
    // Sin Cliente, sin Soporte, sin "Otro número".
    return html;
  }

  async function postNotifyWithTimeout(url, payload, msTimeout){
    if (!msTimeout) msTimeout = 12000;
    var ctrl = new AbortController();
    var t = setTimeout(function(){ try{ ctrl.abort('timeout'); }catch(e){} }, msTimeout);
    try{
      var res = await fetch(url, {
        method:'POST',
        headers:{ 'Content-Type':'application/json',
                  'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '') },
        body: JSON.stringify(payload),
        signal: ctrl.signal
      });
      if (!res.ok){
        var msg = 'HTTP ' + res.status;
        try{ var j = await res.json(); if (j && (j.error || j.message)) msg = j.error || j.message; }catch(e){}
        var err = new Error(msg); err.status = res.status; throw err;
      }
      try { return await res.json(); } catch(e) { return { ok:true }; }
    } finally { clearTimeout(t); }
  }

  // === Modal principal (UI compacta) ===
  window.openSendModal = function(task){
    // task: { id, tipo, nombre, telefono, direccion, plan, descripcion, coords }
    var mask = document.getElementById('sendMaskMulti');
    if (!mask){
      mask = document.createElement('div');
      mask.id = 'sendMaskMulti';
      mask.className = 'mask show';
      mask.innerHTML =
        '<div class="dialog" role="dialog" aria-modal="true" aria-labelledby="mSendTitle">'+
        '  <div class="dlg-hd">'+
        '    <div class="dlg-ttl" id="mSendTitle">Enviar a técnico</div>'+
        '    <button class="dlg-x" type="button" data-close>✕</button>'+
        '  </div>'+
        '  <div class="dlg-bd">'+
        '    <form id="mSendForm">'+
        '      <div class="g">'+
        '        <div class="g-1">'+
        '          <label class="lbl">Destinatarios</label>'+
        '          <div id="rec-list" class="g" style="gap:.25rem">'+ buildRecipientsList() +'</div>'+
        '        </div>'+
        '        <div class="g-1">'+
        '          <label class="lbl">Mensaje</label>'+
        '          <textarea class="txt" id="mSendMsg" required></textarea>'+
        '        </div>'+
        '      </div>'+
        '      <div class="actions" style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.75rem">'+
        '        <button class="btn" type="button" data-close>Cancelar</button>'+
        '        <button class="btn primary" type="submit" id="btnSend">Enviar</button>'+
        '      </div>'+
        '    </form>'+
        '  </div>'+
        '</div>';
      document.body.appendChild(mask);

      mask.addEventListener('click', function(e){ if (e.target.matches('[data-close],#sendMaskMulti')) mask.remove(); });

      var form = mask.querySelector('#mSendForm');
      if (form) {
        form.addEventListener('submit', async function(e){
          e.preventDefault();
          var btn = mask.querySelector('#btnSend'); var txt = btn ? btn.textContent : '';
          if (btn){ btn.disabled = true; btn.textContent = 'Enviando…'; }
          var shouldClose = false;
          try{
            // Tomamos los que el usuario dejó marcados
            var recipients = [];
            var items = mask.querySelectorAll('.rec-item');
            for (var i=0;i<items.length;i++){
              var el = items[i];
              if (el.checked === true) {
                recipients.push({
                  name: String(el.dataset.name||'').trim(),
                  number: String(el.dataset.number||'').trim(),
                  selected: true
                });
              }
            }
            if (!recipients.length) { alert('Selecciona al menos un destinatario'); return; }

            var msgBox = document.getElementById('mSendMsg');
            var message = msgBox ? String(msgBox.value || '').trim() : '';
            if (!message) { alert('Escribe un mensaje'); return; }

            var url = '/agenda/tareas/' + encodeURIComponent(task && task.id ? task.id : '') + '/notificar';
            var resp = await postNotifyWithTimeout(url, { recipients:recipients, message:message }, 12000);
            if (resp && resp.ok) { alert('Notificaciones encoladas correctamente.'); shouldClose = true; }
            else { alert((resp && (resp.error||resp.message)) || 'No se pudo enviar. Intenta nuevamente.'); }
          } catch(err){
            var status = err && err.status ? '('+err.status+') ' : '';
            var msg = (err && err.name === 'AbortError') ? 'Tiempo de espera agotado. Verifica conexión o intenta de nuevo.' : ((err && err.message) || 'Error desconocido al enviar.');
            alert(status + msg);
          } finally {
            if (btn){ btn.disabled = false; btn.textContent = txt; }
            if (shouldClose) { try { mask.remove(); } catch(e){} }
          }
        });
      }
    }

    // Mensaje por defecto (como antes)
    var msgBox = document.getElementById('mSendMsg');
    if (msgBox) {
      var lines = [
        '--- ACTIVIDAD ASIGNADA ---','',
        (task && task.nombre) ? ('Cliente: ' + U(task.nombre)) : '',
        (task && task.telefono) ? ('Teléfono: ' + task.telefono) : '',
        (task && task.direccion) ? ('Dirección: ' + U(task.direccion)) : '',
        (task && task.tipo) ? ('Actividad: ' + U(task.tipo)) : '',
        (task && task.plan) ? ('Servicio: ' + U(task.plan)) : '',
        (task && task.ppp) ? ('Usuario PPP/Hotspot: ' + U(task.ppp)) : '',
        (task && task.precinto) ? ('Precinto: ' + task.precinto) : '',
        (task && task.descripcion) ? ('Descripción: ' + U(task.descripcion)) : '',
        (task && task.coords) ? ('Ubicación: https://www.google.com/maps?q=' + encodeURIComponent(task.coords)) : '',
        '',
        'Por favor, confirma la recepción y el estado al finalizar.'
      ].filter(function(x){ return x; }).join('\n');
      msgBox.value = lines;
    }
  };
})();
