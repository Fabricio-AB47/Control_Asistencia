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
  <h1 class="report__title">Reporte Docente (Personal)</h1>

  <form class="filter" method="get" novalidate>
    <input type="hidden" name="r" value="reporte">
    <input type="hidden" name="mod" value="<?= h($module ?? 'docente') ?>">
    <label class="filter__field">Desde
      <input type="date" name="desde" value="<?= h($desde) ?>">
    </label>
    <label class="filter__field">Hasta
      <input type="date" name="hasta" value="<?= h($hasta) ?>">
    </label>
    <div class="filter__actions">
      <button class="btn btn--primary" type="submit">🔍 Filtrar</button>
      <a class="btn btn--excel"
         href="?r=reporte&amp;mod=<?= h($module ?? 'docente') ?>&amp;desde=<?= h($desde) ?>&amp;hasta=<?= h($hasta) ?>&amp;export=excel">
        📊 Exportar Excel
      </a>
    </div>
  </form>

  <div class="table-container">
    <table class="table">
      <thead class="table__head">
        <tr>
          <th>Fecha</th>
          <th>Ingresos (máx 3)</th>
          <th>Fines (máx 3)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($rows ?? []) as $r): ?>
          <?php
            $ingList = array_filter(explode(';;', (string)($r['ingresos'] ?? '')));
            $outList = array_filter(explode(';;', (string)($r['salidas'] ?? '')));
            $fmt = function($arr){
              if (empty($arr)) return '-';
              $parts = [];
              foreach ($arr as $item) {
                [$t,$la,$lo,$di] = array_pad(explode('|',$item),4,'');
                $parts[] = h($t) . '<br>' . h($di) . linkMap($la,$lo);
              }
              return '<ul class="list-unstyled"><li>'.implode('</li><li>',$parts).'</li></ul>';
            };
          ?>
          <tr>
            <td><?= h($r['fecha']) ?></td>
            <td><?= $fmt($ingList) ?></td>
            <td><?= $fmt($outList) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

