<?php
session_start();
require_once __DIR__ . '/../../../core/conexion.php';

// Tiempo máximo de inactividad (en segundos) - 15 minutos
$tiempo_inactividad_maximo = 900; // 15 minutos = 900 segundos

// Verificar si la sesión está activa
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . appBasePath() . '/index.php');
    exit();
}

// Verificar si ha pasado más de 15 minutos desde el último acceso
if (isset($_SESSION['ultimo_acceso']) && (time() - $_SESSION['ultimo_acceso']) > $tiempo_inactividad_maximo) {
    // Si han pasado más de 15 minutos, cerrar sesión
    session_unset();
    session_destroy();
    header('Location: ' . appBasePath() . '/index.php');
    exit();
}

// Actualizar último acceso
$_SESSION['ultimo_acceso'] = time();

// Variables de usuario
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$apellido = $_SESSION['apellido'] ?? 'Usuario';
$tipo_usuario = $_SESSION['tipo'] ?? 'Desconocido';
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($tipo_usuario); ?></title>
    <link rel="stylesheet" href="../../../build/css/app.css">
    <script src="../../../build/js/menu.js" defer></script>
</head>
<body>

    <header class="header">
      <h1 class="header__welcome">Bienvenido, <?php echo htmlspecialchars(trim("$nombre $apellido")); ?></h1>
      <div class="header__brand">
        <img src="../../../src/img/intec.png" alt="Logo Intec" class="logo">
      </div>
      <br>
      <nav class="header__menu" aria-label="Menú principal">
        <h2 class="sr-only">Menú</h2>
        <a class="btn btn--ghost" href="../ti/dashboard.php">Inicio</a>
        <a class="btn btn--ghost"href="../ti/control.php">Registro de Control Horario</a>
        <a class="btn btn--ghost" href="../ti/reporte_timbres.php">Reporte de timbres</a>
        <a class="btn btn--danger" href="<?= htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar Sesión</a>
      </nav>
    </header>
 <!-- Script de inactividad -->
 <script>
        let tiempoInactividad = 0;
        const tiempoMaximo = 900; // 15 minutos = 900 segundos

        function resetInactividad() {
            tiempoInactividad = 0;
        }

        window.onload = resetInactividad;
        document.onmousemove = resetInactividad;
        document.onkeypress = resetInactividad;
        document.onclick = resetInactividad;
        document.onscroll = resetInactividad;

        setInterval(() => {
            tiempoInactividad++;
            if (tiempoInactividad >= tiempoMaximo) {
                alert("Tu sesión ha expirado por inactividad.");
                window.location.href = "<?= htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8') ?>/logout.php";
            }
        }, 1000); // 1 segundo
    </script>

</body>
</html>
