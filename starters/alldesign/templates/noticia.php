<?php /** Una noticia (PyMEs, Feng Shui, premios…). Variables: $lang, $S, $t, $page, $item, $def */ declare(strict_types=1);
$hero = (string) ($item['hero'] ?? '');
?>
<?php if ($hero !== ''): ?>
<section class="sec ag-hero"><div class="ag-hero-in ag-hero-short ag-pos-center"><div class="ag-slide is-active"><?= cms_picture($hero, (string) cms_f($item, 'title', $lang), 'class="ag-slide-img"', true) ?></div><?= function_exists('ag_share_tab') ? ag_share_tab($lang, cms_t('share_label', $lang, 'Compartir esta página')) : '' ?></div></section>
<?php endif; ?>
<section class="sec ad-texto"><div class="ad-container ad-article">
  <div class="ad-article-head">
    <span class="ad-news-icon" aria-hidden="true"></span>
    <div>
<?php if (!empty($item['date'])): ?>      <span class="ad-kicker"><?= cms_e(mb_strtoupper(cms_date((string) $item['date'], $lang))) ?><?= cms_f($item, 'kicker', $lang) ? ' · ' . cms_e(cms_f($item, 'kicker', $lang)) : '' ?></span>
<?php elseif (cms_f($item, 'kicker', $lang)): ?>      <span class="ad-kicker"><?= cms_e(cms_f($item, 'kicker', $lang)) ?></span>
<?php endif; ?>
      <h1 class="ad-h-light"><?= cms_e(cms_f($item, 'title', $lang)) ?></h1>
    </div>
  </div>
  <div class="ad-article-grid">
    <div class="ad-prose"><?= cms_content((string) cms_f($item, 'body', $lang)) ?></div>
<?php if (!empty($item['image']) && $hero === ''): ?>    <div class="ad-article-img"><?= cms_picture((string) $item['image'], (string) cms_f($item, 'title', $lang)) ?></div>
<?php endif; ?>
  </div>
</div></section>
