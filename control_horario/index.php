<?php
require __DIR__ . '/app/init.php';
app_boot_session();
// Asegura token CSRF disponible en vistas (login/selección)
if (function_exists('app_ensure_csrf_token')) {
    app_ensure_csrf_token();
}

$error = "";
$ip = getClientIP();

// Paso 2: selección de cuenta cuando hay correos duplicados (excepto admin)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['select_user_id'])) {
    try {
        $selId = (int)($_POST['select_user_id'] ?? 0);
        if ($selId <= 0) {
            throw new \RuntimeException('Selección inválida.');
        }
        
        $db = conexion();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        include __DIR__ . "/app/Views/auth/login.php";
        exit();
    }
    
    if (!isset($_SESSION['login_choices']) || !is_array($_SESSION['login_choices'])) {
        $error = 'Sesión inválida. Intente nuevamente.';
        include __DIR__ . "/app/Views/auth/login.php";
        exit();
    }
    $allowed = array_column($_SESSION['login_choices'], 'id_usuario');
    if (!in_array($selId, $allowed, true)) {
        $error = 'Selección inválida.';
        include __DIR__ . "/app/Views/auth/login.php";
        exit();
    }
    $stmt = $db->prepare("SELECT u.id_usuario, u.primer_apellido, u.primer_nombre, u.correo, u.pwd, u.id_tp_user AS id_tipo, t.detalle_tp_user AS nombre_funcion FROM usuario u INNER JOIN tipo_usuario t ON u.id_tp_user = t.id_tp_user WHERE u.id_usuario = ? LIMIT 1");
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
        
        // Validar entrada básica
        if (empty($usuario) || empty($password)) {
            throw new \RuntimeException('Usuario y contraseña son requeridos.');
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
        $stmt = $db->prepare(
            "SELECT u.id_usuario, u.primer_apellido, u.primer_nombre, u.correo, u.pwd, u.id_tp_user AS id_tipo, t.detalle_tp_user AS nombre_funcion
               FROM usuario u INNER JOIN tipo_usuario t ON u.id_tp_user = t.id_tp_user
              WHERE u.correo = ? LIMIT 10"
        );
        $stmt->execute([$usuario]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$rows) {
            // No registra auditoría para no revelar si el email existe
            $error = "Usuario o contraseña incorrectos.";
        } else {
            $matches = [];
            foreach ($rows as $r) {
                if (password_verify($password, $r['pwd'])) {
                    $matches[] = $r;
                } elseif ($password === $r['pwd']) {
                    // Re-hash si estaba en texto plano
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $updateStmt = $db->prepare("UPDATE usuario SET pwd = ? WHERE id_usuario = ?");
                    $updateStmt->execute([$hashed_password, $r['id_usuario']]);
                    $r['pwd'] = $hashed_password;
                    $matches[] = $r;
                }
            }
            if (count($matches) === 0) {
                $error = "Usuario o contraseña incorrectos.";
            } elseif (count($matches) === 1) {
                loginSuccess($matches[0]);
            } else {
                // Si entre los matches está ADMIN/ADMINISTRADOR, se inicia como admin directamente
                $hasAdmin = false;
                $adminUser = null;
                foreach ($matches as $m) {
                    $roleNorm = normalizeRoleName($m['nombre_funcion']);
                    if ($roleNorm === 'ADMIN' || $roleNorm === 'ADMINISTRADOR') {
                        $hasAdmin = true;
                        $adminUser = $m;
                        break;
                    }
                }
                if ($hasAdmin && $adminUser) {
                    loginSuccess($adminUser);
                }
                // Construir selección por pestañas: Administrativo vs Docente
                $choices = [];
                foreach ($matches as $m) {
                    $roleNorm = normalizeRoleName($m['nombre_funcion']);
                    if ($roleNorm === 'ADMIN' || $roleNorm === 'ADMINISTRADOR') {
                        continue; // excluir admin de la selección
                    }
                    $cat = ($roleNorm === 'DOCENTE' || $roleNorm === 'DOCENTES') ? 'DOCENTE' : 'ADMINISTRATIVO';
                    $choices[] = [
                        'id_usuario' => (int)$m['id_usuario'],
                        'nombre'     => $m['primer_nombre'] . ' ' . $m['primer_apellido'],
                        'rol'        => $m['nombre_funcion'],
                        'categoria'  => $cat,
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
    // Normaliza acentos y mayúsculas para comparar roles sin problemas de encoding
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
    ];
    $clean = strtr($name, $map);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
    if ($ascii !== false) {
        $clean = $ascii;
    }
    return strtoupper($clean);
}

// ---- Función de login ----
function loginSuccess($user) {
    session_regenerate_id(true);
    $_SESSION["id_usuario"]     = (int)$user["id_usuario"];
    $_SESSION["nombre"]         = $user["primer_nombre"];
    $_SESSION["apellido"]       = $user["primer_apellido"];
    $_SESSION["correo"]         = $user["correo"];
    $_SESSION["tipo"]           = $user["nombre_funcion"]; // alias de detalle_tp_user
    $_SESSION["ultimo_acceso"]  = time();
    $_SESSION["token"]          = bin2hex(random_bytes(32));

    // Redirige según rol a MVC (router)
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
    $base = function_exists('appBasePath') ? appBasePath() : '';
    $routerBase = ($base === '/') ? '' : rtrim($base, '/');
    header('Location: ' . $routerBase . '/index.php?r=dashboard&mod=' . $mod);
    exit();
}

include __DIR__ . "/app/Views/auth/login.php";
