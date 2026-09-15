<?php /** Portada mientras no exista la página "inicio" del constructor. */ declare(strict_types=1); ?>
<div class="ad-container ad-empty-page"><h1 class="ad-h-light"><?= cms_e($S['site_name'] ?? 'All Design') ?></h1><p>Todavía no hay portada. Crea en el panel una página con la URL <strong>inicio</strong>.</p><p><a class="ad-btn" href="<?= CMS_BASE ?>/admin/">Entrar al panel</a></p></div>
