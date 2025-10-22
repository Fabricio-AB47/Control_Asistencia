<?php
namespace App\Controllers;

use App\Services\RbacService;

class DashboardController extends BaseController
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

        $this->render('dashboard/index', [
            'title'     => 'Dashboard',
            'module'    => $mod,
            'nombre'    => $nombre,
            'apellido'  => $apellido,
            'menu'      => $rbac->menuForRole($rol, $mod),
            'base'      => (function_exists('appBasePath') ? appBasePath() : ''),
        ]);
    }
}

