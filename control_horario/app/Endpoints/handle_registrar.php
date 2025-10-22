<?php
use App\Services\ControlService;

// Precondición: session_start() ya fue llamado por el endpoint.
require_once __DIR__ . '/../init.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo 'No autorizado';
    return;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido';
    return;
}

if (!app_csrf_valid()) {
    http_response_code(403);
    echo 'CSRF inválido';
    return;
}

$uid = (int)$_SESSION['id_usuario'];
$input = app_json_input();

try {
    $db = conexion();
    $svc = new ControlService($db);
    $action = $action ?? '';
    switch ($action) {
        case 'ingreso':
            $msg = $svc->registrarIngreso($uid, $input);
            echo $msg; break;
        case 'salida_almuerzo':
            $msg = $svc->registrarSalidaAlmuerzo($uid, $input);
            echo $msg; break;
        case 'regreso_almuerzo':
            $msg = $svc->registrarRegresoAlmuerzo($uid, $input);
            echo $msg; break;
        case 'salida_laboral':
            $msg = $svc->registrarSalidaLaboral($uid, $input);
            echo $msg; break;
        default:
            http_response_code(400);
            echo 'Acción no soportada';
    }
} catch (Throwable $e) {
    error_log('registrar: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error al procesar la solicitud.';
}
