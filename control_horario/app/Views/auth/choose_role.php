<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <?php $assetBase = function_exists('appAssetBase') ? appAssetBase() : ((function_exists('appBasePath') ? appBasePath() : '')); ?>
  <?php $routerBase = function_exists('appRouterBase') ? appRouterBase() : $assetBase; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/build/css/app.css', ENT_QUOTES, 'UTF-8') ?>">
  <title>Elige tu Rol</title>
</head>
<body>
  <main class="login-main">
    <div class="login-container login-container--roles">
      <?php
        app_ensure_csrf_token();
        $choices = $_SESSION['login_choices'] ?? [];
        $first = $choices[0] ?? null;
        $nombrePrincipal = $first['nombre'] ?? '';
      ?>

      <?php if ($nombrePrincipal !== ''): ?>
        <h2 class="login-title"><?= htmlspecialchars($nombrePrincipal, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="login-subtitle">&#191;Con qu&#233; perfil deseas ingresar?</p>
      <?php else: ?>
        <h2 class="login-title">Elige con qu&#233; rol deseas continuar</h2>
      <?php endif; ?>

      <?php if (empty($choices)): ?>
        <p class="login-subtitle">No hay roles disponibles para este usuario.</p>
      <?php else: ?>
        <div class="role-buttons">
          <?php foreach ($choices as $c):
            $cat = $c['categoria'] ?? 'ADMINISTRATIVO';
            $label = ($cat === 'DOCENTE') ? 'Docente' : (($cat === 'ADMIN') ? 'Administrador' : 'Administrativo');
            $icon = ($cat === 'DOCENTE') ? '&#127979;' : (($cat === 'ADMIN') ? '&#128295;' : '&#128188;');
          ?>
            <form method="post" action="<?= htmlspecialchars(rtrim($routerBase,'/') . '/index.php', ENT_QUOTES, 'UTF-8') ?>" class="role-buttons__form">
              <input type="hidden" name="select_user_id" value="<?= (int)($c['id_usuario'] ?? 0) ?>">
              <button type="submit" class="role-buttons__btn<?= $cat === 'DOCENTE' ? ' role-buttons__btn--secondary' : '' ?>">
                <span class="role-buttons__icon" aria-hidden="true"><?= $icon; ?></span>
                <span>
                  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><br>
                  <small><?= htmlspecialchars($c['rol'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                </span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>
  <script>/* Cada botón envía el formulario y redirige según el rol elegido */</script>
</body>
</html>
