<?php
namespace App\Controllers;

use App\Services\ReporteService;
use App\Services\RbacService;
use function app_session_guard;
use function app_ensure_csrf_token;
use function conexion;

class ReporteController extends BaseController
{
    private function parseYmd(?string $s): ?string {
        if (!$s) return null;
        $d = \DateTime::createFromFormat('Y-m-d', $s);
        return ($d && $d->format('Y-m-d') === $s) ? $s : null;
    }

    public function timbres(string $mod): void
    {
        app_session_guard(900);
        app_ensure_csrf_token();

        $nombre   = $_SESSION['nombre']   ?? 'Usuario';
        $apellido = $_SESSION['apellido'] ?? '';
        $rol      = $_SESSION['tipo']     ?? '';
        $uid      = (int)$_SESSION['id_usuario'];

        $rbac = new RbacService();
        // Admin puede ver reportes de cualquier módulo
        $roleNorm = strtoupper(strtr($rol,[
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
        ]));
        $isAdmin = ($roleNorm==='ADMIN' || $roleNorm==='ADMINISTRADOR');
        if (!$isAdmin && !$rbac->canAccessModule($rol, $mod)) {
            http_response_code(403);
            echo 'Acceso no autorizado para este módulo';
            return;
        }

        $hoy          = (new \DateTime('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $primerDiaMes = (new \DateTime('first day of this month'))->format('Y-m-d');
        $desde = $this->parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
        $hasta = $this->parseYmd($_GET['hasta'] ?? '') ?? $hoy;
        if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

        try {
            $db = conexion();
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }
        $svc = new ReporteService($db);
        $cfg = $svc->horarioProgramaUsuario($uid);
        $rows = $svc->timbres($uid, $desde, $hasta);

        $this->render('reporte/timbres', [
            'title'     => 'Reporte de Timbrado',
            'module'    => $mod,
            'nombre'    => $nombre,
            'apellido'  => $apellido,
            'menu'      => $rbac->menuForRole($rol, $mod),
            'desde'     => $desde,
            'hasta'     => $hasta,
            'rows'      => $rows,
            'horaProgIn'  => $cfg['hora_ingreso_personal'] ?? null,
            'horaProgOut' => $cfg['hora_salida_personal']  ?? null,
        ]);
    }

    public function timbresTodos(): void
    {
        app_session_guard(900);
        app_ensure_csrf_token();

        $rol      = $_SESSION['tipo']     ?? '';
        // Solo Admin puede ver el reporte global
        $roleNorm = strtoupper(strtr($rol,[ 'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N' ]));
        $isAdmin = ($roleNorm==='ADMIN' || $roleNorm==='ADMINISTRADOR');
        if (!$isAdmin) { http_response_code(403); echo 'Acceso no autorizado'; return; }

        $hoy          = (new \DateTime('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $primerDiaMes = (new \DateTime('first day of this month'))->format('Y-m-d');
        $desde = $this->parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
        $hasta = $this->parseYmd($_GET['hasta'] ?? '') ?? $hoy;
        if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

        $qName = trim((string)($_GET['q'] ?? ''));
        $fRol  = trim((string)($_GET['rol'] ?? ''));

        try { $db = conexion(); }
        catch (\RuntimeException $e) { http_response_code(500); echo 'No se pudo conectar: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); return; }
        $svc = new \App\Services\ReporteService($db);
        $rows = $svc->timbresAll($desde, $hasta, $qName, $fRol);

        // Lista de roles (excepto admin)
        $rolesList = ($db->query("SELECT detalle_tp_user FROM tipo_usuario WHERE id_tp_user <> 1 ORDER BY detalle_tp_user"))->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        $rbac = new \App\Services\RbacService();
        $this->render('reporte/todos', [
            'title'   => 'Reporte General de Timbres',
            'module'  => 'admin',
            'menu'    => $rbac->menuForRole($_SESSION['tipo'] ?? '', 'admin'),
            'desde'   => $desde,
            'hasta'   => $hasta,
            'rows'    => $rows,
            'q'       => $qName,
            'rolSel'  => $fRol,
            'rolesList' => $rolesList,
        ]);
    }
}
