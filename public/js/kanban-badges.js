/* Globos de notificación para Agenda - carrusel de días
   Requisitos:
   - En la vista existe #kanbanBoard con data-endpoint-counts (-> /agenda/counts)
   - En el carrusel, cada día tiene atributo data-date="YYYY-MM-DD"
*/

(function () {
  const board = document.getElementById('kanbanBoard');
  if (!board) return;

  const countsUrl = board.dataset.endpointCounts;
  if (!countsUrl) return;

  const daysRoot = document.getElementById('daysScroll');
  if (!daysRoot) return;

  // Cache por fecha para evitar spam al backend
  const cache = new Map();
  let inflight = new Map();

  async function fetchCounts(fecha) {
    if (cache.has(fecha)) return cache.get(fecha);

    // Reusar request en vuelo
    if (inflight.has(fecha)) return inflight.get(fecha);

    const p = fetch(`${countsUrl}?fecha=${encodeURIComponent(fecha)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`)))
      .then(j => {
        // Esperamos { ok:true, data:{pendiente: n, en_curso: n, completado: n} }
        if (!j || !j.ok || !j.data) throw new Error('Formato inválido');
        cache.set(fecha, j.data);
        return j.data;
      })
      .catch(err => {
        console.warn('No se pudieron obtener counts para', fecha, err);
        const empty = { pendiente: 0, en_curso: 0, completado: 0 };
        cache.set(fecha, empty);
        return empty;
      })
      .finally(() => inflight.delete(fecha));

    inflight.set(fecha, p);
    return p;
  }

  function ensureBadge(el, side, color) {
    const cls = `day-badge ${side} ${color}`;
    let badge = el.querySelector(`.${side}.day-badge.${color}`);
    if (!badge) {
      badge = document.createElement('span');
      badge.className = cls;
      el.appendChild(badge);
    }
    return badge;
  }

  function isDayNode(node) {
    if (!(node instanceof HTMLElement)) return false;
    // Debe tener data-date="YYYY-MM-DD"
    const d = node.getAttribute('data-date');
    return !!d && /^\d{4}-\d{2}-\d{2}$/.test(d);
  }

  async function paintDay(el) {
    const fecha = el.getAttribute('data-date');
    if (!fecha) return;

    const counts = await fetchCounts(fecha);

    // Pendientes (izquierda, rojo)
    const left = ensureBadge(el, 'left', 'red');
    left.textContent = counts.pendiente > 0 ? String(counts.pendiente) : '';

    // Completadas (derecha, verde)
    const right = ensureBadge(el, 'right', 'green');
    right.textContent = counts.completado > 0 ? String(counts.completado) : '';

    // Si no hay nada, oculta
    left.style.display = left.textContent ? 'inline-block' : 'none';
    right.style.display = right.textContent ? 'inline-block' : 'none';
  }

  function paintAllVisibleDays() {
    const nodes = daysRoot.querySelectorAll('[data-date]');
    nodes.forEach(n => paintDay(n));
  }

  // Observa cambios del carrusel (cuando cambias de día/semana)
  const mo = new MutationObserver((mutations) => {
    for (const m of mutations) {
      if (m.type === 'childList') {
        m.addedNodes.forEach(n => {
          if (isDayNode(n)) paintDay(n);
          // También si se agrega un contenedor completo
          if (n.querySelectorAll) {
            n.querySelectorAll('[data-date]').forEach(el => paintDay(el));
          }
        });
      } else if (m.type === 'attributes' && isDayNode(m.target) && m.attributeName === 'data-date') {
        paintDay(m.target);
      }
    }
  });

  mo.observe(daysRoot, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-date'] });

  // Primera pasada
  paintAllVisibleDays();

  // Exponer una API mínima por si tu JS principal quiere forzar refresco
  window.AgendaBadges = {
    refresh(fecha) {
      if (fecha) cache.delete(fecha);
      paintAllVisibleDays();
    },
    clearCache() { cache.clear(); }
  };
})();
