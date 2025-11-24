// public/js/clientes.js (v5.2)
(function () {
  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  // ===========================
  //  Helpers de URL (evitan http y duplicados)
  // ===========================
  function normalizeUrl(url) {
    // Fuerza mismo host y protocolo que la página, y devuelve solo path+query
    const u = new URL(url, location.origin);
    u.protocol = location.protocol;
    u.host     = location.host;
    return u.pathname + u.search;
  }
  function withPartial(url) {
    const u = new URL(normalizeUrl(url), location.origin);
    if (!u.searchParams.has('partial')) u.searchParams.set('partial','1');
    return u.pathname + u.search; // siempre relativo, sin http/https
  }

  // ===========================
  //  A) AJAX: cargar tabla
  // ===========================
  const AREA_ID = '#clientes-area';

  async function loadTable(url, {push=false} = {}) {
    const area = $(AREA_ID);
    if (!area) return;

    area.style.opacity = '0.6';

    const reqUrl  = withPartial(url);         // para fetch (incluye partial=1)
    const histUrl = normalizeUrl(url);        // para la barra de direcciones (sin partial)

    const res  = await fetch(reqUrl, { headers: { 'X-Requested-With':'fetch' } });
    if (!res.ok) { area.style.opacity=''; alert('No se pudo actualizar la tabla'); return; }

    const html = await res.text();
    area.innerHTML = html;
    area.style.opacity = '';

    // Actualiza la barra de direcciones SIN recargar
    if (push) history.pushState(null, '', histUrl);
    else       history.replaceState(null, '', histUrl);

    // Re-enganchar eventos sobre el HTML nuevo
    wireDynamicInsideArea();
  }

  // Construye URL desde el formulario (devuelve path+query)
  function formToUrl(form) {
    const params = new URLSearchParams(new FormData(form));
    const base   = form.getAttribute('action') || location.pathname;
    return normalizeUrl(base + '?' + params.toString());
  }

  // Volver/avanzar del navegador
  window.addEventListener('popstate', () => {
    loadTable(location.pathname + location.search, {push:false});
  });

  // ===========================
  //  B) Buscador con debounce (AJAX) + botón ✕
  // ===========================
  function wireSearchDebounce() {
    const form = $('.toolbar');
    if (!form) return;

    const input    = $('#filtro-s') || $('input[name="s"]', form);
    const clearBtn = $('#btn-clear-s');
    const selects  = $$('select', form);

    const toggleX = () => { if (clearBtn) clearBtn.style.display = input && input.value ? 'inline-flex' : 'none'; };
    toggleX();

    let t;
    const submitAjax = (push=false) => {
      clearTimeout(t);
      t = setTimeout(() => {
        // al escribir, ir siempre a página 1
        const page = form.querySelector('input[name="page"]');
        if (page) page.remove();
        const url = formToUrl(form);
        loadTable(url, {push});
      }, 450);
    };

    if (input) {
      input.addEventListener('input', () => { toggleX(); submitAjax(false); });
      input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); submitAjax(true); } });
    }

    if (clearBtn && input) {
      clearBtn.addEventListener('click', (e) => {
        e.preventDefault();
        input.value = '';
        input.focus();
        toggleX();
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    }

    selects.forEach(sel => sel.addEventListener('change', () => submitAjax(true)));
  }

  // ===========================
  //  C) Modal de ficha (JSON)
  // ===========================
  function injectStylesOnce() {
    if (document.getElementById("cliente-modal-styles")) return;
    const css = `
      .c-mask{position:fixed;inset:0;background:rgba(15,23,42,.55);display:flex;align-items:center;justify-content:center;z-index:9999}
      .c-modal{width:min(900px,96vw);max-height:90vh;overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
      .c-hd{display:flex;align-items:center;justify-content:space-between;padding:.9rem 1rem;border-bottom:1px solid #e5e7eb}
      .c-ttl{margin:0;font-size:1.05rem}
      .c-x{background:none;border:0;font-size:1.2rem;cursor:pointer;padding:.25rem .5rem}
      .c-bd{padding:1rem}
      .grid{display:grid;grid-template-columns:repeat(2, minmax(0,1fr));gap:.75rem}
      .row{display:flex;gap:.5rem}
      .key{min-width:160px;color:#6b7280}
      .val{color:#0f172a}
      @media (max-width:700px){ .grid{grid-template-columns:1fr} .key{min-width:120px} }
    `;
    const style = document.createElement("style");
    style.id = "cliente-modal-styles";
    style.textContent = css;
    document.head.appendChild(style);
  }

  function fmt(v) { return (v === null || v === undefined || v === '') ? '—' : String(v); }

  function buildRows(obj) {
    const fields = [
      ["ID externo", "id_externo"],
      ["Nombre", "nombre"],
      ["Dirección", "direccion"],
      ["Móvil", "movil"],
      ["Correo", "correo"],
      ["Teléfono", "telefono"],
      ["Plan", (o) => o.el_plan || o.plan],
      ["Valor plan", "valor_plan"],
      ["Estado", "estado"],
      ["Día de pago", "dia_pago"],
      ["Fecha de pago", "fecha_pago"],
      ["Instalado", "instalado"],
      ["Cédula", "cedula"],
      ["Usuario PPP/Hotspot", "user_ppp_hotspot"],
      ["Coordenadas", "coordenadas"],
      ["Precinto", "precinto"],
      ["Creado", "created_at"],
      ["Actualizado", "updated_at"],
    ];

    return fields.map(([label, key]) => {
      const value = typeof key === "function" ? key(obj) : obj[key];
      let shown = fmt(value);
      if (label === "Coordenadas" && value && String(value).includes(",")) {
        const [lat, lng] = String(value).split(",").map(s => s.trim());
        const murl = `https://www.google.com/maps?q=${encodeURIComponent(lat)},${encodeURIComponent(lng)}`;
        shown = `<a class="link" href="${murl}" target="_blank" rel="noopener">${lat}, ${lng}</a>`;
      }
      return `<div class="row"><div class="key">${label}</div><div class="val">${shown}</div></div>`;
    }).join("");
  }

  function openModal(cliente) {
    injectStylesOnce();
    const mask = document.createElement("div");
    mask.className = "c-mask";
    mask.innerHTML = `
      <div class="c-modal" role="dialog" aria-modal="true">
        <div class="c-hd">
          <h3 class="c-ttl">Ficha del cliente</h3>
          <button class="c-x" aria-label="Cerrar">✕</button>
        </div>
        <div class="c-bd">
          <div class="grid">${buildRows(cliente)}</div>
        </div>
      </div>
    `;
    document.body.appendChild(mask);

    function close() { mask.remove(); document.removeEventListener('keydown', onKey); }
    function onKey(e){ if (e.key === 'Escape') close(); }

    mask.addEventListener("click", (e) => { if (e.target === mask) close(); });
    $(".c-x", mask).addEventListener("click", close);
    document.addEventListener('keydown', onKey);
  }

  async function fetchCliente(id) {
    const endpoints = [ `/api/clientes/${id}`, `/clientes/${id}` ];
    let lastErr;

    for (const url of endpoints) {
      try {
        const res = await fetch(url, { headers: { "Accept": "application/json" } });
        if (!res.ok) { lastErr = new Error(`HTTP ${res.status} en ${url}`); continue; }
        const payload = await res.json();
        const data = payload?.data ?? payload;
        if (!data || typeof data !== "object") { lastErr = new Error(`Respuesta inválida de ${url}`); continue; }
        return data;
      } catch (e) { lastErr = e; }
    }
    throw lastErr || new Error("No se pudo obtener el cliente");
  }

  // ===========================
  //  D) Eventos dentro del área AJAX
  // ===========================
  function wireDynamicInsideArea() {
    // Modal
    $$(AREA_ID + ' .cliente-link').forEach(a => {
      a.addEventListener('click', async (e) => {
        e.preventDefault();
        const id = a.getAttribute('data-id');
        if (!id) return;
        try { openModal(await fetchCliente(id)); }
        catch (err) { console.error(err); alert('No se pudo cargar la ficha'); }
      });
    });

    // Paginación por AJAX
    $$(AREA_ID + ' .pagination a').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        // Tomamos el href tal cual (absoluto o relativo) y lo normalizamos
        const href = a.getAttribute('href') || a.href;
        loadTable(href, {push:true});
      });
    });

    // Orden por AJAX
    $$(AREA_ID + ' thead a').forEach(a => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const href = a.getAttribute('href') || a.href;
        loadTable(href, {push:true});
      });
    });
  }

  // ===========================
  //  E) Inicialización
  // ===========================
  document.addEventListener('DOMContentLoaded', () => {
    wireSearchDebounce();   // búsqueda en vivo sin perder el foco
    wireDynamicInsideArea();// modal, paginación y ordenamiento por AJAX
  });
})();
