<?php /** Listado de entradas con búsqueda, categoría, etiqueta y paginación. Variables: $lang, $S, $t, $page, $type, $def */ declare(strict_types=1);
$q = trim((string) ($_GET['q'] ?? '')); $tag = trim((string) ($_GET['tag'] ?? '')); $cat = trim((string) ($_GET['cat'] ?? ''));
$posts = cms_items('posts');
if ($q !== '') { $n = mb_strtolower($q); $posts = array_filter($posts, fn($p) => mb_strpos(mb_strtolower(cms_f($p, 'title', $lang) . ' ' . cms_f($p, 'excerpt', $lang) . ' ' . strip_tags((string) cms_f($p, 'body', $lang))), $n) !== false); }
if ($tag !== '') $posts = array_filter($posts, fn($p) => in_array($tag, (array) cms_f($p, 'tags', $lang, []), true));
if ($cat !== '') $posts = array_filter($posts, fn($p) => cms_f($p, 'category', $lang) === $cat);
$per = 9; $pg = max(1, (int) ($_GET['pg'] ?? 1)); $pages = max(1, (int) ceil(count($posts) / $per));
$posts = array_slice($posts, ($pg - 1) * $per, $per, true);
$blog = cms_url('list:posts', $lang); ?>
<section class="wrap">
  <h1><?= cms_e($t('posts_title', 'Blog')) ?></h1>
  <form class="search" action="<?= $blog ?>" method="get"><input type="search" name="q" value="<?= cms_e($q) ?>" placeholder="<?= cms_e($t('search_placeholder', 'Buscar…')) ?>"><button type="submit"><?= cms_e($t('search_label', 'Buscar')) ?></button></form>
<?php if ($q || $tag || $cat): ?><p class="muted"><?= cms_e($t('blog_filter_results', 'Resultados para')) ?> <strong><?= cms_e($q ?: $tag ?: $cat) ?></strong> · <a href="<?= $blog ?>"><?= cms_e($t('blog_filter_clear', 'ver todo')) ?></a></p><?php endif; ?>
<?php if (!$posts): ?><p class="muted"><?= cms_e($t('blog_empty', 'Aún no hay entradas.')) ?></p><?php endif; ?>
  <div class="grid">
<?php foreach ($posts as $p): $u = cms_url('item:posts', $lang, $p['slug']); ?>
    <article class="card">
      <?php if (!empty($p['image'])): ?><a href="<?= $u ?>"><?= cms_picture((string) $p['image'], (string) cms_f($p, 'title', $lang)) ?></a><?php endif; ?>
      <div class="card-b">
        <small><a href="<?= $blog ?>?cat=<?= rawurlencode(cms_f($p, 'category', $lang)) ?>"><?= cms_e(cms_f($p, 'category', $lang)) ?></a> · <?= cms_date($p['date'] ?? '', $lang) ?></small>
        <h3><a href="<?= $u ?>"><?= cms_e(cms_f($p, 'title', $lang)) ?></a></h3>
        <p><?= cms_e(cms_f($p, 'excerpt', $lang)) ?></p>
        <a class="more" href="<?= $u ?>"><?= cms_e($t('read_more', 'Leer más')) ?> →</a>
      </div>
    </article>
<?php endforeach; ?>
  </div>
<?php if ($pages > 1): ?>
  <nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): $qs = http_build_query(array_filter(['q' => $q, 'tag' => $tag, 'cat' => $cat, 'pg' => $i > 1 ? $i : null])); ?><a href="<?= $blog . ($qs ? '?' . $qs : '') ?>"<?= $i === $pg ? ' class="on"' : '' ?>><?= $i ?></a><?php endfor; ?></nav>
<?php endif; ?>
</section>
