(function(){
  const dz = document.getElementById('drop-zip');
  const input = document.getElementById('zipfile');
  const wrapSel = document.getElementById('zip-selected');
  const nameEl = document.getElementById('zipname');
  if(dz && input){
    const show = (file)=>{
      if(!file){ wrapSel?.classList.add('d-none'); return; }
      nameEl.textContent = file.name; wrapSel?.classList.remove('d-none');
    };
    ['dragenter','dragover'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault(); dz.classList.add('drag');}));
    ['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault(); dz.classList.remove('drag');}));
    dz.addEventListener('drop', e=>{
      const f = e.dataTransfer?.files?.[0]; if(!f) return;
      if(!/\.zip$/i.test(f.name)){ alert('Debe ser un .zip'); return; }
      const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files; show(f);
    });
    input.addEventListener('change', e=> show(e.target.files?.[0]));
  }
})();