<?php /** Galería de proyectos. $b: source, category, count, images, layout, columns, ratio, captions, lightbox */ declare(strict_types=1);
$items = ad_gallery_items($b, $lang);
$lay = in_array((string) $b['layout'], ['grid', 'strip', 'masonry'], true) ? (string) $b['layout'] : 'grid';
$cols = in_array((string) $b['columns'], ['2', '3', '4', '5'], true) ? (int) $b['columns'] : 3;
$ratio = in_array((string) $b['ratio'], ['4x3', '1x1', '3x2', '16x9'], true) ? (string) $b['ratio'] : '4x3';
$lb = !empty($b['lightbox']);
$gid = 'g' . $sec['id'];
if (!$items) { if (cms_is_demo() || !empty($GLOBALS['cms_builder'])) echo '<div class="ad-container"><p class="ad-empty">' . cms_e(cms_t('projects_empty', $lang, 'Todavía no hay proyectos en esta categoría.')) . '</p></div>'; return; }
?>
<div class="<?= $lay === 'strip' ? 'ad-strip' : 'ad-container' ?>">
  <div class="ad-gal ad-gal-<?= $lay ?> ad-gal-<?= $cols ?> ad-ratio-<?= $ratio ?>">
<?php foreach ($items as $i => $it):
    $thumb = $it['thumb'] !== '' ? $it['thumb'] : $it['full'];
    $href = $lb ? cms_img($it['full']) : ($it['slug'] !== '' ? cms_url('item:proyectos', $lang, $it['slug']) : ($it['url'] ?: cms_img($it['full'])));
    $cid = 'v' . $sec['id'] . '-' . $i; ?>
    <a class="ad-gal-item<?= $lb ? ' glightbox' : '' ?>" href="<?= cms_e($href) ?>"<?= $lb ? ' data-gallery="' . $gid . '" data-glightbox="description: .' . $cid . '"' : '' ?>>
      <?= cms_picture($thumb, $it['title']) ?>
<?php if (!empty($b['captions']) && $it['title'] !== ''): ?>      <span class="ad-gal-cap"><span><?= cms_e($it['title']) ?></span></span>
<?php endif; ?>
    </a>
<?php if ($lb): ?>    <div class="glightbox-desc <?= $cid ?>" hidden><?= ad_gallery_caption($it, $lang) ?></div>
<?php endif; ?>
<?php endforeach; ?>
  </div>
</div>
