<?php
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function linkMap($lat,$lon,$label='Mapa'){
  if(!$lat||!$lon||$lat==='0.000000'||$lon==='0.000000') return '';
  $u='https://www.google.com/maps/search/?api=1&query='.rawurlencode($lat.','.$lon);
  $uEsc=h($u); $latEsc=h($lat); $lonEsc=h($lon); $lab=h($label);
  return ' <a class="js-map" target="_blank" rel="noopener noreferrer" href="'.$uEsc.'" data-lat="'.$latEsc.'" data-lon="'.$lonEsc.'">['.$lab.']</a>';
}
?>
<main class="report">
  <h1 class="report__title">Reporte General de Timbres</h1>

  <form class="filter" method="get" novalidate>
    <input type="hidden" name="r" value="reporte_all">
    <label class="filter__field">Desde
      <input type="date" name="desde" value="<?= h($desde) ?>">
    </label>
    <label class="filter__field">Hasta
      <input type="date" name="hasta" value="<?= h($hasta) ?>">
    </label>
    <label class="filter__field">Nombre
      <input type="text" name="q" placeholder="Buscar por nombre" value="<?= h($q ?? '') ?>">
    </label>
    <label class="filter__field">Rol
      <select name="rol">
        <option value="">-- Todos --</option>
        <?php foreach (($rolesList ?? []) as $r): $sel = (!empty($rolSel) && strtoupper($rolSel)===strtoupper($r)) ? 'selected' : ''; ?>
          <option value="<?= h($r) ?>" <?= $sel ?>><?= h($r) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="filter__actions">
      <button class="btn btn--primary" type="submit">🔍 Filtrar</button>
      <a class="btn btn--excel"
         href="?r=reporte_all&amp;desde=<?= h($desde) ?>&amp;hasta=<?= h($hasta) ?>&amp;q=<?= h($q ?? '') ?>&amp;rol=<?= h($rolSel ?? '') ?>&amp;export=excel">
        📊 Exportar Excel
      </a>
    </div>
  </form>

  <div class="table-container">
    <table class="table">
      <thead class="table__head">
        <tr>
          <th>Fecha</th>
          <th>Usuario</th>
          <th>Rol</th>
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
      <tbody>
        <?php
          function secs($a,$b){ if(!$a||!$b) return 0; return $b->getTimestamp()-$a->getTimestamp(); }
          function hhmmss($s){ if($s<=0) return '00:00:00'; $h=intdiv($s,3600); $m=intdiv($s%3600,60); $ss=$s%60; return sprintf('%02d:%02d:%02d',$h,$m,$ss);} 
          foreach ($rows as $r):
            $fecha = $r['fecha'];
            $dtIn  = ($r['hora_ingreso']??null)      ? new DateTime($fecha.' '.$r['hora_ingreso']) : null;
            $dtSl  = ($r['hora_sl_almuerzo']??null)  ? new DateTime($fecha.' '.$r['hora_sl_almuerzo']) : null;
            $dtRt  = ($r['hora_rt_almuerzo']??null)  ? new DateTime($fecha.' '.$r['hora_rt_almuerzo']) : null;
            $dtOut = ($r['hora_salida']??null)       ? new DateTime($fecha.' '.$r['hora_salida']) : null;
            $secJornada  = ($dtIn && $dtOut) ? secs($dtIn,$dtOut) : 0;
            $secAlmuerzo = ($dtSl && $dtRt)  ? max(0, secs($dtSl,$dtRt)) : 0;
            $secEfectiva = max(0, $secJornada - $secAlmuerzo);
            $tardanzaMin = 0; $extraMin = 0;
            if (!empty($r['hora_prog_in']) && $dtIn) { $dtProgIn=new DateTime($fecha.' '.$r['hora_prog_in']); $dif=secs($dtProgIn,$dtIn); $tardanzaMin=max(0,intdiv($dif,60)-10); }
            if (!empty($r['hora_prog_out']) && $dtOut){ $dtProgOut=new DateTime($fecha.' '.$r['hora_prog_out']); $dif=secs($dtProgOut,$dtOut); $extraMin = $dif>0 ? intdiv($dif,60) : 0; }
        ?>
          <tr>
            <td><?= h($r['fecha']) ?></td>
            <td><?= h($r['usuario']) ?></td>
            <td><?= h($r['rol']) ?></td>
            <td>
              <?= h($r['hora_ingreso']) ?>
              <?php if(!empty($r['estado_ingreso'])): ?><span class="badge"><?= h($r['estado_ingreso']) ?></span><?php endif; ?><br>
              <?= h($r['dir_in']) ?><?= linkMap($r['lat_in'],$r['lon_in']) ?>
            </td>
            <td>
              <?= h($r['hora_sl_almuerzo']) ?>
              <?php if(!empty($r['estado_sl_alm'])): ?><span class="badge"><?= h($r['estado_sl_alm']) ?></span><?php endif; ?><br>
              <?= h($r['dir_sl']) ?><?= linkMap($r['lat_sl'],$r['lon_sl']) ?>
            </td>
            <td>
              <?= h($r['hora_rt_almuerzo']) ?>
              <?php if(!empty($r['estado_rt_alm'])): ?><span class="badge"><?= h($r['estado_rt_alm']) ?></span><?php endif; ?><br>
              <?= h($r['dir_rt']) ?><?= linkMap($r['lat_rt'],$r['lon_rt']) ?>
            </td>
            <td>
              <?= h($r['hora_salida']) ?>
              <?php if(!empty($r['estado_salida'])): ?><span class="badge"><?= h($r['estado_salida']) ?></span><?php endif; ?><br>
              <?= h($r['dir_out']) ?><?= linkMap($r['lat_out'],$r['lon_out']) ?>
            </td>
            <td><?= hhmmss($secJornada) ?></td>
            <td><?= hhmmss($secAlmuerzo) ?></td>
            <td><?= hhmmss($secEfectiva) ?></td>
            <td><?= $tardanzaMin>0 ? "<span class='chip chip--warn'>".$tardanzaMin." min</span>" : '—' ?></td>
            <td><?= $extraMin>0 ? "<span class='chip chip--info'>".$extraMin." min</span>" : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
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
</main>

<script>
(function(){
  const MODAL = document.getElementById('modal');
  const MODAL_OK = document.getElementById('modal-ok');
  const TITLE = document.getElementById('modal-title');
  const FRAME = document.getElementById('map-frame');
  function openMap(lat, lon){
    if(!lat||!lon) return; const url = 'https://www.google.com/maps?q=' + encodeURIComponent(lat+','+lon) + '&z=17&output=embed';
    FRAME.src = url; TITLE.textContent = 'Ubicación ('+lat+', '+lon+')'; MODAL.removeAttribute('hidden'); MODAL_OK.focus();
  }
  function closeM(){ MODAL.setAttribute('hidden',''); FRAME.src='about:blank'; }
  MODAL_OK.addEventListener('click', closeM); MODAL.addEventListener('click', e=>{ if(e.target===MODAL) closeM(); });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !MODAL.hasAttribute('hidden')) closeM(); });
  document.addEventListener('click', function(e){ const a=e.target.closest('a.js-map'); if(!a) return; if(e.ctrlKey||e.metaKey||a.target==='_blank') return; e.preventDefault(); openMap(a.dataset.lat,a.dataset.lon); }, {passive:false});
})();
</script>
