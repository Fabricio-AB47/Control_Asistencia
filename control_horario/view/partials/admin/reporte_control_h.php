<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../../core/conexion.php';

// Seguridad básica de sesión
date_default_timezone_set('America/Guayaquil');
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . appBasePath() . '/index.php');
    exit();
}

// Expiración por inactividad (15 min)
$tiempo_inactividad = 900;
if (isset($_SESSION['ultimo_acceso']) && (time() - ($_SESSION['ultimo_acceso'] ?? 0)) > $tiempo_inactividad) {
    session_unset();
    session_destroy();
    header('Location: ' . appBasePath() . '/index.php?error=sesion_expirada');
    exit();
}
$_SESSION['ultimo_acceso'] = time();

// Verificar rol administrador (id 1) con detección de columna (id_tipo | id_tp_user)
$db = conexion();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$idSesion = (int)($_SESSION['id_usuario'] ?? 0);
$colTipo = 'id_tipo';
try { $db->query("SELECT {$colTipo} FROM usuario LIMIT 0"); } catch (Throwable $e) { $colTipo = 'id_tp_user'; }
$st = $db->prepare("SELECT {$colTipo} FROM usuario WHERE id_usuario=? LIMIT 1");
$st->execute([$idSesion]);
$idTipoSes = (int)($st->fetchColumn() ?: 0);
if ($idTipoSes !== 1) {
    header('Location: ' . appBasePath() . '/index.php');
    exit();
}

// Helpers
function parseYmd(?string $s): ?string {
    if (!$s) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s ? $s : null;
}
function fmt(?string $t): string { return $t ? htmlspecialchars($t, ENT_QUOTES, 'UTF-8') : '-'; }
function linkMap(?string $lat, ?string $lon, ?string $label = 'Mapa'): string {
    if (!$lat || !$lon || $lat === '0.000000' || $lon === '0.000000') return '';
    $u = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lon);
    return ' <a target="_blank" href="'.htmlspecialchars($u, ENT_QUOTES, 'UTF-8').'">['.htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8').']</a>';
}
function secs(?DateTime $a, ?DateTime $b): int { return (!$a || !$b) ? 0 : ($b->getTimestamp() - $a->getTimestamp()); }
function hhmmss(int $seconds): string {
    if ($seconds <= 0) return '00:00:00';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}

// Filtros
$hoy          = (new DateTime('now', new DateTimeZone('America/Guayaquil')))->format('Y-m-d');
$primerDiaMes = (new DateTime('first day of this month'))->format('Y-m-d');

$desde = parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
$hasta = parseYmd($_GET['hasta'] ?? '') ?? $hoy;
if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

$uidFilter   = (int)($_GET['uid']   ?? 0); // opcional
$tipoFilter  = (int)($_GET['tipo']  ?? 0); // opcional (id_tp_user)

// Cargar selects (usuarios y tipos)
$usuarios = $db->query("SELECT id_usuario, CONCAT_WS(' ', primer_apellido, segundo_apellido, primer_nombre, segundo_nombre) AS nombre FROM usuario ORDER BY primer_apellido, primer_nombre")
             ->fetchAll(PDO::FETCH_ASSOC);
$tipos    = $db->query("SELECT id_tp_user, detalle_tp_user FROM tipo_usuario ORDER BY detalle_tp_user")
             ->fetchAll(PDO::FETCH_ASSOC);

// Consulta unificada de timbradas para TODOS los usuarios (con filtros)
$sql = "
SELECT
  fr.id_fecha_registro,
  fr.fecha_ingreso                                      AS fecha,
  u.id_usuario,
  u.primer_nombre, u.segundo_nombre,
  u.primer_apellido, u.segundo_apellido,
  u.correo,
  tu.detalle_tp_user                                    AS tipo_usuario,

  hi.hora_ingreso,  ei.detalle_ingreso,
  hi.latitud  AS lat_in,  hi.longitud AS lon_in,  hi.direccion AS dir_in,

  sla.hora_sl_almuerzo, esa.detalle_salida_almuerzo,
  sla.latitud AS lat_sl, sla.longitud AS lon_sl, sla.direccion AS dir_sl,

  rta.hora_rt_almuerzo, era.detalle_retorno_almuerzo,
  rta.latitud AS lat_rt, rta.longitud AS lon_rt, rta.direccion AS dir_rt,

  sa.hora_salida, es.detalle_salida,
  sa.latitud  AS lat_out, sa.longitud AS lon_out, sa.direccion AS dir_out,

  hep.hora_ingreso_personal AS hora_prog_in,
  hsp.hora_salida_personal  AS hora_prog_out

FROM fecha_registro fr
JOIN usuario u ON u.id_usuario = fr.id_usuario
LEFT JOIN tipo_usuario tu ON tu.id_tp_user = u.id_tp_user

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

LEFT JOIN horario_entrada_personal hep
  ON hep.id_usuario = u.id_usuario AND hep.id_tp_user = u.id_tp_user
LEFT JOIN horario_salida_personal hsp
  ON hsp.id_usuario = u.id_usuario AND hsp.id_tp_user = u.id_tp_user

WHERE fr.fecha_ingreso BETWEEN :desde AND :hasta
";

$params = [':desde' => $desde, ':hasta' => $hasta];
if ($uidFilter > 0) { $sql .= " AND fr.id_usuario = :uid";  $params[':uid']  = $uidFilter; }
if ($tipoFilter> 0) { $sql .= " AND u.id_tp_user = :tipo"; $params[':tipo'] = $tipoFilter; }

$sql .= " ORDER BY fr.fecha_ingreso DESC, u.primer_apellido, u.primer_nombre";

$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales
$totalEfectiva = 0;
$registrosConJornada = 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reporte de Timbradas - Todos los usuarios</title>
  <link rel="stylesheet" href="../../../build/css/app.css">
  <script src="../../../build/js/menu.js" defer></script>
</head>
<body>
<header class="header">
  <h1 class="header__welcome">Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></h1>
  <div class="header__brand">
    <img src="../../../src/img/intec.png" alt="Logo Intec" class="logo">
  </div>
  <br>
  <nav class="header__menu" aria-label="Menú principal">
    <h2 class="sr-only">Menú</h2>
    <a class="btn btn--ghost" href="../admin/dashboard_adm.php">Inicio</a>
    <a class="btn btn--danger" href="<?= htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar Sesión</a>
  </nav>
</header>

<main class="report">
  <form class="filter" method="get" novalidate>
    <label class="filter__field">Desde
      <input type="date" name="desde" value="<?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="filter__field">Hasta
      <input type="date" name="hasta" value="<?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="filter__field">Usuario
      <select name="uid">
        <option value="0">Todos</option>
        <?php foreach ($usuarios as $u): ?>
          <option value="<?= (int)$u['id_usuario'] ?>" <?= $uidFilter === (int)$u['id_usuario'] ? 'selected' : '' ?>>
            <?= (int)$u['id_usuario'] ?> - <?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="filter__field">Tipo usuario
      <select name="tipo">
        <option value="0">Todos</option>
        <?php foreach ($tipos as $t): ?>
          <option value="<?= (int)$t['id_tp_user'] ?>" <?= $tipoFilter === (int)$t['id_tp_user'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['detalle_tp_user'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn btn--primary filter__btn" type="submit">Filtrar</button>
  </form>

  <div class="table-container">
    <table class="table">
      <thead class="table__head">
      <tr>
        <th>Fecha</th>
        <th>Usuario</th>
        <th>Tipo</th>
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

          $nombreCompleto = trim(($r['primer_apellido'] ?? '') . ' ' . ($r['segundo_apellido'] ?? '') . ' ' . ($r['primer_nombre'] ?? '') . ' ' . ($r['segundo_nombre'] ?? ''));
          $nombreCompleto = preg_replace('/\s+/', ' ', $nombreCompleto);

          $dtIn  = ($r['hora_ingreso'])     ? new DateTime($fecha.' '.$r['hora_ingreso']) : null;
          $dtSl  = ($r['hora_sl_almuerzo']) ? new DateTime($fecha.' '.$r['hora_sl_almuerzo']) : null;
          $dtRt  = ($r['hora_rt_almuerzo']) ? new DateTime($fecha.' '.$r['hora_rt_almuerzo']) : null;
          $dtOut = ($r['hora_salida'])      ? new DateTime($fecha.' '.$r['hora_salida']) : null;

          $secJornada  = ($dtIn && $dtOut) ? secs($dtIn, $dtOut) : 0;
          $secAlmuerzo = ($dtSl && $dtRt)  ? max(0, secs($dtSl, $dtRt)) : 0;
          $secEfectiva = max(0, $secJornada - $secAlmuerzo);

          $tardanzaMin = 0;
          if (!empty($r['hora_prog_in']) && $dtIn) {
              $dtProgIn = new DateTime($fecha.' '.$r['hora_prog_in']);
              $dif = secs($dtProgIn, $dtIn); // positivo si llegó después
              $tardanzaMin = max(0, intdiv($dif, 60) - 10); // tolerancia 10 min
          }

          $salidaAntMin = 0;
          if (!empty($r['hora_prog_out']) && $dtOut) {
              $dtProgOut = new DateTime($fecha.' '.$r['hora_prog_out']);
              $dif = secs($dtOut, $dtProgOut); // negativo si salió antes
              $salidaAntMin = $dif < 0 ? intdiv(abs($dif), 60) : 0;
          }

          if ($secJornada > 0) { $totalEfectiva += $secEfectiva; $registrosConJornada++; }

          $ingresoCell = $r['hora_ingreso']
              ? fmt($r['hora_ingreso'])." <span class='badge'>".fmt($r['detalle_ingreso'])."</span><br>".fmt($r['dir_in']).linkMap($r['lat_in'],$r['lon_in'],'Mapa')
              : '-';
          $slCell = $r['hora_sl_almuerzo']
              ? fmt($r['hora_sl_almuerzo'])." <span class='badge'>".fmt($r['detalle_salida_almuerzo'])."</span><br>".fmt($r['dir_sl']).linkMap($r['lat_sl'],$r['lon_sl'],'Mapa')
              : '-';
          $rtCell = $r['hora_rt_almuerzo']
              ? fmt($r['hora_rt_almuerzo'])." <span class='badge'>".fmt($r['detalle_retorno_almuerzo'])."</span><br>".fmt($r['dir_rt']).linkMap($r['lat_rt'],$r['lon_rt'],'Mapa')
              : '-';
          $outCell = $r['hora_salida']
              ? fmt($r['hora_salida'])." <span class='badge'>".fmt($r['detalle_salida'])."</span><br>".fmt($r['dir_out']).linkMap($r['lat_out'],$r['lon_out'],'Mapa')
              : '-';

          $tardanzaCell = $tardanzaMin > 0 ? "<span class='chip chip--warn'>".$tardanzaMin." min</span>" : '-';
          $extraCell    = $salidaAntMin > 0 ? "<span class='chip chip--info'>".$salidaAntMin." min</span>" : '-';
      ?>
        <tr>
          <td><?= fmt($fecha) ?></td>
          <td>
            <?= fmt($nombreCompleto) ?><br>
            <small><?= fmt($r['correo'] ?? '') ?></small>
          </td>
          <td><?= fmt($r['tipo_usuario'] ?? '-') ?></td>
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
        <td colspan="9" class="table__foot-label">
          Total horas efectivas (<?= (int)$registrosConJornada ?> registro<?= $registrosConJornada===1?'':'s' ?> con jornada):
        </td>
        <td class="table__foot-value" colspan="3"><?= hhmmss($totalEfectiva) ?></td>
      </tr>
      </tfoot>
    </table>
  </div>
</main>

</body>
</html>
