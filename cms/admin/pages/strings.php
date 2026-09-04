<?php
declare(strict_types=1);

$all = cms_strings_all(true);   // incluye claves nuevas de site/defaults/strings.json; al guardar quedan en data/

if (admin_is_post()) {
    admin_csrf_check();
    foreach ($all as $key => $row) {
        $is_list = is_array($row[cms_default_lang()] ?? null);
        foreach (cms_langs() as $l) {
            $v = $_POST['s'][$key][$l] ?? '';
            $v = is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : '';
            $all[$key][$l] = $is_list ? cms_lines($v) : $v;
        }
    }
    if (cms_json_write(CMS_DATA . '/strings.json', $all)) admin_flash('Textos guardados.');
    else admin_flash('No se pudieron guardar los textos.', 'err');
    admin_redirect(admin_url('strings'));
}

$groups = (array) cms_config('strings_groups');
$listed = array_merge(...array_values($groups));
$rest = array_diff(array_keys($all), $listed);
if ($rest) $groups[$groups ? 'Otros' : 'Textos'] = array_values($rest);

admin_header('Textos del sitio', 'strings');
?>
<p class="ad-help">Textos fijos del sitio (títulos, botones, formulario, SEO). Las listas se escriben una por línea. <?= count(cms_langs()) > 1 ? 'Si un idioma queda vacío se muestra el predeterminado.' : '' ?></p>
<form method="post" class="ad-form">
  <?= admin_csrf_field() ?>
  <p class="ad-actions"><button class="ad-btn" type="submit">Guardar textos</button></p>
<?php foreach ($groups as $g => $keys): ?>
  <details class="ad-box" open>
    <summary><h2><?= cms_e($g) ?></h2></summary>
<?php foreach ($keys as $key): if (!isset($all[$key])) continue; $row = $all[$key]; $is_list = is_array($row[cms_default_lang()] ?? null); $long = $is_list || mb_strlen((string) ($row[cms_default_lang()] ?? '')) > 60 || strpos($key, 'meta_desc') !== false; ?>
    <div class="ad-field ad-string">
      <label><?= cms_e($row['help'] ?: $key) ?> <code><?= cms_e($key) ?></code></label>
      <div class="ad-langs">
<?php foreach (cms_langs() as $l): $v = $row[$l] ?? ''; if (is_array($v)) $v = implode("\n", $v); ?>
        <div class="ad-lang"><span class="ad-lang-tag"><?= strtoupper($l) ?></span>
<?php if ($long): ?>          <textarea name="s[<?= cms_e($key) ?>][<?= $l ?>]" rows="<?= $is_list ? 4 : 2 ?>"><?= cms_e($v) ?></textarea>
<?php else: ?>          <input type="text" name="s[<?= cms_e($key) ?>][<?= $l ?>]" value="<?= cms_e($v) ?>">
<?php endif; ?>
        </div>
<?php endforeach; ?>
      </div>
    </div>
<?php endforeach; ?>
  </details>
<?php endforeach; ?>
  <p><button class="ad-btn" type="submit">Guardar textos</button></p>
</form>
<?php admin_footer();
