<?php
  $assetBase = function_exists('appAssetBase') ? appAssetBase() : ((function_exists('appBasePath') ? appBasePath() : ''));
  $routerBase = function_exists('appRouterBase') ? appRouterBase() : $assetBase;
  $formAction = rtrim($routerBase, '/') . '/index.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/build/css/app.css', ENT_QUOTES, 'UTF-8') ?>">
  <title>Control Horario Ingreso</title>
</head>
<body>
  <header class="header">
    <div class="header__brand">
      <img src="<?= htmlspecialchars($assetBase . '/build/img/intec.png', ENT_QUOTES, 'UTF-8') ?>" alt="Logo Intec" class="logo">
    </div>
  </header>
  <main class="login-main">
    <div class="login-container">
      <form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="login-form">
        <h2 class="login-title">Iniciar sesión</h2>
        <div class="form-group">
          <label for="email" class="form-label">Ingrese el correo</label>
          <input type="email" id="email" name="usuario" class="form-input" placeholder="Correo electrónico" required>
        </div>
        <div class="form-group">
          <label for="password" class="form-label">Ingrese la contraseña</label>
          <input type="password" id="password" name="password" class="form-input" placeholder="Contraseña" required>
        </div>
        <div class="form-group"><button type="submit" class="login-btn">Iniciar sesión</button></div>
      </form>
      <?php if (!empty($error)) echo "<p style='color:red;'>".htmlspecialchars($error,ENT_QUOTES,'UTF-8')."</p>"; ?>
      <?php if (isset($_GET['error'])) echo "<p style='color:red;'>Sesión expirada. Por favor, inicia sesión de nuevo.</p>"; ?>
    </div>
  </main>
</body>
</html>
