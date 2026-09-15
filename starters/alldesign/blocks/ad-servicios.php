<?php /** Servicios con icono. $b: items, icon_size */ declare(strict_types=1);
$items = []; foreach ((array) $b['items'] as $l) { [$ic, $t2, $tx] = ad_split((string) $l, 3); if ($t2 !== '') $items[] = [$ic, $t2, $tx]; }
if (!$items) return;
$sz = in_array((string) $b['icon_size'], ['s', 'm', 'l'], true) ? (string) $b['icon_size'] : 'm';
?>
<div class="ad-container">
  <div class="ad-services ad-icon-<?= $sz ?>">
<?php foreach ($items as [$ic, $t2, $tx]): ?>
    <div class="ad-service">
      <div class="ad-service-icon"><?php if ($ic !== '' && preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $ic)): ?><img src="<?= cms_e(cms_img($ic)) ?>" alt="" loading="lazy"><?php elseif ($ic !== ''): ?><span><?= cms_e($ic) ?></span><?php endif; ?></div>
      <div class="ad-service-body">
        <h3 class="ad-h-light"><?= ad_inline($t2) ?></h3>
        <div class="ad-prose"><?= ad_rich($tx) ?></div>
      </div>
    </div>
<?php endforeach; ?>
  </div>
</div>
