<?php
session_start();

// Configuración de expiración de sesión (15 minutos)
$tiempo_inactividad = 900;

if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    if ($tiempo_transcurrido > $tiempo_inactividad) {
        session_unset();
        session_destroy();
        header("Location: ../../../index.php?error=sesion_expirada");
        exit();
    }
}
$_SESSION['ultimo_acceso'] = time();

// Verificar sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../view/login.php");
    exit();
}

require '../../../core/conexion.php';
$conn = conexion();

// Variables para mensajes
$mensaje_exito = "";
$mensaje_error = "";

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo = $_POST['rol'] ?? null; // El "name" del select en el formulario es "rol"
    $primer_apellido = trim($_POST['pri_apellido'] ?? '');
    $segundo_apellido = trim($_POST['seg_apellido'] ?? '');
    $primer_nombre = trim($_POST['pri_nombre'] ?? '');
    $segundo_nombre = trim($_POST['seg_nombre'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $correo_intec = trim($_POST['email'] ?? '');
    $clave = $_POST['password'] ?? '';

    if ($id_tipo && $primer_apellido && $primer_nombre && $cedula && $correo_intec && $clave) {

        $check_query = "SELECT COUNT(*) FROM usuario WHERE correo = ?";
        $stmt_check = $conn->prepare($check_query);
        $stmt_check->execute([$correo_intec]);
        $existe_usuario = $stmt_check->fetchColumn();

        if ($existe_usuario > 0) {
            $mensaje_error = "El correo electrónico ya está registrado. Intente con otro.";
        } else {
            try {
                $hashed_password = password_hash($clave, PASSWORD_BCRYPT);

                $insert_query = "INSERT INTO usuario (id_tp_user, primer_apellido, segundo_apellido, primer_nombre, segundo_nombre, cedula, correo, pwd)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($insert_query);
                $stmt_insert->execute([
                    $id_tipo,
                    $primer_apellido,
                    $segundo_apellido,
                    $primer_nombre,
                    $segundo_nombre,
                    $cedula,
                    $correo_intec,
                    $hashed_password
                ]);

                header("Location: ../../partials/admin/dashboard_adm.php?success=1");
                exit();

            } catch (PDOException $e) {
                $mensaje_error = "Error al registrar el usuario: " . $e->getMessage();
            }
        }
    } else {
        $mensaje_error = "Por favor, complete todos los campos obligatorios.";
    }
}

// Cargar roles para el formulario
try {
    $query_roles = "SELECT id_tp_user, detalle_tp_user FROM tipo_usuario";
    $stmt_roles = $conn->prepare($query_roles);
    $stmt_roles->execute();
    $roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $roles = [];
    $mensaje_error = "Error al cargar los roles: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="../../../build/css/app.css">
</head>
<body>
    <header class="header">
        <img src="../../../src/img/intec.png" alt="Logo Intec" class="logo">

    </header>

    <div class="registro-wrapper">
    <div class="registro-container">
        <h1 class="registro-titulo">Registro de Usuario</h1>

        <!-- Mostrar mensajes -->
        <?php if ($mensaje_error): ?>
            <div class="registro-alerta error"><?php echo htmlspecialchars($mensaje_error); ?></div>
        <?php endif; ?>
        <?php if ($mensaje_exito): ?>
            <div class="registro-alerta exito"><?php echo htmlspecialchars($mensaje_exito); ?></div>
        <?php endif; ?>

        <form method="POST" action="#" class="registro-formulario">

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
                <input type="password" id="password" name="password" required>
            </div>

            <div class="registro-campo">
                <label for="rol">Rol:</label>
                <select id="rol" name="rol" required>
                    <option value="">Seleccione un rol</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?php echo htmlspecialchars($rol['id_tp_user'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($rol['detalle_tp_user'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="registro-botones">
                <button type="submit" class="registro-btn">Registrar</button>
                <a href="../admin/dashboard_adm.php" class="registro-btn volver">← Volver al Panel</a>
            </div>

        </form>
    </div>
</div>

</body>
</html>
