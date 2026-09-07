<?php declare(strict_types=1); ?>
<main class="lz-empty"><h1><?= cms_e($t('not_found_title', 'Página no encontrada')) ?></h1><p><?= cms_e($t('not_found_text', 'La dirección no existe o cambió.')) ?></p><p><a class="lz-btn lz-btn-primary" href="<?= cms_url('home', $lang) ?>"><?= cms_e($t('go_home', 'Ir al inicio')) ?></a></p></main>
