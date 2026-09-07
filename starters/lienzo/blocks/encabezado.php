<?php /** Encabezado de página. $b: title, text */ declare(strict_types=1); ?>
<div class="lz-container"><h1><?= lz_inline((string) $b['title']) ?></h1><?php if (trim((string) $b['text']) !== ''): ?><p class="lz-lead"><?= lz_inline((string) $b['text']) ?></p><?php endif; ?></div>
