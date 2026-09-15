<?php /** Portada con pase de imágenes. $b: images, height, interval, title, subtitle, position, overlay, share */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$imgs = [];
foreach ((array) $b['images'] as $l) { [$src, $alt] = ag_split((string) $l, 2); if ($src !== '') $imgs[] = [$src, $alt]; }
if (!$imgs) return;
$h = in_array((string) $b['height'], ['full', 'tall', 'medium', 'short'], true) ? (string) $b['height'] : 'medium';
$pos = in_array((string) $b['position'], ['center', 'top', 'bottom'], true) ? (string) $b['position'] : 'center';
$multi = count($imgs) > 1;
$hasText = trim((string) $b['title']) !== '' || trim((string) $b['subtitle']) !== '';
$ov = $hasText ? max(0, min(90, (int) $b['overlay'])) : 0;
?>
<div class="ag-hero-in ag-hero-<?= $h ?> ag-pos-<?= $pos ?><?= $multi ? ' is-slideshow' : '' ?>"<?= $multi ? ' data-ag-slideshow data-interval="' . max(2, (int) $b['interval']) * 1000 . '"' : '' ?><?= $ov ? ' style="--ag-overlay:' . $ov / 100 . '"' : '' ?>>
<?php foreach ($imgs as $i => [$src, $alt]): ?>
  <div class="ag-slide<?= $i === 0 ? ' is-active' : '' ?>"><?= cms_picture($src, $alt !== '' ? $alt : strip_tags((string) $b['title']), 'class="ag-slide-img"', true) ?></div>
<?php endforeach; ?>
<?php if ($multi): ?>
  <button type="button" class="ag-slide-btn ag-slide-prev" data-ag-prev aria-label="<?= $lang === 'en' ? 'Previous' : 'Anterior' ?>"><?= ag_svg('chevron-l') ?></button>
  <button type="button" class="ag-slide-btn ag-slide-next" data-ag-next aria-label="<?= $lang === 'en' ? 'Next' : 'Siguiente' ?>"><?= ag_svg('chevron-r') ?></button>
  <div class="ag-slide-dots"><?php foreach ($imgs as $i => $_): ?><button type="button"<?= $i === 0 ? ' class="is-active"' : '' ?> data-ag-dot="<?= $i ?>" aria-label="<?= $i + 1 ?>"></button><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($hasText): ?>
  <div class="ag-hero-text"><?php if (trim((string) $b['title']) !== ''): ?><h1><?= ag_inline((string) $b['title']) ?></h1><?php endif; ?><?php if (trim((string) $b['subtitle']) !== ''): ?><p><?= ag_inline((string) $b['subtitle']) ?></p><?php endif; ?></div>
<?php endif; ?>
<?php if (!empty($b['share'])) echo ag_share_tab($lang); ?>
</div>
