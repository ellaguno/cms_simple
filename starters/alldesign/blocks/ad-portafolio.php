<?php /** Mosaico del portafolio. $b: items, columns, hover */ declare(strict_types=1);
$tiles = []; foreach ((array) $b['items'] as $l) { [$t2, $img, $url] = ad_split((string) $l, 3); if ($t2 !== '' || $img !== '') $tiles[] = [$t2, $img, $url]; }
if (!$tiles) return;
$cols = in_array((string) $b['columns'], ['2', '3', '4'], true) ? (int) $b['columns'] : 3;
?>
<div class="ad-container">
  <div class="ad-tiles ad-tiles-<?= $cols ?><?= (string) $b['hover'] === 'always' ? ' ad-tiles-always' : '' ?>">
<?php foreach ($tiles as [$t2, $img, $url]): ?>
    <a class="ad-tile" href="<?= cms_e(ad_url($url, $lang) ?: '#') ?>">
      <?= $img !== '' ? cms_picture($img, $t2) : '' ?>
      <span class="ad-tile-label"><span class="ad-tile-icon"><?= ad_svg('lines') ?></span><span><?= cms_e($t2) ?></span></span>
    </a>
<?php endforeach; ?>
  </div>
</div>
