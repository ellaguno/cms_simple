<?php /** Detalle de entrada. Variables: $lang, $S, $t, $page, $item, $def */ declare(strict_types=1);
$tags = (array) cms_f($item, 'tags', $lang, []); $cat = cms_f($item, 'category', $lang); $blog = cms_url('list:posts', $lang);
$related = array_filter(cms_items('posts'), fn($p) => $p['slug'] !== $item['slug'] && (cms_f($p, 'category', $lang) === $cat || array_intersect($tags, (array) cms_f($p, 'tags', $lang, []))));
$related = array_slice($related, 0, 3, true); ?>
<article class="wrap post">
  <p class="muted"><?php if ($cat): ?><a href="<?= $blog ?>?cat=<?= rawurlencode($cat) ?>"><?= cms_e($cat) ?></a> · <?php endif; ?><?= cms_date($item['date'] ?? '', $lang) ?></p>
  <h1><?= cms_e(cms_f($item, 'title', $lang)) ?></h1>
  <?php if (!empty($item['image'])): ?><div class="post-img"><?= cms_picture((string) $item['image'], (string) cms_f($item, 'title', $lang), '', true) ?></div><?php endif; ?>
  <div class="content"><?= cms_content((string) cms_f($item, 'body', $lang)) ?></div>
  <?php if ($tags): ?><p class="tags"><?= cms_e($t('tags_label', 'Etiquetas')) ?>: <?php foreach ($tags as $tg): ?><a href="<?= $blog ?>?tag=<?= rawurlencode($tg) ?>"><?= cms_e($tg) ?></a> <?php endforeach; ?></p><?php endif; ?>
</article>
<?php if ($related): ?>
<section class="wrap">
  <h2><?= cms_e($t('recent_posts', 'También te puede interesar')) ?></h2>
  <div class="grid">
<?php foreach ($related as $p): $u = cms_url('item:posts', $lang, $p['slug']); ?>
    <article class="card"><?php if (!empty($p['image'])): ?><a href="<?= $u ?>"><?= cms_picture((string) $p['image'], '') ?></a><?php endif; ?><div class="card-b"><h3><a href="<?= $u ?>"><?= cms_e(cms_f($p, 'title', $lang)) ?></a></h3></div></article>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
