<?php /** Índice de noticias. Variables: $lang, $S, $t, $page */ declare(strict_types=1);
$pg = cms_paginate(array_values(cms_items('noticias')), 12);
?>
<section class="sec ad-etiqueta"><div class="ad-container"><h1 class="ad-label"><?= cms_e(cms_config('types')['noticias']['label'] ?? 'Noticias') ?></h1></div></section>
<section class="sec ad-tarjetas"><div class="ad-container"><div class="ad-cards ad-cards-2">
<?php foreach ($pg['items'] as $p): $href = cms_e(cms_url('item:noticias', $lang, (string) $p['slug'])); $lay = ($p['layout'] ?? 'bottom') === 'left' ? 'left' : 'bottom'; ?>
  <article class="ad-card ad-card-<?= $lay ?>">
<?php if (!empty($p['image']) && $lay === 'left'): ?>    <a class="ad-card-img" href="<?= $href ?>"><?= cms_picture((string) $p['image'], (string) cms_f($p, 'title', $lang)) ?></a>
<?php endif; ?>
    <div class="ad-card-body"><?php if (cms_f($p, 'kicker', $lang)): ?><span class="ad-card-kicker"><?= cms_e(cms_f($p, 'kicker', $lang)) ?></span><?php endif; ?><h2 class="ad-card-title"><a href="<?= $href ?>"><?= cms_e(cms_f($p, 'title', $lang)) ?></a></h2><a class="ad-card-more" href="<?= $href ?>" aria-label="<?= cms_e($t('read_more', 'Leer más')) ?>"><?= ad_svg('arrow') ?></a></div>
<?php if (!empty($p['image']) && $lay === 'bottom'): ?>    <a class="ad-card-img" href="<?= $href ?>"><?= cms_picture((string) $p['image'], (string) cms_f($p, 'title', $lang)) ?></a>
<?php endif; ?>
  </article>
<?php endforeach; ?>
</div><?= cms_pager($pg, cms_url('list:noticias', $lang), []) ?></div></section>
