<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="build/css/app.css">
  <title>Elige tu Rol</title>
</head>
<body>
  <main class="login-main">
    <div class="login-container login-container--roles">
      <?php
        app_ensure_csrf_token();
        $choices = $_SESSION['login_choices'] ?? [];
        $adm = [];
        $doc = [];
        foreach ($choices as $c) {
            if (($c['categoria'] ?? '') === 'DOCENTE') {
                $doc[] = $c;
            } else {
                $adm[] = $c;
            }
        }
        $first = $choices[0] ?? null;
        $nombrePrincipal = $first['nombre'] ?? '';
      ?>

      <?php if ($nombrePrincipal !== ''): ?>
        <h2 class="login-title"><?= htmlspecialchars($nombrePrincipal, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="login-subtitle">¿Con qué perfil deseas ingresar?</p>
      <?php else: ?>
        <h2 class="login-title">Elige con qué rol deseas continuar</h2>
      <?php endif; ?>

      <div class="role-buttons">
        <?php if (!empty($adm)): $c = $adm[0]; ?>
          <form method="post" class="role-buttons__form">
            <input type="hidden" name="select_user_id" value="<?= (int)$c['id_usuario'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
            <button type="submit" class="role-buttons__btn">
              <span class="role-buttons__icon">👔</span>
              <span>Administrativo</span>
            </button>
          </form>
        <?php endif; ?>

        <?php if (!empty($doc)): $c = $doc[0]; ?>
          <form method="post" class="role-buttons__form">
            <input type="hidden" name="select_user_id" value="<?= (int)$c['id_usuario'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
            <button type="submit" class="role-buttons__btn role-buttons__btn--secondary">
              <span class="role-buttons__icon">📚</span>
              <span>Docente</span>
            </button>
          </form>
        <?php endif; ?>
      </div>

    </div>
  </main>
  <script>/* Cada botón envía el formulario y redirige según el rol elegido */</script>
</body>
</html>
