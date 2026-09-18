<?php /** Imagen genérica. $b: image, alt, caption, link, size, align, radius */ declare(strict_types=1);
if (trim((string) $b['image']) === '') return;
$pic = cms_picture((string) $b['image'], (string) ($b['alt'] ?: $b['caption']));
$w = ['xs' => '320px', 's' => '480px', 'm' => '720px', 'l' => '960px'][(string) $b['size']] ?? '';
$style = ($w !== '' ? 'max-width:' . $w . ';' : '') . ($b['align'] === 'left' ? 'margin-left:0;' : ($b['align'] === 'right' ? 'margin-right:0;' : ''));
$link = trim((string) $b['link']);
?>
<div class="es-container"><figure class="es-figure<?= $b['size'] === 'orig' ? ' es-figure-orig' : '' ?><?= $b['align'] === 'left' ? ' es-figure-left' : ($b['align'] === 'right' ? ' es-figure-right' : '') ?><?= empty($b['radius']) ? ' es-figure-square' : '' ?>"<?= $style !== '' ? ' style="' . $style . '"' : '' ?>><?= $link !== '' ? '<a href="' . cms_e(preg_match('#^(https?:|mailto:|tel:|\#)#i', $link) ? $link : cms_menu_url($link, $lang)) . '">' . $pic . '</a>' : $pic ?><?php if (trim((string) $b['caption']) !== ''): ?><figcaption><?= cms_e($b['caption']) ?></figcaption><?php endif; ?></figure></div>
