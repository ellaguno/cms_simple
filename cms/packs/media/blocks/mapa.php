<?php /** Mapa OpenStreetMap. $b: title, lat, lng, label, zoom, height */ declare(strict_types=1);
$lat = (float) str_replace(',', '.', (string) $b['lat']); $lng = (float) str_replace(',', '.', (string) $b['lng']);
if (!$lat && !$lng) return; ?>
<div class="<?= cms_e(cms_block_class('container')) ?>">
  <?= cms_block_header((string) $b['title']) ?>
  <div class="med-map med-map-<?= cms_e((string) $b['height']) ?>" data-med-map data-lat="<?= $lat ?>" data-lng="<?= $lng ?>" data-zoom="<?= (int) $b['zoom'] ?>" data-label="<?= cms_e((string) $b['label']) ?>">
    <a href="https://www.openstreetmap.org/?mlat=<?= $lat ?>&mlon=<?= $lng ?>#map=<?= (int) $b['zoom'] ?>/<?= $lat ?>/<?= $lng ?>" target="_blank" rel="noopener">Ver en OpenStreetMap</a>
  </div>
</div>
