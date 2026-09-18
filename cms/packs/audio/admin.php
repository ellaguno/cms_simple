<?php
/** Página "Audio" del panel: estado del proveedor, prueba de voz y audio por colección. $pack trae el manifiesto. */
declare(strict_types=1);
$o = au_settings();
$langs = cms_active_langs();
$multi = count(cms_langs()) > 1;

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    $type = preg_replace('/[^a-z0-9_-]/i', '', admin_post('type'));
    $slug = cms_slugify(admin_post('slug'));
    $lang = in_array(admin_post('lang'), cms_langs(), true) ? admin_post('lang') : cms_default_lang();
    $back = admin_post('back');
    if (strpos($back, ADMIN_URL . '/') !== 0 && strpos($back, cms_origin() . ADMIN_URL . '/') !== 0) $back = admin_url('pack:audio');
    if ($action === 'generate' && cms_type($type)) {
        [$ok, $msg] = au_generate($type, $slug, $lang);
        admin_flash($msg, $ok ? 'ok' : 'err');
    } elseif ($action === 'remove' && cms_type($type)) {
        admin_flash(au_remove($type, $slug, $lang) ? 'Audio quitado.' : 'No se pudo quitar el audio.', 'ok');
    } elseif ($action === 'test') {
        @set_time_limit(120);
        try {
            $mp3 = au_synth(cms_default_lang() === 'en' ? 'Hello. This is a voice test from the site.' : 'Hola. Esta es una prueba de voz del sitio.', cms_default_lang());
            $dir = CMS_ROOT . '/uploads/audio';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (strlen($mp3) > 100 && @file_put_contents($dir . '/prueba-' . $o['provider'] . '.mp3', $mp3) !== false) admin_flash('Prueba generada con ' . au_provider_label($o['provider']) . ' (' . (int) round(strlen($mp3) / 1024) . ' KB).');
            else admin_flash('El proveedor no devolvió audio o no se pudo escribir en uploads/audio/.', 'err');
        } catch (\Throwable $e) {
            admin_flash(au_provider_label($o['provider']) . ': ' . $e->getMessage(), 'err');
        }
    }
    admin_redirect($back);
}

$keyOk = $o['provider'] === 'prueba' || $o[$o['provider'] . '_key'] !== '';
$testFile = 'uploads/audio/prueba-' . $o['provider'] . '.mp3';
admin_header('Audio: texto a voz', 'pack:audio');
?>
<div class="ad-grid2">
  <section class="ad-box">
    <h2>Proveedor</h2>
    <p><strong><?= cms_e(au_provider_label($o['provider'])) ?></strong>
      <?php if ($o['provider'] === 'openai'): ?> · voz <?= cms_e($o['openai_voice']) ?> · <?= cms_e($o['openai_model']) ?>
      <?php elseif ($o['provider'] === 'elevenlabs'): ?> · voz <?= cms_e($o['elevenlabs_voice']) ?> · <?= cms_e($o['elevenlabs_model']) ?>
      <?php elseif ($o['provider'] === 'azure'): ?> · <?= cms_e($o['azure_voice']) ?> · <?= cms_e($o['azure_region']) ?>
      <?php elseif ($o['provider'] === 'google'): ?> · <?= cms_e($o['google_voice']) ?><?php endif; ?>
      <?php if (!$keyOk): ?> <span class="ad-pill warn">falta la clave</span><?php endif; ?></p>
    <p class="ad-help">Reproductor: <?= ['top' => 'al principio del texto', 'bottom' => 'al final del texto', 'none' => 'solo con el bloque o desde el tema'][$o['player']] ?> · Automático al publicar: <?= $o['auto'] ? 'sí' : 'no' ?> · Máximo <?= number_format($o['max_chars']) ?> caracteres · Colecciones: <?= $o['types'] ? cms_e(implode(', ', $o['types'])) : 'todas' ?>.
      Se cambia en <a href="<?= admin_url('settings') ?>#audio">Ajustes → Audio</a>.</p>
    <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="test"><button class="ad-btn ad-btn-sm" type="submit">Probar la voz</button></form>
    <?php if (is_file(CMS_ROOT . '/' . $testFile)): ?><p><audio controls preload="none" src="<?= cms_e(cms_img($testFile)) ?>?v=<?= filemtime(CMS_ROOT . '/' . $testFile) ?>" style="width:100%;height:36px"></audio></p><?php endif; ?>
  </section>
  <section class="ad-box">
    <h2>Cómo funciona</h2>
    <ul class="ad-list">
      <li>En cada elemento, la barra lateral tiene <strong>Generar audio</strong> por idioma. Convierte el texto guardado (título, cuerpo y textos de las secciones) y guarda el MP3 en <code>uploads/audio/</code>.</li>
      <li>El reproductor se pone solo en el cuerpo del artículo. En páginas del constructor usa el bloque <strong>Reproductor</strong>; en un tema propio, <code>au_player($ruta, $lang)</code>.</li>
      <li>Cada proveedor cobra por carácter: el máximo de caracteres de Ajustes limita el gasto. El proveedor de prueba no llama a nadie: genera un MP3 en silencio para revisar el flujo.</li>
      <li>Si editas el texto, vuelve a generar: el audio no se actualiza solo (salvo con "automático al publicar", que solo genera cuando falta).</li>
    </ul>
  </section>
</div>
<?php foreach (cms_config('types') as $k => $def): if (!au_type_ok($k) || !empty($def['internal'])) continue; $all = cms_items($k, false); if (!$all) continue;
    $with = 0; foreach ($all as $it) if (au_path($it, cms_default_lang()) !== '') $with++; ?>
<section class="ad-box">
  <h2><?= cms_e($def['label'] ?? $k) ?> <small class="ad-help"><?= $with ?> de <?= count($all) ?> con audio<?= $multi ? ' en ' . strtoupper(cms_default_lang()) : '' ?></small></h2>
  <table class="ad-table">
    <thead><tr><th>Elemento</th><th>Estado</th><?php foreach ($langs as $l): ?><th>Audio<?= $multi ? ' ' . strtoupper($l) : '' ?></th><?php endforeach; ?></tr></thead>
    <tbody>
<?php $n = 0; foreach ($all as $it): if (++$n > 100) break; $live = cms_item_is_live($it); ?>
      <tr>
        <td><a href="<?= admin_url('edit', ['type' => $k, 'slug' => $it['slug']]) ?>"><?= cms_e(cms_f($it, $def['title_field'] ?? 'title', cms_default_lang()) ?: $it['slug']) ?></a></td>
        <td><span class="ad-pill <?= $live ? 'on' : '' ?>"><?= $live ? 'Publicado' : (($it['status'] ?? '') === 'published' ? 'Programado' : 'Borrador') ?></span></td>
<?php foreach ($langs as $l): $p = au_path($it, $l); ?>
        <td class="ad-row-actions">
<?php if ($p !== ''): ?>          <audio controls preload="none" src="<?= cms_e(cms_img($p)) ?>" style="height:28px;width:180px;vertical-align:middle"></audio>
<?php endif; ?>
          <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="generate"><input type="hidden" name="type" value="<?= cms_e($k) ?>"><input type="hidden" name="slug" value="<?= cms_e($it['slug']) ?>"><input type="hidden" name="lang" value="<?= cms_e($l) ?>">
            <button class="ad-btn ad-btn-sm<?= $p !== '' ? ' ad-btn-light' : '' ?>" type="submit"><?= $p !== '' ? 'Regenerar' : 'Generar' ?></button></form>
<?php if ($p !== ''): ?>          <form method="post" class="ad-inline" data-confirm="¿Quitar este audio?"><?= admin_csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="type" value="<?= cms_e($k) ?>"><input type="hidden" name="slug" value="<?= cms_e($it['slug']) ?>"><input type="hidden" name="lang" value="<?= cms_e($l) ?>"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Quitar</button></form>
<?php endif; ?>
        </td>
<?php endforeach; ?>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php if (count($all) > 100): ?>  <p class="ad-help">Se muestran los primeros 100; el resto se genera desde su editor.</p><?php endif; ?>
</section>
<?php endforeach; ?>
<?php admin_footer();
