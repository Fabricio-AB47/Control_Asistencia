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
header('Content-Type: text/plain; charset=UTF-8');

// CSRF: validar token de la sesión vs cabecera/cuerpo
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['token']) || !is_string($csrfToken) || !hash_equals($_SESSION['token'], $csrfToken)) {
    http_response_code(403);
    exit('CSRF inválido');
}

$uid   = (int)$_SESSION['id_usuario'];
$hoy   = (new DateTime('now', new DateTimeZone('America/Guayaquil')))->format('Y-m-d');
$ahora = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

// === Body JSON (fallback a $_POST) ===
$payload  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$latitud  = $payload['latitud']   ?? null;
$longitud = $payload['longitud']  ?? null;
$direccion= $payload['direccion'] ?? null;

// === Exigir ubicación para proceder ===
if ($latitud === null || $longitud === null || !$direccion) {
    http_response_code(400);
    echo "❌ Debes activar y compartir tu ubicación para registrar el regreso del almuerzo.";
    exit();
}

// Normalizar a DECIMAL(9,6) y validar rango
$latitud  = number_format((float)$latitud, 6, '.', '');
$longitud = number_format((float)$longitud, 6, '.', '');
if ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
    http_response_code(422);
    echo "❌ Coordenadas inválidas.";
    exit();
}

try {
    $db->beginTransaction();

    // 1) Verificar que EXISTA fecha_registro de hoy (NO crear si falta)
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
        echo "ℹ️ No existe una fecha de registro para hoy. No se registró el regreso del almuerzo.";
        exit();
    }
    $idFecha = (int)$idFecha;

    // 2) Evitar duplicado de regreso del almuerzo
    $q = $db->prepare(
        'SELECT 1 FROM horario_rt_almuerzo
          WHERE id_usuario = ? AND id_fecha_registro = ?
          LIMIT 1'
    );
    $q->execute([$uid, $idFecha]);
    if ($q->fetch()) {
        $db->rollBack();
        echo "ℹ️ Ya se registró el regreso del almuerzo para hoy.";
        exit();
    }

    // 3) Obtener/crear estado catálogo
    $detalleEstado = 'Regreso de almuerzo';
    $q = $db->prepare(
        'SELECT id_estado_retorno_almuerzo
           FROM estado_retorno_almuerzo
          WHERE detalle_retorno_almuerzo = ?
          LIMIT 1'
    );
    $q->execute([$detalleEstado]);
    $idEstado = $q->fetchColumn();

    if (!$idEstado) {
        $ins = $db->prepare('INSERT INTO estado_retorno_almuerzo (detalle_retorno_almuerzo) VALUES (?)');
        $ins->execute([$detalleEstado]);
        $idEstado = (int)$db->lastInsertId();
    } else {
        $idEstado = (int)$idEstado;
    }

    // 4) Insertar regreso con ubicación
    $ins = $db->prepare(
        'INSERT INTO horario_rt_almuerzo
           (id_usuario, id_fecha_registro, id_estado_retorno_almuerzo, hora_rt_almuerzo, latitud, longitud, direccion)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$uid, $idFecha, $idEstado, $ahora, $latitud, $longitud, $direccion]);

    // (Se eliminaron todas las referencias a la tabla registro_control)

    $db->commit();

    echo "✅ Regreso del almuerzo registrado a las " . htmlspecialchars($ahora, ENT_QUOTES, 'UTF-8') . ".";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage();
}
?>
