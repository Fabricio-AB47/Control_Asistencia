<?php
// Layout base. Variables esperadas: $title, $content, $module, $nombre, $apellido, $menu
$base = function_exists('appBasePath') ? appBasePath() : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <?php if (!empty($_SESSION['token'])): ?>
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= $base ?>/build/css/app.css">
  <script src="<?= $base ?>/build/js/menu.js" defer></script>
  <title><?= htmlspecialchars($title ?? 'App', ENT_QUOTES, 'UTF-8') ?></title>
  <style> .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;} </style>
  <?php /* Podrías inyectar CSP/meta adicionales aquí */ ?>
  </head>
<body>

<header class="header">
  <h1 class="header__welcome">Bienvenido, <?= htmlspecialchars(trim(($nombre ?? 'Usuario').' '.($apellido ?? '')), ENT_QUOTES, 'UTF-8') ?></h1>
  <div class="header__brand">
    <img src="<?= $base ?>/src/img/intec.png" alt="Logo Intec" class="logo">
  </div>
  <br>
  <nav class="header__menu" aria-label="Menú principal">
    <h2 class="sr-only">Menú</h2>
    <?php if (!empty($menu)): foreach ($menu as $item): ?>
      <a class="btn btn--ghost<?= $item['current'] ? ' is-active' : '' ?>" href="<?= htmlspecialchars((function_exists('appBasePath')?appBasePath():'') . '/public/index.php?r=dashboard&mod=' . $item['mod'], ENT_QUOTES, 'UTF-8') ?>">Inicio <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; else: ?>
      <?php if (!empty($module)): ?>
        <a class="btn btn--ghost" href="<?= htmlspecialchars((function_exists('appBasePath')?appBasePath():'') . '/public/index.php?r=dashboard&mod=' . $module, ENT_QUOTES, 'UTF-8') ?>">Inicio</a>
      <?php endif; ?>
    <?php endif; ?>

    <?php
      // Enlaces especiales para ADMIN dentro del menú
      $rolMenu = $_SESSION['tipo'] ?? '';
      $rolNorm = strtr($rolMenu, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N'
      ]);
      $rolNorm = strtoupper($rolNorm);
      $isAdmin = ($rolNorm === 'ADMIN' || $rolNorm === 'ADMINISTRADOR');
      $baseUrl = (function_exists('appBasePath')?appBasePath():'');
    ?>
    <?php if ($isAdmin): ?>
      <a class="btn btn--ghost" href="<?= $baseUrl ?>/public/index.php?r=admin&action=roles">Gestión de Roles</a>
      <a class="btn btn--ghost" href="<?= $baseUrl ?>/public/index.php?r=admin&action=users">Gestión de Usuarios</a>
      <a class="btn btn--ghost" href="<?= $baseUrl ?>/public/index.php?r=admin&action=schedules">Horarios Entrada/Salida</a>
      <a class="btn btn--ghost" href="<?= $baseUrl ?>/public/index.php?r=admin&action=timbres_edit">Editar Timbres</a>
      <a class="btn btn--ghost" href="<?= $baseUrl ?>/public/index.php?r=reporte_all">Reporte General</a>
    <?php else: ?>
      <?php if (!empty($module)): ?>
        <a class="btn btn--ghost" href="<?= $baseUrl ?>/public/index.php?r=reporte&mod=<?= htmlspecialchars($module,ENT_QUOTES,'UTF-8') ?>">Reporte</a>
      <?php endif; ?>
    <?php endif; ?>
    <a class="btn btn--danger" href="<?= $base ?>/logout.php?logout=1">Cerrar Sesión</a>
  </nav>
</header>

<?= $content ?>

</body>
</html>
