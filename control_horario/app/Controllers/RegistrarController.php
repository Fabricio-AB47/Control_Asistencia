<?php
namespace App\Controllers;

use App\Services\ControlService;

class RegistrarController
{
    public function handle(string $mod, string $action): void
    {
        app_boot_session();
        \app_session_guard(900);
        header('Content-Type: text/plain; charset=UTF-8');
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
        // Limitar la excepción de Docente solo a roles Docente
        $isDoc = ($roleNorm==='DOCENTE' || $roleNorm==='DOCENTES');
        $isDocAction   = in_array($action, ['docente_ingreso','docente_fin'], true);
        $isSchedAction = in_array($action, ['ingreso','salida_almuerzo','regreso_almuerzo','salida_laboral'], true);
        if ($isDocAction && !$isDoc) { http_response_code(403); echo 'Solo el rol DOCENTE puede usar esta acción.'; return; }
        if ($mod === 'docente' && $isSchedAction) { http_response_code(400); echo 'Usa las acciones de Docente para timbrar.'; return; }

        if (!app_rate_limit('post_'.$action.'_'.$mod, 5, 10)) { http_response_code(429); echo 'Demasiadas solicitudes. Intenta nuevamente en unos segundos.'; return; }

        $uid = (int)$_SESSION['id_usuario'];
        $input = \app_json_input();

        // Normaliza la acción para aceptar etiquetas del front (por si falta en la query)
        $actionRaw = $action ?: ($input['accion'] ?? '');
        $actionRaw = is_string($actionRaw) ? trim($actionRaw) : '';
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
        if ($action === '') { http_response_code(400); echo 'Acción requerida'; return; }

        try {
            $db = \conexion();
            $svc = new ControlService($db);
            switch ($action) {
                case 'ingreso':           echo $svc->registrarIngreso($uid, $input); break;
                case 'salida_almuerzo':   echo $svc->registrarSalidaAlmuerzo($uid, $input); break;
                case 'regreso_almuerzo':  echo $svc->registrarRegresoAlmuerzo($uid, $input); break;
                case 'salida_laboral':    echo $svc->registrarSalidaLaboral($uid, $input); break;
                case 'docente_ingreso':   echo $svc->registrarDocenteIngreso($uid, $input); break;
                case 'docente_fin':       echo $svc->registrarDocenteFin($uid, $input); break;
                default: http_response_code(400); echo 'Acción no soportada';
            }
        } catch (\Throwable $e) {
            error_log('registrar-mvc: '.$e->getMessage());
            http_response_code(500);
            $msg = trim($e->getMessage());
            if ($msg === '') {
                $msg = 'Ocurri� un error al procesar la solicitud.';
            }
            echo 'Error: ' . $msg;
        }
    }
}


