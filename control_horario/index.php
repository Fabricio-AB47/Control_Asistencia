<?php
// Si llegan rutas al front controller del login, despacha al router de /public
if (isset($_GET['r']) || isset($_GET['route'])) {
    require __DIR__ . '/public/index.php';
    exit();
}

require __DIR__ . '/app/init.php';
app_boot_session();
// Asegura token CSRF disponible en vistas (login/selecciÃ³n)
if (function_exists('app_ensure_csrf_token')) {
    app_ensure_csrf_token();
}
$schema = (function_exists('isMssql') && isMssql()) ? (dbSchema() . '.') : '';

$error = "";
$ip = getClientIP();

// Paso 2: selecciÃ³n de cuenta cuando hay correos duplicados (excepto admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['select_user_id'])) {
    try {
        $selId = (int)($_POST['select_user_id'] ?? 0);
        if ($selId <= 0) {
            throw new \RuntimeException('SelecciÃ³n invÃ¡lida.');
        }
        
        $db = conexion();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        include __DIR__ . "/app/Views/auth/login.php";
        exit();
    }
    
    if (!isset($_SESSION['login_choices']) || !is_array($_SESSION['login_choices'])) {
        $error = 'SesiÃ³n invÃ¡lida. Intente nuevamente.';
        include __DIR__ . "/app/Views/auth/login.php";
        exit();
    }
    $allowed = array_column($_SESSION['login_choices'], 'id_usuario');
    if (!in_array($selId, $allowed, true)) {
        $error = 'SelecciÃ³n invÃ¡lida.';
        include __DIR__ . "/app/Views/auth/login.php";
        exit();
    }
    $sql = \isMssql()
        ? "SELECT TOP 1 u.id_usuario, u.primer_apellido, u.primer_nombre, u.correo, u.pwd, u.id_tp_user AS id_tipo, t.detalle_tp_user AS nombre_funcion FROM {$schema}usuario u INNER JOIN {$schema}tipo_usuario t ON u.id_tp_user = t.id_tp_user WHERE u.id_usuario = ?"
        : "SELECT u.id_usuario, u.primer_apellido, u.primer_nombre, u.correo, u.pwd, u.id_tp_user AS id_tipo, t.detalle_tp_user AS nombre_funcion FROM {$schema}usuario u INNER JOIN {$schema}tipo_usuario t ON u.id_tp_user = t.id_tp_user WHERE u.id_usuario = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$selId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        loginSuccess($user);
    }
    $error = "Usuario no disponible.";
    include __DIR__ . "/app/Views/auth/login.php";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['select_user_id'])) {
    try {
        $usuario  = trim($_POST['usuario'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        // Validar entrada bA­sica
        if (empty($usuario) || empty($password)) {
            throw new \RuntimeException('Usuario y contraseAña son requeridos.');
        }
        
        if (strlen($usuario) > 254) {
            throw new \RuntimeException('Usuario demasiado largo.');
        }
        
        // Rate limit login attempts per IP to mitigate brute force
        if (function_exists('app_rate_limit') && !app_rate_limit('login_'.$ip, 5, 60)) {
            throw new \RuntimeException('Demasiados intentos. Intenta nuevamente en un minuto.');
        }

        $db = conexion();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Buscar todas las cuentas por correo (permitimos correos repetidos)
        $sql = \isMssql()
            ? "SELECT TOP 10 u.id_usuario, u.primer_apellido, u.primer_nombre, u.correo, u.pwd, u.id_tp_user AS id_tipo, t.detalle_tp_user AS nombre_funcion
               FROM {$schema}usuario u INNER JOIN {$schema}tipo_usuario t ON u.id_tp_user = t.id_tp_user
              WHERE u.correo = ?"
            : "SELECT u.id_usuario, u.primer_apellido, u.primer_nombre, u.correo, u.pwd, u.id_tp_user AS id_tipo, t.detalle_tp_user AS nombre_funcion
               FROM {$schema}usuario u INNER JOIN {$schema}tipo_usuario t ON u.id_tp_user = t.id_tp_user
              WHERE u.correo = ? LIMIT 10";
        $stmt = $db->prepare($sql);
        $stmt->execute([$usuario]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$rows) {
            // No registra auditorA-a para no revelar si el email existe
            $error = "Usuario o contraseAña incorrectos.";
        } else {
            $matches = [];
            foreach ($rows as $r) {
                if (password_verify($password, $r['pwd'])) {
                    $matches[] = $r;
                } elseif ($password === $r['pwd']) {
                    // Re-hash si estaba en texto plano
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $updateStmt = $db->prepare("UPDATE {$schema}usuario SET pwd = ? WHERE id_usuario = ?");
                    $updateStmt->execute([$hashed_password, $r['id_usuario']]);
                    $r['pwd'] = $hashed_password;
                    $matches[] = $r;
                }
            }
            if (count($matches) === 0) {
                $error = "Usuario o contraseAña incorrectos.";
            } elseif (count($matches) === 1) {
                loginSuccess($matches[0]);
            } else {
                // Construir selecciA3n de roles (incluye admin) para que el usuario elija
                $choices = [];
                foreach ($matches as $m) {
                    $roleNorm = normalizeRoleName($m['nombre_funcion']);
                    if ($roleNorm === 'ADMIN' || $roleNorm === 'ADMINISTRADOR') {
                        $cat = 'ADMIN';
                    } elseif ($roleNorm === 'DOCENTE' || $roleNorm === 'DOCENTES') {
                        $cat = 'DOCENTE';
                    } else {
                        $cat = 'ADMINISTRATIVO';
                    }
                    $choices[] = [
                        'id_usuario' => (int)$m['id_usuario'],
                        'nombre'     => $m['primer_nombre'] . ' ' . $m['primer_apellido'],
                        'rol'        => $m['nombre_funcion'],
                        'categoria'  => $cat,
                        'rol_norm'   => $roleNorm,
                    ];
                }
                if (empty($choices)) {
                    $error = 'No hay roles elegibles.';
                } else {
                    $_SESSION['login_choices'] = $choices;
                    include __DIR__ . "/app/Views/auth/choose_role.php";
                    exit();
                }
            }
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}
function normalizeRoleName($name) {
    // Normaliza acentos y mayÃºsculas para comparar roles sin problemas de encoding
    $map = [
        'Ã¡' => 'a', 'Ã©' => 'e', 'Ã­' => 'i', 'Ã³' => 'o', 'Ãº' => 'u', 'Ã±' => 'n',
        'Ã' => 'A', 'Ã‰' => 'E', 'Ã' => 'I', 'Ã“' => 'O', 'Ãš' => 'U', 'Ã‘' => 'N',
    ];
    $clean = strtr($name, $map);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
    if ($ascii !== false) {
        $clean = $ascii;
    }
    return strtoupper($clean);
}

// ---- FunciÃ³n de login ----
function loginSuccess($user) {
    session_regenerate_id(true);
    $_SESSION["id_usuario"]     = (int)$user["id_usuario"];
    $_SESSION["nombre"]         = $user["primer_nombre"];
    $_SESSION["apellido"]       = $user["primer_apellido"];
    $_SESSION["correo"]         = $user["correo"];
    $_SESSION["tipo"]           = $user["nombre_funcion"]; // alias de detalle_tp_user
    $_SESSION["ultimo_acceso"]  = time();
    $_SESSION["token"]          = bin2hex(random_bytes(32));

    // Redirige segÃºn rol a MVC (router)
    $mapIdToMod = [
        1 => 'admin',
        2 => 'financiero',
        3 => 'admisiones',
        4 => 'academico',
        5 => 'bienestar',
        6 => 'ti',
        7 => 'docente',
        8 => 'autoridades',
    ];
    $mod = $mapIdToMod[(int)$user['id_tipo']] ?? '';
    if ($mod === '') {
        echo "Tipo de usuario desconocido.";
        exit();
    }
    $routerBase = function_exists('appRouterBase')
        ? appRouterBase()
        : (function_exists('appAssetBase') ? appAssetBase() : '');
    $routerBase = rtrim($routerBase, '/');
    header('Location: ' . $routerBase . '/index.php?r=dashboard&mod=' . $mod);
    exit();
}

include __DIR__ . "/app/Views/auth/login.php";






