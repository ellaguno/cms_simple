<?php /** Imagen. $b: image, alt, caption, size, align */ declare(strict_types=1); if (empty($b['image'])) return;
$w = ['xs' => '320px', 's' => '480px', 'm' => '720px', 'l' => '960px'][(string) $b['size']] ?? '';
$style = ($w !== '' ? 'max-width:' . $w . ';' : '') . ($b['align'] === 'left' ? 'margin-left:0;' : ($b['align'] === 'right' ? 'margin-right:0;' : ''));
?>
<div class="lz-container"><figure class="lz-figure<?= $b['size'] === 'orig' ? ' lz-figure-orig' : '' ?><?= $b['align'] === 'left' ? ' lz-figure-left' : ($b['align'] === 'right' ? ' lz-figure-right' : '') ?>"<?= $style !== '' ? ' style="' . $style . '"' : '' ?>><?= cms_picture((string) $b['image'], (string) ($b['alt'] ?: $b['caption']), '', true) ?><?php if (trim((string) $b['caption']) !== ''): ?><figcaption><?= cms_e($b['caption']) ?></figcaption><?php endif; ?></figure></div>
