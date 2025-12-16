<?php
use App\Services\ControlService;
use App\Security\InputValidator;

// Precondición: session_start() ya fue llamado por el endpoint.
require_once __DIR__ . '/../init.php';

// Mantener texto plano para compatibilidad con front existente
header('Content-Type: text/plain; charset=UTF-8');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    return;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido';
    return;
}

if (!app_csrf_valid()) {
    http_response_code(403);
    echo 'Token CSRF inválido';
    return;
}

$uid = (int)$_SESSION['id_usuario'];
$input = app_json_input();

// Rate limit por usuario para evitar abuso de timbrado automatizado
if (!app_rate_limit('registrar_'.$uid, 8, 60)) {
    http_response_code(429);
    echo 'Demasiadas solicitudes. Intenta nuevamente en un minuto.';
    return;
}

try {
    $db = conexion();
    $svc = new ControlService($db);
    
    // Determinar la acción desde la URL, POST o el JSON recibido
    $actionRaw = $_GET['action'] ?? $_POST['action'] ?? ($input['accion'] ?? '');
    $actionRaw = is_string($actionRaw) ? trim($actionRaw) : '';

    // Normaliza la acción para aceptar etiquetas del front ("Ingreso", "Salida al Almuerzo", etc.)
    $map = [
        'ingreso'                => 'ingreso',
        'registrar ingreso'      => 'ingreso',
        'entrada'                => 'ingreso',
        'salida al almuerzo'     => 'salida_almuerzo',
        'salida_almuerzo'        => 'salida_almuerzo',
        'regreso del almuerzo'   => 'regreso_almuerzo',
        'regreso_almuerzo'       => 'regreso_almuerzo',
        'salida laboral'         => 'salida_laboral',
        'salida_laboral'         => 'salida_laboral',
        'docente_ingreso'        => 'docente_ingreso',
        'ingreso docente'        => 'docente_ingreso',
        'docente_fin'            => 'docente_fin',
        'fin docente'            => 'docente_fin',
    ];
    $actionKey = strtolower(str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $actionRaw));
    $action = $map[$actionKey] ?? $actionKey;
    if ($action === '') {
        http_response_code(400);
        echo 'Acción requerida';
        return;
    }
    
    // Validar que action sea un string conocido (whitelist)
    $allowed_actions = [
        'ingreso',
        'salida_almuerzo',
        'regreso_almuerzo',
        'salida_laboral',
        // Acciones para docentes (compatibilidad)
        'docente_ingreso',
        'docente_fin'
    ];
    $action = InputValidator::validateEnum($action, $allowed_actions);
    
    switch ($action) {
        case 'ingreso':
            echo $svc->registrarIngreso($uid, $input);
            break;
        case 'salida_almuerzo':
            echo $svc->registrarSalidaAlmuerzo($uid, $input);
            break;
        case 'regreso_almuerzo':
            echo $svc->registrarRegresoAlmuerzo($uid, $input);
            break;
        case 'salida_laboral':
            echo $svc->registrarSalidaLaboral($uid, $input);
            break;
        case 'docente_ingreso':
            echo $svc->registrarDocenteIngreso($uid, $input);
            break;
        case 'docente_fin':
            echo $svc->registrarDocenteFin($uid, $input);
            break;
        default:
            http_response_code(400);
            echo 'Acción no soportada';
    }
} catch (RuntimeException $e) {
    http_response_code(400);
    echo $e->getMessage();
} catch (Exception $e) {
    error_log('registrar error: ' . $e->getMessage() . ' [' . getClientIP() . ']');
    http_response_code(500);
    echo 'Error al procesar la solicitud.';
}
