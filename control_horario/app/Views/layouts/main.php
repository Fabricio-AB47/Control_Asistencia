<?php
// Layout base. Variables esperadas: $title, $content, $module, $nombre, $apellido, $menu
$base = function_exists('appBasePath') ? appBasePath() : '';
$baseUrl = function_exists('appAssetBase')
  ? appAssetBase()
  : (($base === '/' || $base === '') ? '' : rtrim($base, '/'));
$routerBase = function_exists('appRouterBase') ? rtrim(appRouterBase(), '/') : $baseUrl;

if (!function_exists('normalizeRoleName')) {
    // Normaliza acentos para comparar roles sin problemas de encoding
    function normalizeRoleName($name) {
        $map = [
            'A­' => 'a', 'Ac' => 'e', 'A-' => 'i', 'A3' => 'o', 'A§' => 'u', 'Añ' => 'n',
            'A?' => 'A', 'A%' => 'E', 'A?' => 'I', 'A"' => 'O', 'As' => 'U', 'A`' => 'N',
        ];
        $clean = strtr($name, $map);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
        if ($ascii !== false) {
            $clean = $ascii;
        }
        return strtoupper($clean);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <?php if (!empty($_SESSION['token'])): ?>
  <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= $baseUrl ?>/build/css/app.css">
  <script src="<?= $baseUrl ?>/build/js/menu.js" defer></script>
  <title><?= htmlspecialchars($title ?? 'App', ENT_QUOTES, 'UTF-8') ?></title>
  <style nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8'); ?>"> .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;} .logout-inline{display:inline;} </style>
</head>
<body>

<header class="header">
  <?php
    // Siempre mostrar primer nombre y primer apellido si existen en la sesión
    $fn = $_SESSION['primer_nombre']  ?? ($_SESSION['nombre']   ?? ($nombre   ?? 'Usuario'));
    $ln = $_SESSION['primer_apellido'] ?? ($_SESSION['apellido'] ?? ($apellido ?? ''));
    $displayName = trim($fn . ' ' . $ln);
  ?>
  <h1 class="header__welcome">Bienvenido, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h1>
  <div class="header__brand">
    <img src="<?= $baseUrl ?>/build/img/intec.png" alt="Logo Intec" class="logo">
  </div>
  <br>
  <?php
    // Enlaces especiales para ADMIN dentro del menú
    $rolMenu = $_SESSION['tipo'] ?? '';
    $rolNorm = normalizeRoleName($rolMenu);
    $isAdmin = ($rolNorm === 'ADMIN' || $rolNorm === 'ADMINISTRADOR');
  ?>
  <nav class="header__menu" aria-label="Menú principal">
    <h2 class="sr-only">Menú</h2>
    <?php if (!$isAdmin): ?>
      <?php if (!empty($menu)): foreach ($menu as $item): ?>
        <?php if ($item['current']): ?>
          <a class="btn btn--ghost<?= $item['current'] ? ' is-active' : '' ?>" href="<?= htmlspecialchars($routerBase . '/index.php?r=dashboard&mod=' . $item['mod'], ENT_QUOTES, 'UTF-8') ?>">Inicio <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
      <?php endforeach; elseif (!empty($module)): ?>
        <a class="btn btn--ghost" href="<?= htmlspecialchars($routerBase . '/index.php?r=dashboard&mod=' . $module, ENT_QUOTES, 'UTF-8') ?>">Inicio</a>
      <?php endif; ?>
    <?php endif; ?>

    <?php
      // Enlaces especiales para ADMIN dentro del menú
      $rolMenu = $_SESSION['tipo'] ?? '';
      $rolNorm = normalizeRoleName($rolMenu);
      $isAdmin = ($rolNorm === 'ADMIN' || $rolNorm === 'ADMINISTRADOR');
    ?>
    <?php if ($isAdmin): ?>
      <a class="btn btn--ghost is-active" href="<?= $routerBase ?>/index.php?r=dashboard&mod=admin">🏠 Inicio Admin</a>
      <a class="btn btn--ghost" href="<?= $routerBase ?>/index.php?r=admin&action=roles">🧭 Gestión de Roles</a>
      <a class="btn btn--ghost" href="<?= $routerBase ?>/index.php?r=admin&action=users">👥 Gestión de Usuarios</a>
      <a class="btn btn--ghost" href="<?= $routerBase ?>/index.php?r=admin&action=schedules">🗓️ Horarios Entrada/Salida</a>
      <a class="btn btn--ghost" href="<?= $routerBase ?>/index.php?r=admin&action=timbres_edit">⏱️ Editar Timbres</a>
      <a class="btn btn--ghost" href="<?= $routerBase ?>/index.php?r=reporte_all">📊 Reporte General</a>
    <?php else: ?>
      <?php if (!empty($module)): ?>
        <a class="btn btn--ghost" href="<?= $routerBase ?>/index.php?r=reporte&mod=<?= htmlspecialchars($module,ENT_QUOTES,'UTF-8') ?>">📄 Reporte</a>
      <?php endif; ?>
    <?php endif; ?>

    <form method="POST" action="<?= $routerBase ?>/logout.php" class="logout-inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn btn--danger">Cerrar Sesión</button>
    </form>
  </nav>
</header>

<?= $content ?>

</body>
</html>
