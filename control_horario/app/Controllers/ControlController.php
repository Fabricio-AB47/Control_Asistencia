<?php
namespace App\Controllers;

use App\Services\RbacService;

class ControlController extends BaseController
{
    public function index(string $mod): void
    {
        \app_session_guard(900);
        \app_ensure_csrf_token();

        $nombre   = $_SESSION['nombre']   ?? 'Usuario';
        $apellido = $_SESSION['apellido'] ?? '';
        $rol      = $_SESSION['tipo']     ?? '';

        $rbac = new RbacService();
        if (!$rbac->canAccessModule($rol, $mod)) {
            http_response_code(403);
            echo 'Acceso no autorizado para este módulo';
            return;
        }

        $redirect = $rbac->dashboardForModule($mod);

        // Normaliza acentos del rol para comparaciones confiables
        $roleNorm = strtoupper(strtr($rol, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
        ]));
        $isDocente = ($mod === 'docente' && ($roleNorm==='DOCENTE' || $roleNorm==='DOCENTES'));
        $viewName = $isDocente ? 'control/docente' : 'control/index';

        $this->render($viewName, [
            'title'     => 'Control Horario',
            'module'    => $mod,
            'redirect'  => $redirect,
            'nombre'    => $nombre,
            'apellido'  => $apellido,
            'menu'      => $rbac->menuForRole($rol, $mod),
            'base'      => (function_exists('appBasePath') ? appBasePath() : ''),
        ]);
    }
}

