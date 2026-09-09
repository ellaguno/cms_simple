<?php
/**
 * Actualizar: comprueba si hay una versión nueva del núcleo (cms/) y la instala, guardando la anterior por si acaso.
 * No toca el contenido, los archivos subidos ni el tema.
 */
declare(strict_types=1);

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    if ($action === 'check') {
        [$info, $err] = cms_update_check(true);
        if ($err !== '') admin_flash('No se pudo consultar: ' . $err, 'err');
        elseif ($info && $info['newer']) admin_flash('Hay una versión nueva: ' . $info['version'] . '.');
        else admin_flash('Tu instalación está al día (' . CMS_VERSION . ').');
    } elseif ($action === 'update') {
        [$info, $err] = cms_update_check(true);
        if (!$info) admin_flash('No se pudo consultar la versión publicada: ' . $err, 'err');
        else {
            [$ok, $msg] = cms_update_apply($info);
            admin_flash($msg, $ok ? 'ok' : 'err');
        }
    } elseif ($action === 'rollback') {
        [$ok, $msg] = cms_update_rollback((string) admin_post('backup'));
        admin_flash($msg, $ok ? 'ok' : 'err');
    } elseif ($action === 'forget') {
        $n = (string) admin_post('backup');
        if (preg_match('/^\.cms-anterior-[0-9-]+$/', $n) && cms_rmdir(CMS_ROOT . '/' . $n)) admin_flash('Copia eliminada.');
        else admin_flash('No se pudo eliminar esa copia.', 'err');
    }
    admin_redirect(admin_url('actualizar'));
}

[$info, $checkErr] = cms_update_check(false);
[$writable, $whyNot] = cms_update_writable();
$backups = cms_update_backups();
$hasZip = class_exists('ZipArchive');
admin_header('Actualizar', 'actualizar');
?>
<div class="ad-cards">
  <div class="ad-card"><strong><?= CMS_VERSION ?></strong><span>Versión instalada</span></div>
  <div class="ad-card"><strong><?= $info && $info['version'] !== '' ? cms_e($info['version']) : '—' ?></strong><span>Versión publicada<?= $info && $info['checked'] ? ' · consultado ' . date('d/m/Y H:i', $info['checked']) : '' ?></span></div>
  <div class="ad-card"><strong><?= PHP_VERSION ?></strong><span>PHP de este servidor</span></div>
</div>

<section class="ad-box">
  <h2><?= $info && $info['newer'] ? 'Hay una versión nueva' : 'Estado' ?></h2>
<?php if ($checkErr !== ''): ?>
  <p class="ad-help">Última consulta con error: <?= cms_e($checkErr) ?></p>
<?php endif; ?>
<?php if (!$info): ?>
  <p class="ad-help">Todavía no se ha consultado si hay actualizaciones. La consulta sale a internet, así que se hace solo cuando la pides.</p>
<?php elseif ($info['newer']): ?>
  <p><strong><?= cms_e($info['version']) ?></strong><?= $info['date'] !== '' ? ' · ' . cms_e($info['date']) : '' ?></p>
<?php if ($info['notes'] !== ''): ?>  <p class="ad-help"><?= nl2br(cms_e($info['notes'])) ?></p><?php endif; ?>
<?php else: ?>
  <p class="ad-help">Tu instalación está al día.</p>
<?php endif; ?>

<?php
$problems = [];
if (!$hasZip) $problems[] = 'Este servidor no tiene la extensión Zip de PHP, necesaria para descomprimir la actualización.';
if (!$writable) $problems[] = $whyNot;
if ($info && $info['too_old']) $problems[] = 'Desde la versión ' . CMS_VERSION . ' hay que actualizar a mano hasta la ' . cms_e($info['min_version']) . '.';
if ($info && $info['php_low']) $problems[] = 'La versión nueva necesita PHP ' . cms_e($info['min_php']) . ' o superior.';
?>
<?php foreach ($problems as $p): ?>
  <p class="ad-help ad-theme-warn"><?= $p ?></p>
<?php endforeach; ?>

  <div class="ad-actions">
    <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="check">
      <button class="ad-btn ad-btn-light ad-btn-sm" type="submit">Buscar actualizaciones</button>
    </form>
<?php if ($info && $info['newer'] && !$problems): ?>
    <form method="post" class="ad-inline" data-confirm="Se descargará la versión <?= cms_e($info['version']) ?> y se sustituirá la carpeta cms/. El contenido, las imágenes y el tema no se tocan, y la versión actual queda guardada por si hay que volver. ¿Actualizar ahora?">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="update">
      <button class="ad-btn ad-btn-sm" type="submit">Actualizar a <?= cms_e($info['version']) ?></button>
    </form>
<?php endif; ?>
  </div>
  <p class="ad-help">Se sustituye solo <code>cms/</code>. El contenido (<code>data/</code>), los archivos subidos (<code>uploads/</code>), el tema y los paquetes que hayas instalado no se tocan. Aun así, antes de una actualización grande conviene pasar por <a href="<?= admin_url('backup') ?>">Respaldos</a>.</p>
</section>

<?php if ($backups): ?>
<section class="ad-box">
  <h2>Versiones anteriores guardadas</h2>
  <p class="ad-help">Cada actualización guarda la carpeta que había. Se conservan las dos últimas.</p>
  <table class="ad-table">
    <thead><tr><th>Copia</th><th>Versión</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
<?php foreach ($backups as $name => $b): ?>
      <tr>
        <td><code><?= cms_e($name) ?></code></td>
        <td><?= cms_e($b['version'] ?: '¿?') ?></td>
        <td><?= date('d/m/Y H:i', $b['time']) ?></td>
        <td class="ad-row-actions">
          <form method="post" class="ad-inline" data-confirm="¿Volver a la versión <?= cms_e($b['version'] ?: '?') ?>? La actual pasará a ocupar el lugar de esta copia.">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="rollback"><input type="hidden" name="backup" value="<?= cms_e($name) ?>">
            <button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Volver a esta</button>
          </form>
          <form method="post" class="ad-inline" data-confirm="¿Eliminar esta copia? No se podrá volver a ella.">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="forget"><input type="hidden" name="backup" value="<?= cms_e($name) ?>">
            <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button>
          </form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; admin_footer();
