<?php $base = function_exists('appRouterBase') ? appRouterBase() : (function_exists('appAssetBase') ? appAssetBase() : ''); ?>
<main class="main-control">
  <div class="container">
    <h3>Usuarios</h3>
    <?php if (!empty($msg)): ?><div class="registro-alerta exito"><?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
    <?php if (!empty($err)): ?><div class="registro-alerta error"><?= htmlspecialchars($err,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

    <form method="post" action="<?= $base ?>/index.php?r=admin&action=users_create" class="registro-formulario" style="margin-bottom:1rem;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
      <div class="registro-campo">
        <label class="required" for="correo">Correo</label>
        <input id="correo" type="email" name="correo" placeholder="ej: usuario@dominio.com" required>
        <div class="help-text">Usa un correo válido; será tu usuario de acceso.</div>
      </div>
      <div class="registro-campo">
        <label class="required" for="pnombre">Primer Nombre</label>
        <input id="pnombre" type="text" name="nombre" placeholder="ej: Juan" required>
      </div>
      <div class="registro-campo">
        <label class="required" for="snombre">Segundo Nombre</label>
        <input id="snombre" type="text" name="snombre" placeholder="ej: Carlos" required>
      </div>
      <div class="registro-campo">
        <label class="required" for="papellido">Primer Apellido</label>
        <input id="papellido" type="text" name="apellido" placeholder="ej: Pérez" required>
      </div>
      <div class="registro-campo">
        <label class="required" for="sapellido">Segundo Apellido</label>
        <input id="sapellido" type="text" name="sapellido" placeholder="ej: García" required>
      </div>
      <div class="registro-campo">
        <label class="required" for="cedula">Cédula</label>
        <input id="cedula" type="text" name="cedula" placeholder="ej: 0102030405" required>
        <div class="help-text">Ingresa el documento sin espacios ni guiones.</div>
      </div>
      <div class="registro-campo">
        <label class="required" for="pwd">Contraseña</label>
        <input id="pwd" type="password" name="pwd" placeholder="Mín. 8 caracteres" required>
        <div class="help-text">Se almacenará de forma segura (hash).</div>
      </div>
      <div class="registro-campo">
        <label class="required" for="rol">Rol</label>
        <select name="rol" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id_tp_user'] ?>"><?= htmlspecialchars($r['detalle_tp_user'],ENT_QUOTES,'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <div class="help-text">Selecciona el perfil del usuario (ej. DOCENTES, TI, etc.).</div>
      </div>
      <div class="registro-botones" style="grid-column:1 / -1;">
        <button type="submit" class="registro-btn">Crear usuario</button>
        <a class="registro-btn volver" href="<?= $base ?>/index.php?r=dashboard&mod=admin">Volver</a>
      </div>
    </form>

    <div class="table-container">
      <table class="table">
        <thead class="table__head"><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th></tr></thead>
        <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><?= (int)$u['id_usuario'] ?></td>
              <td><?= htmlspecialchars(trim(($u['primer_nombre']??'').' '.($u['primer_apellido']??'')),ENT_QUOTES,'UTF-8') ?></td>
              <td><?= htmlspecialchars($u['correo']??'',ENT_QUOTES,'UTF-8') ?></td>
              <td><?= htmlspecialchars($u['rol']??'',ENT_QUOTES,'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
