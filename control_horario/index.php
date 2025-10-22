<?php
session_start();
require __DIR__ . '/app/init.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        $db = conexion();
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
        include __DIR__ . "/app/Views/auth/login.php";
        exit();
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ Alias para mantener compatibilidad con tu código:
    //    - u.id_tp_user AS id_tipo
    //    - t.detalle_tp_user AS nombre_funcion
    $stmt = $db->prepare("
        SELECT 
            u.id_usuario,
            u.primer_apellido,
            u.primer_nombre,
            u.correo,
            u.pwd,
            u.id_tp_user AS id_tipo,
            t.detalle_tp_user AS nombre_funcion
        FROM usuario u
        INNER JOIN tipo_usuario t ON u.id_tp_user = t.id_tp_user
        WHERE u.correo = ?
        LIMIT 1
    ");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['pwd'])) {
            loginSuccess($user);
        } elseif ($password === $user['pwd']) {
            // Re-hash si estaba en texto plano
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare("UPDATE usuario SET pwd = ? WHERE id_usuario = ?");
            $updateStmt->execute([$hashed_password, $user['id_usuario']]);
            // Actualiza el array en memoria para que futuras verificaciones funcionen
            $user['pwd'] = $hashed_password;
            loginSuccess($user);
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
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
        1 => 'admin',        // ADMINISTRADOR
        2 => 'financiero',   // FINANCIERO
        3 => 'admisiones',   // ADMISIONES
        4 => 'academico',    // ACADEMICO
        5 => 'bienestar',    // BIENESTAR
        6 => 'ti',           // TI
        7 => 'academico',    // DOCENTES (accede a Académico)
    ];
    $mod = $mapIdToMod[(int)$user['id_tipo']] ?? '';
    if ($mod === '') { echo "Tipo de usuario desconocido."; exit(); }
    $base = function_exists('appBasePath') ? appBasePath() : '';
    header('Location: ' . $base . '/public/index.php?r=dashboard&mod=' . $mod);
    exit();
    exit();
}

include __DIR__ . "/app/Views/auth/login.php";
