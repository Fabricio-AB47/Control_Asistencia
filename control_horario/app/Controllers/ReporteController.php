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
        // Normaliza acentos del rol para comparaciones
        $roleNorm = strtoupper(strtr($rol, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
        ]));
        $isAdmin = ($roleNorm==='ADMIN' || $roleNorm==='ADMINISTRADOR');
        if (!$isAdmin && !$rbac->canAccessModule($rol, $mod)) {
            http_response_code(403);
            echo 'Acceso no autorizado para este modulo';
            return;
        }

        $hoy          = (new \DateTime('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $primerDiaMes = (new \DateTime('first day of this month'))->format('Y-m-d');
        $desde = $this->parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
        $hasta = $this->parseYmd($_GET['hasta'] ?? '') ?? $hoy;
        if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

        $export = (isset($_GET['export']) && $_GET['export'] === 'excel');

        try { $db = conexion(); }
        catch (\RuntimeException $e) { http_response_code(500); echo 'No se pudo conectar a la base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'); return; }

        $svc = new ReporteService($db);
        $roleNorm2 = strtoupper(strtr($rol, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
        ]));
        $isDoc = ($mod === 'docente' && ($roleNorm2 === 'DOCENTE' || $roleNorm2 === 'DOCENTES'));
        if ($isDoc) {
            $rows = $svc->timbresDocente($uid, $desde, $hasta);
            if ($export) {
                $this->exportTimbresDocenteExcel($rows, $desde, $hasta);
                return;
            }
            $view = 'reporte/timbres_docente';
            $cfg = ['hora_ingreso_personal'=>null,'hora_salida_personal'=>null];
        } else {
            $cfg = $svc->horarioProgramaUsuario($uid);
            $rows = $svc->timbres($uid, $desde, $hasta);
            if ($export) {
                $this->exportTimbresExcel(
                    $rows,
                    $desde,
                    $hasta,
                    $cfg['hora_ingreso_personal'] ?? null,
                    $cfg['hora_salida_personal']  ?? null
                );
                return;
            }
            $view = 'reporte/timbres';
        }

        $this->render($view, [
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
        $roleNorm = strtoupper(strtr($rol, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
            'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
        ]));
        $isAdmin = ($roleNorm==='ADMIN' || $roleNorm==='ADMINISTRADOR');
        if (!$isAdmin) { http_response_code(403); echo 'Acceso no autorizado'; return; }

        $hoy          = (new \DateTime('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $primerDiaMes = (new \DateTime('first day of this month'))->format('Y-m-d');
        $desde = $this->parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
        $hasta = $this->parseYmd($_GET['hasta'] ?? '') ?? $hoy;
        if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

        $qName = trim((string)($_GET['q'] ?? ''));
        $fRol  = trim((string)($_GET['rol'] ?? ''));
        $export = (isset($_GET['export']) && $_GET['export'] === 'excel');

        try { $db = conexion(); }
        catch (\RuntimeException $e) { http_response_code(500); echo 'No se pudo conectar: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); return; }
        $svc = new \App\Services\ReporteService($db);
        $rows = $svc->timbresAll($desde, $hasta, $qName, $fRol);

        if ($export) {
            $this->exportTimbresAllExcel($rows, $desde, $hasta, $qName, $fRol);
            return;
        }

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

    /**
     * Exporta a Excel el reporte personal de timbres (no docente).
     */
    private function exportTimbresExcel(array $rows, string $desde, string $hasta, ?string $horaProgIn, ?string $horaProgOut): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="timbres_'.$desde.'_'.$hasta.'.xls"');
        // BOM para que Excel detecte UTF-8
        echo "\xEF\xBB\xBF";

        $h = static function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $secs = static function ($a, $b) {
            if (!$a || !$b) return 0;
            return $b->getTimestamp() - $a->getTimestamp();
        };
        $hhmmss = static function ($seconds) {
            if ($seconds <= 0) return '00:00:00';
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);
            $s = $seconds % 60;
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        };

        // HTML con cabecera de texto y estilos básicos
        echo "<html><head><meta charset=\"UTF-8\">";
        echo "<style>
body{font-family:Arial,Helvetica,sans-serif;}
table{border-collapse:collapse;width:100%;}
th,td{border:1px solid #000;padding:3px 5px;font-size:11px;}
th{background-color:#f2f2f2;font-weight:bold;}
</style>";
        echo "</head><body>";

        // Cabecera tipo INTEC, sin imágenes
        echo "<table border=\"1\" style=\"margin-bottom:10px;\">";
        echo "<tr>";
        echo "<td style=\"width:120px;\">&nbsp;</td>";
        echo "<td style=\"text-align:left;\">";
        echo "<div style=\"font-weight:bold;font-size:16px;\">INTEC</div>";
        echo "<div style=\"font-size:12px;\">Reporte de Timbres (Personal)</div>";
        echo "<div style=\"font-size:11px;\">Rango: ".$h($desde)." al ".$h($hasta)."</div>";
        echo "</td>";
        echo "</tr>";
        echo "</table>";

        echo "<table>\n";
        echo "<thead><tr>";
        $cols = [
            'Fecha',
            'Ingreso',
            'Salida almuerzo',
            'Retorno almuerzo',
            'Salida',
            'Jornada',
            'Almuerzo',
            'Horas efectivas',
            'Horas extra (>8h)',
            'Tardanza (min)',
            'Salida anticipada (min)',
        ];
        foreach ($cols as $col) {
            echo '<th>'.$h($col).'</th>';
        }
        echo "</tr></thead>\n<tbody>\n";

        foreach ($rows as $r) {
            $fecha = $r['fecha'] ?? '';
            $dtIn  = (!empty($r['hora_ingreso']))     ? new \DateTime($fecha.' '.$r['hora_ingreso']) : null;
            $dtSl  = (!empty($r['hora_sl_almuerzo'])) ? new \DateTime($fecha.' '.$r['hora_sl_almuerzo']) : null;
            $dtRt  = (!empty($r['hora_rt_almuerzo'])) ? new \DateTime($fecha.' '.$r['hora_rt_almuerzo']) : null;
            $dtOut = (!empty($r['hora_salida']))      ? new \DateTime($fecha.' '.$r['hora_salida']) : null;

            $secJornada  = ($dtIn && $dtOut) ? $secs($dtIn, $dtOut) : 0;
            $secAlmuerzo = ($dtSl && $dtRt)  ? max(0, $secs($dtSl, $dtRt)) : 0;
            $secEfectiva = max(0, $secJornada - $secAlmuerzo);
            $secExtra8h  = max(0, $secEfectiva - 28800);

            $tardanzaMin = 0;
            if ($horaProgIn && $dtIn) {
                $dtProgIn = new \DateTime($fecha.' '.$horaProgIn);
                $dif = $secs($dtProgIn, $dtIn); // positivo si llegó después
                $tardanzaMin = max(0, intdiv($dif, 60) - 10);
            }

            $salidaAntMin = 0;
            if ($horaProgOut && $dtOut) {
                $dtProgOut = new \DateTime($fecha.' '.$horaProgOut);
                $dif = $secs($dtOut, $dtProgOut); // negativo si salió antes
                $salidaAntMin = $dif < 0 ? intdiv(abs($dif), 60) : 0;
            }

            echo "<tr>";
            echo "<td>".$h($fecha)."</td>";
            echo "<td>".$h($r['hora_ingreso']     ?? '')."</td>";
            echo "<td>".$h($r['hora_sl_almuerzo'] ?? '')."</td>";
            echo "<td>".$h($r['hora_rt_almuerzo'] ?? '')."</td>";
            echo "<td>".$h($r['hora_salida']      ?? '')."</td>";
            echo "<td>".$h($hhmmss($secJornada))."</td>";
            echo "<td>".$h($hhmmss($secAlmuerzo))."</td>";
            echo "<td>".$h($hhmmss($secEfectiva))."</td>";
            echo "<td>".$h($hhmmss($secExtra8h))."</td>";
            echo "<td>".$h($tardanzaMin)."</td>";
            echo "<td>".$h($salidaAntMin)."</td>";
            echo "</tr>\n";
        }

        echo "</tbody></table>";
        echo "</body></html>";
    }

    /**
     * Exporta a Excel el reporte docente personal.
     */
    private function exportTimbresDocenteExcel(array $rows, string $desde, string $hasta): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="timbres_docente_'.$desde.'_'.$hasta.'.xls"');
        echo "\xEF\xBB\xBF";

        $h = static function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };

        echo "<html><head><meta charset=\"UTF-8\">";
        echo "<style>
body{font-family:Arial,Helvetica,sans-serif;}
table{border-collapse:collapse;width:100%;}
th,td{border:1px solid #000;padding:3px 5px;font-size:11px;}
th{background-color:#f2f2f2;font-weight:bold;}
</style>";
        echo "</head><body>";

        echo "<table border=\"1\" style=\"margin-bottom:10px;\">";
        echo "<tr>";
        echo "<td style=\"width:120px;\">&nbsp;</td>";
        echo "<td style=\"text-align:left;\">";
        echo "<div style=\"font-weight:bold;font-size:16px;\">INTEC</div>";
        echo "<div style=\"font-size:12px;\">Reporte Docente de Timbres</div>";
        echo "<div style=\"font-size:11px;\">Rango: ".$h($desde)." al ".$h($hasta)."</div>";
        echo "</td>";
        echo "</tr>";
        echo "</table>";

        echo "<table>\n";
        echo "<thead><tr>";
        $cols = [
            'Fecha',
            'Ingreso 1',
            'Ingreso 2',
            'Ingreso 3',
            'Fin 1',
            'Fin 2',
            'Fin 3',
        ];
        foreach ($cols as $col) {
            echo '<th>'.$h($col).'</th>';
        }
        echo "</tr></thead>\n<tbody>\n";

        foreach ($rows as $r) {
            $fecha = $r['fecha'] ?? '';
            $ingList = array_values(array_filter(explode(';;', (string)($r['ingresos'] ?? ''))));
            $outList = array_values(array_filter(explode(';;', (string)($r['salidas'] ?? ''))));

            $ingTimes = [];
            foreach ($ingList as $item) {
                $parts = explode('|', $item);
                $ingTimes[] = $parts[0] ?? '';
            }
            $outTimes = [];
            foreach ($outList as $item) {
                $parts = explode('|', $item);
                $outTimes[] = $parts[0] ?? '';
            }

            echo "<tr>";
            echo "<td>".$h($fecha)."</td>";
            echo "<td>".$h($ingTimes[0] ?? '')."</td>";
            echo "<td>".$h($ingTimes[1] ?? '')."</td>";
            echo "<td>".$h($ingTimes[2] ?? '')."</td>";
            echo "<td>".$h($outTimes[0] ?? '')."</td>";
            echo "<td>".$h($outTimes[1] ?? '')."</td>";
            echo "<td>".$h($outTimes[2] ?? '')."</td>";
            echo "</tr>\n";
        }

        echo "</tbody></table>";
        echo "</body></html>";
    }

    /**
     * Exporta a Excel el reporte general (timbresTodos).
     */
    private function exportTimbresAllExcel(array $rows, string $desde, string $hasta, string $qName, string $fRol): void
    {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="timbres_general_'.$desde.'_'.$hasta.'.xls"');
        echo "\xEF\xBB\xBF";

        $h = static function ($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        };
        $secs = static function ($a, $b) {
            if (!$a || !$b) return 0;
            return $b->getTimestamp() - $a->getTimestamp();
        };
        $hhmmss = static function ($seconds) {
            if ($seconds <= 0) return '00:00:00';
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);
            $s = $seconds % 60;
            return sprintf('%02d:%02d:%02d', $h, $m, $s);
        };

        echo "<html><head><meta charset=\"UTF-8\">";
        echo "<style>
body{font-family:Arial,Helvetica,sans-serif;}
table{border-collapse:collapse;width:100%;}
th,td{border:1px solid #000;padding:3px 5px;font-size:11px;}
th{background-color:#f2f2f2;font-weight:bold;}
</style>";
        echo "</head><body>";

        echo "<table border=\"1\" style=\"margin-bottom:10px;\">";
        echo "<tr>";
        echo "<td style=\"width:120px;\">&nbsp;</td>";
        echo "<td style=\"text-align:left;\">";
        echo "<div style=\"font-weight:bold;font-size:16px;\">INTEC</div>";
        echo "<div style=\"font-size:12px;\">Reporte General de Timbres</div>";
        echo "<div style=\"font-size:11px;\">Rango: ".$h($desde)." al ".$h($hasta)."</div>";
        echo "</td>";
        echo "</tr>";
        echo "</table>";

        echo "<table>\n";
        echo "<thead><tr>";
        $cols = [
            'Fecha',
            'Usuario',
            'Rol',
            'Hora prog. ingreso',
            'Hora prog. salida',
            'Ingreso',
            'Salida almuerzo',
            'Retorno almuerzo',
            'Salida',
            'Jornada',
            'Almuerzo',
            'Horas efectivas',
            'Horas extra (>8h)',
            'Tardanza (min)',
            'Tiempo adicional (min)',
        ];
        foreach ($cols as $col) {
            echo '<th>'.$h($col).'</th>';
        }
        echo "</tr></thead>\n<tbody>\n";

        foreach ($rows as $r) {
            $fecha = $r['fecha'] ?? '';
            $dtIn  = (!empty($r['hora_ingreso']))     ? new \DateTime($fecha.' '.$r['hora_ingreso']) : null;
            $dtSl  = (!empty($r['hora_sl_almuerzo'])) ? new \DateTime($fecha.' '.$r['hora_sl_almuerzo']) : null;
            $dtRt  = (!empty($r['hora_rt_almuerzo'])) ? new \DateTime($fecha.' '.$r['hora_rt_almuerzo']) : null;
            $dtOut = (!empty($r['hora_salida']))      ? new \DateTime($fecha.' '.$r['hora_salida']) : null;

            $secJornada  = ($dtIn && $dtOut) ? $secs($dtIn, $dtOut) : 0;
            $secAlmuerzo = ($dtSl && $dtRt)  ? max(0, $secs($dtSl, $dtRt)) : 0;
            $secEfectiva = max(0, $secJornada - $secAlmuerzo);
            $secExtra8h  = max(0, $secEfectiva - 28800);

            $tardanzaMin = 0;
            $extraMin    = 0;
            if (!empty($r['hora_prog_in']) && $dtIn) {
                $dtProgIn = new \DateTime($fecha.' '.$r['hora_prog_in']);
                $dif = $secs($dtProgIn, $dtIn);
                $tardanzaMin = max(0, intdiv($dif, 60) - 10);
            }
            if (!empty($r['hora_prog_out']) && $dtOut) {
                $dtProgOut = new \DateTime($fecha.' '.$r['hora_prog_out']);
                $dif = $secs($dtProgOut, $dtOut);
                $extraMin = $dif > 0 ? intdiv($dif, 60) : 0;
            }

            echo "<tr>";
            echo "<td>".$h($fecha)."</td>";
            echo "<td>".$h($r['usuario'] ?? '')."</td>";
            echo "<td>".$h($r['rol'] ?? '')."</td>";
            echo "<td>".$h($r['hora_prog_in']  ?? '')."</td>";
            echo "<td>".$h($r['hora_prog_out'] ?? '')."</td>";
            echo "<td>".$h($r['hora_ingreso']     ?? '')."</td>";
            echo "<td>".$h($r['hora_sl_almuerzo'] ?? '')."</td>";
            echo "<td>".$h($r['hora_rt_almuerzo'] ?? '')."</td>";
            echo "<td>".$h($r['hora_salida']      ?? '')."</td>";
            echo "<td>".$h($hhmmss($secJornada))."</td>";
            echo "<td>".$h($hhmmss($secAlmuerzo))."</td>";
            echo "<td>".$h($hhmmss($secEfectiva))."</td>";
            echo "<td>".$h($hhmmss($secExtra8h))."</td>";
            echo "<td>".$h($tardanzaMin)."</td>";
            echo "<td>".$h($extraMin)."</td>";
            echo "</tr>\n";
        }

        echo "</tbody></table>";
        echo "</body></html>";
    }
}
