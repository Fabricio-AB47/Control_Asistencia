<?php
declare(strict_types=1);
session_start();
require '../../../core/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('No autorizado');
}

date_default_timezone_set('America/Guayaquil');

$db = conexion(); // PDO
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$uid = (int)$_SESSION['id_usuario'];

// --- Rango de fechas (GET) ---
function parseYmd(?string $s): ?string {
    if (!$s) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s ? $s : null;
}
$hoy          = (new DateTime('now', new DateTimeZone('America/Guayaquil')))->format('Y-m-d');
$primerDiaMes = (new DateTime('first day of this month'))->format('Y-m-d');

$desde = parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
$hasta = parseYmd($_GET['hasta'] ?? '') ?? $hoy;
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

// --- Horario programado del usuario (para cálculos) ---
$stmt = $db->prepare("
    SELECT u.id_tp_user,
           he.hora_ingreso_personal,
           hs.hora_salida_personal
      FROM usuario u
 LEFT JOIN horario_entrada_personal he ON he.id_usuario = u.id_usuario AND he.id_tp_user = u.id_tp_user
 LEFT JOIN horario_salida_personal  hs ON hs.id_usuario = u.id_usuario AND hs.id_tp_user = u.id_tp_user
     WHERE u.id_usuario = ?
     LIMIT 1
");
$stmt->execute([$uid]);
$cfg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$horaProgIn  = $cfg['hora_ingreso_personal'] ?? null; // HH:MM:SS
$horaProgOut = $cfg['hora_salida_personal']  ?? null; // HH:MM:SS

// --- Datos de timbrado por día ---
$sql = "
SELECT
    fr.id_fecha_registro,
    fr.fecha_ingreso                                     AS fecha,

    hi.hora_ingreso, ei.detalle_ingreso                  AS estado_ingreso,
    hi.latitud   AS lat_in,  hi.longitud AS lon_in,  hi.direccion AS dir_in,

    sla.hora_sl_almuerzo, esa.detalle_salida_almuerzo    AS estado_sl_alm,
    sla.latitud  AS lat_sl,  sla.longitud AS lon_sl, sla.direccion AS dir_sl,

    rta.hora_rt_almuerzo, era.detalle_retorno_almuerzo   AS estado_rt_alm,
    rta.latitud  AS lat_rt,  rta.longitud AS lon_rt, rta.direccion AS dir_rt,

    sa.hora_salida, es.detalle_salida                    AS estado_salida,
    sa.latitud   AS lat_out, sa.longitud AS lon_out, sa.direccion  AS dir_out

FROM fecha_registro fr
LEFT JOIN horario_ingreso hi
       ON hi.id_usuario = fr.id_usuario AND hi.id_fecha_registro = fr.id_fecha_registro
LEFT JOIN estado_ingreso ei
       ON ei.id_estado_ingreso = hi.id_estado_ingreso

LEFT JOIN horario_sl_almuerzo sla
       ON sla.id_usuario = fr.id_usuario AND sla.id_fecha_registro = fr.id_fecha_registro
LEFT JOIN estado_salida_almuerzo esa
       ON esa.id_estado_salida_almuerzo = sla.id_estado_salida_almuerzo

LEFT JOIN horario_rt_almuerzo rta
       ON rta.id_usuario = fr.id_usuario AND rta.id_fecha_registro = fr.id_fecha_registro
LEFT JOIN estado_retorno_almuerzo era
       ON era.id_estado_retorno_almuerzo = rta.id_estado_retorno_almuerzo

LEFT JOIN horario_salida sa
       ON sa.id_usuario = fr.id_usuario AND sa.id_fecha_registro = fr.id_fecha_registro
LEFT JOIN estado_salida es
       ON es.id_estado_salida = sa.id_estado_salida

WHERE fr.id_usuario = :uid
  AND fr.fecha_ingreso BETWEEN :desde AND :hasta
ORDER BY fr.fecha_ingreso DESC
";
$stmt = $db->prepare($sql);
$stmt->bindValue(':uid',   $uid,   PDO::PARAM_INT);
$stmt->bindValue(':desde', $desde, PDO::PARAM_STR);
$stmt->bindValue(':hasta', $hasta, PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Helpers de formato y cálculo ---
function fmt(?string $t): string {
    return $t ? htmlspecialchars($t, ENT_QUOTES, 'UTF-8') : '—';
}
function linkMap(?string $lat, ?string $lon, ?string $label = 'Mapa'): string {
    if (!$lat || !$lon || $lat === '0.000000' || $lon === '0.000000') return '';
    $u = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lon);
    $uEsc = htmlspecialchars($u, ENT_QUOTES, 'UTF-8');
    $latEsc = htmlspecialchars($lat, ENT_QUOTES, 'UTF-8');
    $lonEsc = htmlspecialchars($lon, ENT_QUOTES, 'UTF-8');
    $labEsc = htmlspecialchars($label ?? 'Mapa', ENT_QUOTES, 'UTF-8');
    return ' <a class="js-map" target="_blank" href="'.$uEsc.'" data-url="'.$uEsc.'" data-lat="'.$latEsc.'" data-lon="'.$lonEsc.'">['.$labEsc.']</a>';
}
function secs(?DateTime $a, ?DateTime $b): int {
    if (!$a || !$b) return 0;
    return $b->getTimestamp() - $a->getTimestamp();
}
function hhmmss(int $seconds): string {
    if ($seconds <= 0) return '00:00:00';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

$totalJornada  = 0;
$totalAlmuerzo = 0;
$totalEfectiva = 0;
$diasContados  = 0;

// (Opcional) nombre y apellido si existen
$nombre   = $_SESSION['nombre']   ?? '';
$apellido = $_SESSION['apellido'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de Timbrado</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../../../build/css/app.css">
<script src="../../../build/js/menu.js" defer></script>
<link rel="stylesheet" href="../../../build/css/reporte.css"><!-- generado a partir de src/scss/pages/reporte.scss -->
</head>
<body>
<header class="header">
      <h1 class="header__welcome">Bienvenido, <?php echo htmlspecialchars(trim("$nombre $apellido")); ?></h1>
  <div class="header__brand">
    <img src="../../../src/img/intec.png" alt="Logo Intec" class="logo">

  </div>
  <br>
  <nav class="header__menu" aria-label="Menú principal">
    <h2 class="sr-only">Menú</h2>
    <a class="btn btn--ghost" href="../academico/dashboard.php">Inicio</a>
    <a class="btn btn--danger" href="<?= htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8') ?>/logout.php?logout=1">Cerrar Sesión</a>
  </nav>
</header>

<!-- Modal para mapa -->
<div id="modal" class="modal-overlay" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal__title" id="modal-title">Ubicación</div>
    <div class="modal__body" id="modal-body">
      <iframe id="map-frame" title="Mapa" style="width:100%;height:min(60vh,480px);border:0;border-radius:8px" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="about:blank"></iframe>
    </div>
    <div class="modal__actions">
      <button type="button" id="modal-ok" class="modal__btn">Aceptar</button>
    </div>
  </div>
  </div>

<main class="report">
  <h1 class="report__title">Reporte de Timbrado</h1>

  <form class="filter" method="get" novalidate>
    <label class="filter__field">Desde
      <input type="date" name="desde" value="<?=htmlspecialchars($desde,ENT_QUOTES,'UTF-8')?>">
    </label>
    <label class="filter__field">Hasta
      <input type="date" name="hasta" value="<?=htmlspecialchars($hasta,ENT_QUOTES,'UTF-8')?>">
    </label>
    <button class="btn btn--primary filter__btn" type="submit">Filtrar</button>
    <span class="note">Usuario ID: <?= (int)$uid ?><?= $horaProgIn ? " · Hora ingreso prog.: ".htmlspecialchars($horaProgIn,ENT_QUOTES,'UTF-8') : "" ?><?= $horaProgOut ? " · Hora salida prog.: ".htmlspecialchars($horaProgOut,ENT_QUOTES,'UTF-8') : "" ?></span>
  </form>

  <div class="table-container">
    <table class="table">
      <thead class="table__head">
        <tr>
          <th>Fecha</th>
          <th>Ingreso</th>
          <th>Salida Almuerzo</th>
          <th>Retorno Almuerzo</th>
          <th>Salida</th>
          <th>Jornada</th>
          <th>Almuerzo</th>
          <th>Horas efectivas</th>
          <th>Tardanza</th>
          <th>Tiempo adicional</th>
        </tr>
      </thead>
      <tbody class="table__body">
<?php foreach ($rows as $r):
    $fecha = $r['fecha'];

    $dtIn  = ($r['hora_ingreso'])      ? new DateTime($fecha.' '.$r['hora_ingreso']) : null;
    $dtSl  = ($r['hora_sl_almuerzo'])  ? new DateTime($fecha.' '.$r['hora_sl_almuerzo']) : null;
    $dtRt  = ($r['hora_rt_almuerzo'])  ? new DateTime($fecha.' '.$r['hora_rt_almuerzo']) : null;
    $dtOut = ($r['hora_salida'])       ? new DateTime($fecha.' '.$r['hora_salida']) : null;

    // Cálculos
    $secJornada  = ($dtIn && $dtOut) ? secs($dtIn, $dtOut) : 0;
    $secAlmuerzo = ($dtSl && $dtRt)  ? max(0, secs($dtSl, $dtRt)) : 0;
    $secEfectiva = max(0, $secJornada - $secAlmuerzo);

    // Tardanza (tolerancia 10 min)
    $tardanzaMin = 0;
    if ($horaProgIn && $dtIn) {
        $dtProgIn = new DateTime($fecha.' '.$horaProgIn);
        $dif = secs($dtProgIn, $dtIn); // positivo si llegó después
        $tardanzaMin = max(0, intdiv($dif, 60) - 10);
    }

    // Salida anticipada (minutos positivos si salió antes de la hora programada)
    $salidaAntMin = 0;
    if ($horaProgOut && $dtOut) {
        $dtProgOut = new DateTime($fecha.' '.$horaProgOut);
        $dif = secs($dtOut, $dtProgOut); // negativo si salió antes
        $salidaAntMin = $dif < 0 ? intdiv(abs($dif), 60) : 0;
    }

    // Totales
    if ($secJornada > 0) {
        $totalEfectiva += $secEfectiva;
        $diasContados++;
    }

    // Celdas con links de mapa
    $ingresoCell = $r['hora_ingreso']
        ? fmt($r['hora_ingreso'])." <span class='badge'>".fmt($r['estado_ingreso'])."</span><br>".fmt($r['dir_in']).linkMap($r['lat_in'],$r['lon_in'],'Mapa')
        : '—';

    $slCell = $r['hora_sl_almuerzo']
        ? fmt($r['hora_sl_almuerzo'])." <span class='badge'>".fmt($r['estado_sl_alm'])."</span><br>".fmt($r['dir_sl']).linkMap($r['lat_sl'],$r['lon_sl'],'Mapa')
        : '—';

    $rtCell = $r['hora_rt_almuerzo']
        ? fmt($r['hora_rt_almuerzo'])." <span class='badge'>".fmt($r['estado_rt_alm'])."</span><br>".fmt($r['dir_rt']).linkMap($r['lat_rt'],$r['lon_rt'],'Mapa')
        : '—';

    $outCell = $r['hora_salida']
        ? fmt($r['hora_salida'])." <span class='badge'>".fmt($r['estado_salida'])."</span><br>".fmt($r['dir_out']).linkMap($r['lat_out'],$r['lon_out'],'Mapa')
        : '—';

    $tardanzaCell = $tardanzaMin > 0 ? "<span class='chip chip--warn'>".$tardanzaMin." min</span>" : '—';
    $extraCell    = $salidaAntMin > 0 ? "<span class='chip chip--info'>".$salidaAntMin." min</span>" : '—';
?>
        <tr>
          <td><?= fmt($fecha) ?></td>
          <td><?= $ingresoCell ?></td>
          <td><?= $slCell ?></td>
          <td><?= $rtCell ?></td>
          <td><?= $outCell ?></td>
          <td><?= hhmmss($secJornada) ?></td>
          <td><?= hhmmss($secAlmuerzo) ?></td>
          <td><?= hhmmss($secEfectiva) ?></td>
          <td><?= $tardanzaCell ?></td>
          <td><?= $extraCell ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
      <tfoot class="table__foot">
        <tr>
          <td colspan="7" class="table__foot-label">
            Total horas efectivas (<?= (int)$diasContados ?> día<?= $diasContados===1?'':'s' ?>):
          </td>
          <td class="table__foot-value"><?= hhmmss($totalEfectiva) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="note">
    * Tardanza aplica tolerancia de 10 minutos sobre la hora de ingreso programada.<br>
    * Salida anticipada se calcula contra la hora de salida programada.
  </p>
</main>

<script>
(function(){
  const MODAL = document.getElementById('modal');
  const MODAL_OK = document.getElementById('modal-ok');
  const MODAL_TITLE = document.getElementById('modal-title');
  const FRAME = document.getElementById('map-frame');
  function openMap(lat, lon){
    if(!lat || !lon) return;
    const url = 'https://www.google.com/maps?q=' + encodeURIComponent(lat + ',' + lon) + '&z=17&output=embed';
    FRAME.src = url;
    MODAL_TITLE.textContent = 'Ubicación (' + lat + ', ' + lon + ')';
    MODAL.removeAttribute('hidden');
    MODAL_OK.focus();
  }
  function closeModal(){
    MODAL.setAttribute('hidden','');
    FRAME.src = 'about:blank';
  }
  MODAL_OK.addEventListener('click', closeModal);
  MODAL.addEventListener('click', (e)=>{ if(e.target===MODAL) closeModal(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !MODAL.hasAttribute('hidden')) closeModal(); });
  document.addEventListener('click', function(e){
    const a = e.target.closest('a.js-map');
    if(!a) return;
    if (e.ctrlKey || e.metaKey || a.target === '_blank') return; // permitir nueva pestaña con Ctrl/Cmd
    e.preventDefault();
    const lat = a.getAttribute('data-lat');
    const lon = a.getAttribute('data-lon');
    openMap(lat, lon);
  }, {passive:false});
})();
</script>

</body>
</html>
