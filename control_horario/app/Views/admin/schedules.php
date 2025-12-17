<?php $base = (function_exists('appBasePath') ? appBasePath() : ''); ?>
<main class="main-control">
  <div class="container">
    <h3>Horarios de Entrada/Salida</h3>
    <?php if (!empty($msg)): ?><div class="registro-alerta exito"><?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="registro-alerta error"><?= htmlspecialchars($err,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

    <form method="get" action="<?= $base ?>/index.php" class="registro-formulario" style="margin-bottom:1rem;">
      <input type="hidden" name="r" value="admin">
      <input type="hidden" name="action" value="schedules">
      <div class="registro-campo">
        <label class="required">Usuario</label>
        <select name="uid" onchange="this.form.submit()" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($usuarios as $u): $sel = (!empty($uidSel) && (int)$uidSel===(int)$u['id_usuario']) ? 'selected' : ''; ?>
            <option value="<?= (int)$u['id_usuario'] ?>" <?= $sel ?>><?= htmlspecialchars($u['nombre'],ENT_QUOTES,'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <form method="post" action="<?= $base ?>/index.php?r=admin&action=schedules_save" class="registro-formulario" style="margin-bottom:1rem;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
      <input type="hidden" name="uid" value="<?= (int)($uidSel ?? 0) ?>">
      <div class="registro-campo">
        <label class="required" for="hora_ingreso">Hora de ingreso (HH:MM:SS)</label>
        <input id="hora_ingreso" type="text" name="hora_ingreso" placeholder="08:00:00" value="<?= htmlspecialchars($currIn ?? '',ENT_QUOTES,'UTF-8') ?>" required>
        <?php if (!empty($currIn)): ?><div class="help-text">Actual: <?= htmlspecialchars($currIn,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="registro-campo">
        <label class="required" for="hora_salida">Hora de salida (HH:MM:SS)</label>
        <input id="hora_salida" type="text" name="hora_salida" placeholder="17:00:00" value="<?= htmlspecialchars($currOut ?? '',ENT_QUOTES,'UTF-8') ?>" required>
        <?php if (!empty($currOut)): ?><div class="help-text">Actual: <?= htmlspecialchars($currOut,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
      </div>
      <div class="registro-botones" style="grid-column:1 / -1;">
        <button type="submit" class="registro-btn">Guardar</button>
        <a class="registro-btn volver" href="<?= $base ?>/index.php?r=dashboard&mod=admin">Volver</a>
      </div>
    </form>

    <p class="note">Esta acción crea/actualiza tablas: horario_entrada_personal y horario_salida_personal.</p>
  </div>
</main>
