<?php /** Detalle de un proyecto. Variables: $lang, $S, $t, $page, $item, $def */ declare(strict_types=1);
$catSlug = cms_item_category('proyectos', $item);
$cat = $catSlug !== '' ? cms_category('proyectos', $catSlug) : null;
$siblings = $catSlug !== '' ? array_values(cms_items_in_category('proyectos', $catSlug)) : [];
$siblings = array_values(array_filter($siblings, fn($p) => $p['slug'] !== $item['slug']));
?>
<section class="sec ad-etiqueta"><div class="ad-container">
<?php if ($cat): ?>  <p class="ad-kicker ad-center"><a href="<?= cms_e(cms_url('cat:proyectos', $lang, $catSlug)) ?>"><?= cms_e(cms_category_label($cat, $lang)) ?></a></p>
<?php endif; ?>
  <h1 class="ad-label"><?= cms_e(cms_f($item, 'title', $lang)) ?></h1>
</div></section>
<section class="sec ad-texto-imagen"><div class="ad-container ad-project">
  <div class="ad-project-img"><?= cms_picture((string) ($item['image'] ?? ''), (string) cms_f($item, 'title', $lang), '', true) ?></div>
  <div class="ad-project-text ad-prose">
    <?= cms_content((string) cms_f($item, 'body', $lang)) ?>
<?php if (!empty($item['client'])): ?>    <p class="ad-muted"><?= cms_e($item['client']) ?></p>
<?php endif; ?>
<?php if (!empty($item['url'])): ?>    <p><a class="ad-btn" href="<?= cms_e($item['url']) ?>" target="_blank" rel="noopener"><?= cms_e($t('gallery_visit', 'Visitar sitio')) ?> →</a></p>
<?php endif; ?>
  </div>
</div></section>
<?php if ($siblings): ?>
<section class="sec ad-galeria sec-bg-darker"><div class="ad-container">
  <h2 class="ad-label"><?= cms_e($t('category_projects', 'Más proyectos')) ?></h2>
  <div class="ad-gal ad-gal-grid ad-gal-4 ad-ratio-4x3">
<?php foreach (array_slice($siblings, 0, 8) as $p): ?>
    <a class="ad-gal-item" href="<?= cms_e(cms_url('item:proyectos', $lang, (string) $p['slug'])) ?>"><?= cms_picture(ad_thumb($p), (string) cms_f($p, 'title', $lang)) ?><span class="ad-gal-cap"><span><?= cms_e(cms_f($p, 'title', $lang)) ?></span></span></a>
<?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>
