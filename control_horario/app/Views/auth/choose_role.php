<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="stylesheet" href="build/css/app.css">
  <title>Elige tu Rol</title>
  <style>
    .tabs{display:flex;gap:.5rem;margin:1rem 0}
    .tab{padding:.5rem 1rem;border:1px solid #ccc;border-radius:6px;cursor:pointer}
    .tab.is-active{background:#0ea5e9;color:#fff;border-color:#0ea5e9}
    .panel{display:none}
    .panel.is-active{display:block}
    .card{border:1px solid #e5e7eb;border-radius:8px;padding:.75rem;margin:.5rem 0;display:flex;justify-content:space-between;align-items:center}
    .btn{padding:.4rem .8rem;border-radius:6px;background:#22c55e;color:#fff;border:none;cursor:pointer}
  </style>
</head>
<body>
  <main class="login-main">
    <div class="login-container">
      <h2>Elige con qué rol deseas continuar</h2>
      <?php app_ensure_csrf_token(); $choices = $_SESSION['login_choices'] ?? []; $adm=[]; $doc=[]; foreach ($choices as $c){ if(($c['categoria']??'')==='DOCENTE') $doc[]=$c; else $adm[]=$c; } ?>
      <div class="tabs">
        <?php if (!empty($adm)): ?><button class="tab is-active" data-target="adm">Administrativo</button><?php endif; ?>
        <?php if (!empty($doc)): ?><button class="tab <?= empty($adm)?'is-active':'' ?>" data-target="doc">Docente</button><?php endif; ?>
      </div>
      <?php if (!empty($adm)): ?>
      <section id="panel-adm" class="panel is-active">
        <?php foreach ($adm as $c): ?>
          <form method="post" class="card">
            <div><strong><?= htmlspecialchars($c['rol'],ENT_QUOTES,'UTF-8') ?></strong><br><?= htmlspecialchars($c['nombre'],ENT_QUOTES,'UTF-8') ?></div>
            <input type="hidden" name="select_user_id" value="<?= (int)$c['id_usuario'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
            <button type="submit" class="btn">Continuar</button>
          </form>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
      <?php if (!empty($doc)): ?>
      <section id="panel-doc" class="panel <?= empty($adm)?'is-active':'' ?>">
        <?php foreach ($doc as $c): ?>
          <form method="post" class="card">
            <div><strong><?= htmlspecialchars($c['rol'],ENT_QUOTES,'UTF-8') ?></strong><br><?= htmlspecialchars($c['nombre'],ENT_QUOTES,'UTF-8') ?></div>
            <input type="hidden" name="select_user_id" value="<?= (int)$c['id_usuario'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['token'] ?? '',ENT_QUOTES,'UTF-8') ?>">
            <button type="submit" class="btn">Continuar</button>
          </form>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
    </div>
  </main>
  <script>
    (function(){
      const tabs = document.querySelectorAll('.tab');
      tabs.forEach(t=>t.addEventListener('click', function(){
        tabs.forEach(x=>x.classList.remove('is-active'));
        this.classList.add('is-active');
        const target = this.getAttribute('data-target');
        document.querySelectorAll('.panel').forEach(p=>p.classList.remove('is-active'));
        const id = target==='adm' ? '#panel-adm' : '#panel-doc';
        const el = document.querySelector(id); if (el) el.classList.add('is-active');
      }));
    })();
  </script>
</body>
</html>
