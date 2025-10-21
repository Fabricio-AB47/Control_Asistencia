<?php
session_start();
require_once __DIR__ . '/../../../core/conexion.php';

// Expiración de sesión (15 min)
$tiempo_inactividad = 900;
if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    if ($tiempo_transcurrido > $tiempo_inactividad) {
        session_unset();
        session_destroy();
        header('Location: ' . appBasePath() . '/index.php?error=sesion_expirada');
        exit();
    }
}
$_SESSION['ultimo_acceso'] = time();

// Verificar sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . appBasePath() . '/index.php');
    exit();
}

$conn = conexion();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// CSRF para el formulario
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['token'];

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['token'], $csrfToken)) {
        $mensaje_error = 'CSRF inválido. Recarga el formulario.';
    } else {
        // Entradas
        $id_tipo = isset($_POST['rol']) ? (int)$_POST['rol'] : 0; // select "rol"
        $primer_apellido  = trim((string)($_POST['pri_apellido'] ?? ''));
        $segundo_apellido = trim((string)($_POST['seg_apellido'] ?? ''));
        $primer_nombre    = trim((string)($_POST['pri_nombre'] ?? ''));
        $segundo_nombre   = trim((string)($_POST['seg_nombre'] ?? ''));
        $cedula           = trim((string)($_POST['cedula'] ?? ''));
        $correo_intec     = trim((string)($_POST['email'] ?? ''));
        $clave            = (string)($_POST['password'] ?? '');

        // Validaciones
        $errores = [];
        if ($id_tipo <= 0) $errores[] = 'Seleccione un rol válido.';
        if ($primer_apellido === '' || $primer_nombre === '') $errores[] = 'Nombres y apellidos obligatorios.';
        if (!preg_match('/^\d{10}$/', $cedula)) $errores[] = 'La cédula debe contener exactamente 10 dígitos.';
        if (!filter_var($correo_intec, FILTER_VALIDATE_EMAIL)) $errores[] = 'Correo electrónico inválido.';
        if (strlen($clave) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';

        if (!$errores) {
            try {
                // Validar que el rol exista
                $st = $conn->prepare('SELECT 1 FROM tipo_usuario WHERE id_tp_user = ? LIMIT 1');
                $st->execute([$id_tipo]);
                if (!$st->fetchColumn()) {
                    throw new RuntimeException('El rol seleccionado no existe.');
                }

                // Verificar duplicados por correo o cédula
                $chk = $conn->prepare('SELECT correo, cedula FROM usuario WHERE correo = ? OR cedula = ? LIMIT 1');
                $chk->execute([$correo_intec, $cedula]);
                $dup = $chk->fetch(PDO::FETCH_ASSOC);
                if ($dup) {
                    if (isset($dup['correo']) && strcasecmp($dup['correo'], $correo_intec) === 0) {
                        $mensaje_error = 'El correo electrónico ya está registrado. Intente con otro.';
                    } else {
                        $mensaje_error = 'La cédula ya está registrada.';
                    }
                } else {
                    $hashed = password_hash($clave, PASSWORD_BCRYPT);
                    $ins = $conn->prepare(
                        'INSERT INTO usuario (id_tp_user, primer_apellido, segundo_apellido, primer_nombre, segundo_nombre, cedula, correo, pwd)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $ins->execute([$id_tipo, $primer_apellido, $segundo_apellido, $primer_nombre, $segundo_nombre, $cedula, $correo_intec, $hashed]);

                    header('Location: ' . appBasePath() . '/view/partials/admin/dashboard_adm.php?success=1');
                    exit();
                }
            } catch (Throwable $e) {
                error_log('registro_usuario: ' . $e->getMessage());
                $mensaje_error = 'Error al registrar el usuario.';
            }
        } else {
            $mensaje_error = implode(' ', $errores);
        }
    }
}

// Cargar roles
try {
    $stmt_roles = $conn->prepare('SELECT id_tp_user, detalle_tp_user FROM tipo_usuario');
    $stmt_roles->execute();
    $roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $roles = [];
    $mensaje_error = 'Error al cargar los roles.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="../../../build/css/app.css">
    <script src="../../../build/js/menu.js" defer></script>
</head>
<body>
    <header class="header">
        <img src="../../../src/img/intec.png" alt="Logo Intec" class="logo">
    </header>

    <div class="registro-wrapper">
    <div class="registro-container">
        <h1 class="registro-titulo">Registro de Usuario</h1>

        <?php if ($mensaje_error): ?>
            <div class="registro-alerta error"><?php echo htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($mensaje_exito): ?>
            <div class="registro-alerta exito"><?php echo htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="#" class="registro-formulario" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <div class="registro-campo">
                <label for="pri_apellido">Primer Apellido:</label>
                <input type="text" id="pri_apellido" name="pri_apellido" required>
            </div>

            <div class="registro-campo">
                <label for="seg_apellido">Segundo Apellido:</label>
                <input type="text" id="seg_apellido" name="seg_apellido" required>
            </div>

            <div class="registro-campo">
                <label for="pri_nombre">Primer Nombre:</label>
                <input type="text" id="pri_nombre" name="pri_nombre" required>
            </div>

            <div class="registro-campo">
                <label for="seg_nombre">Segundo Nombre:</label>
                <input type="text" id="seg_nombre" name="seg_nombre" required>
            </div>

            <div class="registro-campo">
                <label for="cedula">Cédula:</label>
                <input type="text" id="cedula" name="cedula" maxlength="10" pattern="\d{10}" title="La cédula debe contener exactamente 10 dígitos" required>
            </div>

            <div class="registro-campo">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="registro-campo">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>

            <div class="registro-campo">
                <label for="rol">Rol:</label>
                <select id="rol" name="rol" required>
                    <option value="">Seleccione un rol</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?php echo htmlspecialchars((string)$rol['id_tp_user'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($rol['detalle_tp_user'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="registro-botones">
                <button type="submit" class="registro-btn">Registrar</button>
                <a href="../admin/dashboard_adm.php" class="registro-btn volver">&larr; Volver al Panel</a>
            </div>

        </form>
    </div>
    </div>

</body>
</html>

