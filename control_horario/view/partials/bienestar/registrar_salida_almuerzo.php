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
$direccion = $payload['direccion'] ?? null;

// === Exigir ubicación para proceder ===
if ($latitud === null || $longitud === null || !$direccion) {
    http_response_code(400);
    echo "❌ Debes activar y compartir tu ubicación para registrar la salida al almuerzo.";
    echo "<script>setTimeout(function(){location.href='../bienestar/dashboard.php';},2000);</script>";
    exit();
}

// Validar rango y luego normalizar a DECIMAL(9,6)
$latF = (float)$latitud;
$lonF = (float)$longitud;
if ($latF < -90 || $latF > 90 || $lonF < -180 || $lonF > 180) {
    http_response_code(422);
    echo "❌ Coordenadas inválidas.";
    echo "<script>setTimeout(function(){location.href='../bienestar/dashboard.php';},2000);</script>";
    exit();
}
$latitud  = number_format($latF, 6, '.', '');
$longitud = number_format($lonF, 6, '.', '');

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
        echo "ℹ️ No existe una fecha de registro para hoy. No se registró la salida al almuerzo.";
        echo "<script>setTimeout(function(){location.href='../bienestar/dashboard.php';},2000);</script>";
        exit();
    }
    $idFecha = (int)$idFecha;

    // 2) Debe existir un ingreso hoy (para asociar y evitar salida sin ingreso)
    $q = $db->prepare(
        'SELECT id_hora_ingreso
           FROM horario_ingreso
          WHERE id_usuario = ? AND id_fecha_registro = ?
          LIMIT 1'
    );
    $q->execute([$uid, $idFecha]);
    $idHoraIngreso = (int)($q->fetchColumn() ?: 0);
    if ($idHoraIngreso <= 0) {
        $db->rollBack();
        echo "ℹ️ No puedes registrar salida al almuerzo sin haber registrado el ingreso.";
        echo "<script>setTimeout(function(){location.href='../bienestar/dashboard.php';},1500);</script>";
        exit();
    }

    // 3) Evitar duplicado de salida al almuerzo
    $q = $db->prepare(
        'SELECT 1
           FROM horario_sl_almuerzo
          WHERE id_usuario = ? AND id_fecha_registro = ?
          LIMIT 1'
    );
    $q->execute([$uid, $idFecha]);
    if ($q->fetch()) {
        $db->rollBack();
        echo "ℹ️ Ya se registró la salida al almuerzo para hoy.";
        echo "<script>setTimeout(function(){location.href='../bienestar/dashboard.php';},1500);</script>";
        exit();
    }

    // 4) Obtener/crear estado catálogo ("Salida al almuerzo")
    $detalleEstado = 'Salida al almuerzo';
    $q = $db->prepare(
        'SELECT id_estado_salida_almuerzo
           FROM estado_salida_almuerzo
          WHERE detalle_salida_almuerzo = ?
          LIMIT 1'
    );
    $q->execute([$detalleEstado]);
    $idEstado = $q->fetchColumn();

    if (!$idEstado) {
        $ins = $db->prepare(
            'INSERT INTO estado_salida_almuerzo (detalle_salida_almuerzo) VALUES (?)'
        );
        $ins->execute([$detalleEstado]);
        $idEstado = (int)$db->lastInsertId();
    } else {
        $idEstado = (int)$idEstado;
    }

    // 5) Insertar salida al almuerzo con ubicación
    $ins = $db->prepare(
        'INSERT INTO horario_sl_almuerzo
           (id_usuario, id_fecha_registro, id_estado_salida_almuerzo, hora_sl_almuerzo, latitud, longitud, direccion)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$uid, $idFecha, $idEstado, $ahora, $latitud, $longitud, $direccion]);

    // (Se eliminó cualquier consulta/actualización a la tabla registro_control)

    $db->commit();

    echo "✅ Salida al almuerzo registrada a las " . htmlspecialchars($ahora, ENT_QUOTES, 'UTF-8') . ".";
    echo "<script>setTimeout(function(){location.href='../bienestar/dashboard.php';},1500);</script>";

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo "❌ Error: " . $e->getMessage();
    echo "<script>setTimeout(function(){location.href='../bienestar/dashboard.php';},2000);</script>";
}
