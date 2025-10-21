<?php
session_start();
require_once __DIR__ . '/../../../core/conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ' . appBasePath() . '/index.php');
    exit();
}

// Establecer tiempo de expiración de la sesión a 15 minutos
$tiempo_inactividad = 900; // 15 minutos en segundos

// Comprobar si la sesión ha expirado
if (isset($_SESSION['ultimo_acceso'])) {
    $tiempo_transcurrido = time() - $_SESSION['ultimo_acceso'];
    if ($tiempo_transcurrido > $tiempo_inactividad) {
        // Destruir la sesión y redirigir al login si ha expirado
        session_unset();
        session_destroy();
        header('Location: ' . appBasePath() . '/index.php?error=sesion_expirada');
        exit();
    }
}

// Actualizar el tiempo del último acceso
$_SESSION['ultimo_acceso'] = time();

// Regenerar el ID de sesión periódicamente para mayor seguridad
if (!isset($_SESSION['regenerar_id']) || time() - $_SESSION['regenerar_id'] > 300) { // Cada 5 minutos
    session_regenerate_id(true);
    $_SESSION['regenerar_id'] = time();
}

// Asegurarse de que las variables de sesión estén disponibles
$nombre = $_SESSION['nombre'] ?? 'Usuario'; // Valor por defecto 'Usuario' si no está definido
$tipo_usuario = $_SESSION['tipo'] ?? 'Desconocido'; // Valor por defecto 'Desconocido' si no está definido
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
      <h1 class="header__welcome">Bienvenido, <?php echo htmlspecialchars(trim("$nombre")); ?></h1>
      <div class="header__brand">
        <img src="../../../src/img/intec.png" alt="Logo Intec" class="logo">
      </div>
      <br>
      <nav class="header__menu" aria-label="Menú principal">
        <h2 class="sr-only">Menú</h2>
        <a class="btn btn--ghost" href="../admin/dashboard_adm.php">Inicio</a>
        <a class="btn btn--ghost" href="../admin/registro_usuario.php">Registrar nuevo usuario</a>
        <a class="btn btn--ghost" href="../admin/horario_personal.php">Registro horario de ingreso personal</a>
        <a class="btn btn--ghost" href="../admin/horario_sl_personal.php">Registro horario de salida personal</a>
        <a class="btn btn--ghost" href="../admin/reporte_control_h.php">Reporte del Personal</a>
        <a class="btn btn--danger" href="<?= htmlspecialchars(appBasePath(), ENT_QUOTES, 'UTF-8') ?>/logout.php">Cerrar sesión</a>
      </nav>
    </header>

    <main>
        <section>
            <h2>Panel de Control</h2>
           
        </section>
    </main>
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
