<?php $base = (function_exists('appBasePath') ? appBasePath() : ''); ?>
<main class="main-control">
  <div class="container">
    <h3>Editar Timbres</h3>
    <?php if (!empty($msg)): ?><div class="registro-alerta exito"><?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="registro-alerta error"><?= htmlspecialchars($err,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

    <form method="get" action="<?= $base ?>/public/index.php" class="registro-formulario" style="margin-bottom:1rem;">
      <input type="hidden" name="r" value="admin">
      <input type="hidden" name="action" value="timbres_edit">
      <div class="registro-campo">
        <label class="required">Usuario</label>
        <select name="uid" id="uid-select" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($usuarios as $u): $sel = (!empty($uidSel) && (int)$uidSel===(int)$u['id_usuario']) ? 'selected' : ''; ?>
            <option value="<?= (int)$u['id_usuario'] ?>" data-rol="<?= htmlspecialchars($u['rol'] ?? '',ENT_QUOTES,'UTF-8') ?>" <?= $sel ?>><?= htmlspecialchars($u['nombre'],ENT_QUOTES,'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="registro-campo">
        <label>Rol del usuario</label>
        <input type="text" id="rol-usuario" value="<?= htmlspecialchars($rolSel ?? '',ENT_QUOTES,'UTF-8') ?>" readonly>
      </div>
      <div class="registro-campo">
        <label class="required">Fecha</label>
        <input type="date" name="fecha" value="<?= htmlspecialchars($fechaSel??'',ENT_QUOTES,'UTF-8') ?>" required>
      </div>
      <div class="registro-botones" style="grid-column:1/-1">
        <button type="submit" class="registro-btn">Cargar</button>
      </div>
    </form>

    <form method="post" action="<?= $base ?>/public/index.php?r=admin&action=timbres_update" class="registro-formulario">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
      <input type="hidden" name="uid" value="<?= (int)($uidSel??0) ?>">
      <input type="hidden" name="fecha" value="<?= htmlspecialchars($fechaSel??'',ENT_QUOTES,'UTF-8') ?>">
      <div class="registro-campo">
        <label>Hora de ingreso (HH:MM:SS)</label>
        <input type="text" name="hora_ingreso" placeholder="08:00:00" value="<?= htmlspecialchars($datos['ingreso']??'',ENT_QUOTES,'UTF-8') ?>">
      </div>
      <div class="registro-campo">
        <label>Salida almuerzo (HH:MM:SS)</label>
        <input type="text" name="hora_sl" placeholder="12:30:00" value="<?= htmlspecialchars($datos['sl']??'',ENT_QUOTES,'UTF-8') ?>">
      </div>
      <div class="registro-campo">
        <label>Retorno almuerzo (HH:MM:SS)</label>
        <input type="text" name="hora_rt" placeholder="13:30:00" value="<?= htmlspecialchars($datos['rt']??'',ENT_QUOTES,'UTF-8') ?>">
      </div>
      <div class="registro-campo">
        <label>Hora de salida (HH:MM:SS)</label>
        <input type="text" name="hora_salida" placeholder="17:00:00" value="<?= htmlspecialchars($datos['salida']??'',ENT_QUOTES,'UTF-8') ?>">
      </div>
      <div class="registro-botones" style="grid-column:1/-1">
        <button type="submit" class="registro-btn">Guardar cambios</button>
        <a class="registro-btn volver" href="<?= $base ?>/public/index.php?r=dashboard&mod=admin">Volver</a>
      </div>
    </form>
  </div>
</main>
<script>
(function(){
  const sel = document.getElementById('uid-select');
  const rolInput = document.getElementById('rol-usuario');
  if (!sel || !rolInput) return;
  function actualizarRol(){
    const opt = sel.options[sel.selectedIndex];
    const rol = opt && opt.dataset.rol ? opt.dataset.rol : '';
    rolInput.value = rol;
  }
  sel.addEventListener('change', actualizarRol);
  actualizarRol();
})();
</script>

