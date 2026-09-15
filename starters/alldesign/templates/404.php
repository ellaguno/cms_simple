<?php /** Página no encontrada. Variables: $lang, $S, $t, $page */ declare(strict_types=1); ?>
<section class="sec ad-etiqueta"><div class="ad-container ad-empty-page">
  <h1 class="ad-label"><?= cms_e($t('not_found_title', 'Página no encontrada')) ?></h1>
  <p class="ad-center ad-muted"><?= cms_e($t('not_found_text', 'La dirección no existe o cambió.')) ?></p>
  <p class="ad-center"><a class="ad-btn" href="<?= cms_url('home', $lang) ?>"><?= cms_e($t('go_home', 'Ir al inicio')) ?></a></p>
</div></section>
