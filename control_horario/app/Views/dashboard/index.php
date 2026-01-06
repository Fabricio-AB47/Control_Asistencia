<?php
$base = $base ?? (function_exists('appBasePath') ? appBasePath() : '');
$baseUrl = ($base === '/' || $base === '') ? '' : rtrim($base, '/');
$routerBase = function_exists('appRouterBase') ? rtrim(appRouterBase(), '/') : $baseUrl;
$mod  = $module ?? 'ti';
?>
<style nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8'); ?>">
  .dash-section { margin-top:1.5rem; text-align:center; }
  .dash-title { margin:.5rem 0 1rem; }
  .dash-form { display:flex; gap:.75rem; justify-content:center; flex-wrap:wrap; }
  .dash-form .filter__field { text-align:left; }
  .dash-chart-wrap { display:flex; justify-content:center; align-items:center; margin-top:.5rem; }
</style>
<main class="main-control">
  <div class="container">
    <h3>Panel de <?= htmlspecialchars($mod === 'admin' ? 'Administrador' : ucfirst($mod), ENT_QUOTES, 'UTF-8') ?></h3>
    <?php if ($mod !== 'admin'): ?>
      <p>Accesos rápidos del módulo.</p>
      <div class="buttons">
        <a class="btn" href="<?= $routerBase ?>/index.php?r=control&mod=<?= htmlspecialchars($mod,ENT_QUOTES,'UTF-8') ?>">
          <span class="btn-icon" aria-hidden="true">&#x1F4DD;</span>
          <span>Registro de Control Horario</span>
        </a>
        <a class="btn" href="<?= $routerBase ?>/index.php?r=reporte&mod=<?= htmlspecialchars($mod,ENT_QUOTES,'UTF-8') ?>">
          <span class="btn-icon" aria-hidden="true">&#x1F4C8;</span>
          <span>Reporte de Timbres</span>
        </a>
      </div>
    <?php else: ?>
      <p>Bienvenido al Panel de Administración. Usa el menú superior para navegar (Roles, Usuarios, Horarios y Reporte General).</p>
      <section class="dash-section">
        <h4 class="dash-title">Distribución de tiempo de llegadas (a tiempo/temprano vs atrasos)</h4>
        <?php $hoy=(new DateTime('now', new DateTimeZone('America/Guayaquil')))->format('Y-m-d'); $ini=(new DateTime('first day of this month'))->format('Y-m-d'); ?>
        <form id="form-stats" class="filter chart-actions dash-form" method="get" onsubmit="return false;">
          <label class="filter__field">Desde <input type="date" id="f-desde" value="<?= htmlspecialchars($ini,ENT_QUOTES,'UTF-8') ?>"></label>
          <label class="filter__field">Hasta <input type="date" id="f-hasta" value="<?= htmlspecialchars($hoy,ENT_QUOTES,'UTF-8') ?>"></label>
          <button class="btn btn--primary" id="btn-load" type="button">Actualizar</button>
        </form>
        <div class="dash-chart-wrap">
          <div class="chart-card">
            <canvas id="chart-arrivals"></canvas>
          </div>
        </div>
        <div id="modal-stats" class="modal-overlay" hidden>
          <div class="modal modal--wide" role="dialog" aria-modal="true" aria-labelledby="modal-stats-title">
            <div class="modal__title" id="modal-stats-title">Detalle</div>
            <div class="modal__body" id="modal-stats-body"></div>
            <div class="modal__actions"><button type="button" id="modal-stats-ok" class="modal__btn">Aceptar</button></div>
          </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script nonce="<?= htmlspecialchars($cspNonce ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          (function(){
            const BASE = <?= json_encode($routerBase) ?>;
            const ctx = document.getElementById('chart-arrivals').getContext('2d');
            let chart = null;
            function render(data){
              const d = { labels:['A tiempo/Temprano','Atrasos'], datasets:[{ data:[data.ontime||0, data.tardy||0], backgroundColor:['#22c55e','#ef4444'], borderColor:'#ffffff', borderWidth:2 }] };
              const opts = { responsive:true, maintainAspectRatio:true, aspectRatio:1.1, layout:{padding:8}, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:14, boxHeight:14 } } } };
              if (chart) { chart.data = d; chart.options = opts; chart.update(); return; }
              chart = new Chart(ctx, { type:'pie', data:d, options:opts });
              document.getElementById('chart-arrivals').onclick = function(evt){
                const points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                if (!points.length) return; const idx = points[0].index; const type = idx===1 ? 'tardy' : 'ontime';
                loadDetails(type);
              };
            }
            async function load(){
              const desde = document.getElementById('f-desde').value; const hasta = document.getElementById('f-hasta').value;
              const url = `${BASE}/index.php?r=admin&action=arrival_stats&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`;
              try { const res = await fetch(url, {credentials:'same-origin'}); const j = await res.json(); if (j && j.ok) render(j.data); } catch(_){/* noop */}
            }
            async function loadDetails(type){
              const desde = document.getElementById('f-desde').value; const hasta = document.getElementById('f-hasta').value;
              const url = `${BASE}/index.php?r=admin&action=arrival_details&type=${encodeURIComponent(type)}&desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`;
              try {
                const res = await fetch(url, {credentials:'same-origin'}); const j = await res.json();
                if (!j || !j.ok) return; 
                const rows = j.data||[];
                let html = '<div class="table-container"><table class="table"><thead class="table__head"><tr><th>Fecha</th><th>Usuario</th><th>Rol</th><th>Hora prog.</th><th>Ingreso</th><th>Estado</th><th>Direcci├│n</th></tr></thead><tbody>';
                for (const r of rows){
                  const link = (r.lat_in&&r.lon_in) ? ` <a class=\"js-map\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(r.lat_in+','+r.lon_in)}\" data-lat=\"${r.lat_in}\" data-lon=\"${r.lon_in}\">[Mapa]</a>` : '';
                  html += `<tr><td>${r.fecha||''}</td><td>${r.usuario||''}</td><td>${r.rol||''}</td><td>${r.hora_prog_in||''}</td><td>${r.hora_ingreso||''}</td><td>${r.estado_ingreso||''}</td><td>${(r.dir_in||'')}${link}</td></tr>`;
                }
                html += '</tbody></table></div>';
                const modal = document.getElementById('modal-stats');
                document.getElementById('modal-stats-title').textContent = (type==='tardy' ? 'Detalle: Atrasos' : 'Detalle: A tiempo/Temprano');
                document.getElementById('modal-stats-body').innerHTML = html;
                modal.removeAttribute('hidden');
                document.getElementById('modal-stats-ok').focus();
              } catch(_){}
            }
            document.getElementById('btn-load').addEventListener('click', load);
            load();
            // Cierre del modal detalle
            (function(){
              const MOD = document.getElementById('modal-stats');
              const OK = document.getElementById('modal-stats-ok');
              function close(){ MOD.setAttribute('hidden',''); }
              OK.addEventListener('click', close);
              MOD.addEventListener('click', e=>{ if(e.target===MOD) close(); });
              document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !MOD.hasAttribute('hidden')) close(); });
            })();
          })();
        </script>
      </section>
    <?php endif; ?>
  </div>
</main>




