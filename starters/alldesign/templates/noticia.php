<?php /** Un artículo (PyMEs, Feng Shui, premios…): fondo claro, imagen arriba, fecha y etiqueta, título con icono y texto. Variables: $lang, $S, $t, $page, $item, $def */ declare(strict_types=1);
$img = (string) (($item['hero'] ?? '') ?: ($item['image'] ?? ''));
$date = (string) ($item['date'] ?? '');
$tag = (string) cms_f($item, 'tag', $lang);
?>
<section class="sec sec-bg-light ad-article-light"><div class="ad-container ad-article">
<?php if ($img !== ''): ?>
  <div class="ad-article-hero"><?= cms_picture($img, (string) cms_f($item, 'title', $lang), '', true) ?></div>
<?php endif; ?>
<?php if ($date !== '' || $tag !== ''): ?>
  <div class="ad-article-meta"><span><?= $date !== '' ? cms_e(mb_strtoupper(cms_date($date, $lang))) : '' ?></span><span><?= cms_e($tag) ?></span></div>
<?php endif; ?>
  <hr class="ad-article-rule">
  <h1 class="ad-article-title"><img class="ad-article-icon" src="<?= cms_e(cms_img('icono-noticia.png')) ?>" alt="" width="27" height="32"><?= cms_e(cms_f($item, 'title', $lang)) ?></h1>
  <div class="ad-prose ad-article-body"><?= cms_content((string) cms_f($item, 'body', $lang)) ?></div>
</div></section>
