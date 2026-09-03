<?php declare(strict_types=1); ?>
<section class="wrap narrow center">
  <h1>404</h1>
  <p class="lead"><?= cms_e($t('not_found_text', 'La página que buscas no existe.')) ?></p>
  <a class="btn" href="<?= cms_url('home', $lang) ?>"><?= cms_e($t('go_home', 'Ir al inicio')) ?></a>
</section>
