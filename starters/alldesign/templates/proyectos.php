<?php /** Índice de proyectos (y subsección por categoría: $page['category']). Variables: $lang, $S, $t, $page */ declare(strict_types=1);
$cat = $page['category'] ?? null;
$items = $cat ? array_values(cms_items_in_category('proyectos', (string) $cat['slug'])) : array_values(cms_items('proyectos'));
$pg = cms_paginate($items, 24);
$title = $cat ? (string) ($cat['label_text'] ?? cms_category_label($cat, $lang)) : (string) $t('all_projects', 'Portafolio');
?>
<section class="sec ad-etiqueta"><div class="ad-container"><h1 class="ad-label"><?= cms_e($title) ?></h1>
<?php if ($cat && cms_category_text($cat, 'desc', $lang) !== ''): ?><p class="ad-center ad-muted"><?= cms_e(cms_category_text($cat, 'desc', $lang)) ?></p><?php endif; ?>
<?php if (!$cat && cms_categories('proyectos')): ?>
<p class="ad-cat-nav"><?php foreach (cms_categories('proyectos') as $slug => $c): ?><a href="<?= cms_e(cms_url('cat:proyectos', $lang, (string) $slug)) ?>"><?= cms_e(cms_category_label($c, $lang)) ?></a><?php endforeach; ?></p>
<?php endif; ?>
</div></section>
<section class="sec ad-galeria">
<?php if (!$pg['items']): ?>
  <div class="ad-container"><p class="ad-empty"><?= cms_e($t('projects_empty', 'Todavía no hay proyectos en esta categoría.')) ?></p></div>
<?php else: ?>
  <div class="ad-container"><div class="ad-gal ad-gal-grid ad-gal-3 ad-ratio-4x3">
<?php foreach ($pg['items'] as $p): ?>
    <a class="ad-gal-item" href="<?= cms_e(cms_url('item:proyectos', $lang, (string) $p['slug'])) ?>"><?= cms_picture(ad_thumb($p), (string) cms_f($p, 'title', $lang)) ?><span class="ad-gal-cap"><span><?= cms_e(cms_f($p, 'title', $lang)) ?></span></span></a>
<?php endforeach; ?>
  </div><?= cms_pager($pg, $cat ? cms_url('cat:proyectos', $lang, (string) $cat['slug']) : cms_url('list:proyectos', $lang), []) ?></div>
<?php endif; ?>
</section>
