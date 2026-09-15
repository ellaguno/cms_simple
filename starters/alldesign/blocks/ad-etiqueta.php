<?php /** Etiqueta espaciada. $b: text, tag */ declare(strict_types=1);
$tag = in_array((string) $b['tag'], ['h1', 'h2', 'p'], true) ? (string) $b['tag'] : 'h2';
if (trim((string) $b['text']) === '') return;
?>
<div class="ad-container"><<?= $tag ?> class="ad-label"><?= cms_e((string) $b['text']) ?></<?= $tag ?>></div>
