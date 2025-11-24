(function(){
  // Copy buttons
  document.querySelectorAll('[data-copy]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const sel = btn.getAttribute('data-copy');
      const el = document.querySelector(sel);
      if(!el) return;
      navigator.clipboard.writeText(el.innerText||'').then(()=>{
        btn.innerText='Copiado'; setTimeout(()=>btn.innerText='Copiar',1500);
      });
    });
  });

  // Dropzone basic
  const dz = document.getElementById('dz-zip');
  if(dz){
    const input = document.getElementById('zipfile');
    const nameEl = document.getElementById('zipname');
    const selected = dz.querySelector('.dz-selected');
    const instr = dz.querySelector('.dz-instructions');
    const showSelected = (file)=>{
      nameEl.textContent = file ? file.name : '';
      selected.classList.toggle('d-none', !file);
      instr.classList.toggle('d-none', !!file);
    };
    input.addEventListener('change', e=> showSelected(e.target.files[0]));
    ['dragenter','dragover'].forEach(ev=> dz.addEventListener(ev, e=>{e.preventDefault(); dz.classList.add('drag');}));
    ['dragleave','drop'].forEach(ev=> dz.addEventListener(ev, e=>{e.preventDefault(); dz.classList.remove('drag');}));
    dz.addEventListener('drop', e=>{
      const file = e.dataTransfer.files[0];
      if(file && /\.zip$/i.test(file.name)){ input.files = e.dataTransfer.files; showSelected(file); }
    });
  }
})();