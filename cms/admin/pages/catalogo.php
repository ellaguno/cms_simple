<?php
/**
 * Catálogo: temas y paquetes de bloques que se pueden instalar desde un catálogo remoto (JSON por https),
 * y activación de los paquetes instalados. Descargar e instalar trae código que se ejecuta en el servidor,
 * así que cada instalación la pide la persona con un clic y solo se admiten catálogos https.
 */
declare(strict_types=1);

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    if ($action === 'install') {
        [$items,] = cms_registry_items();
        $id = (string) admin_post('item');
        if (!isset($items[$id])) admin_flash('Ese elemento ya no está en el catálogo.', 'err');
        else {
            [$ok, $msg] = cms_registry_install($items[$id]);
            admin_flash(($ok ? $items[$id]['label'] . ': ' : '') . $msg, $ok ? 'ok' : 'err');
            if ($ok && $items[$id]['kind'] === 'theme') admin_flash('Actívalo en Diseño cuando quieras probarlo.');
        }
    } elseif ($action === 'refresh') {
        cms_registry_items(true);
        admin_flash('Catálogo actualizado.');
    } elseif ($action === 'pack') {
        $name = (string) admin_post('pack');
        $on = admin_post('on') === '1';
        $available = cms_packs_available();
        if (!isset($available[$name])) admin_flash('Ese paquete no está instalado.', 'err');
        else {
            $S = cms_json_read(CMS_DATA . '/settings.json', []);
            $S['packs_on'] = array_values(array_diff((array) ($S['packs_on'] ?? []), [$name]));
            $S['packs_off'] = array_values(array_diff((array) ($S['packs_off'] ?? []), [$name]));
            if ($on) $S['packs_on'][] = $name; else $S['packs_off'][] = $name;
            if (cms_json_write(CMS_DATA . '/settings.json', $S)) admin_flash('Paquete ' . ($on ? 'activado' : 'desactivado') . ': ' . ($available[$name]['label'] ?? $name) . '.');
            else admin_flash('No se pudo guardar.', 'err');
        }
    }
    admin_redirect(admin_url('catalogo'));
}

[$items, $errors] = cms_registry_items();
$available = cms_packs_available();
$enabled = cms_packs_enabled();
$themesRemote = array_filter($items, fn($i) => $i['kind'] === 'theme');
$packsRemote = array_filter($items, fn($i) => $i['kind'] === 'pack');
admin_header('Catálogo', 'catalogo');

/** Ficha de un elemento del catálogo. */
function catalogo_card(array $it): void
{
    $id = $it['kind'] . ':' . $it['key'];
    ?>
    <article class="ad-theme<?= $it['installed'] ? ' is-on' : '' ?>">
<?php if ($it['screenshot'] !== ''): ?>      <img src="<?= cms_e($it['screenshot']) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
<?php else: ?>      <div class="ad-theme-noshot" aria-hidden="true"><?= cms_e(mb_strtoupper(mb_substr($it['label'], 0, 2))) ?></div>
<?php endif; ?>
      <div class="ad-theme-body">
        <div class="ad-style-head">
          <strong><?= cms_e($it['label']) ?></strong>
          <?php if ($it['update']): ?><span class="ad-pill warn">v<?= cms_e($it['version']) ?> disponible</span>
          <?php elseif ($it['installed']): ?><span class="ad-pill on">Instalado</span><?php endif; ?>
        </div>
        <p class="ad-help"><?= cms_e($it['desc']) ?></p>
        <p class="ad-help"><?= $it['version'] !== '' ? 'v' . cms_e($it['version']) : '' ?><?= $it['author'] !== '' ? ' · ' . cms_e($it['author']) : '' ?><?= $it['license'] !== '' ? ' · ' . cms_e($it['license']) : '' ?><?= $it['tags'] ? ' · ' . cms_e(implode(', ', array_slice($it['tags'], 0, 4))) : '' ?></p>
<?php if ($it['too_old']): ?>
        <p class="ad-help ad-theme-warn">Necesita cms_simple <?= cms_e((string) ($it['requires']['cms'] ?? '')) ?> o superior; tienes <?= CMS_VERSION ?>.</p>
<?php elseif ($it['core']): ?>
        <p class="ad-help">Viene con el núcleo: se actualiza al actualizar <code>cms/</code>.</p>
<?php else: ?>
        <form method="post" data-confirm="Se descargará e instalará «<?= cms_e($it['label']) ?>» desde <?= cms_e(parse_url($it['url'], PHP_URL_HOST) ?: '') ?>. Es programación que se ejecutará en tu servidor. ¿Seguir?">
          <?= admin_csrf_field() ?><input type="hidden" name="action" value="install"><input type="hidden" name="item" value="<?= cms_e($id) ?>">
          <button class="ad-btn ad-btn-sm<?= $it['installed'] && !$it['update'] ? ' ad-btn-light' : '' ?>" type="submit"><?= $it['update'] ? 'Actualizar' : ($it['installed'] ? 'Reinstalar' : 'Instalar') ?></button>
        </form>
<?php endif; ?>
      </div>
    </article>
<?php
}
?>
<p class="ad-actions">
  <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="refresh"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Actualizar el catálogo</button></form>
  <span class="ad-help">Lo que instales aquí se descarga de internet y se ejecuta en tu servidor. Instala solo de catálogos de confianza; los defines en <a href="<?= admin_url('settings') ?>">Ajustes</a>.</span>
</p>
<?php foreach ($errors as $url => $e): ?>
<div class="ad-flash err">No se pudo leer <?= cms_e($url) ?>: <?= cms_e($e) ?></div>
<?php endforeach; ?>

<h2 class="ad-subtitle">Temas</h2>
<?php if (!$themesRemote): ?><p class="ad-help">El catálogo no trae temas ahora mismo.</p><?php else: ?>
<div class="ad-themes"><?php foreach ($themesRemote as $it) catalogo_card($it); ?></div>
<p class="ad-help">Los temas se instalan en <code>themes/</code>. Para usar uno, ve a <a href="<?= admin_url('diseno') ?>">Diseño</a>.</p>
<?php endif; ?>

<h2 class="ad-subtitle">Paquetes de bloques del catálogo</h2>
<?php if (!$packsRemote): ?><p class="ad-help">El catálogo no trae paquetes ahora mismo.</p><?php else: ?>
<div class="ad-themes"><?php foreach ($packsRemote as $it) catalogo_card($it); ?></div>
<?php endif; ?>

<h2 class="ad-subtitle">Paquetes instalados</h2>
<p class="ad-help">Cada paquete aporta bloques y efectos al constructor. Desactivar uno no borra nada: las secciones que lo usen dejan de dibujarse hasta que lo vuelvas a activar.</p>
<table class="ad-table">
  <thead><tr><th>Paquete</th><th>Qué trae</th><th>Dónde está</th><th></th></tr></thead>
  <tbody>
<?php foreach ($available as $name => $p): $on = isset($enabled[$name]); ?>
    <tr>
      <td><strong><?= cms_e($p['label'] ?? $name) ?></strong><small class="ad-help"><?= cms_e($name) ?><?= !empty($p['version']) ? ' · v' . cms_e((string) $p['version']) : '' ?></small></td>
      <td><span class="ad-help"><?= cms_e(mb_strimwidth((string) ($p['desc'] ?? ''), 0, 130, '…')) ?></span></td>
      <td><span class="ad-help"><?= $p['core'] ? 'núcleo' : (strpos($p['dir'], CMS_SITE) === 0 ? 'tema' : 'sitio') ?></span></td>
      <td class="ad-row-actions">
        <span class="ad-pill <?= $on ? 'on' : '' ?>"><?= $on ? 'Activo' : 'Desactivado' ?></span>
        <form method="post" class="ad-inline">
          <?= admin_csrf_field() ?><input type="hidden" name="action" value="pack"><input type="hidden" name="pack" value="<?= cms_e($name) ?>"><input type="hidden" name="on" value="<?= $on ? '0' : '1' ?>">
          <button class="ad-btn ad-btn-sm <?= $on ? 'ad-btn-light' : '' ?>" type="submit"><?= $on ? 'Desactivar' : 'Activar' ?></button>
        </form>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php admin_footer();
