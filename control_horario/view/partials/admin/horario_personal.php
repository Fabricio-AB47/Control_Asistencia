<?php
session_start();
date_default_timezone_set('America/Guayaquil');

if (!isset($_SESSION['id_usuario'])) {
  header("Location: /control_horario/view/login.php");
  exit();
}

require '../../../core/conexion.php';
$db = conexion();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$homeUrl = '/control_horario/index.php';

/* --- (opcional) asegurar índice único por usuario en horario_entrada_personal --- */
try {
  $db->exec("ALTER TABLE horario_entrada_personal ADD UNIQUE KEY uq_hep_usuario (id_usuario)");
} catch (Throwable $e) { /* ya existe o sin privilegios; ignorar */ }

/* --- CSRF --- */
if (empty($_SESSION['token'])) {
  $_SESSION['token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['token'];

/* --- identificar sesión/rol --- */
$idSesion  = (int)$_SESSION['id_usuario'];
$idTipoSes = isset($_SESSION['id_tipo']) ? (int)$_SESSION['id_tipo'] : 0;
if (!$idTipoSes) {
  // detectar nombre de columna (id_tipo o id_tp_user) en tabla usuario
  $colTipoUsuario = 'id_tipo';
  try { $db->query("SELECT {$colTipoUsuario} FROM usuario LIMIT 0"); }
  catch (Throwable $e) { $colTipoUsuario = 'id_tp_user'; }

  $s = $db->prepare("SELECT {$colTipoUsuario} FROM usuario WHERE id_usuario=?");
  $s->execute([$idSesion]);
  $idTipoSes = (int)($s->fetchColumn() ?: 0);
}
$isAdmin = ($idTipoSes === 1); // Ajusta si tu ID de admin es otro

/* --- listado de usuarios (solo admin) --- */
$usuarios = [];
if ($isAdmin) {
  $q = $db->query("
    SELECT id_usuario,
           CONCAT_WS(' ', primer_nombre, segundo_nombre, primer_apellido, segundo_apellido) AS nombre
    FROM usuario
    ORDER BY primer_apellido, primer_nombre
  ");
  $usuarios = $q->fetchAll(PDO::FETCH_ASSOC);
}

/* --- usuario objetivo a configurar --- */
$uid = $isAdmin ? (int)($_GET['uid'] ?? $idSesion) : $idSesion;
if ($uid <= 0) $uid = $idSesion;

/* --- cargar config actual del usuario objetivo --- */
$cfgHora = null;
$st = $db->prepare('SELECT hora_ingreso_personal FROM horario_entrada_personal WHERE id_usuario=? LIMIT 1');
$st->execute([$uid]);
$cfgHora = $st->fetchColumn();
$cfgExiste = $cfgHora !== false && $cfgHora !== null;

/* --- mensajes --- */
$msgOk = $msgErr = '';

/* --- detectar nombre de columna de tipo en horario_entrada_personal --- */
$colTipoHEP = 'id_tipo';
try { $db->query("SELECT {$colTipoHEP} FROM horario_entrada_personal LIMIT 0"); }
catch (Throwable $e) { $colTipoHEP = 'id_tp_user'; }

/* --- detectar nombre de columna de tipo en usuario (id_tipo o id_tp_user) --- */
$colTipoUsuario = 'id_tipo';
try { $db->query("SELECT {$colTipoUsuario} FROM usuario LIMIT 0"); }
catch (Throwable $e) { $colTipoUsuario = 'id_tp_user'; }

/* --- POST: registrar por única vez --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar'])) {

  // CSRF
  $token = $_POST['csrf_token'] ?? '';
  if (!is_string($token) || !hash_equals($_SESSION['token'], $token)) {
    $msgErr = 'CSRF inválido. Recarga la página e inténtalo de nuevo.';
  } else {
    $uidPost = $isAdmin ? (int)($_POST['id_usuario'] ?? 0) : $idSesion;
    if ($uidPost <= 0) $uidPost = $idSesion;

    // ¿ya existe horario para este usuario?
    $chk = $db->prepare('SELECT 1 FROM horario_entrada_personal WHERE id_usuario=? LIMIT 1');
    $chk->execute([$uidPost]);
    $yaExiste = (bool)$chk->fetchColumn();

    if ($yaExiste) {
      $msgErr = 'Este usuario ya tiene una hora de entrada registrada. No se puede registrar nuevamente.';
    } else {
      // OJO: el input se llama 'hora_ingreso'
      $hora = trim($_POST['hora_ingreso'] ?? '');
      if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
        $msgErr = 'Formato de hora inválido. Usa HH:MM.';
      } else {
        $hora .= ':00';

        // obtener tipo de usuario según columna detectada
        $u = $db->prepare("SELECT {$colTipoUsuario} FROM usuario WHERE id_usuario=? LIMIT 1");
        $u->execute([$uidPost]);
        $idTipo = (int)($u->fetchColumn() ?: 0);

        if (!$idTipo) {
          $msgErr = 'Usuario inválido o sin tipo asignado.';
        } else {
          try {
            $ins = $db->prepare("
              INSERT INTO horario_entrada_personal ({$colTipoHEP}, hora_ingreso_personal, id_usuario)
              VALUES (?, ?, ?)
            ");
            $ins->execute([$idTipo, $hora, $uidPost]);

            $msgOk = 'Horario registrado correctamente.';
            // recargar config/hora para reflejar el bloqueo de edición
            $st = $db->prepare('SELECT hora_ingreso_personal FROM horario_entrada_personal WHERE id_usuario=? LIMIT 1');
            $st->execute([$uid]);
            $cfgHora = $st->fetchColumn();
            $cfgExiste = $cfgHora !== false && $cfgHora !== null;
          } catch (Throwable $e) {
            $msgErr = 'No se pudo registrar el horario.';
          }
        }
      }
    }
  }
}

/* --- URL de inicio para "Regresar" --- */
$homeUrl = $isAdmin
  ? '../admin/dashboard_adm.php'
  : '../../../index.php';
?>
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Configurar hora de entrada por usuario</title>
  <link rel="stylesheet" href="../../../build/css/app.css">
  <style>
    .wrap{max-width:820px;margin:24px auto;padding:0 16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    h1{margin:0 0 10px;color:#600000}
    .row{margin:10px 0}
    label{display:block;margin-bottom:6px;color:#374151}
    input[type=time],select{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
    .btn{background:#600000;color:#fff;border:none;border-radius:10px;padding:10px 16px;cursor:pointer;text-decoration:none;display:inline-block}
    .btn.secondary{background:#374151}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .ok{background:#ecfdf5;border:1px solid #34d399;color:#065f46;padding:10px;border-radius:10px;margin-bottom:10px}
    .err{background:#fef2f2;border:1px solid #fca5a5;color:#7f1d1d;padding:10px;border-radius:10px;margin-bottom:10px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Configurar hora de entrada por usuario</h1>

    <?php if ($msgOk): ?><div class="ok"><?=htmlspecialchars($msgOk)?></div><?php endif; ?>
    <?php if ($msgErr): ?><div class="err"><?=htmlspecialchars($msgErr)?></div><?php endif; ?>

    <?php if ($isAdmin): ?>
      <form method="get" class="row">
        <label for="uid">Usuario a configurar</label>
        <select id="uid" name="uid" onchange="this.form.submit()">
          <?php foreach($usuarios as $u): ?>
            <option value="<?=$u['id_usuario']?>" <?=$u['id_usuario']==$uid?'selected':''?>>
              <?=$u['id_usuario']?> · <?=htmlspecialchars($u['nombre'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>

    <form method="post" class="row" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
      <?php if ($isAdmin): ?>
        <input type="hidden" name="id_usuario" value="<?=$uid?>">
      <?php endif; ?>

      <div class="grid">
        <div>
          <label for="hora_ingreso">Hora de ingreso</label>
          <input
            type="time"
            id="hora_ingreso"
            name="hora_ingreso"
            step="60"
            value="<?= $cfgExiste ? htmlspecialchars(substr($cfgHora,0,5)) : '' ?>"
            <?= $cfgExiste ? 'readonly' : 'required' ?>
          >
          <?php if ($cfgExiste): ?>
            <div style="margin-top:6px;color:#6b7280">
              Ya existe una hora registrada para este usuario (<?=htmlspecialchars(substr($cfgHora,0,5))?>).
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="row actions" style="display:flex;gap:8px;align-items:center">
        <button class="btn" id="btn-registrar" type="submit" name="registrar" <?= $cfgExiste ? 'disabled title="Este usuario ya tiene hora registrada"' : '' ?>>
          Registrar horario
        </button>
        <a class="btn secondary" href="<?=$homeUrl?>">Regresar al inicio</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
