/* public/js/kanban.js  v3.77b */
/* eslint-disable */
(function () {
  const $  = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const upper = (s) => String(s || '').toUpperCase();

  const board = $('#kanbanBoard');
  if (!board) return;

  const API_LIST   = board.dataset.endpointList;
  const API_COUNTS = board.dataset.endpointCounts;
  const API_PENDS  = board.dataset.endpointPendientes;
  const MAPS_SCOPE = board.dataset.mapsScope || 'Chile';

  
// === Fix mapa: selector en modal interno (mapMask) ===
  const DEFAULT_LAT = parseFloat(board.dataset.defaultLat || '-36.012889') || -36.012889;
  const DEFAULT_LNG = parseFloat(board.dataset.defaultLng || '-71.653852') || -71.653852;

  function ensureLatLngFields(){
    const form = document.querySelector('#taskForm');
    if(!form) return;
    if(!document.querySelector('#cliLat')){
      const lat = document.createElement('input');
      lat.type = 'hidden';
      lat.id = 'cliLat';
      lat.name = 'lat';
      lat.value = '';
      form.appendChild(lat);
    }
    if(!document.querySelector('#cliLng')){
      const lng = document.createElement('input');
      lng.type = 'hidden';
      lng.id = 'cliLng';
      lng.name = 'lng';
      lng.value = '';
      form.appendChild(lng);
    }
  }

  function getCurrentCoords(){
    ensureLatLngFields();
    const latInput = document.querySelector('#cliLat');
    const lngInput = document.querySelector('#cliLng');
    let lat = parseFloat(latInput && latInput.value);
    let lng = parseFloat(lngInput && lngInput.value);
    if(!isFinite(lat)) lat = DEFAULT_LAT;
    if(!isFinite(lng)) lng = DEFAULT_LNG;
    return { lat, lng, latInput, lngInput };
  }

  let gmapsLoading = false;
  let gmapsLoaded  = false;
  let gmapsQueue   = [];

  function loadGMaps(callback){
    if(gmapsLoaded){ callback(); return; }
    gmapsQueue.push(callback);
    if(gmapsLoading) return;
    gmapsLoading = true;

    const key = board.dataset.gmapsKey || window.GMAPS_KEY || '';
    const script = document.createElement('script');
    const params = [];
    if(key) params.push('key='+encodeURIComponent(key));
    // libraries opcionales a futuro
    const qs = params.length ? '?' + params.join('&') : '';
    script.src = 'https://maps.googleapis.com/maps/api/js' + qs;
    script.async = true;
    script.defer = true;
    script.onload = function(){
      gmapsLoaded = true;
      const q = gmapsQueue.slice(); gmapsQueue = [];
      q.forEach(fn=>{ try{ fn(); }catch(_e){} });
    };
    script.onerror = function(){
      gmapsLoaded = false;
      gmapsLoading = false;
      alert('No se pudo cargar Google Maps.');
    };
    document.head.appendChild(script);
  }

  let modalMap = null;
  let modalMarker = null;

  function updateMapUI(lat, lng, latInput, lngInput){
    const latSpan = document.getElementById('latVal');
    const lngSpan = document.getElementById('lngVal');
    if(latSpan) latSpan.textContent = lat.toFixed(6);
    if(lngSpan) lngSpan.textContent = lng.toFixed(6);
    if(latInput) latInput.value = lat.toFixed(6);
    if(lngInput) lngInput.value = lng.toFixed(6);
  }

  function openMapSelector(){
    const mask   = document.getElementById('mapMask');
    const canvas = document.getElementById('mapCanvas');
    if(!mask || !canvas) return;

    const { lat, lng, latInput, lngInput } = getCurrentCoords();
    const center = { lat, lng };

    loadGMaps(function(){
      if(!window.google || !window.google.maps){
        alert('Google Maps no está disponible.');
        return;
      }

      if(!modalMap){
        modalMap = new google.maps.Map(canvas, {
          center: center,
          zoom: 17,
          mapTypeId: 'roadmap'
        });
        modalMarker = new google.maps.Marker({
          position: center,
          map: modalMap,
          draggable: true
        });

        function onPosChanged(pos){
          const latNum = pos.lat();
          const lngNum = pos.lng();
          updateMapUI(latNum, lngNum, latInput, lngInput);
        }

        modalMap.addListener('click', function(e){
          if(!modalMarker){
            modalMarker = new google.maps.Marker({ position: e.latLng, map: modalMap, draggable: true });
            modalMarker.addListener('dragend', function(ev){ onPosChanged(ev.latLng); });
          }else{
            modalMarker.setPosition(e.latLng);
          }
          onPosChanged(e.latLng);
        });

        modalMarker.addListener('dragend', function(e){
          onPosChanged(e.latLng);
        });
      }else{
        const newCenter = new google.maps.LatLng(center.lat, center.lng);
        modalMap.setCenter(newCenter);
        if(modalMarker){
          modalMarker.setPosition(newCenter);
        }else{
          modalMarker = new google.maps.Marker({
            position: newCenter,
            map: modalMap,
            draggable: true
          });
          modalMarker.addListener('dragend', function(e){
            const pos = e.latLng;
            updateMapUI(pos.lat(), pos.lng(), latInput, lngInput);
          });
        }
      }

      updateMapUI(center.lat, center.lng, latInput, lngInput);
      openMask(mask);
    });
  }
let selectedDay = board.dataset.selected || (() => {
    const d = new Date();
    const y = d.getFullYear(), m = String(d.getMonth()+1).padStart(2,'0'), day = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  })();
  let rangeStart  = selectedDay;

  const DEFAULTS = Array.isArray(window.DEFAULT_RECIPIENTS) ? window.DEFAULT_RECIPIENTS : [
  { name: 'Harry', number: '942415211', selected: false, tags: ['INSTALACION'] },
  { name: 'Cesar', number: '975897774', selected: false, tags: ['ASISTENCIA'] },
  { name: 'Daniel', number: '966368928', selected: true,  tags: ['INSTALACION','ASISTENCIA'] },
  { name: 'Juan',   number: '938750384', selected: true,  tags: ['INSTALACION'] },
  { name: 'Soporte 24/7', number: '900000000', selected: false, tags: ['ASISTENCIA'] },
];

  function csrf(){ return $('meta[name="csrf-token"]')?.content || ''; }
  function fmtDate(d){ if (typeof d === 'string') return d; return d.toISOString().slice(0,10); }
  function el(tag, cls){ const n = document.createElement(tag); if (cls) n.className = cls; return n; }
  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, (m) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }
  function todayStr(){ const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; }
  function toInt(s){ const n=parseInt(String(s||'').trim(),10); return Number.isFinite(n)?n:0; }

  // Normalizador E.164 CL (devuelve 569xxxxxxxx o null)
  function normalizePhoneCL(raw){
    const digits = String(raw || '').replace(/\D+/g, '');
    if (!digits) return null;
    if (digits.startsWith('56')) return (digits.length === 11) ? digits : null;
    if (digits[0] === '0') return null;
    if (digits.length === 9 && digits[0] === '9') return '56' + digits;
    return null;
  }

  // ===== Modales con pila =====
  function getOpenMasks(){ return $$('.mask.show'); }
  function topMask(){ const ms=getOpenMasks(); return ms[ms.length-1]||null; }
  function openMask(maskEl){
    if (!maskEl) return;
    const depth = getOpenMasks().length;
    maskEl.classList.add('show'); maskEl.style.pointerEvents='auto'; maskEl.style.zIndex=String(1000+depth);
    maskEl.setAttribute('aria-hidden','false');
    const dlg = maskEl.querySelector('.dialog'); if (dlg && !dlg.hasAttribute('tabindex')) dlg.setAttribute('tabindex','-1');
    const first = maskEl.querySelector('.dlg-x, [data-close], .btn.primary, button, input, textarea, select, [tabindex="0"]') || dlg;
    try { (first||dlg).focus({preventScroll:true}); } catch(_){}
  }
  function closeMask(maskEl){
    if (!maskEl) return;
    try{ if (maskEl.contains(document.activeElement)) document.activeElement.blur(); }catch(_){}
    maskEl.classList.remove('show'); maskEl.style.pointerEvents='none'; maskEl.style.zIndex=''; maskEl.removeAttribute('aria-hidden');
  }
  function enableBackdropClose(maskSel, closeAttr, onClose){
    const mask=document.querySelector(maskSel); if(!mask) return;
    const doClose=()=>{ if(topMask()===mask){ onClose&&onClose(); closeMask(mask);} };
    mask.addEventListener('click',(e)=>{ if(e.target===mask) doClose(); });
    mask.querySelectorAll(closeAttr).forEach(b=>b.addEventListener('click',doClose));
    document.addEventListener('keydown',(e)=>{ if(e.key==='Escape' && topMask()===mask) doClose(); });
  }

  // ===== Contadores =====
  function updateCountsUI(){
    $$('.column').forEach(col=>{
      const c = col.querySelectorAll('.dropzone .card').length;
      const span = $('[data-count]', col);
      if (span) span.textContent = String(c);
    });
  }
  function toggleTodayDisabled(){ const b=$('#btnToday'); if(b) b.disabled=(selectedDay===todayStr()); }
  function incTextInt(el,delta){ if(!el) return; el.textContent=String(Math.max(0,toInt(el.textContent)+delta)); }
  function getPill(dateStr){ return $(`#daysScroll .day-pill[data-date="${CSS.escape(dateStr)}"]`); }
  function incDayBadge(dateStr,delta){
    const pill=getPill(dateStr); if(!pill) return;
    const badge=$('.badge-pend',pill); if(!badge) return;
    const nv=Math.max(0,toInt(badge.textContent)+delta);
    badge.textContent=String(nv); pill.classList.toggle('has-pending',nv>0);
  }
  function incTotalPending(delta){ const totalEl=$('#pendTotal'); if(totalEl) incTextInt(totalEl,delta); }

  // ===== Parsing notas / coords =====
  function coordFromText(text){
    if (!text) return null;
    const m = String(text).match(/(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)/);
    if (!m) return null;
    const lat = parseFloat(m[1]), lng = parseFloat(m[2]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    return {lat, lng};
  }
  function parseManualFields(text){
    const s=String(text||'');
    const get=(labels)=>{ const re=new RegExp('(?:'+labels+')\\s*:\\s*([^\\n\\r]*)','i'); const m=s.match(re); return (m&&typeof m[1]==='string')?m[1].trim():null; };
    const nombre=get('Nombre|Cliente'); const tel=get('Tel[eé]fono|Telefono|Tel'); const dir=get('Direcci[oó]n|Direccion');
    let coords=null; const coordLbl=get('Coordenadas|Coords');
    if(coordLbl){ const c=coordFromText(coordLbl); if(c) coords=`${c.lat},${c.lng}`; }
    if(!coords){ const c2=coordFromText(s); if(c2) coords=`${c2.lat},${c2.lng}`; }
    return { nombre, telefono:tel, direccion:dir, coordenadas:coords };
  }

  // ===== Links de mapa =====
  function isLikelyAddress(s){ const t=String(s||'').trim(); if(t.length<6) return false; if(!/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]/.test(t)) return false; return true; }
  function normalizeAddress(addr){ let a=String(addr||'').trim(); a=a.replace(/\s*[-–—]\s*/g,', '); a=a.replace(/\s{2,}/g,' '); if(!a.includes(',')) a=`${a}, ${MAPS_SCOPE}`; return a; }
  function buildAddressLink(addr){ if(!isLikelyAddress(addr)) return null; const norm=normalizeAddress(addr); return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(norm)}`; }
  function buildCoordsLink(coordsStr){ return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(coordsStr)}`; }

  // ===== Modal ENVIAR =====
  let sendMask=null;
  function buildSendModal(){
    if (sendMask) return;
    sendMask=document.createElement('div'); sendMask.className='mask'; sendMask.id='sendMask'; sendMask.style.pointerEvents='none';
    sendMask.innerHTML=`
      <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="sendTitle">
        <div class="dlg-hd">
          <div class="dlg-ttl" id="sendTitle">Enviar a técnico</div>
          <button class="dlg-x" type="button" data-close aria-label="Cerrar">✕</button>
        </div>
        <div class="dlg-bd">
          <form id="sendForm">
            <div class="g">
              <div>
                <label class="lbl">Destinatarios</label>
                <div id="rec-list" class="g" style="gap:.25rem"></div>
              </div>
              <div class="g-1">
                <label class="lbl">Mensaje</label>
                <textarea class="txt" id="sendMsg" required></textarea>
              </div>
            </div>
            <div class="actions" style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.75rem">
              <button class="btn" type="button" data-close>Cancelar</button>
              <button class="btn primary" type="submit">Enviar</button>
            </div>
          </form>
        </div>
      </div>`;
    document.body.appendChild(sendMask);
    enableBackdropClose('#sendMask','#sendMask [data-close]',()=>{ const t=$('#sendMsg'); if(t) t.value=''; });
    const list = document.querySelector('#rec-list');
    if (list) { list.innerHTML = DEFAULTS.map(function(r){
      var name = (r.name||''); var num = (r.number||''); var checked = r.selected ? 'checked' : '';
      return '<label class="chk" style="display:block;margin:.25rem 0">'+
             '<input type="checkbox" class="rec-item" data-name="'+name.replace(/"/g,'&quot;')+'" data-number="'+num.replace(/"/g,'&quot;')+'" '+checked+'> '+
             '<span>'+name+' — '+num+'</span></label>';
    }).join(''); }
    $('#sendForm', sendMask)?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const boxes = sendMask.querySelectorAll('.rec-item');
      const contacts = [];
      boxes.forEach(b=>{
        if (b.checked) {
          const raw = String(b.dataset.number||'').trim();
          const n = normalizePhoneCL(raw);
          if (n) contacts.push(n);
        }
      });
      if (!contacts.length) { alert('Selecciona al menos un destinatario'); return; }
      const msg = $('#sendMsg', sendMask)?.value?.trim() || '';
      const id = sendMask.dataset.taskId;
      try {
        await sendNotification(id, contacts, msg);
        alert('Mensajes encolados correctamente.');
        closeMask(sendMask);
      } catch (err) {
        const human =
          (err && (err.message || err.status)) ? `${err.message || ''} ${err.status ? `(HTTP ${err.status})` : ''}`.trim()
          : JSON.stringify(err || {});
        alert('Error al enviar: ' + human);
      }
    });

    if (res.ok || looksLikeCf502 || payload.ok === true) {
      return Object.assign({ ok: true, status: res.status }, payload || {});
    }

    const msg = (payload && (payload.message || payload.error)) ? (payload.message || payload.error)
              : (typeof payload === 'string' ? payload
              : JSON.stringify(payload || {}));
    const err = new Error(msg || `HTTP ${res.status}`);
    err.status = res.status;
    err.payload = payload;
    throw err;
  }

  // ===== Carga tablero =====
  let listAbort=null;
  async function loadBoard(date){
    if(listAbort) listAbort.abort();
    listAbort=new AbortController();
    try{
      const res=await fetch(`${API_LIST}?fecha=${encodeURIComponent(date)}`,{
        signal:listAbort.signal, headers:{'Accept':'application/json'}, cache:'no-store', credentials:'same-origin'
      });
      if(!res.ok) throw new Error('No se pudo cargar la agenda');
      const p=await res.json();
      renderBoard(p?.data||{pendiente:[],en_curso:[],completado:[]});
    }catch(err){ console.error(err); alert('No se pudo cargar la agenda'); }
  }

  const clientCoordsCache=new Map();
  function addMapLinkToCard(card,coordsStr,addressStr){
    if(!card) return; if(card.querySelector('.map-link')) return;
    const a=document.createElement('a'); a.className='map-link muted';
    const href=coordsStr?buildCoordsLink(coordsStr):buildAddressLink(addressStr);
    if(!href) return; a.href=href; a.target='_blank'; a.rel='noopener'; a.textContent='🗺️ Mapa';
    a.addEventListener('click',ev=>{ ev.stopPropagation(); ev.stopImmediatePropagation(); });
    const info=card.querySelector('div[min-width="0"]')||card; info.appendChild(a);
  }
  async function fetchClientCoords(clienteId){
    if(!clienteId) return null; if(clientCoordsCache.has(clienteId)) return clientCoordsCache.get(clienteId);
    try{
      const res=await fetch(`/clientes/${encodeURIComponent(clienteId)}`,{ headers:{'Accept':'application/json'}, credentials:'same-origin' });
      if(!res.ok) return null;
      const p=await res.json(); const c=(p?.data?.coordenadas||'').trim();
      if(c&&c.includes(',')){ clientCoordsCache.set(clienteId,c); return c; }
    }catch(_){}
    return null;
  }
  async function hydrateMapLinksFromClients(){
    const cards=Array.from(document.querySelectorAll('.card[data-cliente-id]')); if(!cards.length) return;
    for(const card of cards){
      if(card.querySelector('.map-link')) continue;
      const id=card.getAttribute('data-cliente-id');
      const dir=$('.muted',card)?.nextElementSibling?.textContent||'';
      const coords=await fetchClientCoords(id);
      addMapLinkToCard(card,coords,dir);
    }
  }

  function renderBoard(data){
    const zones={ pendiente:$('.dropzone[data-status="pendiente"]'), en_curso:$('.dropzone[data-status="en_curso"]'), completado:$('.dropzone[data-status="completado"]') };
    Object.values(zones).forEach(z=>{ if(z) z.innerHTML=''; });
    for(const estado of ['pendiente','en_curso','completado']){
      (data[estado]||[]).forEach(t=>zones[estado].appendChild(buildCard(t,estado)));
    }
    wireDnD(); updateCountsUI(); hydrateMapLinksFromClients();
  }

  function buildCard(t,estado){
    const card=el('article','card');
    const isClosedDay=selectedDay<todayStr();
    const isLocked=(estado==='completado'&&isClosedDay);
    if(!isLocked) card.setAttribute('draggable','true'); else card.classList.add('locked');

    card.dataset.id=t.id; if(t.cliente?.id) card.dataset.clienteId=t.cliente.id;

    const notesText=String(t.notas || t.descripcion || '');
    const parsed=parseManualFields(notesText);

    let coordsStr=null;
    if (t.coord_lat != null && t.coord_lng != null) coordsStr = `${t.coord_lat},${t.coord_lng}`;
    else if(t.cliente?.coordenadas) coordsStr=String(t.cliente.coordenadas);
    else if(parsed.coordenadas) coordsStr=parsed.coordenadas;
    else if(t.coordenadas) coordsStr=String(t.coordenadas);
    else { const c=coordFromText(notesText); if(c) coordsStr=`${c.lat},${c.lng}`; }

    const displayName=t.cliente?.nombre||parsed.nombre||t.titulo||'(SIN CLIENTE)';
    const tel=t.cliente?.telefono||parsed.telefono||'—';
    const dir=t.cliente?.direccion||parsed.direccion||'—';
    const actPlan=`${t.tipo ? String(t.tipo).toUpperCase() : ''}${t.plan ? ' — '+t.plan : ''}`;

    const mapHref=coordsStr?buildCoordsLink(coordsStr):buildAddressLink(dir);
    const mapLink=mapHref?`<a class="map-link muted" href="${mapHref}" target="_blank" rel="noopener">🗺️ Mapa</a>`:''; 

    card.innerHTML=`
      <div style="display:flex;justify-content:space-between;gap:.5rem;">
        <div style="min-width:0;">
          <h4>${escapeHtml(upper(displayName))}</h4>
          <p class="muted">${escapeHtml(actPlan)}</p>
          <p class="muted">${escapeHtml(dir)}</p>
          <p class="muted">Tel: ${escapeHtml(tel)}</p>
          ${mapLink}
        </div>
        <div>
          <button class="btn" data-send>Enviar</button>
        </div>
      </div>`;

    card.querySelectorAll('a').forEach(a=>{
      const stop=(ev)=>{ ev.stopPropagation(); ev.stopImmediatePropagation(); };
      a.addEventListener('click',stop); a.addEventListener('mousedown',stop); a.addEventListener('mouseup',stop);
    });

    if(!isLocked){
      let dragged=false;
      card.addEventListener('dragstart',e=>{ e.dataTransfer.setData('text/plain',card.dataset.id); dragged=true; });
      card.addEventListener('dragend',()=>setTimeout(()=>{ dragged=false; },50));
      card.addEventListener('click',(e)=>{ if(e.target&&(e.target.closest('a')||e.target.closest('[data-send]'))) return; if(!dragged) openView(card.dataset.id); });
    }else{
      card.addEventListener('click',()=>openView(card.dataset.id));
    }

    const btnSend=$('[data-send]',card);
    if(btnSend){
      btnSend.addEventListener('click',(ev)=>{
        ev.stopPropagation();
        openSendModal({
          id:t.id, nombre:upper(displayName), telefono:tel, direccion:upper(dir),
          tipo:upper(t.tipo||''), plan:upper(t.plan||''), descripcion:notesText, coords:coordsStr,
          ppp:(t.user_ppp_hotspot||t.ppp||''), precinto:(t.precinto||'')
        });
      });
    }

    return card;
  }

  // ===== Drag & Drop =====
  let moving=false;
  function moveUrl(id){ return `/agenda/tareas/${encodeURIComponent(id)}/mover`; }
  function wireDnD(){
    $$('.dropzone').forEach(zone=>{
      zone.addEventListener('dragover',e=>{ const z=e.target.closest('.dropzone'); if(!z) return; e.preventDefault(); z.classList.add('over'); });
      zone.addEventListener('dragleave',e=>{ const z=e.target.closest('.dropzone'); if(z) z.classList.remove('over'); });
      zone.addEventListener('drop',async e=>{
        e.preventDefault(); const z=e.target.closest('.dropzone'); if(!z) return; z.classList.remove('over');
        const id=e.dataTransfer.getData('text/plain'); if(!id) return;
        const card=$(`.card[data-id="${CSS.escape(id)}"]`); if(!card) return;
        const fromCol=card.closest('.column')?.dataset.col; const toCol=z.dataset.status;
        if(!toCol||fromCol===toCol) return;

        z.appendChild(card); updateCountsUI();
        if(moving) return; moving=true;

        try{
          const today=todayStr(); let targetDate=selectedDay;
          if(toCol==='en_curso' && selectedDay>today){ targetDate=today; }
          const result=await moveTask(id,toCol,targetDate);
          const finalDate=result?.fecha||targetDate;

          if(finalDate!==selectedDay){
            card.remove(); updateCountsUI(); refreshCounts();
            selectedDay=finalDate; buildDays(selectedDay); await loadBoard(selectedDay); return;
          }
          refreshCounts();
        }catch(err){
          console.error(err); alert('No se pudo mover la tarea');
          const fallback=$(`.column[data-col="${fromCol}"] .dropzone`); if(fallback) fallback.appendChild(card); updateCountsUI();
        }finally{ moving=false; }
      });
    });
  }
  async function moveTask(id,estado,fecha){
    const url=moveUrl(id);
    const res=await fetch(url,{ method:'PATCH', headers:{'X-CSRF-TOKEN':csrf(),'Accept':'application/json','Content-Type':'application/json'}, body:JSON.stringify({estado,fecha}), credentials:'same-origin' });
    if(!res.ok) throw new Error('move error');
    try{ return await res.json(); }catch{ return { ok:true, estado, fecha }; }
  }

  // ===== Ver actividad =====
  function setByIds(ids, value){
    ids.forEach(id => { const el=document.getElementById(id); if(el){ el.value = value ?? ''; } });
  }

  async function openView(id){
    try{
      const res=await fetch(`/agenda/tareas/${encodeURIComponent(id)}`,{ headers:{'Accept':'application/json'}, credentials:'same-origin', cache:'no-store' });
      if(!res.ok) throw new Error('No se pudo obtener el detalle');
      const p=await res.json(); const d=p?.data;

      const parsed = (function(txt){ const s=String(txt||''); const get=(labels)=>{ const re=new RegExp('(?:'+labels+')\\s*:\\s*([^\\n\\r]*)','i'); const m=s.match(re); return m?m[1].trim():null; }; return { nombre:get('Nombre|Cliente'), direccion:get('Direcci[oó]n|Direccion'), telefono:get('Tel[eé]fono|Telefono|Tel') }; })(d?.descripcion || d?.notas || '');

      const set = (id,val)=>{ const el=document.getElementById(id); if(el){ el.value=val ?? ''; } };

      set('v_titulo',d.titulo ?? '');
      set('v_estado',d.estado ?? '');
      set('v_tipo',  d.tipo   ?? '');
      set('v_plan',  d.plan   ?? '');
      set('v_fecha', d.fecha  ?? '');
      set('v_cliente',   d.cliente?.nombre    || parsed.nombre    || '');
      set('v_direccion', d.cliente?.direccion || parsed.direccion || '');
      set('v_telefono',  d.cliente?.telefono  || parsed.telefono  || '');

      const descVal = d.notas ?? d.descripcion ?? '';
      setByIds(['v_desc','v_descripcion','v_detalle'], descVal);

      const link = (function(d){
        if (d?.coord_lat != null && d?.coord_lng != null) return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${d.coord_lat},${d.coord_lng}`)}`;
        if (d?.cliente?.coordenadas) return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(String(d.cliente.coordenadas))}`;
        const c = (d?.descripcion || d?.notas || '').match(/(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)/);
        if (c) return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${c[1]},${c[2]}`)}`;
        const addr = d?.cliente?.direccion || '';
        if (!addr) return null;
        const norm = addr.includes(',') ? addr : `${addr}, ${MAPS_SCOPE}`;
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(norm)}`;
      })(d);

      const wrap=$('#v_map_wrap'); const a=$('#v_map');
      if(wrap && a){ if(link){ a.href=link; wrap.style.display=''; } else { wrap.style.display='none'; } }

      (function fillReadonly(selector){ const root=$(selector); if(!root) return; root.querySelectorAll('input, textarea, select').forEach(el=>{ if(el.tagName==='SELECT') el.disabled=true; else el.readOnly=true; }); })('#viewMask');

      openMask($('#viewMask'));
    }catch(err){ console.error(err); alert('No se pudo abrir el detalle'); }
  }
  enableBackdropClose('#viewMask','#viewMask [data-close-view]');

  // ===== Carrusel de días =====
  const daysScroll=$('#daysScroll');
  function buildDays(startStr){
    const start=new Date(startStr+'T00:00:00'); rangeStart=fmtDate(start);
    if (!daysScroll) return;
    daysScroll.innerHTML='';
    for(let i=0;i<10;i++){
      const d=new Date(start); d.setDate(start.getDate()+i);
      const pill=el('button','day-pill'); pill.dataset.date=fmtDate(d);
      const es=d.toLocaleDateString('es-CL',{weekday:'short'}); const di=String(d.getDate()).padStart(2,'0'); const mo=String(d.getMonth()+1).toString().padStart(2,'0');
      pill.innerHTML=`<small>${es}</small>${di}/${mo}<span class="pill-badge badge-pend">0</span><span class="pill-badge badge-done">0</span>`;
      if(fmtDate(d)===selectedDay) pill.classList.add('active');

      pill.addEventListener('click',()=>{ selectedDay=pill.dataset.date; $$('.day-pill',daysScroll).forEach(x=>x.classList.remove('active')); pill.classList.add('active'); loadBoard(selectedDay); toggleTodayDisabled(); });
      pill.addEventListener('dragover',(e)=>{ e.preventDefault(); pill.classList.add('over'); });
      pill.addEventListener('dragleave',()=>pill.classList.remove('over'));
      pill.addEventListener('drop',async(e)=>{
        e.preventDefault(); pill.classList.remove('over');
        const id=e.dataTransfer.getData('text/plain'); if(!id) return;
        const newDate=pill.dataset.date;
        if(newDate<todayStr()){ alert('No puedes reprogramar al pasado'); return; }
        const card=$(`.card[data-id="${CSS.escape(id)}"]`); if(!card) return;
        const fromCol=card.closest('.column')?.dataset.col||'pendiente';
        let sendEstado=fromCol; if(sendEstado==='en_curso' && newDate>todayStr()) sendEstado='pendiente';
        try{
          const result=await moveTask(id,sendEstado,newDate);
          const finalDate=result?.fecha||newDate; const finalEstado=result?.estado||sendEstado;
          if(finalDate!==selectedDay){ card.remove(); } else if(finalEstado!==fromCol){ const dest=$(`.dropzone[data-status="${finalEstado}"]`); if(dest) dest.appendChild(card); }
          updateCountsUI(); refreshCounts();
          if(finalDate!==selectedDay){ selectedDay=finalDate; buildDays(selectedDay); await loadBoard(selectedDay); }
          toggleTodayDisabled();
        }catch(err){ console.error(err); alert('No se pudo reprogramar la actividad'); }
      });

      daysScroll.appendChild(pill);
    }
    refreshCounts(); toggleTodayDisabled();
  }

  async function refreshCounts(){
    try{
      const res=await fetch(`${API_COUNTS}?start=${encodeURIComponent(rangeStart)}&days=10`,{ headers:{'Accept':'application/json'}, credentials:'same-origin', cache:'no-store' });
      if(!res.ok) return;
      const p=await res.json(); const perPend=p?.per_day||{}; const perDone=p?.per_day_done||{}; const total=p?.total||0;
      $$('#daysScroll .day-pill').forEach(pill=>{
        const d=pill.dataset.date; const cPend=perPend[d]||0; const cDone=perDone[d]||0;
        const bp=$('.badge-pend',pill), bd=$('.badge-done',pill);
        if(bp){ bp.textContent=String(cPend); bp.style.display=cPend>0?'flex':'none'; }
        if(bd){ bd.textContent=String(cDone); bd.style.display=cDone>0?'flex':'none'; }
        pill.classList.toggle('has-pending',cPend>0); pill.classList.toggle('has-done',cDone>0);
      });
      const totalEl=$('#pendTotal'); if(totalEl) totalEl.textContent=String(total);
    }catch(_){}
  }

  // ===== Navegación =====
  $('#prevDays')?.addEventListener('click',()=>{ const d=new Date(rangeStart+'T00:00:00'); d.setDate(d.getDate()-10); selectedDay=fmtDate(d); buildDays(selectedDay); loadBoard(selectedDay); toggleTodayDisabled(); });
  $('#nextDays')?.addEventListener('click',()=>{ const d=new Date(rangeStart+'T00:00:00'); d.setDate(d.getDate()+10); selectedDay=fmtDate(d); buildDays(selectedDay); loadBoard(selectedDay); toggleTodayDisabled(); });
  $('#btnToday')?.addEventListener('click',async()=>{ selectedDay=todayStr(); buildDays(selectedDay); await loadBoard(selectedDay); toggleTodayDisabled(); });

  // ===== Nueva actividad =====
  const taskMask=$('#taskMask');

  function resetNewForm(){
    const f=$('#taskForm'); if(!f) return;
    f.reset();
    ['cliente_id','cliNombre','cliTel','cliDir','cliLat','cliLng','cliPPP','cliPrecinto','dbLat','dbLng'].forEach(id=>{ const el=$('#'+id); if(el) el.value=''; });
    const ul=$('#sugList'); if(ul) ul.innerHTML='';
    const sugBox=$('.sug'); if(sugBox) sugBox.classList.remove('show'); // <<< FIX
    const wrap=$('#tipoOtroWrap'); if(wrap) wrap.style.display='none';
    const hint=$('#coordHint'); if(hint) hint.textContent='';
  }
  function ensureMotivoUI(){
    const tipoSel=$('#taskForm [name="tipo"]'); if(!tipoSel) return;
    let wrap=$('#tipoOtroWrap');
    if(!wrap){ wrap=document.createElement('div'); wrap.id='tipoOtroWrap'; wrap.style.cssText='display:none;margin-top:.5rem'; wrap.innerHTML=`<label class="lbl">Motivo (si eliges OTRO)</label><input class="inp" id="tipoOtro" name="tipo_otro" placeholder="Escribe el motivo..." />`; const parent=tipoSel.closest('div')||tipoSel.parentElement; parent&&parent.insertAdjacentElement('afterend',wrap); }
    const sync=()=>{ wrap.style.display=(String(tipoSel.value).toUpperCase()==='OTRO')?'':'none'; };
    tipoSel.addEventListener('change',sync); sync();
  }

  $('#openNew')?.addEventListener('click',()=>{ resetNewForm(); ensureMotivoUI(); const inputFecha=$('#fecha'); const hoy=todayStr(); const value=(selectedDay<hoy)?hoy:selectedDay; if(inputFecha){ inputFecha.min=hoy; inputFecha.value=value; } openMask(taskMask); setTimeout(()=>{ const bc=$('#buscaCliente'); if(bc) bc.focus(); },30); });
  enableBackdropClose('#taskMask','#taskMask [data-close]',resetNewForm);

  function enhanceDateInput(sel){
    const input=$(sel); if(!input) return;
    input.setAttribute('inputmode','none'); const show=()=>{ try{ if(input.showPicker) input.showPicker(); }catch(_){} };
    input.addEventListener('focus',show); input.addEventListener('click',show); input.addEventListener('keydown',(e)=>{ e.preventDefault(); });
  }
  enhanceDateInput('#fecha');

  
  // Autocomplete cliente
  let sugT=null;
  $('#buscaCliente')?.addEventListener('input',(e)=>{
    const q=e.target.value.trim();
    clearTimeout(sugT);

    const ul=$('#sugList');
    const box=$('.sug');

    if(!q){
      if(ul) ul.innerHTML='';
      if(box) box.classList.remove('show');
      return;
    }

    sugT=setTimeout(async()=>{
      try{
        const res=await fetch(`/clientes/buscar?s=${encodeURIComponent(q)}`,{
          headers:{'Accept':'application/json'},
          credentials:'same-origin'
        });
        if(!res.ok) return;

        const payload=await res.json();
        const arr=Array.isArray(payload?.data)?payload.data:[];

        if(!ul) return;
        ul.innerHTML='';

        arr.forEach(it=>{
          const li=el('div','sug-item');
          li.textContent=`${it.nombre} — ${it.id_externo ?? '...'}`;

          li.addEventListener('click',()=>{
            const idEl=$('#cliente_id');
            const nomEl=$('#cliNombre');
            const telEl=$('#cliTel');
            const dirEl=$('#cliDir');

            if(idEl)  idEl.value  = it.id ?? '';
            if(nomEl) nomEl.value = it.nombre ?? '';
            if(telEl) telEl.value = it.telefono || it.movil || '';
            if(dirEl) dirEl.value = it.direccion || '';

            // Plan desde cliente
            const planSel=$('#plan');
            const planRaw = it.el_plan || it.plan;
            if(planSel && planRaw){
              const planCli=String(planRaw).toUpperCase().trim();
              let set=false;
              Array.from(planSel.options).forEach(op=>{
                const val=String(op.value||'').toUpperCase().trim();
                if(!set && val===planCli){
                  planSel.value=op.value;
                  set=true;
                }
              });
            }

            // PPP / Precinto
            const pppEl=$('#cliPPP');
            const precEl=$('#cliPrecinto');
            if(pppEl)  pppEl.value  = it.user_ppp_hotspot || '';
            if(precEl) precEl.value = it.precinto || '';

            // Coordenadas (desde BD, con uso por defecto)
            const latEl=$('#cliLat');
            const lngEl=$('#cliLng');
            const dbLatEl=$('#dbLat');
            const dbLngEl=$('#dbLng');
            const hint=$('#coordHint');

            let dbLat=null;
            let dbLng=null;
            if(typeof it.coordenadas==='string'){
              const match=it.coordenadas.match(/-?\d+(?:\.\d+)?/g);
              if(match && match.length>=2){
                dbLat=match[0].trim();
                dbLng=match[1].trim();
              }
            }

            if(dbLatEl) dbLatEl.value=dbLat || '';
            if(dbLngEl) dbLngEl.value=dbLng || '';

            // Por defecto usamos las coordenadas de la BD si existen
            if(latEl) latEl.value=dbLat || '';
            if(lngEl) lngEl.value=dbLng || '';

            if(hint){
              if(dbLat && dbLng){
                hint.textContent='✅ Cliente con coordenadas guardadas en la BD. Se usarán por defecto.';
              }else{
                hint.textContent='⚠️ Cliente sin coordenadas guardadas. Usa el botón "Mapa" para fijarlas.';
              }
            }

// Cambiar tipo a ASISTENCIA por defecto si estaba vacío o en INSTALACION
            const tipoSel=$('#tipo');
            if(tipoSel){
              const cur=String(tipoSel.value||'').toUpperCase();
              if(!cur || cur==='INSTALACION'){
                tipoSel.value='ASISTENCIA';
                tipoSel.dispatchEvent(new Event('change',{bubbles:true}));
              }
            }

            if(box) box.classList.remove('show');
            ul.innerHTML='';
          });

          ul.appendChild(li);
        });

        if(box) box.classList.toggle('show',arr.length>0);
      }catch(_){}
    },300);
  });
// Guardar nueva
  $('#taskForm')?.addEventListener('submit',async(e)=>{
    e.preventDefault();
    const fd=new FormData(e.currentTarget);
    const _lat=$('#cliLat')?.value?.trim(); const _lng=$('#cliLng')?.value?.trim();
    if(_lat) fd.set('lat',_lat); if(_lng) fd.set('lng',_lng);

    const newDate=String(fd.get('fecha')); const hoy=todayStr();
    if(newDate<hoy){ alert('No puedes agendar en una fecha anterior a hoy.'); return; }

    const tipoSel=String((fd.get('tipo')||'')).toUpperCase(); const motivo=(fd.get('tipo_otro')||'').toString().trim();
    if(tipoSel==='OTRO'&&!motivo){ alert('Debes ingresar el motivo cuando el tipo es OTRO.'); $('#tipoOtro')?.focus(); return; }
    if(tipoSel==='OTRO'&&motivo){ const d=(fd.get('descripcion')||'').toString(); fd.set('descripcion',motivo+(d?'\n\n'+d:'')); }

    const noCliente=!fd.get('cliente_id'); const manualNombre=$('#cliNombre')?.value?.trim();
    if(noCliente&&manualNombre){ fd.set('titulo',upper(manualNombre)); }
    else if(fd.get('titulo')){ fd.set('titulo',upper(String(fd.get('titulo')))); }

    try{
      const res=await fetch('/agenda',{ method:'POST', headers:{'X-CSRF-TOKEN':csrf(),'Accept':'application/json'}, body:fd, credentials:'same-origin' });
      const payload=await res.json().catch(()=>({}));
      if(!res.ok||!payload.ok){ console.error('save error',payload); alert('No se pudo guardar la actividad'); return; }

      if(newDate===selectedDay){
        const tipo=String(fd.get('tipo'));
        const cardData={
          id:payload.id,
          titulo:(fd.get('titulo') && String(fd.get('titulo')).trim()) || upper(tipo),
          tipo:tipo,
          plan:upper(String(fd.get('plan')||'')),
          notas:String(fd.get('descripcion')||''),
          coord_lat:_lat?Number(_lat):null,
          coord_lng:_lng?Number(_lng):null,
          cliente:($('#cliente_id').value ? {
            id:$('#cliente_id').value, nombre:upper($('#cliNombre').value||''), telefono:$('#cliTel').value||null, direccion:upper($('#cliDir').value||''), coordenadas:(_lat&&_lng)?`${_lat},${_lng}`:null
          } : (manualNombre ? {
            id:null, nombre:upper(manualNombre), telefono:$('#cliTel').value||null, direccion:upper($('#cliDir').value||''), coordenadas:(_lat&&_lng)?`${_lat},${_lng}`:null
          } : null))
        };
        const dest=$('.dropzone[data-status="pendiente"]'); if(dest) dest.prepend(buildCard(cardData,'pendiente'));
        wireDnD(); updateCountsUI();
      }

      incDayBadge(newDate,+1); incTotalPending(+1); refreshCounts();
      resetNewForm(); closeMask(taskMask);
    }catch(err){ console.error(err); alert('No se pudo guardar la actividad'); }
  });

  // Pendientes
  const pendMask=$('#pendMask');
  $('#btnPend')?.addEventListener('click',async()=>{
    try{
      const res=await fetch(API_PENDS,{ headers:{'Accept':'application/json'}, credentials:'same-origin' });
      if(!res.ok) throw new Error('pend');
      const p=await res.json(); const list=p?.data||[]; const box=$('#pendList'); if(!box) return;
      box.innerHTML=''; if(!list.length){ box.innerHTML='<p>No hay actividades pendientes.</p>'; }
      else{ list.forEach(t=>{ const div=el('div','pend-item'); div.innerHTML=`<strong>${escapeHtml(t.titulo||'(SIN TÍTULO)')}</strong><br><small>${escapeHtml(t.fecha||'')} · ${escapeHtml(t.cliente?.nombre ?? '—')}</small>`; div.addEventListener('click',async()=>{ selectedDay=t.fecha; buildDays(selectedDay); await loadBoard(selectedDay); closeMask(pendMask); toggleTodayDisabled(); }); box.appendChild(div); }); }
      openMask(pendMask);
    }catch(err){ console.error(err); alert('No se pudo cargar la lista de pendientes'); }
  });
  enableBackdropClose('#pendMask','#pendMask [data-close-pend]');


  
// Map picker interno (modal con Google Maps en #mapMask)
  document.addEventListener('click',(ev)=>{
    const btn = ev.target.closest('#openMap');
    if(!btn) return;
    ev.preventDefault();
    openMapSelector();
  });

  
  // Guardar ubicación desde el modal de mapa
  document.addEventListener('click', (ev) => {
    const saveBtn = ev.target.closest('#save-coords-btn');
    if (!saveBtn) return;
    ev.preventDefault();
    const mask = document.getElementById('mapMask');
    if (mask) closeMask(mask);
  });

  // Cerrar modal de mapa con botones que tengan data-close-map
  document.addEventListener('click', (ev) => {
    const closeBtn = ev.target.closest('[data-close-map]');
    if (!closeBtn) return;
    ev.preventDefault();
    const mask = document.getElementById('mapMask');
    if (mask) closeMask(mask);
  });


  // Vista previa de ubicación (modal interno con mapa)

  document.addEventListener('click',(ev)=>{
    const btn=ev.target.closest('#btnPreviewCoords'); if(!btn) return; ev.preventDefault();
    const dbLat=$('#dbLat')?.value?.trim();
    const dbLng=$('#dbLng')?.value?.trim();
    if(!dbLat || !dbLng){
      alert('Este cliente no tiene coordenadas guardadas.');
      return;
    }
    const mask=$('#coordsMask');
    const frame=$('#coordsFrame');
    if(frame){
      const url='https://www.google.com/maps?q='+encodeURIComponent(dbLat+','+dbLng)+'&z=18&output=embed';
      frame.src=url;
    }
    if(mask) openMask(mask);
  });
  enableBackdropClose('#mapMask','#mapMask [data-close-map]');
  enableBackdropClose('#coordsMask','#coordsMask [data-close]');

  // Init
  document.addEventListener('DOMContentLoaded',()=>{
    const hoy=todayStr(); if(selectedDay<hoy) selectedDay=hoy;
    $$('.mask').forEach(m=>{ m.classList.remove('show'); m.style.pointerEvents='none'; m.removeAttribute('aria-hidden'); m.style.zIndex=''; });
    buildDays(selectedDay); loadBoard(selectedDay); toggleTodayDisabled();
  });
})();
