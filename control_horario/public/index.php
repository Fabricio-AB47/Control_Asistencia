<?php
declare(strict_types=1);

require __DIR__ . '/../app/init.php';
app_boot_session();

use App\Controllers\ControlController;
use App\Controllers\ReporteController;
use App\Controllers\RegistrarController;
use App\Controllers\DashboardController;
use App\Controllers\AdminController;

$route = $_GET['r'] ?? $_GET['route'] ?? '';

// Simple router por query string (compatible con XAMPP sin .htaccess)
switch ($route) {
    case 'control':
        $mod = $_GET['mod'] ?? 'ti';
        $c = new ControlController();
        $c->index($mod);
        break;
    case 'reporte':
        $mod = $_GET['mod'] ?? 'ti';
        $c = new ReporteController();
        $c->timbres($mod);
        break;
    case 'reporte_all':
        $c = new ReporteController();
        $c->timbresTodos();
        break;
    case 'registrar':
        $mod = $_GET['mod'] ?? 'ti';
        $action = $_GET['action'] ?? '';
        $c = new RegistrarController();
        $c->handle($mod, $action);
        break;
    case 'dashboard':
        $mod = $_GET['mod'] ?? 'ti';
        $c = new DashboardController();
        $c->index($mod);
        break;
    case 'admin':
        $action = $_GET['action'] ?? 'roles';
        $a = new AdminController();
        switch ($action) {
            case 'roles':          $a->rolesIndex(); break;
            case 'roles_create':   $a->rolesCreate(); break;
            case 'users':          $a->usersIndex(); break;
            case 'users_create':   $a->usersCreate(); break;
            case 'schedules':      $a->schedulesIndex(); break;
            case 'schedules_save': $a->schedulesSave(); break;
            case 'arrival_stats':  $a->arrivalStats(); break;
            case 'arrival_details':$a->arrivalDetails(); break;
            case 'timbres_edit':   $a->timbresIndex(); break;
            case 'timbres_update': $a->timbresSave(); break;
            default:               $a->rolesIndex(); break;
        }
        break;
    default:
        // Ayuda mínima
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Rutas disponibles:\n";
        echo "- /public/index.php?r=control&mod=ti\n";
        echo "- /public/index.php?r=reporte&mod=ti&desde=YYYY-MM-DD&hasta=YYYY-MM-DD\n";
        echo "  (mod: ti | financiero | admisiones | academico | bienestar | autoridades | docente)\n";
}


