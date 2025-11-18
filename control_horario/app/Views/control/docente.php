<?php
// Vista de control para Docentes
$mod = $module ?? 'academico';
$base = $base ?? (function_exists('appBasePath') ? appBasePath() : '');
?>
<main class="main-control">
  <div class="container">
    <h3>Registro Docente</h3>
    <p>Hasta 3 timbradas de ingreso y 3 de fin por día. Mínimo 10 minutos entre timbres del mismo tipo.</p>
    <div class="buttons">
      <form id="form-doc-ingreso" method="post">
        <button type="submit" class="btn-ingreso" id="btn-doc-ingreso">Ingreso Docente</button>
      </form>
      <form id="form-doc-fin" method="post">
        <button type="submit" class="btn-salida-laboral" id="btn-doc-fin">Fin Docente</button>
      </form>
    </div>
    <div id="geo-msg" style="margin-top:10px;color:#444;"></div>
  </div>
</main>

<div id="modal" class="modal-overlay" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal__title" id="modal-title">Aviso</div>
    <div class="modal__body" id="modal-body"></div>
    <div class="modal__actions">
      <button type="button" id="modal-ok" class="modal__btn">Aceptar</button>
    </div>
  </div>
</div>

<script>
(function(){
  const MSG   = document.getElementById('geo-msg');
  const CSRF  = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const MOD = <?= json_encode($mod) ?>;
  const BASE = <?= json_encode($base) ?>;
  const ENDPOINTS = {
    docenteIngreso: `${BASE}/public/index.php?r=registrar&mod=${MOD}&action=docente_ingreso`,
    docenteFin:     `${BASE}/public/index.php?r=registrar&mod=${MOD}&action=docente_fin`
  };
  const REDIR = <?= json_encode($redirect ?? ('../'+($mod||'academico')+'/dashboard.php')) ?>;

  function showMsg(t){ MSG.textContent = t; }
  async function reverseGeocode(lat, lon){
    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=es&lat=${lat}&lon=${lon}`;
    try { const r = await fetch(url, { headers: { 'Accept':'application/json', 'User-Agent':'ControlHorario/1.0 (+contact)' } }); if (!r.ok) throw new Error('reverse geocode error'); const j = await r.json(); return j.display_name || `${lat}, ${lon}`; } catch(_) { return `${lat}, ${lon}`; }
  }
  function pedirCoordenadas(){
    return new Promise(async (resolve, reject)=>{
      if (!('geolocation' in navigator)) return reject(new Error('Tu navegador no soporta geolocalización.'));
      try { if (navigator.permissions && navigator.permissions.query) { const perm = await navigator.permissions.query({name:'geolocation'}); if (perm.state === 'denied') return reject(new Error('Debes ACTIVAR la ubicación para continuar. Revisa los permisos del sitio.')); } } catch(_){ }
      navigator.geolocation.getCurrentPosition(async (pos)=>{
        const lat = +pos.coords.latitude.toFixed(6);
        const lon = +pos.coords.longitude.toFixed(6);
        const addr = await reverseGeocode(lat, lon);
        resolve({lat, lon, addr});
      }, (err)=>{
        let m = 'Error al obtener ubicación.';
        if (err && err.code !== undefined) {
          switch (err.code) {
            case err.PERMISSION_DENIED:    m = 'Debes ACTIVAR la ubicación para proceder (permite el acceso a tu ubicación).'; break;
            case err.POSITION_UNAVAILABLE: m = 'Ubicación no disponible. Verifica GPS/servicios de ubicación.'; break;
            case err.TIMEOUT:              m = 'Tiempo agotado al obtener la ubicación. Intenta otra vez.'; break;
          }
        }
        reject(new Error(m));
      }, { enableHighAccuracy:true, timeout:12000, maximumAge:0 });
    });
  }
  async function enviarRegistro(url, etiqueta){
    showMsg('Solicitando ubicación...');
    try {
      const {lat, lon, addr} = await pedirCoordenadas();
      showMsg('Ubicación obtenida. Registrando...');
      const res = await fetch(url, { method:'POST', credentials:'same-origin', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify({ latitud: lat, longitud: lon, direccion: addr, accion: etiqueta }) });
      const payload = await res.text();
      const messageOnly = (payload || '').replace(/<script[\s\S]*?<\/script>/gi,'').trim();
      if (!res.ok) { openModal('Error ('+res.status+'): '+messageOnly); showMsg('Ocurrió un error.'); return; }
      openModal(messageOnly || 'Registro realizado correctamente.', REDIR);
    } catch(e){ openModal('⚠️ ' + e.message); }
  }
  document.getElementById('form-doc-ingreso').addEventListener('submit', function(ev){ ev.preventDefault(); enviarRegistro(ENDPOINTS.docenteIngreso, 'Ingreso Docente'); });
  document.getElementById('form-doc-fin').addEventListener('submit', function(ev){ ev.preventDefault(); enviarRegistro(ENDPOINTS.docenteFin, 'Fin Docente'); });

  const MODAL = document.getElementById('modal'); const MODAL_BODY = document.getElementById('modal-body'); const MODAL_OK = document.getElementById('modal-ok'); let modalRedirect = null; function openModal(text, redirect=null, autoAcceptMs=null){ const content = String(text ?? '').trim(); if (!content) { if (redirect) window.location.href = redirect; return; } modalRedirect = redirect; MODAL_BODY.textContent = content; MODAL.removeAttribute('hidden'); MODAL_OK.focus(); if (typeof autoAcceptMs === 'number' && autoAcceptMs > 0) { setTimeout(()=>{ if(!MODAL.hasAttribute('hidden')) closeModal(); }, autoAcceptMs); } } function closeModal(){ MODAL.setAttribute('hidden',''); const r = modalRedirect; modalRedirect = null; if (r) window.location.href = r; } MODAL_OK.addEventListener('click', closeModal); MODAL.addEventListener('click', (e)=>{ if(e.target===MODAL) closeModal(); }); document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !MODAL.hasAttribute('hidden')) closeModal(); });
})();
</script>

