<?php
declare(strict_types=1);
session_start();
require '../../../core/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo "No autorizado";
    exit();
}

date_default_timezone_set('America/Guayaquil');

$db = conexion(); // PDO
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$BASE = appBasePath();
header('Content-Type: text/plain; charset=UTF-8');

// CSRF: validar token de la sesión vs cabecera/cuerpo
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['token']) || !is_string($csrfToken) || !hash_equals($_SESSION['token'], $csrfToken)) {
    http_response_code(403);
    exit('CSRF inválido');
}

$uid   = (int)$_SESSION['id_usuario'];
$hoy   = date('Y-m-d');
$ahora = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit();
}

// Entrada JSON (fallback a $_POST)
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || empty($input)) $input = $_POST;

$latitud   = $input['latitud']   ?? null;
$longitud  = $input['longitud']  ?? null;
$direccion = isset($input['direccion']) ? trim((string)$input['direccion']) : null;
// Truncar dirección para evitar errores por longitud de columna en BD
if ($direccion !== null && $direccion !== '') {
    if (function_exists('mb_substr')) {
        $direccion = mb_substr($direccion, 0, 190, 'UTF-8');
    } else {
        $direccion = substr($direccion, 0, 190);
    }
}

if ($latitud === null || $longitud === null || $direccion === null || $direccion === '') {
    http_response_code(400);
    echo "❌ No se pudo obtener la ubicación. El registro no se guardará.";
    exit();
}
$latF = (float)$latitud;
$lonF = (float)$longitud;
if ($latF < -90 || $latF > 90 || $lonF < -180 || $lonF > 180) {
    http_response_code(422);
    echo "❌ Coordenadas inválidas.";
    exit();
}
$latitud  = number_format($latF, 6, '.', '');
$longitud = number_format($lonF, 6, '.', '');

try {
    $db->beginTransaction();

    // 1) Tipo de usuario
    $st = $db->prepare("SELECT id_tp_user FROM usuario WHERE id_usuario = ? LIMIT 1");
    $st->execute([$uid]);
    $idTpUser = (int)$st->fetchColumn();
    if (!$idTpUser) {
        throw new RuntimeException('Usuario sin tipo asignado.');
    }

    // 2) Horario (usuario + tipo)
    $st = $db->prepare("
        SELECT id_hora_entrada, hora_ingreso_personal
          FROM horario_entrada_personal
         WHERE id_usuario = ? AND id_tp_user = ?
         LIMIT 1
    ");
    $st->execute([$uid, $idTpUser]);
    $horario = $st->fetch(PDO::FETCH_ASSOC);
    if (!$horario) {
        throw new RuntimeException('No se encontró un horario de entrada para tu usuario/tipo. Contacta al administrador.');
    }

    $idHoraEntrada  = (int)$horario['id_hora_entrada'];
    $horaProgramada = $horario['hora_ingreso_personal']; // HH:MM:SS

    // 3) fecha_registro del día (crear si no existe)
    $st = $db->prepare("
        SELECT id_fecha_registro
          FROM fecha_registro
         WHERE id_usuario = ? AND fecha_ingreso = ?
         LIMIT 1
    ");
    $st->execute([$uid, $hoy]);
    $idFecha = $st->fetchColumn();

    if (!$idFecha) {
        $insFR = $db->prepare("INSERT INTO fecha_registro (id_usuario, fecha_ingreso) VALUES (?, ?)");
        $insFR->execute([$uid, $hoy]);
        $idFecha = (int)$db->lastInsertId();
    } else {
        $idFecha = (int)$idFecha;
    }

    // 4) Evitar duplicado del día
    $st = $db->prepare("
        SELECT 1
          FROM horario_ingreso
         WHERE id_usuario = ? AND id_fecha_registro = ?
         LIMIT 1
    ");
    $st->execute([$uid, $idFecha]);
    if ($st->fetch()) {
        $db->rollBack();
        echo "ℹ️ Ya existe un registro de ingreso para hoy.";
        exit();
    }

    // 5) Estado con tolerancia de 10 minutos
    $prog   = new DateTime("$hoy $horaProgramada");
    $now    = new DateTime("$hoy $ahora");
    $tolEnd = (clone $prog)->modify('+10 minutes');

    if ($now < $prog) {
        $detalleEstado = 'Ingreso antes de su hora de ingreso';
    } elseif ($now <= $tolEnd) {
        $detalleEstado = 'Ingreso a tiempo';
    } else {
        $detalleEstado = 'Atraso';
    }

    // 6) id_estado_ingreso (crear si no existe)
    $st = $db->prepare("SELECT id_estado_ingreso FROM estado_ingreso WHERE detalle_ingreso = ? LIMIT 1");
    $st->execute([$detalleEstado]);
    $idEstado = (int)($st->fetchColumn() ?: 0);
    if (!$idEstado) {
        $ins = $db->prepare("INSERT INTO estado_ingreso (detalle_ingreso) VALUES (?)");
        $ins->execute([$detalleEstado]);
        $idEstado = (int)$db->lastInsertId();
    }

    // 7) Insertar en horario_ingreso
    $st = $db->prepare("
        INSERT INTO horario_ingreso
            (id_usuario, id_hora_entrada, id_estado_ingreso, id_fecha_registro, hora_ingreso, latitud, longitud, direccion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([
        $uid,
        $idHoraEntrada,
        $idEstado,
        $idFecha,
        $ahora,
        $latitud,
        $longitud,
        $direccion
    ]);

    $db->commit();

    echo "✅ Ingreso registrado con estado: " . htmlspecialchars($detalleEstado, ENT_QUOTES, 'UTF-8');
    exit();

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('registrar_ingreso(ti): ' . $e->getMessage());
    http_response_code(500);
    echo "Error al procesar la solicitud.";
    exit();
}
