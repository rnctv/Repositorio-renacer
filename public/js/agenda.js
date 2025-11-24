(function(){
  const $  = (s,ctx=document)=>ctx.querySelector(s);
  const $$ = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // ===== A) Calendario =====
  let calendar;
  document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('calendar');
    if (!el) return;

    calendar = new FullCalendar.Calendar(el, {
      initialView: 'dayGridMonth',
      locale: 'es',
      height: 'auto',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      },
      events: '/agenda/events'
    });

    calendar.render();
    wireModal();
    wireAutocomplete();
  });

  // ===== B) Modal Nueva Actividad =====
  function wireModal(){
    const mask = $('#modal-nuevo');
    const btn  = $('#btn-nuevo');

    const open = ()=>{ mask.classList.add('show'); };
    const close= ()=>{ mask.classList.remove('show'); resetForm(); };

    btn?.addEventListener('click', open);
    $$('#modal-nuevo [data-close]').forEach(b=> b.addEventListener('click', close));
    mask.addEventListener('click', (e)=>{ if(e.target === mask) close(); });

    // Enviar formulario (AJAX)
    const form = $('#form-actividad');
    form?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const fd = new FormData(form);
      const payload = Object.fromEntries(fd.entries());

      try{
        const res = await fetch('/agenda', {
          method: 'POST',
          headers: { 'Accept':'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type':'application/json' },
          body: JSON.stringify(payload)
        });
        if(!res.ok){
          const err = await res.json().catch(()=> ({}));
          alert('No se pudo guardar.\n' + (err.message || 'Revisa los campos.'));
          return;
        }
        await res.json();
        close();
        calendar?.refetchEvents();
      }catch(err){
        console.error(err);
        alert('Error de red al guardar.');
      }
    });
  }

  function resetForm(){
    const f = $('#form-actividad');
    if(!f) return;
    f.reset();
    $('#ac-id').value = '';
    $('#ac-list').classList.remove('show');
    $('#ac-list').innerHTML = '';
  }

  // ===== C) Autocomplete clientes =====
  function wireAutocomplete(){
    const input = $('#ac-buscar');
    const hidden= $('#ac-id');
    const list  = $('#ac-list');
    if(!input || !list) return;

    let t;
    input.addEventListener('input', ()=>{
      clearTimeout(t);
      const q = input.value.trim();
      hidden.value = '';
      if(q.length < 2){ list.classList.remove('show'); list.innerHTML=''; return; }

      t = setTimeout(async ()=>{
        try{
          const url = '/clientes/buscar?q=' + encodeURIComponent(q);
          const res = await fetch(url, { headers:{'Accept':'application/json'} });
          if(!res.ok){ list.classList.remove('show'); return; }
          const rows = await res.json();

          list.innerHTML = rows.map(r => `<div class="ac-item" data-id="${r.id}">${r.label}</div>`).join('') || '<div class="ac-item" style="pointer-events:none;color:#6b7280">Sin resultados</div>';
          list.classList.add('show');

          $$('.ac-item', list).forEach(it=>{
            it.addEventListener('click', ()=>{
              hidden.value = it.getAttribute('data-id');
              input.value  = it.textContent;
              list.classList.remove('show');
            });
          });

        }catch(e){
          list.classList.remove('show');
        }
      }, 300);
    });

    // cerrar si clic fuera
    document.addEventListener('click', (e)=>{
      if(!list.contains(e.target) && e.target !== input){
        list.classList.remove('show');
      }
    });
  }
})();
