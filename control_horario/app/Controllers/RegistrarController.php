<?php
namespace App\Controllers;

use App\Services\ControlService;

class RegistrarController
{
    public function handle(string $mod, string $action): void
    {
        app_boot_session();
        header('Content-Type: text/plain; charset=UTF-8');

        if (!isset($_SESSION['id_usuario'])) { http_response_code(401); echo 'No autorizado'; return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Método no permitido'; return; }
        if (!\app_csrf_valid()) { http_response_code(403); echo 'CSRF inválido'; return; }

        // RBAC adicional: asegurar que el rol puede operar en el módulo solicitado
        $rol = $_SESSION['tipo'] ?? '';
        $rbac = new \App\Services\RbacService();
        $roleNorm = strtoupper(strtr($rol,[ 'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N' ]));
        $isAdmin = ($roleNorm==='ADMIN' || $roleNorm==='ADMINISTRADOR');
        if (!$isAdmin && !$rbac->canAccessModule($rol, $mod)) {
            http_response_code(403); echo 'Acceso no autorizado para el módulo'; return;
        }

        // Rate limiting básico por acción
        if (!app_rate_limit('post_'.$action.'_'.$mod, 5, 10)) { http_response_code(429); echo 'Demasiadas solicitudes. Intenta nuevamente en unos segundos.'; return; }

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
