<?php
namespace App\Controllers;

use App\Services\RbacService;
use PDO;

class AdminController extends BaseController
{
    private function parseYmd(?string $s): ?string {
        if (!$s) return null; $d = \DateTime::createFromFormat('Y-m-d', $s); return ($d && $d->format('Y-m-d') === $s) ? $s : null;
    }
    private function guard(): void
    {
        \app_session_guard(900);
        \app_ensure_csrf_token();
        $rol = $_SESSION['tipo'] ?? '';
        $rbac = new RbacService();
        if (!$rbac->canAccessModule($rol, 'admin')) {
            http_response_code(403);
            echo 'Acceso no autorizado para este módulo';
            exit;
        }
    }

    public function rolesIndex(): void
    {
        $this->guard();
        $db = \conexion();
        // Crear tabla si no existe (defensivo; opcional)
        // $db->exec("CREATE TABLE IF NOT EXISTS tipo_usuario (id_tp_user INT AUTO_INCREMENT PRIMARY KEY, detalle_tp_user VARCHAR(100) UNIQUE)");

        $stmt = $db->query('SELECT id_tp_user, detalle_tp_user FROM tipo_usuario ORDER BY id_tp_user');
        $roles = $stmt->fetchAll() ?: [];

        $nombre   = $_SESSION['nombre']   ?? 'Usuario';
        $apellido = $_SESSION['apellido'] ?? '';
        $rbac = new RbacService();
        $this->render('admin/roles', [
            'title'    => 'Gestión de Roles',
            'module'   => 'admin',
            'menu'     => $rbac->menuForRole($_SESSION['tipo'] ?? '', 'admin'),
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'roles'    => $roles,
            'base'     => (function_exists('appBasePath') ? appBasePath() : ''),
            'msg'      => $_GET['msg'] ?? '',
            'err'      => $_GET['err'] ?? '',
        ]);
    }

    public function rolesCreate(): void
    {
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Método no permitido'; return; }
        if (!\app_csrf_valid()) { http_response_code(403); echo 'CSRF inválido'; return; }
        $nombre = trim((string)($_POST['detalle'] ?? ''));
        if ($nombre === '') {
            $this->redirectWith('Detalle del rol requerido', true);
            return;
        }
        // Normalizar: mayúsculas y sin acentos
        $norm = strtr($nombre, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']);
        $norm = strtoupper($norm);
        try {
            $db = \conexion();
            // Evitar duplicados
            $s = $db->prepare('SELECT 1 FROM tipo_usuario WHERE UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(detalle_tp_user, "á", "a"), "é", "e"), "í", "i"), "ó", "o"), "ú", "u"), "ñ","n")) = ?');
            $s->execute([$norm]);
            if ($s->fetch()) { $this->redirectWith('El rol ya existe.', true); return; }

            $toInsert = function_exists('mb_strtoupper') ? mb_strtoupper($nombre,'UTF-8') : strtoupper($nombre);
            $ins = $db->prepare('INSERT INTO tipo_usuario (detalle_tp_user) VALUES (?)');
            $ins->execute([$toInsert]);
            $this->redirectWith('Rol creado correctamente.');
        } catch (\Throwable $e) {
            error_log('admin roles create: ' . $e->getMessage());
            $this->redirectWith('Error al crear el rol.', true);
        }
    }

    private function redirectWith(string $message, bool $isError = false): void
    {
        $base = function_exists('appRouterBase') ? appRouterBase() : (function_exists('appAssetBase') ? appAssetBase() : '');
        $q = $isError ? ('err=' . urlencode($message)) : ('msg=' . urlencode($message));
        header('Location: ' . rtrim($base, '/') . '/index.php?r=admin&action=roles&' . $q);
        exit;
    }

    // ---- Usuarios ----
    public function usersIndex(): void
    {
        $this->guard();
        $db = \conexion();
        $q = $db->query('SELECT u.id_usuario, u.primer_nombre, u.segundo_nombre, u.primer_apellido, u.segundo_apellido, u.cedula, u.correo, t.detalle_tp_user AS rol
                         FROM usuario u LEFT JOIN tipo_usuario t ON t.id_tp_user = u.id_tp_user
                         ORDER BY u.primer_apellido, u.primer_nombre');
        $usuarios = $q->fetchAll() ?: [];
        $roles = ($db->query('SELECT id_tp_user, detalle_tp_user FROM tipo_usuario ORDER BY id_tp_user'))->fetchAll() ?: [];
        $rbac = new RbacService();
        $this->render('admin/users', [
            'title'=>'Usuarios', 'module'=>'admin',
            'menu'=>$rbac->menuForRole($_SESSION['tipo'] ?? '', 'admin'),
            'usuarios'=>$usuarios, 'roles'=>$roles,
            'nombre'=>($_SESSION['nombre']??'Usuario'), 'apellido'=>($_SESSION['apellido']??''),
            'msg'=>$_GET['msg']??'', 'err'=>$_GET['err']??''
        ]);
    }

    public function usersCreate(): void
    {
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Método no permitido'; return; }
        if (!app_csrf_valid()) { http_response_code(403); echo 'CSRF inválido'; return; }
        $correo = trim((string)($_POST['correo'] ?? ''));
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $segundoNombre = trim((string)($_POST['snombre'] ?? ''));
        $apellido = trim((string)($_POST['apellido'] ?? ''));
        $segundoApellido = trim((string)($_POST['sapellido'] ?? ''));
        $cedula = trim((string)($_POST['cedula'] ?? ''));
        $pwd = (string)($_POST['pwd'] ?? '');
        $rolId = (int)($_POST['rol'] ?? 0);
        if ($correo==='' || $nombre==='' || $segundoNombre==='' || $apellido==='' || $segundoApellido==='' || $cedula==='' || $pwd==='' || $rolId<=0) {
            $this->redirectUsers('Todos los campos son obligatorios.', true); return;
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { $this->redirectUsers('Correo inválido.', true); return; }
        try {
            $db = conexion();

            // Validación principal: máximo 2 roles/usuarios por persona (misma cédula + mismo correo)
            $correoL = strtolower($correo);
            $sPersona = $db->prepare('SELECT id_tp_user FROM usuario WHERE cedula = ? AND correo = ?');
            $sPersona->execute([$cedula, $correoL]);
            $rolesPersona = array_map('intval', $sPersona->fetchAll(PDO::FETCH_COLUMN) ?: []);

            // No repetir exactamente el mismo rol para la misma persona
            if (in_array($rolId, $rolesPersona, true)) {
                $this->redirectUsers('La persona ya tiene ese rol asignado con esa cédula y correo.', true);
                return;
            }
            // Bloquear cuando ya tiene 2 roles para esa cédula+correo
            if (count($rolesPersona) >= 2) {
                $this->redirectUsers('Máximo 2 roles distintos por persona (misma cédula y correo).', true);
                return;
            }

            // --- Lógica previa se mantiene como respaldo ---
            // Permitir correo y cédula repetidos, pero máximo 2 roles distintos por persona y sin repetir rol
            $correoL = strtolower($correo);
            $s = $db->prepare('SELECT id_tp_user FROM usuario WHERE cedula = ?');
            $s->execute([$cedula]);
            $roles = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if (in_array($rolId, $roles, true)) { $this->redirectUsers('La persona ya tiene ese rol.', true); return; }

            // Validar máximo 2 usuarios con la misma cédula y correo
            $s2 = $db->prepare('SELECT COUNT(*) FROM usuario WHERE cedula = ? AND correo = ?');
            $s2->execute([$cedula, $correoL]);
            $countPersona = (int)($s2->fetchColumn() ?: 0);
            if ($countPersona >= 2) { $this->redirectUsers('Máximo 2 usuarios con la misma cédula y correo.', true); return; }
            // Límite de cantidad de roles deshabilitado para permitir registrar más de 2 por persona.
            // if (count($roles) >= 2) { $this->redirectUsers('Máximo 2 roles distintos por persona.', true); return; }

            $hash = password_hash($pwd, PASSWORD_BCRYPT);
            $up = function($s){ return function_exists('mb_strtoupper') ? mb_strtoupper($s,'UTF-8') : strtoupper($s); };
            $nombreU  = $up($nombre);
            $sNombreU = $up($segundoNombre);
            $apU      = $up($apellido);
            $sApU     = $up($segundoApellido);
            $correoL  = strtolower($correo);
            $ins = $db->prepare('INSERT INTO usuario (primer_apellido, segundo_apellido, primer_nombre, segundo_nombre, cedula, correo, pwd, id_tp_user) VALUES (?,?,?,?,?,?,?,?)');
            $ins->execute([$apU, $sApU, $nombreU, $sNombreU, $cedula, $correoL, $hash, $rolId]);
            $this->redirectUsers('Usuario creado correctamente.');
        } catch (\PDOException $e) {
            error_log('admin users create: ' . $e->getMessage());
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate entry') !== false || $e->getCode() === '23000') {
                $this->redirectUsers('La base de datos impide correos/cédulas repetidos (índice único). Elimina esa restricción para permitir hasta 2 roles por persona.', true);
                return;
            }
            $fileEnv = function_exists('loadDotEnv') ? loadDotEnv() : [];
            $dbg = function_exists('getEnvVar') ? ((getEnvVar('APP_DEBUG', $fileEnv, 'app_debug') ?? '0') === '1') : (getenv('APP_DEBUG')==='1');
            $this->redirectUsers($dbg ? ('Error: '.$msg) : 'Error al crear el usuario.', true);
        } catch (\Throwable $e) {
            error_log('admin users create: ' . $e->getMessage());
            $fileEnv = function_exists('loadDotEnv') ? loadDotEnv() : [];
            $dbg = function_exists('getEnvVar') ? ((getEnvVar('APP_DEBUG', $fileEnv, 'app_debug') ?? '0') === '1') : (getenv('APP_DEBUG')==='1');
            $this->redirectUsers($dbg ? ('Error: '.$e->getMessage()) : 'Error al crear el usuario.', true);
        }
    }private function redirectUsers(string $message, bool $isError=false): void
    {
        $base = function_exists('appBasePath') ? appBasePath() : '';
        $q = $isError ? ('err=' . urlencode($message)) : ('msg=' . urlencode($message));
        header('Location: ' . $base . '/index.php?r=admin&action=users&' . $q);
        exit;
    }

    // ---- Horarios (entrada/salida) ----
    public function schedulesIndex(): void
    {
        $this->guard();
        $db = \conexion();
        $usuarios = ($db->query('SELECT id_usuario, CONCAT_WS(" ", primer_nombre, primer_apellido) AS nombre FROM usuario ORDER BY primer_apellido, primer_nombre'))->fetchAll() ?: [];
        $uidSel = (int)($_GET['uid'] ?? 0);
        $currIn = $currOut = '';
        if ($uidSel>0) {
            $si = $db->prepare('SELECT hora_ingreso_personal FROM horario_entrada_personal WHERE id_usuario=? LIMIT 1');
            $si->execute([$uidSel]);
            $currIn = (string)($si->fetchColumn() ?: '');
            $so = $db->prepare('SELECT hora_salida_personal FROM horario_salida_personal WHERE id_usuario=? LIMIT 1');
            $so->execute([$uidSel]);
            $currOut = (string)($so->fetchColumn() ?: '');
        }
        $rbac = new RbacService();
        $this->render('admin/schedules', [
            'title'=>'Horarios', 'module'=>'admin',
            'menu'=>$rbac->menuForRole($_SESSION['tipo'] ?? '', 'admin'),
            'usuarios'=>$usuarios, 'uidSel'=>$uidSel, 'currIn'=>$currIn, 'currOut'=>$currOut,
            'msg'=>$_GET['msg']??'', 'err'=>$_GET['err']??''
        ]);
    }

    public function schedulesSave(): void
    {
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Método no permitido'; return; }
        if (!\app_csrf_valid()) { http_response_code(403); echo 'CSRF inválido'; return; }
        $uid = (int)($_POST['uid'] ?? 0);
        $horaIn = trim((string)($_POST['hora_ingreso'] ?? ''));
        $horaOut= trim((string)($_POST['hora_salida'] ?? ''));
        if ($uid<=0 || $horaIn==='' || $horaOut==='') { $this->redirectSchedules('Todos los campos son obligatorios.', true); return; }
        // Validación de formato HH:MM:SS
        $validTime = function(string $h){ $d=\DateTime::createFromFormat('H:i:s',$h); return $d && $d->format('H:i:s')===$h; };
        if (!$validTime($horaIn) || !$validTime($horaOut)) { $this->redirectSchedules('Formato de hora inválido (HH:MM:SS).', true); return; }
        try {
            $db = \conexion();
            // obtener rol del usuario para guardar id_tp_user en tablas de horario
            $s = $db->prepare('SELECT id_tp_user FROM usuario WHERE id_usuario=? LIMIT 1');
            $s->execute([$uid]);
            $idTp = (int)($s->fetchColumn() ?: 0);
            // Entrada: upsert
            $q = $db->prepare('SELECT id_usuario FROM horario_entrada_personal WHERE id_usuario=?');
            $q->execute([$uid]);
            if ($q->fetch()) {
                $u = $db->prepare('UPDATE horario_entrada_personal SET hora_ingreso_personal=?, id_tp_user=? WHERE id_usuario=?');
                $u->execute([$horaIn, $idTp, $uid]);
            } else {
                $i = $db->prepare('INSERT INTO horario_entrada_personal (id_tp_user, hora_ingreso_personal, id_usuario) VALUES (?,?,?)');
                $i->execute([$idTp, $horaIn, $uid]);
            }
            // Salida: upsert
            $q = $db->prepare('SELECT id_usuario FROM horario_salida_personal WHERE id_usuario=?');
            $q->execute([$uid]);
            if ($q->fetch()) {
                $u = $db->prepare('UPDATE horario_salida_personal SET hora_salida_personal=?, id_tp_user=? WHERE id_usuario=?');
                $u->execute([$horaOut, $idTp, $uid]);
            } else {
                $i = $db->prepare('INSERT INTO horario_salida_personal (id_tp_user, hora_salida_personal, id_usuario) VALUES (?,?,?)');
                $i->execute([$idTp, $horaOut, $uid]);
            }
            $this->redirectSchedules('Horarios actualizados.');
        } catch (\Throwable $e) {
            error_log('admin schedules save: ' . $e->getMessage());
            $this->redirectSchedules('Error al actualizar horarios.', true);
        }
    }

    private function redirectSchedules(string $message, bool $isError=false): void
    {
        $base = function_exists('appBasePath') ? appBasePath() : '';
        $q = $isError ? ('err=' . urlencode($message)) : ('msg=' . urlencode($message));
        header('Location: ' . $base . '/index.php?r=admin&action=schedules&' . $q);
        exit;
    }

    // ---- Estadísticas para gráfica (A tiempo/Temprano vs Atrasos) ----
    public function arrivalStats(): void
    {
        $this->guard();
        header('Content-Type: application/json; charset=UTF-8');
        $hoy          = (new \DateTime('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $primerDiaMes = (new \DateTime('first day of this month'))->format('Y-m-d');
        $desde = $this->parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
        $hasta = $this->parseYmd($_GET['hasta'] ?? '') ?? $hoy;
        if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }
        try {
            $db = \conexion();
            $sql = "SELECT
                        SUM(CASE WHEN TIME_TO_SEC(TIMEDIFF(hi.hora_ingreso, he.hora_ingreso_personal)) > 600 THEN 1 ELSE 0 END) AS tardy,
                        SUM(CASE WHEN TIME_TO_SEC(TIMEDIFF(hi.hora_ingreso, he.hora_ingreso_personal)) <= 600 THEN 1 ELSE 0 END) AS ontime
                    FROM fecha_registro fr
                    INNER JOIN usuario u ON u.id_usuario = fr.id_usuario
                    LEFT JOIN horario_ingreso hi ON hi.id_usuario = fr.id_usuario AND hi.id_fecha_registro = fr.id_fecha_registro
                    LEFT JOIN horario_entrada_personal he ON he.id_usuario = u.id_usuario
                    WHERE fr.fecha_ingreso BETWEEN :desde AND :hasta
                      AND (u.id_tp_user IS NULL OR u.id_tp_user <> 1)
                      AND hi.hora_ingreso IS NOT NULL AND he.hora_ingreso_personal IS NOT NULL";
            $st = $db->prepare($sql);
            $st->bindValue(':desde', $desde, \PDO::PARAM_STR);
            $st->bindValue(':hasta', $hasta, \PDO::PARAM_STR);
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: ['tardy'=>0,'ontime'=>0];
            echo json_encode(['ok'=>true,'desde'=>$desde,'hasta'=>$hasta,'data'=>['ontime'=>(int)$row['ontime'],'tardy'=>(int)$row['tardy']]]);
        } catch (\Throwable $e) {
            error_log('admin arrivalStats: '.$e->getMessage());
            echo json_encode(['ok'=>false,'error'=>'No se pudo obtener estadísticas']);
        }
    }

    // ---- Edición de timbres (Ingreso, Salida/Retorno almuerzo, Salida) ----
    public function timbresIndex(): void
    {
        $this->guard();
        $db = \conexion();
        // Traer también el rol de cada usuario
        $usuarios = ($db->query('SELECT u.id_usuario,
                                        CONCAT_WS(" ", u.primer_nombre, u.primer_apellido) AS nombre,
                                        COALESCE(t.detalle_tp_user, "") AS rol
                                 FROM usuario u
                                 LEFT JOIN tipo_usuario t ON t.id_tp_user = u.id_tp_user
                                 ORDER BY u.primer_apellido, u.primer_nombre'))->fetchAll() ?: [];
        $uid = (int)($_GET['uid'] ?? 0);
        $fecha = $this->parseYmd($_GET['fecha'] ?? '') ?? (new \DateTime('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $datos = ['ingreso'=>'','sl'=>'','rt'=>'','salida'=>''];
        if ($uid>0 && $fecha) {
            $st = $db->prepare('SELECT id_fecha_registro FROM fecha_registro WHERE id_usuario=? AND fecha_ingreso=? LIMIT 1');
            $st->execute([$uid, $fecha]);
            $idFecha = (int)($st->fetchColumn() ?: 0);
            if ($idFecha>0) {
                $q = $db->prepare('SELECT hi.hora_ingreso, sla.hora_sl_almuerzo, rta.hora_rt_almuerzo, sa.hora_salida
                                   FROM fecha_registro fr
                                   LEFT JOIN horario_ingreso hi ON hi.id_usuario=fr.id_usuario AND hi.id_fecha_registro=fr.id_fecha_registro
                                   LEFT JOIN horario_sl_almuerzo sla ON sla.id_usuario=fr.id_usuario AND sla.id_fecha_registro=fr.id_fecha_registro
                                   LEFT JOIN horario_rt_almuerzo rta ON rta.id_usuario=fr.id_usuario AND rta.id_fecha_registro=fr.id_fecha_registro
                                   LEFT JOIN horario_salida sa ON sa.id_usuario=fr.id_usuario AND sa.id_fecha_registro=fr.id_fecha_registro
                                   WHERE fr.id_usuario=? AND fr.id_fecha_registro=? LIMIT 1');
                $q->execute([$uid, $idFecha]);
                $row = $q->fetch(\PDO::FETCH_ASSOC) ?: [];
                $datos['ingreso'] = $row['hora_ingreso'] ?? '';
                $datos['sl']      = $row['hora_sl_almuerzo'] ?? '';
                $datos['rt']      = $row['hora_rt_almuerzo'] ?? '';
                $datos['salida']  = $row['hora_salida'] ?? '';
            }
        }
        // Rol y nombre seleccionados (si hay usuario seleccionado)
        $rolUsuarioSel = '';
        $nombreUsuarioSel = '';
        if ($uid > 0) {
            foreach ($usuarios as $u) {
                if ((int)$u['id_usuario'] === $uid) {
                    $rolUsuarioSel = (string)($u['rol'] ?? '');
                    $nombreUsuarioSel = (string)($u['nombre'] ?? '');
                    break;
                }
            }
        }

        // Calcular horas efectivas si hay datos cargados
        $horasEfectivas = '';
        if (!empty($datos['ingreso']) && !empty($datos['salida'])) {
            $sIn  = strtotime($fecha . ' ' . $datos['ingreso']);
            $sOut = strtotime($fecha . ' ' . $datos['salida']);
            $sSl  = !empty($datos['sl']) ? strtotime($fecha . ' ' . $datos['sl']) : null;
            $sRt  = !empty($datos['rt']) ? strtotime($fecha . ' ' . $datos['rt']) : null;
            if ($sIn !== false && $sOut !== false) {
                $jornada = max(0, $sOut - $sIn);
                $alm = ($sSl !== null && $sRt !== null) ? max(0, $sRt - $sSl) : 0;
                $eff = max(0, $jornada - $alm);
                $h = intdiv($eff, 3600);
                $m = intdiv($eff % 3600, 60);
                $sec = $eff % 60;
                $horasEfectivas = sprintf('%02d:%02d:%02d', $h, $m, $sec);
            }
        }

        $rbac = new RbacService();
        $this->render('admin/timbres', [
            'title'=>'Editar Timbres', 'module'=>'admin',
            'menu'=>$rbac->menuForRole($_SESSION['tipo'] ?? '', 'admin'),
            'usuarios'=>$usuarios, 'uidSel'=>$uid, 'fechaSel'=>$fecha, 'datos'=>$datos,
            'rolSel'=>$rolUsuarioSel,
            'nombreSel'=>$nombreUsuarioSel,
            'horasEfectivas'=>$horasEfectivas,
            'msg'=>$_GET['msg']??'', 'err'=>$_GET['err']??''
        ]);
    }

    public function timbresSave(): void
    {
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Método no permitido'; return; }
        if (!\app_csrf_valid()) { http_response_code(403); echo 'CSRF inválido'; return; }
        $uid = (int)($_POST['uid'] ?? 0);
        $fecha = $this->parseYmd($_POST['fecha'] ?? '');
        $ingreso = trim((string)($_POST['hora_ingreso'] ?? ''));
        $sl      = trim((string)($_POST['hora_sl'] ?? ''));
        $rt      = trim((string)($_POST['hora_rt'] ?? ''));
        $salida  = trim((string)($_POST['hora_salida'] ?? ''));
        if ($uid<=0 || !$fecha) { $this->redirectEditTimbres('Usuario y fecha son obligatorios.', true); return; }
        $validTime = function(string $h){ if($h==='') return true; $d=\DateTime::createFromFormat('H:i:s',$h); return $d && $d->format('H:i:s')===$h; };
        if (!$validTime($ingreso) || !$validTime($sl) || !$validTime($rt) || !$validTime($salida)) { $this->redirectEditTimbres('Formato de hora inválido (HH:MM:SS).', true); return; }
        try {
            $db = \conexion();
            // Buscar id_fecha_registro
            $st = $db->prepare('SELECT id_fecha_registro FROM fecha_registro WHERE id_usuario=? AND fecha_ingreso=? LIMIT 1');
            $st->execute([$uid, $fecha]);
            $idFecha = (int)($st->fetchColumn() ?: 0);
            if ($idFecha<=0) {
                // Si no existe, créalo para permitir edición manual
                $insFr = $db->prepare('INSERT INTO fecha_registro (id_usuario, fecha_ingreso) VALUES (?, ?)');
                $insFr->execute([$uid, $fecha]);
                $idFecha = (int)$db->lastInsertId();
            }

            // Actualizaciones condicionales (solo si viene hora)
            if ($ingreso !== '') {
                // Upsert ingreso
                $q = $db->prepare('SELECT 1 FROM horario_ingreso WHERE id_usuario=? AND id_fecha_registro=? LIMIT 1');
                $q->execute([$uid, $idFecha]);
                if ($q->fetch()) {
                    $q = $db->prepare('UPDATE horario_ingreso SET hora_ingreso=? WHERE id_usuario=? AND id_fecha_registro=?');
                    $q->execute([$ingreso, $uid, $idFecha]);
                } else {
                    $ins = $db->prepare('INSERT INTO horario_ingreso (id_usuario, id_fecha_registro, id_estado_ingreso, hora_ingreso, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $ins->execute([$uid, $idFecha, null, $ingreso, null, null, null]);
                }
                // Recalcular estado_ingreso con tolerancia de 10 minutos sobre hora_ingreso_personal
                $hp = $db->prepare('SELECT hora_ingreso_personal FROM horario_entrada_personal WHERE id_usuario=? LIMIT 1');
                $hp->execute([$uid]);
                $progIn = $hp->fetchColumn();
                if ($progIn) {
                    $dtProg = new \DateTime($fecha.' '.$progIn);
                    $dtIn   = new \DateTime($fecha.' '.$ingreso);
                    $tolEnd = (clone $dtProg)->modify('+10 minutes');
                    if ($dtIn < $dtProg)      { $det = 'Ingreso antes de su hora de ingreso'; }
                    elseif ($dtIn <= $tolEnd) { $det = 'Ingreso a tiempo'; }
                    else                      { $det = 'Atraso'; }
                    $idEstado = $this->ensureCatalog($db, 'estado_ingreso', 'id_estado_ingreso', 'detalle_ingreso', $det);
                    $q = $db->prepare('UPDATE horario_ingreso SET id_estado_ingreso=? WHERE id_usuario=? AND id_fecha_registro=?');
                    $q->execute([$idEstado, $uid, $idFecha]);
                }
            }
            // Tomar latitud/longitud/dirección del ingreso (si existe), para reusar en inserts
            $coordLat = $coordLon = $coordDir = null;
            $qi = $db->prepare('SELECT latitud, longitud, direccion FROM horario_ingreso WHERE id_usuario=? AND id_fecha_registro=? LIMIT 1');
            $qi->execute([$uid, $idFecha]);
            if ($rowIn = $qi->fetch(\PDO::FETCH_ASSOC)) {
                $coordLat = $rowIn['latitud'];
                $coordLon = $rowIn['longitud'];
                $coordDir = $rowIn['direccion'];
            }

            if ($sl !== '') {
                // ¿Existe salida almuerzo?
                $q = $db->prepare('SELECT 1 FROM horario_sl_almuerzo WHERE id_usuario=? AND id_fecha_registro=? LIMIT 1');
                $q->execute([$uid, $idFecha]);
                if ($q->fetch()) {
                    $q = $db->prepare('UPDATE horario_sl_almuerzo SET hora_sl_almuerzo=?, latitud=?, longitud=?, direccion=? WHERE id_usuario=? AND id_fecha_registro=?');
                    $q->execute([$sl, $coordLat, $coordLon, $coordDir, $uid, $idFecha]);
                } else {
                    if ($coordLat === null || $coordLon === null || $coordDir === null) {
                        $this->redirectEditTimbres('Registra hora de ingreso (con ubicación) antes de añadir salida de almuerzo.', true);
                        return;
                    }
                    $idEstadoSl = $this->ensureCatalog($db, 'estado_salida_almuerzo', 'id_estado_salida_almuerzo', 'detalle_salida_almuerzo', 'Salida al almuerzo');
                    $q = $db->prepare('INSERT INTO horario_sl_almuerzo (id_usuario, id_fecha_registro, id_estado_salida_almuerzo, hora_sl_almuerzo, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $q->execute([$uid, $idFecha, $idEstadoSl, $sl, $coordLat, $coordLon, $coordDir]);
                }
            }

            if ($rt !== '') {
                $q = $db->prepare('SELECT 1 FROM horario_rt_almuerzo WHERE id_usuario=? AND id_fecha_registro=? LIMIT 1');
                $q->execute([$uid, $idFecha]);
                if ($q->fetch()) {
                    $q = $db->prepare('UPDATE horario_rt_almuerzo SET hora_rt_almuerzo=?, latitud=?, longitud=?, direccion=? WHERE id_usuario=? AND id_fecha_registro=?');
                    $q->execute([$rt, $coordLat, $coordLon, $coordDir, $uid, $idFecha]);
                } else {
                    if ($coordLat === null || $coordLon === null || $coordDir === null) {
                        $this->redirectEditTimbres('Registra hora de ingreso (con ubicación) antes de añadir regreso de almuerzo.', true);
                        return;
                    }
                    $idEstadoRt = $this->ensureCatalog($db, 'estado_retorno_almuerzo', 'id_estado_retorno_almuerzo', 'detalle_retorno_almuerzo', 'Regreso de almuerzo');
                    $q = $db->prepare('INSERT INTO horario_rt_almuerzo (id_usuario, id_fecha_registro, id_estado_retorno_almuerzo, hora_rt_almuerzo, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $q->execute([$uid, $idFecha, $idEstadoRt, $rt, $coordLat, $coordLon, $coordDir]);
                }
            }

            if ($salida !== '') {
                // ¿Existe salida laboral?
                $q = $db->prepare('SELECT 1 FROM horario_salida WHERE id_usuario=? AND id_fecha_registro=? LIMIT 1');
                $q->execute([$uid, $idFecha]);
                $existsSalida = (bool)$q->fetch();

                if ($existsSalida) {
                    $q = $db->prepare('UPDATE horario_salida SET hora_salida=?, latitud=?, longitud=?, direccion=? WHERE id_usuario=? AND id_fecha_registro=?');
                    $q->execute([$salida, $coordLat, $coordLon, $coordDir, $uid, $idFecha]);
                } else {
                    if ($coordLat === null || $coordLon === null || $coordDir === null) {
                        $this->redirectEditTimbres('Registra hora de ingreso (con ubicación) antes de añadir salida laboral.', true);
                        return;
                    }
                    // Calcular estado de salida contra horario programado
                    $hp = $db->prepare('SELECT hora_salida_personal FROM horario_salida_personal WHERE id_usuario=? LIMIT 1');
                    $hp->execute([$uid]);
                    $progOut = $hp->fetchColumn();
                    $idEstado = null;
                    if ($progOut) {
                        $dtProg = new \DateTime($fecha.' '.$progOut);
                        $dtOut  = new \DateTime($fecha.' '.$salida);
                        $det = ($dtOut >= $dtProg) ? 'Fin de jornada laboral' : 'Salida anticipada';
                        $idEstado = $this->ensureCatalog($db, 'estado_salida', 'id_estado_salida', 'detalle_salida', $det);
                    }
                    $q = $db->prepare('INSERT INTO horario_salida (id_usuario, id_fecha_registro, id_estado_salida, hora_salida, latitud, longitud, direccion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $q->execute([$uid, $idFecha, $idEstado, $salida, $coordLat, $coordLon, $coordDir]);
                }

                // Recalcular estado_salida (Fin de jornada / Salida anticipada) contra hora_salida_personal también para el caso UPDATE
                $hp = $db->prepare('SELECT hora_salida_personal FROM horario_salida_personal WHERE id_usuario=? LIMIT 1');
                $hp->execute([$uid]);
                $progOut = $hp->fetchColumn();
                if ($progOut) {
                    $dtProg = new \DateTime($fecha.' '.$progOut);
                    $dtOut  = new \DateTime($fecha.' '.$salida);
                    $det = ($dtOut >= $dtProg) ? 'Fin de jornada laboral' : 'Salida anticipada';
                    $idEstado = $this->ensureCatalog($db, 'estado_salida', 'id_estado_salida', 'detalle_salida', $det);
                    $q = $db->prepare('UPDATE horario_salida SET id_estado_salida=? WHERE id_usuario=? AND id_fecha_registro=?');
                    $q->execute([$idEstado, $uid, $idFecha]);
                }
            }
            $this->redirectEditTimbres('Timbres actualizados.');
        } catch (\Throwable $e) {
            error_log('admin timbres save: '.$e->getMessage());
            $this->redirectEditTimbres('Error al actualizar timbres.', true);
        }
    }

    // Asegura la existencia de un catálogo y devuelve su id
    private function ensureCatalog(\PDO $db, string $table, string $pk, string $col, string $detalle): int
    {
        $q = $db->prepare("SELECT $pk FROM $table WHERE $col=? LIMIT 1");
        $q->execute([$detalle]);
        $id = (int)($q->fetchColumn() ?: 0);
        if (!$id) {
            $i = $db->prepare("INSERT INTO $table ($col) VALUES (?)");
            $i->execute([$detalle]);
            $id = (int)$db->lastInsertId();
        }
        return $id;
    }

    private function redirectEditTimbres(string $message, bool $isError=false): void
    {
        $base = function_exists('appRouterBase') ? appRouterBase() : (function_exists('appAssetBase') ? appAssetBase() : '');
        $q = $isError ? ('err=' . urlencode($message)) : ('msg=' . urlencode($message));
        header('Location: ' . rtrim($base, '/') . '/index.php?r=admin&action=timbres_edit&' . $q);
        exit;
    }

    public function arrivalDetails(): void
    {
        $this->guard();
        header('Content-Type: application/json; charset=UTF-8');
        $type = strtolower(trim((string)($_GET['type'] ?? '')));
        if ($type !== 'ontime' && $type !== 'tardy') { echo json_encode(['ok'=>false,'error'=>'Tipo inválido']); return; }
        $hoy          = (new \DateTime('now', new \DateTimeZone('America/Guayaquil')))->format('Y-m-d');
        $primerDiaMes = (new \DateTime('first day of this month'))->format('Y-m-d');
        $desde = $this->parseYmd($_GET['desde'] ?? '') ?? $primerDiaMes;
        $hasta = $this->parseYmd($_GET['hasta'] ?? '') ?? $hoy;
        if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }
        try {
            $db = \conexion();
            $cond = ($type === 'tardy') ? ' > 600 ' : ' <= 600 ';
            $sql = "SELECT fr.fecha_ingreso AS fecha,
                           CONCAT_WS(' ', u.primer_nombre, u.primer_apellido) AS usuario,
                           tu.detalle_tp_user AS rol,
                           he.hora_ingreso_personal AS hora_prog_in,
                           hi.hora_ingreso,
                           ei.detalle_ingreso AS estado_ingreso,
                           hi.direccion AS dir_in,
                           hi.latitud AS lat_in,
                           hi.longitud AS lon_in
                    FROM fecha_registro fr
                    INNER JOIN usuario u ON u.id_usuario = fr.id_usuario
                    LEFT JOIN tipo_usuario tu ON tu.id_tp_user = u.id_tp_user
                    LEFT JOIN horario_ingreso hi ON hi.id_usuario = fr.id_usuario AND hi.id_fecha_registro = fr.id_fecha_registro
                    LEFT JOIN estado_ingreso ei ON ei.id_estado_ingreso = hi.id_estado_ingreso
                    LEFT JOIN horario_entrada_personal he ON he.id_usuario = u.id_usuario
                    WHERE fr.fecha_ingreso BETWEEN :desde AND :hasta
                      AND (u.id_tp_user IS NULL OR u.id_tp_user <> 1)
                      AND hi.hora_ingreso IS NOT NULL AND he.hora_ingreso_personal IS NOT NULL
                      AND TIME_TO_SEC(TIMEDIFF(hi.hora_ingreso, he.hora_ingreso_personal))".$cond.
                    "ORDER BY fr.fecha_ingreso DESC, u.primer_apellido, u.primer_nombre";
            $st = $db->prepare($sql);
            $st->bindValue(':desde', $desde, \PDO::PARAM_STR);
            $st->bindValue(':hasta', $hasta, \PDO::PARAM_STR);
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['ok'=>true,'data'=>$rows]);
        } catch (\Throwable $e) {
            error_log('admin arrivalDetails: '.$e->getMessage());
            echo json_encode(['ok'=>false,'error'=>'No se pudo obtener el detalle']);
        }
    }
}
