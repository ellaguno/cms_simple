<?php
declare(strict_types=1);
$S = cms_settings();
$types = cms_config('types');
$writable = is_writable(CMS_DATA) && is_writable(CMS_UPLOADS);
admin_header('Inicio', 'dashboard');
?>
<?php if (!$writable): ?>
<div class="ad-flash err">Las carpetas <code>data/</code> y <code>uploads/</code> deben tener permiso de escritura (755 o 775) para poder guardar cambios.</div>
<?php endif; ?>
<div class="ad-cards">
<?php foreach ($types as $k => $def): $all = cms_items($k, false); $pub = count(array_filter($all, fn($i) => ($i['status'] ?? '') === 'published')); ?>
  <a class="ad-card" href="<?= admin_url('content', ['type' => $k]) ?>"><strong><?= count($all) ?></strong><span><?= cms_e($def['label'] ?? $k) ?> · <?= $pub ?> publicados</span></a>
<?php endforeach; ?>
  <a class="ad-card" href="<?= admin_url('map') ?>"><strong>⌂</strong><span>Mapa del sitio</span></a>
  <a class="ad-card" href="<?= admin_url('manual') ?>"><strong>?</strong><span>Manual: cómo construir el sitio</span></a>
  <a class="ad-card" href="<?= admin_url('media') ?>"><strong><?= count(media_list()) ?></strong><span>Archivos en Medios</span></a>
  <a class="ad-card" href="<?= admin_url('menu') ?>"><strong><?= count(cms_menu(cms_default_lang())) ?></strong><span>Enlaces en el menú</span></a>
  <a class="ad-card" href="<?= admin_url('users') ?>"><strong><?= count(cms_users()) ?></strong><span>Usuarios del admin</span></a>
  <a class="ad-card" href="<?= admin_url('settings') ?>"><strong><?= cms_e(strtoupper(implode(' + ', cms_active_langs()))) ?></strong><span>Idiomas activos</span></a>
</div>
<div class="ad-grid2">
  <section class="ad-box">
    <h2>Acciones rápidas</h2>
    <div class="ad-quick"><?php foreach ($types as $k => $def): ?><a class="ad-btn" href="<?= admin_url('edit', ['type' => $k]) ?>">+ <?= cms_e($def['label_singular'] ?? ('Nuevo en ' . ($def['label'] ?? $k))) ?></a><?php endforeach; ?></div>
    <ul class="ad-list">
      <li><a href="<?= admin_url('settings') ?>">Cambiar correo, teléfono, WhatsApp y redes sociales</a></li>
      <li><a href="<?= admin_url('menu') ?>">Editar los enlaces del menú</a></li>
      <li><a href="<?= admin_url('strings') ?>">Revisar los textos fijos del sitio<?= count(cms_langs()) > 1 ? ' y sus traducciones' : '' ?></a></li>
      <li><a href="<?= admin_url('backup') ?>">Crear un respaldo del sitio</a></li>
      <li><a href="<?= CMS_BASE ?>/sitemap.xml" target="_blank" rel="noopener">Ver sitemap.xml</a></li>
    </ul>
  </section>
  <section class="ad-box">
    <h2>Últimos cambios</h2>
    <ul class="ad-list">
<?php
$recent = [];
foreach ($types as $k => $def) foreach (cms_items($k, false) as $it) $recent[] = [$it['updated'] ?? ($it['date'] ?? ''), $k, $it];
usort($recent, fn($a, $b) => strcmp((string) $b[0], (string) $a[0]));
foreach (array_slice($recent, 0, 8) as [$d, $k, $it]): ?>
      <li><a href="<?= admin_url('edit', ['type' => $k, 'slug' => $it['slug']]) ?>"><?= cms_e(cms_f($it, $types[$k]['title_field'] ?? 'title', cms_default_lang()) ?: $it['slug']) ?></a> <small><?= cms_e($types[$k]['label_singular'] ?? $k) ?> · <?= cms_e($d) ?> · <?= ($it['status'] ?? '') === 'published' ? 'publicado' : 'borrador' ?></small></li>
<?php endforeach; if (!$recent): ?>      <li class="ad-help">Aún no hay contenido.</li><?php endif; ?>
    </ul>
  </section>
</div>
<section class="ad-box">
  <h2>Cómo funciona</h2>
  <ul class="ad-list">
    <li><strong>Contenido</strong>: cada tipo (a la izquierda) tiene sus campos. Los textos largos se escriben en un editor visual; <?= count(cms_langs()) > 1 ? 'cada campo de texto tiene su versión por idioma y el conmutador muestra un idioma a la vez; si un idioma se deja vacío se muestra el predeterminado.' : 'lo que ves es lo que se publica.' ?></li>
    <li><strong>Medios</strong>: sube imágenes, PDF y video; copia su ruta o insértalos desde la Biblioteca del editor.</li>
    <li><strong>Menú</strong>: rutas relativas al idioma (<code>/blog</code>) o URLs completas.</li>
    <li><strong>Formulario</strong>: los mensajes llegan al correo de Ajustes y quedan en <code>data/mensajes.log</code>.</li>
  </ul>
</section>
<?php admin_footer();
