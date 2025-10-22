<?php
namespace App\Controllers;

use App\Services\ControlService;

class RegistrarController
{
    public function handle(string $mod, string $action): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        header('Content-Type: text/plain; charset=UTF-8');

        if (!isset($_SESSION['id_usuario'])) { http_response_code(401); echo 'No autorizado'; return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Método no permitido'; return; }
        if (!\app_csrf_valid()) { http_response_code(403); echo 'CSRF inválido'; return; }

        $uid = (int)$_SESSION['id_usuario'];
        $input = \app_json_input();

        try {
            $db = \conexion();
            $svc = new ControlService($db);
            switch ($action) {
                case 'ingreso':           echo $svc->registrarIngreso($uid, $input); break;
                case 'salida_almuerzo':   echo $svc->registrarSalidaAlmuerzo($uid, $input); break;
                case 'regreso_almuerzo':  echo $svc->registrarRegresoAlmuerzo($uid, $input); break;
                case 'salida_laboral':    echo $svc->registrarSalidaLaboral($uid, $input); break;
                default: http_response_code(400); echo 'Acción no soportada';
            }
        } catch (\Throwable $e) {
            error_log('registrar-mvc: '.$e->getMessage());
            http_response_code(500);
            echo 'Error al procesar la solicitud.';
        }
    }
}

