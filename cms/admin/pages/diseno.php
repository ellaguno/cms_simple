<?php
/**
 * Diseño: variaciones de estilo del tema activo ("pieles"), con muestra en vivo y aplicación con un clic.
 * Aplicar una variación guarda su clave en Ajustes ('style') y, salvo que se pida conservarlos, copia sus
 * colores y tipografías a Ajustes → Diseño para que el sitio se vea tal cual la muestra.
 */
declare(strict_types=1);
$styles = cms_styles();
$themes = cms_themes();
$themeKey = cms_theme_key();

/** Copia recursiva de una carpeta (para duplicar el tema clásico al instalar el primero). */
function diseno_copy(string $src, string $dst): bool
{
    if (!is_dir($src)) return false;
    if (!is_dir($dst) && !@mkdir($dst, 0775, true)) return false;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $to = $dst . '/' . $it->getSubPathname();
        if ($f->isDir()) { if (!is_dir($to) && !@mkdir($to, 0775, true)) return false; }
        elseif (!@copy($f->getPathname(), $to)) return false;
    }
    return true;
}

if (admin_is_post() && admin_post('action') === 'theme') {
    admin_csrf_check();
    $k = (string) admin_post('theme');
    if (!isset($themes[$k])) admin_flash('Ese tema no está instalado.', 'err');
    elseif ($k === $themeKey) admin_flash('Ese tema ya está en uso.');
    else {
        $S = cms_json_read(CMS_DATA . '/settings.json', []);
        if ($k === 'site') unset($S['theme']); else $S['theme'] = $k;
        unset($S['style']);   // las variaciones son de cada tema
        // los colores y tipografías que copió una variación del tema anterior se retiran: si no, taparían la piel del nuevo
        foreach (array_merge(cms_style_setting_keys(CMS_SITE), cms_style_setting_keys($themes[$k]['dir'])) as $sk) unset($S[$sk]);
        if (cms_json_write(CMS_DATA . '/settings.json', $S)) admin_flash('Tema activado: ' . $themes[$k]['label'] . '. Los colores y tipografías vuelven a los del tema; ajústalos en Diseño o en Ajustes.');
        else admin_flash('No se pudo guardar en data/settings.json.', 'err');
    }
    admin_redirect(admin_url('diseno'));
}

if (admin_is_post() && admin_post('action') === 'install') {
    admin_csrf_check();
    $f = $_FILES['theme_zip'] ?? null;
    $name = cms_slugify((string) admin_post('theme_key')) ?: ($f ? cms_slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME)) : '');
    if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) admin_flash('No se recibió el archivo (¿supera el límite del servidor?).', 'err');
    elseif ($name === '' || !preg_match('/^[a-z0-9_-]+$/', $name)) admin_flash('El nombre del tema solo admite minúsculas, números y guiones.', 'err');
    elseif (isset($themes[$name]) || is_dir(CMS_THEMES . '/' . $name)) admin_flash('Ya hay un tema con ese nombre.', 'err');
    elseif (!class_exists('ZipArchive')) admin_flash('Este servidor no tiene la extensión Zip de PHP.', 'err');
    else {
        $z = new ZipArchive();
        if ($z->open((string) $f['tmp_name']) !== true) admin_flash('El archivo no es un zip válido.', 'err');
        else {
            // se admite el zip con el tema en la raíz o dentro de una única carpeta
            $root = '';
            $first = (string) ($z->getNameIndex(0) ?: '');
            if (strpos($first, '/') !== false) { $cand = explode('/', $first)[0] . '/'; $all = true;
                for ($i = 0; $i < $z->numFiles; $i++) if (strpos((string) $z->getNameIndex($i), $cand) !== 0) { $all = false; break; }
                if ($all) $root = $cand; }
            $hasConf = false;
            for ($i = 0; $i < $z->numFiles; $i++) { $n = (string) $z->getNameIndex($i); if ($n === $root . 'config.php' || $n === $root . 'theme.json') { $hasConf = true; break; } }
            if (!$hasConf) { admin_flash('El zip no parece un tema: falta config.php o theme.json en su raíz.', 'err'); $z->close(); }
            else {
                $dst = CMS_THEMES . '/' . $name;
                @mkdir($dst, 0775, true);
                cms_protect_dir(CMS_THEMES);
                cms_protect_dir($dst);
                $n = 0; $bad = 0;
                for ($i = 0; $i < $z->numFiles; $i++) {
                    $entry = (string) $z->getNameIndex($i);
                    if ($root !== '' && strpos($entry, $root) !== 0) continue;
                    $rel = $root !== '' ? substr($entry, strlen($root)) : $entry;
                    if ($rel === '' || strpos($rel, '..') !== false || $rel[0] === '/') { $bad++; continue; }
                    if (substr($rel, -1) === '/') { @mkdir($dst . '/' . $rel, 0775, true); continue; }
                    if (!preg_match('/\.(php|json|css|js|md|html|txt|svg|png|jpe?g|webp|gif|ico|woff2?|mp4|webm|htaccess)$/i', $rel) && basename($rel) !== '.htaccess') { $bad++; continue; }
                    @mkdir(dirname($dst . '/' . $rel), 0775, true);
                    $src = $z->getStream($entry);
                    if (!$src) { $bad++; continue; }
                    $out = @fopen($dst . '/' . $rel, 'wb');
                    if ($out) { stream_copy_to_stream($src, $out); fclose($out); $n++; }
                    fclose($src);
                }
                $z->close();
                admin_flash($n . ' archivos instalados en themes/' . $name . ($bad ? ' (' . $bad . ' omitidos por seguridad)' : '') . '. Actívalo cuando quieras.');
            }
        }
    }
    admin_redirect(admin_url('diseno'));
}

if (admin_is_post() && admin_post('action') === 'export') {
    admin_csrf_check();
    if (!class_exists('ZipArchive')) { admin_flash('Este servidor no tiene la extensión Zip de PHP.', 'err'); admin_redirect(admin_url('diseno')); }
    $tmp = tempnam(sys_get_temp_dir(), 'tema') . '.zip';
    $z = new ZipArchive();
    $z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CMS_SITE, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) { if ($f->isFile()) $z->addFile($f->getPathname(), $it->getSubPathname()); }
    $z->close();
    $file = 'tema-' . $themeKey . '-' . date('Y-m-d') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

if (admin_is_post()) {
    admin_csrf_check();
    $k = (string) admin_post('style');
    $S = cms_json_read(CMS_DATA . '/settings.json', []);
    if ($k === '') {
        unset($S['style']);
        if (cms_json_write(CMS_DATA . '/settings.json', $S)) admin_flash('Se quitó la variación: el sitio usa el estilo base del tema.');
        else admin_flash('No se pudo guardar.', 'err');
    } elseif (isset($styles[$k])) {
        $S['style'] = $k;
        if (!admin_post('keep')) foreach ((array) ($styles[$k]['settings'] ?? []) as $sk => $sv) {
            if (preg_match('/^[a-z0-9_]+$/i', (string) $sk)) $S[$sk] = is_scalar($sv) ? (string) $sv : '';
        }
        if (cms_json_write(CMS_DATA . '/settings.json', $S)) admin_flash('Estilo aplicado: ' . ($styles[$k]['label'] ?? $k) . '.');
        else admin_flash('No se pudo guardar en data/settings.json.', 'err');
    } else admin_flash('Esa variación no existe.', 'err');
    admin_redirect(admin_url('diseno'));
}

$active = cms_style_key();
$themeName = cms_config('name');
$themeInfo = cms_json_read(CMS_SITE . '/theme.json', []);
admin_header('Diseño', 'diseno');
?>
<section class="ad-box">
  <h2>Tema</h2>
  <p class="ad-help">El tema pone las plantillas, los bloques y el estilo. El contenido vive aparte, así que cambiar de tema no borra nada; eso sí, cada tema trae sus propios bloques, y las secciones que el nuevo no conozca dejarán de dibujarse (siguen guardadas).</p>
  <div class="ad-themes">
<?php foreach ($themes as $k => $t): $on = $k === $themeKey; $w = $on ? null : cms_theme_warnings($t['dir']); ?>
    <article class="ad-theme<?= $on ? ' is-on' : '' ?>">
<?php if ($t['screenshot'] !== ''): ?>      <img src="<?= cms_e($t['url'] . '/' . $t['screenshot']) ?>" alt="">
<?php else: ?>      <div class="ad-theme-noshot" aria-hidden="true"><?= cms_e(mb_strtoupper(mb_substr($t['label'], 0, 2))) ?></div>
<?php endif; ?>
      <div class="ad-theme-body">
        <div class="ad-style-head"><strong><?= cms_e($t['label']) ?></strong><?php if ($on): ?><span class="ad-pill on">En uso</span><?php endif; ?></div>
        <p class="ad-help"><?= cms_e($t['desc'] ?: 'Sin descripción.') ?><?= $t['version'] !== '' ? ' v' . cms_e($t['version']) : '' ?><?= $t['styles'] ? ' · ' . (int) $t['styles'] . ' variaciones' : '' ?></p>
<?php if (!$on && $w && !$w['unknown'] && ($w['types'] || $w['blocks'])): ?>
        <p class="ad-help ad-theme-warn">Ojo: este tema no trae <?= $w['types'] ? 'los tipos <code>' . cms_e(implode(', ', $w['types'])) . '</code>' : '' ?><?= $w['types'] && $w['blocks'] ? ' ni ' : '' ?><?= $w['blocks'] ? 'los bloques <code>' . cms_e(implode(', ', array_slice($w['blocks'], 0, 6))) . '</code>' : '' ?> que usa tu contenido.</p>
<?php endif; ?>
<?php if (!$on): ?>
        <form method="post" data-confirm="¿Activar el tema <?= cms_e($t['label']) ?>? El contenido no se borra, pero el sitio cambiará de aspecto y de bloques.">
          <?= admin_csrf_field() ?><input type="hidden" name="action" value="theme"><input type="hidden" name="theme" value="<?= cms_e($k) ?>">
          <button class="ad-btn ad-btn-sm" type="submit">Activar</button>
        </form>
<?php endif; ?>
      </div>
    </article>
<?php endforeach; ?>
  </div>
  <div class="ad-actions">
    <form method="post" enctype="multipart/form-data" class="ad-theme-install">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="install">
      <label class="ad-btn ad-btn-light ad-btn-sm">Instalar un tema (.zip) <input type="file" name="theme_zip" accept=".zip,application/zip" hidden data-auto-submit></label>
      <input type="text" name="theme_key" placeholder="nombre de la carpeta (opcional)" pattern="[a-z0-9_-]+">
      <span class="ad-help">Un tema trae código PHP que se ejecuta en tu servidor: instala solo los que te dé alguien de confianza.</span>
    </form>
    <form method="post"><?= admin_csrf_field() ?><input type="hidden" name="action" value="export">
      <button class="ad-btn ad-btn-light ad-btn-sm" type="submit">Descargar el tema en uso (.zip)</button>
    </form>
  </div>
</section>

<h2 class="ad-subtitle">Variaciones de estilo</h2>
<p class="ad-help">Cambian colores, tipografías, esquinas y espacios de todo el sitio. El contenido y las páginas no se tocan.</p>
<?php if (!$styles): ?>
<div class="ad-box">
  <h2>Este tema no trae variaciones</h2>
  <p class="ad-help">Un tema puede incluirlas en <code><?= cms_e(basename(CMS_SITE)) ?>/styles/&lt;clave&gt;.json</code>: nombre, descripción, las variables CSS que cambia y los colores y tipografías que sugiere. Mientras tanto, ajusta el diseño en <a href="<?= admin_url('settings') ?>">Ajustes → Diseño</a>.</p>
</div>
<?php else: ?>
<div class="ad-styles">
<?php foreach ($styles as $k => $st): $on = $k === $active; ?>
  <article class="ad-style<?= $on ? ' is-on' : '' ?>">
    <div class="ad-demo" data-demo="<?= cms_e(admin_url('demo', ['style' => $k])) ?>"><a class="ad-demo-open" href="<?= cms_e(admin_url('demo', ['style' => $k])) ?>" target="_blank" rel="noopener" title="Ver la muestra a tamaño real">Abrir ↗</a></div>
    <div class="ad-style-body">
      <div class="ad-style-head">
        <strong><?= cms_e($st['label']) ?></strong>
        <?php if ($on): ?><span class="ad-pill on">En uso</span><?php endif; ?>
      </div>
      <p class="ad-help"><?= cms_e($st['desc']) ?></p>
      <div class="ad-style-swatches" aria-hidden="true">
<?php foreach (['--lz-primary', '--lz-accent', '--lz-bg', '--lz-light'] as $v): $c = (string) ($st['vars'][$v] ?? ''); if (!preg_match('/^#[0-9a-f]{3,8}$/i', $c)) continue; ?>
        <span style="background:<?= cms_e($c) ?>"></span>
<?php endforeach; ?>
<?php if (!empty($st['fonts'])): ?>        <small><?= cms_e(implode(' · ', array_map('cms_font_name', (array) $st['fonts']))) ?></small><?php endif; ?>
      </div>
      <form method="post" class="ad-style-form">
        <?= admin_csrf_field() ?><input type="hidden" name="style" value="<?= cms_e($k) ?>">
        <button class="ad-btn ad-btn-sm<?= $on ? ' ad-btn-light' : '' ?>" type="submit"<?= $on ? ' disabled' : '' ?>><?= $on ? 'En uso' : 'Usar esta' ?></button>
        <label class="ad-check ad-help" title="Deja tus colores y tipografías de Ajustes → Diseño tal como están"><input type="checkbox" name="keep" value="1"> conservar mis colores</label>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if ($active !== ''): ?>
<form method="post" class="ad-actions"><?= admin_csrf_field() ?><input type="hidden" name="style" value="">
  <button class="ad-btn ad-btn-light ad-btn-sm" type="submit">Quitar la variación y usar el estilo base del tema</button>
</form>
<?php endif; ?>
<p class="ad-help">Al aplicar una variación se copian sus colores y tipografías a <a href="<?= admin_url('settings') ?>">Ajustes → Diseño</a>, donde puedes seguir afinándolos. Marca «conservar mis colores» si ya tienes los tuyos y solo quieres el resto (esquinas, espacios y tipografías del conjunto).</p>
<?php endif; admin_footer();
