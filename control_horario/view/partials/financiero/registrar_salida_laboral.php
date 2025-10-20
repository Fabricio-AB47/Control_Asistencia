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

$uid   = (int)$_SESSION['id_usuario'];
$hoy   = (new DateTime('now', new DateTimeZone('America/Guayaquil')))->format('Y-m-d');
$ahora = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// === Body JSON (fallback a $_POST) ===
$payload   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$latitud   = $payload['latitud']   ?? null;
$longitud  = $payload['longitud']  ?? null;
$direccion = isset($payload['direccion']) ? trim((string)$payload['direccion']) : null;

// === Exigir geolocalización válida para salida laboral ===
if ($latitud === null || $longitud === null || $direccion === null || $direccion === '') {
    http_response_code(400);
    echo "❌ Debes activar y compartir tu ubicación para registrar la salida laboral.";
    echo "<script>setTimeout(function(){location.href='../financiero/dashboard.php';},2000);</script>";
    exit();
}
$latF = (float)$latitud;
$lonF = (float)$longitud;
if ($latF < -90 || $latF > 90 || $lonF < -180 || $lonF > 180) {
    http_response_code(422);
    echo "❌ Coordenadas inválidas.";
    echo "<script>setTimeout(function(){location.href='../financiero/dashboard.php';},2000);</script>";
    exit();
}
// Normalizar a DECIMAL(9,6)
$latitud  = number_format($latF, 6, '.', '');
$longitud = number_format($lonF, 6, '.', '');

// ---- Helpers ----
function ensureIdEstadoSalida(PDO $db, string $detalle): int {
    $q = $db->prepare('SELECT id_estado_salida FROM estado_salida WHERE detalle_salida = ? LIMIT 1');
    $q->execute([$detalle]);
    $id = (int)($q->fetchColumn() ?: 0);
    if (!$id) {
        $ins = $db->prepare('INSERT INTO estado_salida (detalle_salida) VALUES (?)');
        $ins->execute([$detalle]);
        $id = (int)$db->lastInsertId();
    }
    return $id;
}
function ensureIdEstadoSalidaAlmuerzo(PDO $db, string $detalle): int {
    $q = $db->prepare('SELECT id_estado_salida_almuerzo FROM estado_salida_almuerzo WHERE detalle_salida_almuerzo = ? LIMIT 1');
    $q->execute([$detalle]);
    $id = (int)($q->fetchColumn() ?: 0);
    if (!$id) {
        $ins = $db->prepare('INSERT INTO estado_salida_almuerzo (detalle_salida_almuerzo) VALUES (?)');
        $ins->execute([$detalle]);
        $id = (int)$db->lastInsertId();
    }
    return $id;
}
function ensureIdEstadoRetornoAlmuerzo(PDO $db, string $detalle): int {
    $q = $db->prepare('SELECT id_estado_retorno_almuerzo FROM estado_retorno_almuerzo WHERE detalle_retorno_almuerzo = ? LIMIT 1');
    $q->execute([$detalle]);
    $id = (int)($q->fetchColumn() ?: 0);
    if (!$id) {
        $ins = $db->prepare('INSERT INTO estado_retorno_almuerzo (detalle_retorno_almuerzo) VALUES (?)');
        $ins->execute([$detalle]);
        $id = (int)$db->lastInsertId();
    }
    return $id;
}

try {
    $db->beginTransaction();

    // 1) Verificar fecha_registro HOY (NO crear si falta)
    $q = $db->prepare(
        'SELECT id_fecha_registro
           FROM fecha_registro
          WHERE id_usuario = ? AND fecha_ingreso = ?
          LIMIT 1 FOR UPDATE'
    );
    $q->execute([$uid, $hoy]);
    $idFecha = $q->fetchColumn();
    if (!$idFecha) {
        $db->rollBack();
        echo "ℹ️ No existe una fecha de registro para hoy. No se registró la salida laboral.";
        echo "<script>setTimeout(function(){location.href='../financiero/dashboard.php';},2000);</script>";
        exit();
    }
    $idFecha = (int)$idFecha;

    // 2) Evitar duplicado de salida laboral del día
    $q = $db->prepare(
        'SELECT 1 FROM horario_salida WHERE id_usuario = ? AND id_fecha_registro = ? LIMIT 1'
    );
    $q->execute([$uid, $idFecha]);
    if ($q->fetch()) {
        $db->rollBack();
        echo "ℹ️ Ya se registró la salida laboral para hoy.";
        echo "<script>setTimeout(function(){location.href='../financiero/dashboard.php';},1500);</script>";
        exit();
    }

    // 3) Obtener tipo de usuario y su horario de salida personal
    $q = $db->prepare('SELECT id_tp_user FROM usuario WHERE id_usuario = ? LIMIT 1');
    $q->execute([$uid]);
    $idTpUser = (int)($q->fetchColumn() ?: 0);
    if (!$idTpUser) {
        throw new RuntimeException('Usuario sin tipo asignado.');
    }

    $q = $db->prepare(
        'SELECT hora_salida_personal
           FROM horario_salida_personal
          WHERE id_usuario = ? AND id_tp_user = ?
          LIMIT 1'
    );
    $q->execute([$uid, $idTpUser]);
    $horaSalidaProgramada = $q->fetchColumn();
    if (!$horaSalidaProgramada) {
        throw new RuntimeException('No se encontró un horario de salida para tu usuario/tipo. Contacta al administrador.');
    }

    // 4) Determinar estado_salida (Fin de jornada laboral / Salida anticipada)
    $prog = new DateTime("$hoy $horaSalidaProgramada");
    $now  = new DateTime("$hoy $ahora");
    $detalleEstado = ($now >= $prog) ? 'Fin de jornada laboral' : 'Salida anticipada';

    // 5) id_estado_salida
    $idEstadoSalida = ensureIdEstadoSalida($db, $detalleEstado);

    // 6) **ASEGURAR PLACEHOLDERS DE ALMUERZO (con dirección = 'NA')**
    // 6.1) Salida a almuerzo (tabla: horario_sl_almuerzo)
    $q = $db->prepare(
        'SELECT 1
           FROM horario_sl_almuerzo
          WHERE id_usuario = ? AND id_fecha_registro = ?
          LIMIT 1 FOR UPDATE'
    );
    $q->execute([$uid, $idFecha]);
    $tieneSalidaAlmuerzo = (bool)$q->fetchColumn();

    if (!$tieneSalidaAlmuerzo) {
        $idEstadoSalidaAlm = ensureIdEstadoSalidaAlmuerzo($db, 'No registrado');
        $ins = $db->prepare(
            'INSERT INTO horario_sl_almuerzo
               (id_usuario, id_fecha_registro, id_estado_salida_almuerzo, hora_sl_almuerzo, latitud, longitud, direccion)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$uid, $idFecha, $idEstadoSalidaAlm, '00:00:00', '0.000000', '0.000000', 'NA']);
    }

    // 6.2) Retorno de almuerzo (tabla: horario_rt_almuerzo)
    $q = $db->prepare(
        'SELECT 1
           FROM horario_rt_almuerzo
          WHERE id_usuario = ? AND id_fecha_registro = ?
          LIMIT 1 FOR UPDATE'
    );
    $q->execute([$uid, $idFecha]);
    $tieneRetornoAlmuerzo = (bool)$q->fetchColumn();

    if (!$tieneRetornoAlmuerzo) {
        $idEstadoRetAlm = ensureIdEstadoRetornoAlmuerzo($db, 'No registrado');
        $ins = $db->prepare(
            'INSERT INTO horario_rt_almuerzo
               (id_usuario, id_fecha_registro, id_estado_retorno_almuerzo, hora_rt_almuerzo, latitud, longitud, direccion)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$uid, $idFecha, $idEstadoRetAlm, '00:00:00', '0.000000', '0.000000', 'NA']);
    }

    // 7) Insertar salida laboral con ubicación real
    $ins = $db->prepare(
        'INSERT INTO horario_salida
           (id_usuario, id_fecha_registro, id_estado_salida, hora_salida, latitud, longitud, direccion)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$uid, $idFecha, $idEstadoSalida, $ahora, $latitud, $longitud, $direccion]);

    $db->commit();

    echo "✅ Salida laboral registrada a las " . htmlspecialchars($ahora, ENT_QUOTES, 'UTF-8') .
         " (estado: " . htmlspecialchars($detalleEstado, ENT_QUOTES, 'UTF-8') . ").";
    echo "<script>setTimeout(function(){location.href='../financiero/dashboard.php';},1500);</script>";

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage();
    echo "<script>setTimeout(function(){location.href='../financiero/dashboard.php';},2000);</script>";
}
