<?php $base = $base ?? (function_exists('appBasePath') ? appBasePath() : ''); ?>
<main class="main-control">
  <div class="container">
    <h3>Gestión de Roles</h3>
    <?php if (!empty($msg)): ?><div class="registro-alerta exito"><?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="registro-alerta error"><?= htmlspecialchars($err,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

    <form method="post" action="<?= $base ?>/public/index.php?r=admin&action=roles_create" class="registro-formulario" style="margin-bottom:1rem;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
      <div class="registro-campo" style="grid-column:1 / -1;">
        <label for="detalle" class="required">Nombre del Rol (ej. DOCENTES)</label>
        <input type="text" id="detalle" name="detalle" placeholder="Nombre del rol" required>
      </div>
      <div class="registro-botones" style="grid-column:1 / -1;">
        <button type="submit" class="registro-btn">Crear rol</button>
        <a class="registro-btn volver" href="<?= $base ?>/public/index.php?r=dashboard&mod=admin">Volver al dashboard</a>
      </div>
    </form>

    <div class="table-container">
      <table class="table">
        <thead class="table__head">
          <tr><th>ID</th><th>Detalle</th></tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $r): ?>
          <tr>
            <td><?= (int)$r['id_tp_user'] ?></td>
            <td><?= htmlspecialchars($r['detalle_tp_user'], ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
