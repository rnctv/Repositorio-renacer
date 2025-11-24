// public/js/tecnico.js  v1.10
(function () {
  const $  = (s, c=document) => c.querySelector(s);
  const $$ = (s, c=document) => Array.from(c.querySelectorAll(s));

  // OJO: el contenedor en Blade es #tecBoard
  const root = $('#tecBoard');
  if (!root) return;

  // Endpoints y zona horaria (opcional)
  const API_LIST = root.dataset.endpointList || '/agenda/events';
  const TZ       = root.dataset.tz || '';

  // ===== Helpers de fecha con TZ =====
  function toYMDWithTZ(date, tz){
    try { return new Date(date).toLocaleDateString('en-CA', { timeZone: tz }); }
    catch {}
    const d = new Date(date);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0,10);
  }
  function todayStr(){ return TZ ? toYMDWithTZ(new Date(), TZ) : toYMDWithTZ(new Date(), undefined); }

  // Estado
  let today = todayStr();

  // DOM
  const listCurso = $('#listCurso');
  const listPend  = $('#listPend');
  const cCurso    = $('#cCurso');
  const cPend     = $('#cPend');
  const daySpan   = $('#tecDay');

  // ===== Net =====
  async function fetchJSON(url, opt={}){
    const res = await fetch(url, Object.assign({
      headers:{'Accept':'application/json'},
      credentials:'same-origin',
      cache:'no-store'
    }, opt));
    if (!res.ok) throw new Error('http '+res.status);
    return await res.json();
  }

  // ===== Parsing de cliente/manual =====
  function coordFromText(text){
    if (!text) return null;
    const m = String(text).match(/(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)/);
    if (!m) return null;
    const lat = parseFloat(m[1]), lng = parseFloat(m[2]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    return {lat, lng};
  }
  function parseManualFields(text){
    const s = String(text || '');
    const get = (labels) => {
      const re = new RegExp('(?:' + labels + ')\\s*:\\s*([^\\n\\r]*)', 'i');
      const m = s.match(re);
      return (m && typeof m[1] === 'string') ? m[1].trim() : null;
    };
    const nombre = get('Nombre|Cliente');
    const tel    = get('Tel[eé]fono|Telefono|Tel');
    const dir    = get('Direcci[oó]n|Direccion');
    let coords = null;
    const coordLbl = get('Coordenadas|Coords');
    if (coordLbl){ const c = coordFromText(coordLbl); if (c) coords = `${c.lat},${c.lng}`; }
    if (!coords){ const c2 = coordFromText(s); if (c2) coords = `${c2.lat},${c2.lng}`; }
    return { nombre, telefono: tel, direccion: dir, coordenadas: coords };
  }

  // ===== UI =====
  function buildItem(t){
    const notesText = String(t.notas || t.descripcion || '');
    const parsed = parseManualFields(notesText);

    let coordsStr = null;
    if (t.cliente?.coordenadas) coordsStr = String(t.cliente.coordenadas);
    else if (parsed.coordenadas) coordsStr = parsed.coordenadas;
    else if (t.coordenadas) coordsStr = String(t.coordenadas);
    else { const c = coordFromText(notesText); if (c) coordsStr = `${c.lat},${c.lng}`; }

    const displayName = t.titulo || t.cliente?.nombre || parsed.nombre || '(sin cliente)';
    const tel = t.cliente?.telefono || parsed.telefono || '—';
    const dir = t.cliente?.direccion || parsed.direccion || '—';
    const actPlan = `${t.tipo ? String(t.tipo).toUpperCase() : ''}${t.plan ? ' — '+t.plan : ''}`;

    const li = document.createElement('div');
    li.className = 'tec-item';
    li.dataset.id = t.id;
    li.innerHTML = `
      <div class="tec-title"><strong>${displayName}</strong></div>
      <div class="tec-sub">${actPlan}</div>
      <div class="tec-dir">${dir}</div>
      <div class="tec-tel">Tel: ${tel}</div>
      ${coordsStr ? `<div class="tec-map"><a href="https://www.google.com/maps?q=${encodeURIComponent(coordsStr)}" target="_blank" rel="noopener">🗺️ Mapa</a></div>` : ''}
    `;
    // Abrir detalle (opcional)
    li.addEventListener('click', () => openTecView(t.id));
    // Evitar que el link del mapa abra el modal
    li.querySelectorAll('a').forEach(a=>{
      a.addEventListener('click', e=>{ e.stopPropagation(); e.stopImmediatePropagation(); });
    });
    return li;
  }

  function renderDay(groups){
    // Solo mostramos pendiente y en_curso (no completado)
    listCurso.innerHTML = '';
    listPend.innerHTML  = '';
    (groups.en_curso || []).forEach(t => listCurso.appendChild(buildItem(t)));
    (groups.pendiente || []).forEach(t => listPend.appendChild(buildItem(t)));
    // Contadores
    if (cCurso) cCurso.textContent = String((groups.en_curso || []).length);
    if (cPend)  cPend.textContent  = String((groups.pendiente || []).length);
  }

  async function loadToday(){
    today = todayStr();
    const p = await fetchJSON(`${API_LIST}?fecha=${encodeURIComponent(today)}`);
    const data = p?.data || {pendiente:[], en_curso:[], completado:[]};
    renderDay(data);
    if (daySpan) daySpan.textContent = today;
  }

  // Utilidad para quitar inmediatamente una tarjeta por ID
  function removeItemById(id){
    $$(`.tec-item[data-id="${CSS.escape(String(id))}"]`).forEach(n => n.remove());
    if (cCurso) cCurso.textContent = String($('#listCurso')?.children.length || 0);
    if (cPend)  cPend.textContent  = String($('#listPend')?.children.length  || 0);
  }

  // ===== Modal de detalle =====
  function fillReadonly(selector){
    const root = $(selector); if (!root) return;
    root.querySelectorAll('input, textarea, select').forEach(el => { el.readOnly = true; if (el.tagName === 'SELECT') el.disabled = true; });
  }
  function coordToLinkOrNull(d){
    if (d?.cliente?.coordenadas) return `https://www.google.com/maps?q=${encodeURIComponent(d.cliente.coordenadas)}`;
    const c = coordFromText(d?.descripcion || d?.notas || ''); if (c) return `https://www.google.com/maps?q=${encodeURIComponent(`${c.lat},${c.lng}`)}`;
    if (d?.coordenadas) return `https://www.google.com/maps?q=${encodeURIComponent(d.coordenadas)}`;
    return null;
  }
  async function openTecView(id){
    try{
      const res = await fetch(`/agenda/tareas/${encodeURIComponent(id)}`, { headers:{'Accept':'application/json'}, credentials:'same-origin', cache:'no-store' });
      if (!res.ok) throw new Error('detalle');
      const p = await res.json(); const d = p?.data;

      $('#tv_titulo').value    = d.titulo ?? '';
      $('#tv_estado').value    = d.estado ?? '';
      $('#tv_tipo').value      = d.tipo   ?? '';
      $('#tv_plan').value      = d.plan   ?? '';
      $('#tv_fecha').value     = d.fecha  ?? '';
      $('#tv_cliente').value   = d.cliente?.nombre    || '';
      $('#tv_direccion').value = d.cliente?.direccion || '';
      $('#tv_telefono').value  = d.cliente?.telefono  || '';
      $('#tv_desc').value      = d.descripcion ?? '';

      const link = coordToLinkOrNull(d);
      const wrap = $('#tv_map_wrap'); const a = $('#tv_map');
      if (link){ a.href = link; wrap.style.display=''; } else { wrap.style.display='none'; }

      fillReadonly('#tecViewMask');
      openMask($('#tecViewMask'));
    }catch(err){ console.error(err); }
  }
  function openMask(maskEl){ if (!maskEl) return; maskEl.classList.add('show'); maskEl.setAttribute('aria-hidden','false'); }
  function closeMask(maskEl){ if (!maskEl) return; maskEl.classList.remove('show'); maskEl.setAttribute('aria-hidden','true'); }
  (function enableBackdropClose(){
    const mask = $('#tecViewMask'); if (!mask) return;
    const doClose = ()=> closeMask(mask);
    mask.addEventListener('click', e=>{ if (e.target === mask) doClose(); });
    $$('#tecViewMask [data-close]').forEach(b=> b.addEventListener('click', doClose));
    document.addEventListener('keydown', e=>{ if (e.key === 'Escape' && mask.classList.contains('show')) doClose(); });
  })();

  // ===== Realtime (Echo) + fallback =====
  let echoChan = null;
  function unsubscribe(){
    try{
      if (window.Echo && echoChan && echoChan.stopListening) echoChan.stopListening('.TareaChanged');
      if (window.Echo && echoChan && echoChan.unsubscribe) echoChan.unsubscribe();
    }catch(_){}
    echoChan = null;
  }
  function handleRtEvent(evt){
    // evt: { action: 'created|moved|updated|deleted', tarea:{...}, oldDate?:'YYYY-MM-DD' }
    const a = evt?.action; const t = evt?.tarea || {};
    if (!a) return;

    // Si la tarea ya NO pertenece al día de hoy, elimínala al instante
    if ((a === 'moved' || a === 'deleted') && t?.fecha && t.fecha !== today){
      removeItemById(t.id);
      return;
    }
    // Para cualquier cambio, recargamos “hoy” (garantiza mapas/estado frescos)
    loadToday().catch(()=>{});
  }
  function subscribeFor(dateStr){
    unsubscribe();
    if (!(window.Echo && window.Echo.connector)) return;
    const channelName = `tareas-${dateStr}`;
    echoChan = window.Echo.private ? window.Echo.private(channelName) : window.Echo.channel(channelName);
    echoChan.listen('.TareaChanged', handleRtEvent);
  }
  function wireRealtimeAndPolling(){
    if (window.Echo && window.Echo.connector){
      subscribeFor(today);
      // Reconciliación incluso con Echo (por si se pierde un evento)
      setInterval(() => loadToday().catch(()=>{}), 45000);
    }else{
      // Fallback por polling frecuente (5s)
      setInterval(() => loadToday().catch(()=>{}), 5000);
    }
    // Refrescar cuando vuelve a la pestaña o enfoca
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') loadToday().catch(()=>{});
    });
    window.addEventListener('focus', () => loadToday().catch(()=>{}));
    // Detectar cambio de día y re-suscribir
    setInterval(() => {
      const now = todayStr();
      if (now !== today){
        today = now;
        loadToday().catch(()=>{});
        if (window.Echo && window.Echo.connector) subscribeFor(today);
      }
    }, 15000);
  }

  // Botón manual de refresh
  $('#btnRefTec')?.addEventListener('click', () => loadToday().catch(()=>{}));

  // ===== Init =====
  document.addEventListener('DOMContentLoaded', () => {
    loadToday().catch(()=>{});
    wireRealtimeAndPolling();
  });
})();
