<?php /** Tarjetas de noticias. $b: source, count, items, columns */ declare(strict_types=1);
$cards = [];
if ((string) $b['source'] === 'manual' || (cms_is_demo() && !cms_items('noticias'))) {
    foreach ((array) $b['items'] as $l) { [$k, $t2, $img, $url, $lay] = ad_split((string) $l, 5); if ($t2 !== '') $cards[] = ['kicker' => $k, 'title' => $t2, 'image' => $img, 'url' => $url, 'layout' => $lay ?: 'bottom']; }
} else {
    $n = max(1, (int) $b['count']);
    foreach (array_slice(cms_items('noticias'), 0, $n) as $p) {
        $cards[] = ['kicker' => (string) cms_f($p, 'kicker', $lang), 'title' => (string) cms_f($p, 'title', $lang), 'image' => (string) ($p['image'] ?? ''), 'url' => cms_url('item:noticias', $lang, (string) $p['slug']), 'layout' => (string) ($p['layout'] ?? 'bottom')];
    }
}
if (!$cards) return;
$cols = in_array((string) $b['columns'], ['2', '3'], true) ? (int) $b['columns'] : 2;
?>
<div class="ad-container">
  <div class="ad-cards ad-cards-<?= $cols ?>">
<?php foreach ($cards as $c): $lay = $c['layout'] === 'left' ? 'left' : 'bottom'; $href = cms_e(ad_url($c['url'], $lang)); ?>
    <article class="ad-card ad-card-<?= $lay ?>">
<?php if ($c['image'] !== '' && $lay === 'left'): ?>      <a class="ad-card-img" href="<?= $href ?>"><?= cms_picture($c['image'], $c['title']) ?></a>
<?php endif; ?>
      <div class="ad-card-body">
<?php if ($c['kicker'] !== ''): ?>        <span class="ad-card-kicker"><?= cms_e($c['kicker']) ?></span>
<?php endif; ?>
        <h3 class="ad-card-title"><a href="<?= $href ?>"><?= ad_inline($c['title']) ?></a></h3>
        <a class="ad-card-more" href="<?= $href ?>" aria-label="<?= cms_e(cms_t('read_more', $lang, 'Leer más')) ?>"><?= ad_svg('arrow') ?></a>
      </div>
<?php if ($c['image'] !== '' && $lay === 'bottom'): ?>      <a class="ad-card-img" href="<?= $href ?>"><?= cms_picture($c['image'], $c['title']) ?></a>
<?php endif; ?>
    </article>
<?php endforeach; ?>
  </div>
</div>
