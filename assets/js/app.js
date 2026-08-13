document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-department]').forEach((dept)=>{
    const scope = dept.closest('form') || document;
    const city = scope.querySelector('[data-municipality]');
    if(!city) return;
    dept.addEventListener('change',async()=>{
      if(!dept.value){ city.innerHTML='<option value="">Seleccione primero un departamento</option>'; return; }
      city.disabled=true;
      city.innerHTML='<option value="">Cargando...</option>';
      try{
        const r=await fetch('api-municipalities.php?department_id='+encodeURIComponent(dept.value),{headers:{'Accept':'application/json'}});
        if(!r.ok) throw new Error('HTTP '+r.status);
        const data=await r.json();
        city.innerHTML='<option value="">Seleccione...</option>'+data.map(x=>`<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
        if(!data.length) city.innerHTML='<option value="">No hay municipios habilitados</option>';
      }catch(e){
        city.innerHTML='<option value="">No fue posible cargar las ciudades</option>';
      }finally{ city.disabled=false; }
    });
  });

  const photos=document.querySelector('#photos');
  if(photos){photos.addEventListener('change',()=>{if(photos.files.length>8){alert('Puedes cargar máximo 8 fotografías.');photos.value='';}})}
});

function escapeHtml(value){
  return String(value).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
}
