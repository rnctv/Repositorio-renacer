
// agenda kanban overrides: coords + plan + preview + fresh send data (v3, PPP fix)
(function() {
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function ensurePreviewButton() {
    var form = document.querySelector('#taskForm');
    if (!form) return;

    // Buscar botones que ya digan "Ver ubicación"
    var vb = $all('button', form).filter(function(btn) {
      return (btn.textContent || '').trim().toLowerCase() === 'ver ubicación';
    });

    // Ocultar duplicados si hay más de uno
    if (vb.length > 1) {
      vb.slice(1).forEach(function(b) { b.style.display = 'none'; });
    }

    var btn = vb[0];
    if (!btn) {
      // Si no existe ninguno, lo creamos antes del botón "Mapa"
      var mapBtn = $('#openMap', form);
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn';
      btn.textContent = 'Ver ubicación';
      if (mapBtn && mapBtn.parentNode) {
        mapBtn.parentNode.insertBefore(btn, mapBtn);
      } else {
        // fallback: al final del contenedor de acciones
        var actions = form.querySelector('.actions') || form;
        actions.appendChild(btn);
      }
    }

    // Asignar listener solo una vez
    if (!btn.__previewHandlerBound) {
      btn.addEventListener('click', function() {
        var lat = ($('#cliLat') || {}).value || '';
        var lng = ($('#cliLng') || {}).value || '';
        lat = String(lat).trim();
        lng = String(lng).trim();
        if (!lat || !lng) {
          alert('Este cliente no tiene coordenadas para mostrar.');
          return;
        }
        var url = 'https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lng);
        window.open(url, '_blank', 'noopener');
      });
      btn.__previewHandlerBound = true;
    }
  }

  function ensureHiddenFields() {
    var form = document.querySelector('#taskForm');
    if (!form) return;

    function ensure(name, id) {
      var el = document.getElementById(id);
      if (!el) {
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = name;
        el.id = id;
        form.appendChild(el);
      }
      return el;
    }

    ensure('user_ppp_hotspot', 'cliPPP');
    ensure('precinto', 'cliPrecinto');
    ensure('lat', 'cliLat');
    ensure('lng', 'cliLng');

    // Hint de coordenadas debajo del buscador
    var hint = document.getElementById('coordHint');
    if (!hint) {
      var buscaRow = document.getElementById('buscaCliente') ?
        document.getElementById('buscaCliente').closest('.g-1') : null;
      hint = document.createElement('small');
      hint.id = 'coordHint';
      hint.style.display = 'block';
      hint.style.fontSize = '.8rem';
      hint.style.color = '#666';
      if (buscaRow && buscaRow.parentNode) {
        buscaRow.parentNode.insertBefore(hint, buscaRow.nextSibling);
      } else {
        form.appendChild(hint);
      }
    }
  }

  function normalizePlanString(raw) {
    if (!raw) return '';
    var s = String(raw).toUpperCase();
    s = s.replace(/\s+/g, ' ').trim();

    // Equivalencias DUO (Internet + TV)
    if (s.includes('DUO 200') || s.includes('INTERNET 200 MBPS + TELEVISION') || s.includes('INTERNET 200 MBPS + TV')) {
      return 'DUO 200';
    }
    if (s.includes('DUO 400') || s.includes('INTERNET 400 MBPS + TELEVISION') || s.includes('INTERNET 400 MBPS + TV')) {
      return 'DUO 400';
    }
    if (s.includes('DUO 600') || s.includes('INTERNET 600 MBPS + TELEVISION') || s.includes('INTERNET 600 MBPS + TV')) {
      return 'DUO 600';
    }
    if (s.includes('DUO 800') || s.includes('INTERNET 800 MBPS + TELEVISION') || s.includes('INTERNET 800 MBPS + TV')) {
      return 'DUO 800';
    }

    // Solo TV
    if (s === 'TELEVISION' || s === 'TELEVISIÓN' || s === 'TV' || s.includes(' SOLO TV')) {
      return 'TELEVISION';
    }

    // Solo Internet
    if (s.includes('INTERNET 200')) return 'INTERNET 200';
    if (s.includes('INTERNET 400')) return 'INTERNET 400';
    if (s.includes('INTERNET 600')) return 'INTERNET 600';
    if (s.includes('INTERNET 800')) return 'INTERNET 800';

    return '';
  }

  function applyPlanToSelect(canonicalPlan) {
    if (!canonicalPlan) return;
    var sel = document.getElementById('plan');
    if (!sel) return;
    var valueToSet = canonicalPlan;
    var found = false;
    Array.prototype.forEach.call(sel.options, function(op) {
      if (!op.value) return;
      var v = String(op.value).toUpperCase().trim();
      if (v === canonicalPlan) {
        valueToSet = op.value;
        found = true;
      }
    });
    if (found) sel.value = valueToSet;
  }

  function setCoordsHint(text) {
    var hint = document.getElementById('coordHint');
    if (hint) hint.textContent = text || '';
  }

  function updateFromClienteData(cli) {
    ensureHiddenFields();

    if (!cli || typeof cli !== 'object') {
      setCoordsHint('');
      return;
    }

    // PPP / Precinto
    var ppp = cli.user_ppp_hotspot || cli.usuario_ppp || cli.ppp || '';
    var prec = cli.precinto || cli.precinto_olt || cli.nro_precinto || '';

    var pppEl = document.getElementById('cliPPP');
    var precEl = document.getElementById('cliPrecinto');
    if (pppEl) pppEl.value = ppp || '';
    if (precEl) precEl.value = prec || '';

    // Coordenadas desde BD
    var coords = cli.coordenadas || cli.coords || '';
    var latEl = document.getElementById('cliLat');
    var lngEl = document.getElementById('cliLng');

    if (typeof coords === 'string' && coords.indexOf(',') !== -1) {
      var parts = coords.split(',');
      var lat = (parts[0] || '').trim();
      var lng = (parts[1] || '').trim();
      if (latEl) latEl.value = lat;
      if (lngEl) lngEl.value = lng;
      setCoordsHint('✅ Cliente con coordenadas guardadas en la BD. Si no modificas nada, se usarán estas coordenadas.');
    } else {
      if (latEl) latEl.value = '';
      if (lngEl) lngEl.value = '';
      setCoordsHint('⚠️ Cliente sin coordenadas guardadas. Usa el botón "Mapa" para fijarlas.');
    }

    // Plan
    var rawPlan = cli.plan || cli['el plan'] || cli.el_plan || cli.plan_label || '';
    var canonical = normalizePlanString(rawPlan);
    applyPlanToSelect(canonical);
  }

  function fetchClienteAndUpdate(id) {
    if (!id) {
      setCoordsHint('');
      return;
    }
    fetch('/clientes/' + encodeURIComponent(id), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function(res) { return res.ok ? res.json() : null; })
      .then(function(payload) {
        if (payload && payload.data) {
          updateFromClienteData(payload.data);
        } else if (payload && payload.ok !== undefined && payload.cliente) {
          updateFromClienteData(payload.cliente);
        } else {
          // payload directo como cliente
          updateFromClienteData(payload);
        }
      })
      .catch(function() {
        setCoordsHint('');
      });
  }

  function startClientWatcher() {
    var cliInput = document.getElementById('cliente_id');
    if (!cliInput) return;

    var lastVal = cliInput.value || '';
    setInterval(function() {
      var v = cliInput.value || '';
      if (v && v !== lastVal) {
        lastVal = v;
        fetchClienteAndUpdate(v);
      }
      if (!v && lastVal) {
        lastVal = '';
        setCoordsHint('');
      }
    }, 600);
  }

  // === Wrap de openSendModal para recargar datos frescos ===
  function wrapSendModal() {
    function enhance() {
      var orig = window.openSendModal;
      if (!orig || orig.__wrappedForFreshData_v3) return;

      function mergedMetaFromFresh(meta, fresh) {
        meta = meta || {};
        if (!fresh || typeof fresh !== 'object') return meta;

        function put(keyFromFresh, keyMeta) {
          keyMeta = keyMeta || keyFromFresh;
          if (fresh[keyFromFresh] != null && (meta[keyMeta] == null || meta[keyMeta] === '')) {
            meta[keyMeta] = fresh[keyFromFresh];
          }
        }

        // Campos genéricos
        put('plan');
        put('maps_url');
        put('tipo');
        put('nombre');
        put('telefono');
        put('direccion');
        put('notas', 'descripcion');

        // PPP: aceptamos varios nombres y rellenamos varias llaves en meta
        var pppFresh = fresh.user_ppp_hotspot || fresh.ppp || fresh.usuario_ppp || fresh.usuario_ppp_hotspot;
        if (pppFresh) {
          if (meta.user_ppp_hotspot == null || meta.user_ppp_hotspot === '') {
            meta.user_ppp_hotspot = pppFresh;
          }
          if (meta.ppp == null || meta.ppp === '') {
            meta.ppp = pppFresh;
          }
          if (meta.usuario_ppp == null || meta.usuario_ppp === '') {
            meta.usuario_ppp = pppFresh;
          }
        }

        // Precinto: igual lógica
        var precFresh = fresh.precinto || fresh.precinto_olt || fresh.nro_precinto;
        if (precFresh) {
          if (meta.precinto == null || meta.precinto === '') {
            meta.precinto = precFresh;
          }
          if (meta.nro_precinto == null || meta.nro_precinto === '') {
            meta.nro_precinto = precFresh;
          }
        }

        return meta;
      }

      window.openSendModal = function(arg1, arg2) {
        var id = null;
        var meta = null;
        var modeOneParam = false;

        if (typeof arg1 === 'object' && arg1 !== null && (arg2 === undefined || arg2 === null)) {
          // Firma: openSendModal(meta)
          modeOneParam = true;
          meta = arg1;
          if (meta && meta.id != null) {
            id = meta.id;
          } else if (meta && meta.task_id != null) {
            id = meta.task_id;
          }
        } else {
          // Firma: openSendModal(id, meta)
          id = arg1;
          meta = arg2;
        }

        if (!id) {
          // No se pudo determinar ID, usamos comportamiento original tal cual
          orig.apply(window, arguments);
          return;
        }

        var idStr = String(id);

        fetch('/agenda/tareas/' + encodeURIComponent(idStr), {
          headers: { 'Accept': 'application/json' }
        })
          .then(function(res) { return res.ok ? res.json() : null; })
          .then(function(payload) {
            var fresh = payload && payload.data ? payload.data : null;
            meta = mergedMetaFromFresh(meta, fresh);

            if (modeOneParam) {
              orig.call(window, meta);
            } else {
              orig.call(window, id, meta);
            }
          })
          .catch(function() {
            // Si algo falla, se llama a la función original con los argumentos originales
            orig.apply(window, arguments);
          });
      };

      window.openSendModal.__wrappedForFreshData_v3 = true;
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      enhance();
    } else {
      document.addEventListener('DOMContentLoaded', enhance);
    }

    // Reintentos por si el script original define openSendModal más tarde
    var tries = 0;
    var iv = setInterval(function() {
      if (window.openSendModal && !window.openSendModal.__wrappedForFreshData_v3) {
        enhance();
        clearInterval(iv);
        return;
      }
      tries++;
      if (tries > 20) clearInterval(iv);
    }, 500);
  }

  document.addEventListener('DOMContentLoaded', function() {
    ensureHiddenFields();
    ensurePreviewButton();
    startClientWatcher();
  });

  wrapSendModal();
})();



// ===== Ensure Lat/Lng Always Exist =====
function ensureLatLngFields(){
    const form=document.querySelector('#taskForm');
    if(!form) return;

    if(!document.querySelector('#cliLat')){
        const lat=document.createElement('input');
        lat.type='hidden'; lat.id='cliLat'; lat.name='lat'; lat.value='';
        form.appendChild(lat);
    }
    if(!document.querySelector('#cliLng')){
        const lng=document.createElement('input');
        lng.type='hidden'; lng.id='cliLng'; lng.name='lng'; lng.value='';
        form.appendChild(lng);
    }
}

document.addEventListener('click',e=>{
    if(e.target.matches('[data-open-task], #btnOpenMap, .open-modal, button, a')){
        setTimeout(()=>ensureLatLngFields(),50);
    }
});
// =======================================
