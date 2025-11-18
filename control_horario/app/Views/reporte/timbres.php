<?php
// Espera: $desde, $hasta, $rows, $horaProgIn, $horaProgOut, $module
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function linkMap($lat, $lon, $label='Mapa'){
  if (!$lat || !$lon || $lat==='0.000000' || $lon==='0.000000') return '';
  $u = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lon);
  $uEsc = h($u); $latEsc = h($lat); $lonEsc = h($lon); $labEsc = h($label);
  return ' <a class="js-map" target="_blank" rel="noopener noreferrer" href="'.$uEsc.'" data-url="'.$uEsc.'" data-lat="'.$latEsc.'" data-lon="'.$lonEsc.'">['.$labEsc.']</a>';
}
function secs($a, $b){ if(!$a||!$b) return 0; return $b->getTimestamp() - $a->getTimestamp(); }
function hhmmss($seconds){ if($seconds<=0)return '00:00:00'; $h=intdiv($seconds,3600); $m=intdiv($seconds%3600,60); $s=$seconds%60; return sprintf('%02d:%02d:%02d',$h,$m,$s);} 
?>

<main class="report">
  <h1 class="report__title">Reporte de Timbrado</h1>

  <form class="filter" method="get" novalidate>
    <input type="hidden" name="r" value="reporte">
    <input type="hidden" name="mod" value="<?= h($module) ?>">
    <label class="filter__field">Desde
      <input type="date" name="desde" value="<?= h($desde) ?>">
    </label>
    <label class="filter__field">Hasta
      <input type="date" name="hasta" value="<?= h($hasta) ?>">
    </label>
    <div class="filter__actions">
      <button class="btn btn--primary" type="submit">🔍 Filtrar</button>
      <a class="btn btn--excel"
         href="?r=reporte&amp;mod=<?= h($module) ?>&amp;desde=<?= h($desde) ?>&amp;hasta=<?= h($hasta) ?>&amp;export=excel">
        📊 Exportar Excel
      </a>
    </div>
    <span class="note">
      <?= $horaProgIn ? 'Hora ingreso prog.: '.h($horaProgIn).' · ' : '' ?>
      <?= $horaProgOut ? 'Hora salida prog.: '.h($horaProgOut) : '' ?>
    </span>
  </form>

  <div class="table-container">
    <table class="table">
      <thead class="table__head">
        <tr>
          <th>Fecha</th>
          <th>Ingreso</th>
          <th>Salida Almuerzo</th>
          <th>Retorno Almuerzo</th>
          <th>Salida</th>
          <th>Jornada</th>
          <th>Almuerzo</th>
          <th>Horas efectivas</th>
          <th>Tardanza</th>
          <th>Tiempo adicional</th>
        </tr>
      </thead>
      <tbody class="table__body">
<?php
  $totalEfectiva = 0; $diasContados = 0;
  foreach ($rows as $r):
    $fecha = $r['fecha'];
    $dtIn  = ($r['hora_ingreso'])     ? new DateTime($fecha.' '.$r['hora_ingreso']) : null;
    $dtSl  = ($r['hora_sl_almuerzo']) ? new DateTime($fecha.' '.$r['hora_sl_almuerzo']) : null;
    $dtRt  = ($r['hora_rt_almuerzo']) ? new DateTime($fecha.' '.$r['hora_rt_almuerzo']) : null;
    $dtOut = ($r['hora_salida'])      ? new DateTime($fecha.' '.$r['hora_salida']) : null;

    $secJornada  = ($dtIn && $dtOut) ? secs($dtIn, $dtOut) : 0;
    $secAlmuerzo = ($dtSl && $dtRt)  ? max(0, secs($dtSl, $dtRt)) : 0;
    $secEfectiva = max(0, $secJornada - $secAlmuerzo);

    $tardanzaMin = 0;
    if ($horaProgIn && $dtIn) {
      $dtProgIn = new DateTime($fecha.' '.$horaProgIn);
      $dif = secs($dtProgIn, $dtIn); // positivo si llegó después
      $tardanzaMin = max(0, intdiv($dif, 60) - 10);
    }
    $salidaAntMin = 0;
    if ($horaProgOut && $dtOut) {
      $dtProgOut = new DateTime($fecha.' '.$horaProgOut);
      $dif = secs($dtOut, $dtProgOut); // negativo si salió antes
      $salidaAntMin = $dif < 0 ? intdiv(abs($dif), 60) : 0;
    }
    if ($secJornada > 0) { $totalEfectiva += $secEfectiva; $diasContados++; }

    $ingresoCell = $r['hora_ingreso']
      ? h($r['hora_ingreso'])." <span class='badge'>".h($r['estado_ingreso'])."</span><br>".h($r['dir_in']).linkMap($r['lat_in'],$r['lon_in'],'Mapa') : '—';
    $slCell = $r['hora_sl_almuerzo']
      ? h($r['hora_sl_almuerzo'])." <span class='badge'>".h($r['estado_sl_alm'])."</span><br>".h($r['dir_sl']).linkMap($r['lat_sl'],$r['lon_sl'],'Mapa') : '—';
    $rtCell = $r['hora_rt_almuerzo']
      ? h($r['hora_rt_almuerzo'])." <span class='badge'>".h($r['estado_rt_alm'])."</span><br>".h($r['dir_rt']).linkMap($r['lat_rt'],$r['lon_rt'],'Mapa') : '—';
    $outCell = $r['hora_salida']
      ? h($r['hora_salida'])." <span class='badge'>".h($r['estado_salida'])."</span><br>".h($r['dir_out']).linkMap($r['lat_out'],$r['lon_out'],'Mapa') : '—';
?>
        <tr>
          <td><?= h($fecha) ?></td>
          <td><?= $ingresoCell ?></td>
          <td><?= $slCell ?></td>
          <td><?= $rtCell ?></td>
          <td><?= $outCell ?></td>
          <td><?= hhmmss($secJornada) ?></td>
          <td><?= hhmmss($secAlmuerzo) ?></td>
          <td><?= hhmmss($secEfectiva) ?></td>
          <td><?= ($tardanzaMin>0? "<span class='chip chip--warn'>".$tardanzaMin." min</span>": '—') ?></td>
          <td><?= ($salidaAntMin>0? "<span class='chip chip--info'>".$salidaAntMin." min</span>": '—') ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
      <tfoot class="table__foot">
        <tr>
          <td colspan="7" class="table__foot-label">
            Total horas efectivas (<?= (int)$diasContados ?> día<?= $diasContados===1?'':'s' ?>):
          </td>
          <td class="table__foot-value"><?= hhmmss($totalEfectiva) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="note">
    * Tardanza aplica tolerancia de 10 minutos sobre la hora de ingreso programada.<br>
    * Salida anticipada se calcula contra la hora de salida programada.
  </p>
</main>

<!-- Modal para mapa -->
<div id="modal" class="modal-overlay" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal__title" id="modal-title">Ubicación</div>
    <div class="modal__body" id="modal-body">
      <iframe id="map-frame" title="Mapa" style="width:100%;height:min(60vh,480px);border:0;border-radius:8px" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="about:blank"></iframe>
    </div>
    <div class="modal__actions">
      <button type="button" id="modal-ok" class="modal__btn">Aceptar</button>
    </div>
  </div>
</div>

<script>
(function(){
  const MODAL = document.getElementById('modal');
  const MODAL_OK = document.getElementById('modal-ok');
  const MODAL_TITLE = document.getElementById('modal-title');
  const FRAME = document.getElementById('map-frame');
  function openMap(lat, lon){ if(!lat||!lon) return; const url = 'https://www.google.com/maps?q=' + encodeURIComponent(lat+','+lon) + '&z=17&output=embed'; FRAME.src=url; MODAL_TITLE.textContent='Ubicación ('+lat+', '+lon+')'; MODAL.removeAttribute('hidden'); MODAL_OK.focus(); }
  function closeModal(){ MODAL.setAttribute('hidden',''); FRAME.src='about:blank'; }
  MODAL_OK.addEventListener('click', closeModal); MODAL.addEventListener('click', (e)=>{ if(e.target===MODAL) closeModal(); }); document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && !MODAL.hasAttribute('hidden')) closeModal(); });
  document.addEventListener('click', function(e){ const a = e.target.closest('a.js-map'); if(!a) return; if (e.ctrlKey || e.metaKey || a.target === '_blank') return; e.preventDefault(); const lat=a.getAttribute('data-lat'); const lon=a.getAttribute('data-lon'); openMap(lat, lon); }, {passive:false});
})();
</script>

