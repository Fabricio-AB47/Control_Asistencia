<?php
// Espera: $module (ti, financiero, admisiones, academico, bienestar), $redirect
$mod = $module ?? 'ti';
?>

<main class="main-control">
  <div class="container">
    <h3>Registro de Control Horario</h3>
    <p>Por favor, selecciona la acción que deseas realizar:</p>
    <div class="buttons">
      <!-- Botón para registrar ingreso -->
      <form id="form-ingreso" method="post">
        <button type="submit" class="btn-ingreso" id="btn-ingreso">Registrar Ingreso</button>
      </form>

      <!-- Botón para registrar salida de almuerzo -->
      <form id="form-salida-almuerzo" method="post">
        <button type="submit" class="btn-salida-almuerzo" id="btn-salida-almuerzo">Salida al Almuerzo</button>
      </form>

      <!-- Botón para registrar regreso del almuerzo -->
      <form id="form-regreso-almuerzo" method="post">
        <button type="submit" class="btn-regreso-almuerzo" id="btn-regreso-almuerzo">Regreso del Almuerzo</button>
      </form>

      <!-- Botón para registrar salida laboral -->
      <form id="form-salida-laboral" method="post">
        <button type="submit" class="btn-salida-laboral" id="btn-salida-laboral">Salida Laboral</button>
      </form>
    </div>

    <div id="geo-msg" style="margin-top:10px;color:#444;"></div>
  </div>
</main>

<!-- Modal genérico -->
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

  // Endpoints por módulo (mantiene los PHP actuales)
  const MOD = <?= json_encode($mod) ?>;
  const BASE = <?= json_encode($base ?? '') ?>;
  const ENDPOINTS = {
    ingreso:         `${BASE}/public/index.php?r=registrar&mod=${MOD}&action=ingreso`,
    salidaAlmuerzo:  `${BASE}/public/index.php?r=registrar&mod=${MOD}&action=salida_almuerzo`,
    regresoAlmuerzo: `${BASE}/public/index.php?r=registrar&mod=${MOD}&action=regreso_almuerzo`,
    salidaLaboral:   `${BASE}/public/index.php?r=registrar&mod=${MOD}&action=salida_laboral`
  };

  const REDIR = <?= json_encode($redirect ?? ('../'+($mod||'ti')+'/dashboard.php')) ?>;

  function showMsg(t){ MSG.textContent = t; }

  async function reverseGeocode(lat, lon){
    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=es&lat=${lat}&lon=${lon}`;
    try {
      const r = await fetch(url, { headers: { 'Accept':'application/json', 'User-Agent':'ControlHorario/1.0 (+contact)' } });
      if (!r.ok) throw new Error('reverse geocode error');
      const j = await r.json();
      return j.display_name || `${lat}, ${lon}`;
    } catch(_) { return `${lat}, ${lon}`; }
  }

  function pedirCoordenadas(){
    return new Promise(async (resolve, reject)=>{
      if (!('geolocation' in navigator)) return reject(new Error('Tu navegador no soporta geolocalización.'));
      try {
        if (navigator.permissions && navigator.permissions.query) {
          const perm = await navigator.permissions.query({name:'geolocation'});
          if (perm.state === 'denied') return reject(new Error('Debes ACTIVAR la ubicación para continuar. Revisa los permisos del sitio en tu navegador.'));
        }
      } catch(_){}

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
    showMsg('Solicitando ubicación…');
    try {
      const {lat, lon, addr} = await pedirCoordenadas();
      showMsg('Ubicación obtenida. Registrando…');
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type':'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ latitud: lat, longitud: lon, direccion: addr, accion: etiqueta })
      });

      const payload = await res.text();
      const messageOnly = (payload || '').replace(/<script[\s\S]*?<\/script>/gi,'').trim();
      if (!res.ok) {
        openModal('❗ Error del servidor (' + res.status + '): ' + messageOnly);
        showMsg('Ocurrió un error al registrar. Intenta nuevamente.');
        return;
      }
      openModal(messageOnly || '✅ Registro realizado correctamente.', REDIR);
    } catch(e){
      showMsg(e.message);
      openModal('❗ ' + e.message);
    }
  }

  document.getElementById('form-ingreso').addEventListener('submit', function(ev){ ev.preventDefault(); enviarRegistro(ENDPOINTS.ingreso, 'Ingreso'); });
  document.getElementById('form-salida-almuerzo').addEventListener('submit', function(ev){ ev.preventDefault(); enviarRegistro(ENDPOINTS.salidaAlmuerzo, 'Salida al almuerzo'); });
  document.getElementById('form-regreso-almuerzo').addEventListener('submit', function(ev){ ev.preventDefault(); enviarRegistro(ENDPOINTS.regresoAlmuerzo, 'Regreso del almuerzo'); });
  document.getElementById('form-salida-laboral').addEventListener('submit', function(ev){ ev.preventDefault(); enviarRegistro(ENDPOINTS.salidaLaboral, 'Salida laboral'); });

  // Inactividad: solo redirige
  let tiempoLimite = 900000; // 15 minutos
  let temporizador = setTimeout(cerrarSesionPorInactividad, tiempoLimite);
  function cerrarSesionPorInactividad() { window.location.href = '<?= $base ?>/logout.php?logout=1'; }
  function reiniciarTemporizador() { clearTimeout(temporizador); temporizador = setTimeout(cerrarSesionPorInactividad, tiempoLimite); }
  ['mousemove','keydown','click','scroll','touchstart'].forEach(evt=>{ document.addEventListener(evt, reiniciarTemporizador, {passive:true}); });

  // Modal helpers
  const MODAL = document.getElementById('modal');
  const MODAL_BODY = document.getElementById('modal-body');
  const MODAL_OK = document.getElementById('modal-ok');
  let modalRedirect = null;
  function openModal(text, redirect=null, autoAcceptMs=null){
    const content = String(text ?? '').trim();
    if (!content) {
      if (redirect) window.location.href = redirect; return;
    }
    modalRedirect = redirect;
    MODAL_BODY.textContent = content;
    MODAL.removeAttribute('hidden');
    MODAL_OK.focus();
    if (typeof autoAcceptMs === 'number' && autoAcceptMs > 0) {
      setTimeout(()=>{ if(!MODAL.hasAttribute('hidden')) closeModal(); }, autoAcceptMs);
    }
  }
  function closeModal(){ MODAL.setAttribute('hidden',''); const r = modalRedirect; modalRedirect = null; if (r) window.location.href = r; }
  MODAL_OK.addEventListener('click', closeModal);
  MODAL.addEventListener('click', (e)=>{ if(e.target===MODAL) closeModal(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !MODAL.hasAttribute('hidden')) closeModal(); });
})();
</script>
