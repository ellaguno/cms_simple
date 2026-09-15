<?php /** Banda con círculo. $b: image, text, url, height, size, color, text_color */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
if (trim((string) $b['image']) === '') return;
$h = in_array((string) $b['height'], ['s', 'm', 'l'], true) ? (string) $b['height'] : 'm';
$sz = in_array((string) $b['size'], ['s', 'm', 'l'], true) ? (string) $b['size'] : 'm';
$tag = trim((string) $b['url']) !== '' ? 'a' : 'div';
$style = '--ag-band-img:url(\'' . cms_e(cms_img((string) $b['image'])) . '\')';
if (preg_match('/^#[0-9a-f]{3,8}$/i', (string) $b['color'])) $style .= ';--ag-circle:' . $b['color'];
?>
<div class="ag-band-in ag-band-h-<?= $h ?>" style="<?= $style ?>">
  <<?= $tag ?> class="ag-circle ag-circle-<?= $sz ?> ag-circle-<?= (string) $b['text_color'] === 'dark' ? 'dark' : 'light' ?>"<?= $tag === 'a' ? ' href="' . cms_e((string) $b['url']) . '"' : '' ?>><span><?= ag_inline((string) $b['text']) ?></span></<?= $tag ?>>
</div>
