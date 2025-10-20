<?php
session_start();

// ==== Configuración de sesión / seguridad ====
$tiempo_inactividad_maximo = 900; // 15 min

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit();
}

if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso']) > $tiempo_inactividad_maximo) {
    session_unset();
    session_destroy();
    header("Location: ../../../index.php");
    exit();
}
$_SESSION['ultimo_acceso'] = time();

// CSRF token para POST a endpoints
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$nombre       = $_SESSION['nombre']   ?? 'Usuario';
$apellido     = $_SESSION['apellido'] ?? '';
$tipo_usuario = $_SESSION['tipo']     ?? 'Desconocido';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="../../../build/css/app.css">
  <title>Control Horario</title>
</head>
<body>
<header class="header">
  <img src="../../../src/img/intec.png" alt="Logo Intec" class="logo">
  <h1>Bienvenido, <?php echo htmlspecialchars(trim("$nombre $apellido")); ?></h1>
  <nav class="contenedor-menu">
    <h2>Menú</h2>
    <a href="../financiero/dashboard.php">Inicio</a>
    <a href="../../../logout.php?logout=1">Cerrar Sesión</a>
  </nav>
</header>

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

<script>
(function(){
  const MSG   = document.getElementById('geo-msg');
  const CSRF  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  // Endpoints para cada acción
  const ENDPOINTS = {
    ingreso:         '../financiero/registrar_ingreso.php',
    salidaAlmuerzo:  '../financiero/registrar_salida_almuerzo.php',
    regresoAlmuerzo: '../financiero/registrar_regreso_almuerzo.php',
    salidaLaboral:   '../financiero/registrar_salida_laboral.php'
  };

  // ✅ Siempre redirige al dashboard TI tras cada registro exitoso
  const REDIR = '../financiero/dashboard.php';

  function showMsg(t){ MSG.textContent = t; }

  // Reverse geocoding (intenta dirección legible; fallback: "lat, lon")
  async function reverseGeocode(lat, lon){
    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=es&lat=${lat}&lon=${lon}`;
    try {
      const r = await fetch(url, { headers: { 'Accept':'application/json' } });
      if (!r.ok) throw new Error('reverse geocode error');
      const j = await r.json();
      return j.display_name || `${lat}, ${lon}`;
    } catch(e){
      return `${lat}, ${lon}`;
    }
  }

  // Solicita geolocalización (dispara el pop-up del navegador si es necesario)
  function pedirCoordenadas(){
    return new Promise(async (resolve, reject)=>{
      if (!('geolocation' in navigator)) {
        return reject(new Error('Tu navegador no soporta geolocalización.'));
      }

      if (navigator.permissions && navigator.permissions.query) {
        try {
          const perm = await navigator.permissions.query({name:'geolocation'});
          if (perm.state === 'denied') {
            return reject(new Error('Debes ACTIVAR la ubicación para continuar. Revisa los permisos del sitio en tu navegador.'));
          }
        } catch(_) {/* ignoramos */}
      }

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

  // Envía el registro con lat/lon/dirección al endpoint indicado
  async function enviarRegistro(url, etiqueta){
    showMsg('Solicitando ubicación…');
    try {
      const {lat, lon, addr} = await pedirCoordenadas();
      showMsg('Ubicación obtenida. Registrando…');
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type':'application/json',
          'X-CSRF-Token': CSRF
        },
        body: JSON.stringify({ latitud: lat, longitud: lon, direccion: addr, accion: etiqueta })
      });

      const payload = await res.text(); // por compatibilidad si el backend devuelve texto plano
      if (!res.ok) {
        alert('❌ Error del servidor (' + res.status + '): ' + payload);
        showMsg('Ocurrió un error al registrar. Intenta nuevamente.');
        return;
      }

      alert(payload || '✅ Registro realizado correctamente.');
      setTimeout(()=>{ window.location.href = REDIR; }, 1000);
    } catch(e){
      showMsg(e.message);
      alert('❌ ' + e.message);
    }
  }

  // Intercepta los submits para forzar geolocalización en todas las acciones
  document.getElementById('form-ingreso').addEventListener('submit', function(ev){
    ev.preventDefault();
    enviarRegistro(ENDPOINTS.ingreso, 'Ingreso');
  });
  document.getElementById('form-salida-almuerzo').addEventListener('submit', function(ev){
    ev.preventDefault();
    enviarRegistro(ENDPOINTS.salidaAlmuerzo, 'Salida al almuerzo');
  });
  document.getElementById('form-regreso-almuerzo').addEventListener('submit', function(ev){
    ev.preventDefault();
    enviarRegistro(ENDPOINTS.regresoAlmuerzo, 'Regreso del almuerzo');
  });
  document.getElementById('form-salida-laboral').addEventListener('submit', function(ev){
    ev.preventDefault();
    enviarRegistro(ENDPOINTS.salidaLaboral, 'Salida laboral');
  });

  // ⏱ Auto-logout por inactividad (15 min) — este SÍ te saca al login
  let tiempoLimite = 900000; // 15 minutos
  let temporizador = setTimeout(cerrarSesionPorInactividad, tiempoLimite);
  function cerrarSesionPorInactividad() {
    alert("⏳ Tu sesión ha expirado por inactividad.");
    window.location.href = "../../../logout.php?logout=1";
  }
  function reiniciarTemporizador() {
    clearTimeout(temporizador);
    temporizador = setTimeout(cerrarSesionPorInactividad, tiempoLimite);
  }
  ['mousemove','keydown','click','scroll','touchstart'].forEach(evt=>{
    document.addEventListener(evt, reiniciarTemporizador, {passive:true});
  });
})();
</script>

</body>
</html>
