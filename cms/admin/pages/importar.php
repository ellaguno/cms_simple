<?php
/**
 * Importar diseño: de un PDF o imagen de una página (diseñador, Figma, Word exportado…) a un borrador del constructor.
 * El navegador rasteriza el archivo (pdf.js) en pantallas y extrae el texto; aquí se llama al modelo (cms/lib/import.php)
 * y se guarda el borrador. Peticiones JSON: action=analizar (multipart), action=modelos.
 */
declare(strict_types=1);
require_once CMS_DIR . '/lib/import.php';

if (cms_config('importer', true) === false) { admin_flash('El importador está desactivado en la configuración del sitio.', 'err'); admin_redirect(admin_url()); }

$targets = array_filter(cms_config('types'), fn($d) => (bool) array_filter((array) ($d['fields'] ?? []), fn($f) => ($f['type'] ?? '') === 'sections'));
$S = cms_settings();
$provider = (string) ($S['import_provider'] ?? 'openrouter');
if (!isset(cms_import_providers()[$provider])) $provider = 'openrouter';
$model = (string) ($S['import_model'] ?? '');

function importar_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');

    if ($action === 'ajustes') {
        $S['import_provider'] = isset(cms_import_providers()[admin_post('provider')]) ? admin_post('provider') : 'openrouter';
        $S['import_model'] = trim(admin_post('model_custom')) !== '' ? trim(admin_post('model_custom')) : admin_post('model');
        $k = admin_post('openrouter_key');
        if ($k !== '' && $k !== '••••') $S['openrouter_key'] = $k;
        if (admin_post('forget_key') === '1') unset($S['openrouter_key']);
        cms_json_write(CMS_DATA . '/settings.json', $S);
        admin_flash('Ajustes del importador guardados.');
        admin_redirect(admin_url('importar'));
    }

    if ($action === 'modelos') {
        $m = cms_import_models(true);
        importar_json(['ok' => (bool) $m, 'count' => count($m), 'models' => $m, 'default' => cms_import_default_model()]);
    }

    if ($action === 'analizar') {
        @set_time_limit(900);
        ignore_user_abort(true);
        $dir = ''; $saved = false;
        try {
            $type = admin_post('type');
            if (!isset($targets[$type])) throw new RuntimeException('Tipo de contenido no válido.');
            $def = $targets[$type];
            $title = admin_post('title');
            $slug = cms_slugify(admin_post('slug') !== '' ? admin_post('slug') : $title);
            if ($slug === '') throw new RuntimeException('Escribe un título o una URL para la página.');
            if (cms_item($type, $slug, false)) throw new RuntimeException("Ya existe una página con la URL «$slug». Elige otra.");
            if (!empty($def['tree']) && admin_post('parent') === '' && in_array($slug, cms_reserved_segments(), true)) throw new RuntimeException("La URL «$slug» está reservada en la raíz.");
            $prov = admin_post('provider') !== '' ? admin_post('provider') : $provider;
            $mod = admin_post('model') !== '' ? admin_post('model') : $model;
            if ($prov === 'openrouter' && $mod === '') $mod = cms_import_default_model();
            if ($prov === 'claude-cli' && $mod === '') $mod = 'sonnet';
            $lang = admin_post('lang'); if (!in_array($lang, cms_langs(), true)) $lang = cms_default_lang();

            $files = $_FILES['screens'] ?? null;
            if (!$files || !is_array($files['tmp_name'] ?? null) || !$files['tmp_name']) throw new RuntimeException('No llegaron las pantallas del diseño.');
            $dir = CMS_UPLOADS . '/import/' . $slug;
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('No se puede escribir en uploads/import/.');
            $screens = [];
            foreach ($files['tmp_name'] as $i => $tmp) {
                if (!is_uploaded_file($tmp)) continue;
                $info = @getimagesize($tmp);
                if (!$info || $info[2] !== IMAGETYPE_PNG) throw new RuntimeException('Las pantallas deben ser PNG.');
                $dest = $dir . '/pantalla-' . str_pad((string) (count($screens) + 1), 2, '0', STR_PAD_LEFT) . '.png';
                if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('No se pudo guardar una pantalla.');
                $screens[] = $dest;
            }
            if (!$screens) throw new RuntimeException('No llegaron las pantallas del diseño.');
            if (count($screens) > 40) throw new RuntimeException('Demasiadas pantallas (' . count($screens) . '). El diseño es demasiado largo para una sola página.');
            $text = admin_post('text');
            if (mb_strlen($text) > 60000) $text = mb_substr($text, 0, 60000);

            [$result, $stats] = cms_import_run($prov, $mod, $screens, $text);

            $extra = [];
            if (!empty($def['tree'])) $extra['parent'] = cms_slugify(admin_post('parent'));
            foreach ((array) ($def['fields'] ?? []) as $name => $fd) {
                if (($fd['type'] ?? '') === 'select' && !empty($fd['sidebar']) && isset($_POST['f_' . $name]) && isset($fd['options'][admin_post('f_' . $name)])) $extra[$name] = admin_post('f_' . $name);
            }
            [$item, $notes] = cms_import_materialize($result, $type, $slug, $lang, $extra, admin_post('source'));
            if ($title !== '') $item[$def['title_field'] ?? 'title'] = [$lang => $title] + (array) $item[$def['title_field'] ?? 'title'];
            $item['import']['screens'] = array_map(fn($p) => 'uploads/import/' . $slug . '/' . basename($p), $screens);
            $item['import']['stats'] = $stats;
            if (!empty($def['tree'])) { $all = cms_items($type, false); $all[$slug] = $item; $item['path'] = cms_tree_path($type, $all, $slug); }
            if (!cms_item_save($type, $item)) throw new RuntimeException('No se pudo escribir en data/content/' . $type . '/.');
            $saved = true;
            if (!empty($def['tree'])) cms_tree_rebuild($type);
            $labels = cms_import_catalog()['meta'];
            importar_json([
                'ok' => true,
                'edit' => admin_url('edit', ['type' => $type, 'slug' => $slug]),
                'preview' => cms_item_url($type, $item, $lang),
                'sections' => array_map(fn($s) => $labels[$s['type']]['label'] ?? $s['type'], $item['sections']),
                'notes' => $notes, 'unmapped' => (array) ($result['unmapped'] ?? []),
                'palette' => $result['palette'] ?? null, 'fonts' => $result['fonts'] ?? [],
                'stats' => $stats,
            ]);
        } catch (Throwable $e) {
            // sin borrador no hay que conservar las pantallas subidas
            if (!empty($dir) && is_dir($dir) && empty($saved)) { foreach (glob($dir . '/*.png') ?: [] as $f) @unlink($f); @rmdir($dir); }
            importar_json(['ok' => false, 'error' => $e->getMessage()], 200);
        }
    }
    admin_redirect(admin_url('importar'));
}

$models = cms_import_models();
$defaultModel = $model !== '' ? $model : cms_import_default_model();
$hasKey = !empty($S['openrouter_key']);
$cli = cms_import_claude_cli() !== '';
$typeKeys = array_keys($targets);
admin_header('Importar diseño', 'importar');
?>
<p class="ad-help" style="margin:-8px 0 18px;max-width:760px">Sube el diseño de una página (PDF de una o varias páginas, PNG o JPG) y el modelo lo convierte en un borrador del constructor: mismas bandas, mismos textos, bloques del catálogo del sitio. Las imágenes del diseño quedan descritas en notas para que las subas desde Medios. Un diseño hecho en Figma, Illustrator, Word o Inkscape se exporta a PDF con un clic.</p>

<?php if (!$targets): ?>
<div class="ad-flash err">Ningún tipo de contenido de este sitio tiene un campo de secciones. El importador crea páginas del constructor.</div>
<?php admin_footer(); return; endif; ?>

<details class="ad-box"<?= (!$hasKey && !$cli) ? ' open' : '' ?>>
  <summary><h2>Motor de análisis</h2></summary>
  <form method="post" class="ad-form-narrow" style="margin-top:12px">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="ajustes">
    <div class="ad-field"><label>Proveedor</label>
      <select name="provider" data-import-provider>
<?php foreach (cms_import_providers() as $k => $l): ?>        <option value="<?= cms_e($k) ?>"<?= $k === $provider ? ' selected' : '' ?>><?= cms_e($l) ?></option>
<?php endforeach; ?>
      </select></div>
    <div data-import-if="openrouter">
      <div class="ad-field"><label>Clave de OpenRouter <small class="ad-help">(openrouter.ai → Keys; se guarda en data/settings.json)</small></label>
        <input type="password" name="openrouter_key" value="<?= $hasKey ? '••••' : '' ?>" placeholder="sk-or-v1-…" autocomplete="off">
        <?php if ($hasKey): ?><label class="ad-check" style="margin-top:6px"><input type="checkbox" name="forget_key" value="1"> Borrar la clave guardada</label><?php endif; ?></div>
      <div class="ad-field"><label>Modelo <small class="ad-help">(con visión; precio por millón de tokens de entrada / salida en USD)</small></label>
        <select name="model" data-import-models>
<?php if (!$models): ?>          <option value="">(pulsa "Actualizar lista")</option>
<?php endif; foreach ($models as $id => $m): ?>          <option value="<?= cms_e($id) ?>"<?= $id === $defaultModel ? ' selected' : '' ?>><?= cms_e($id) ?> — <?= $m['in'] ?> / <?= $m['out'] ?><?= $m['structured'] ? '' : ' (sin salida estructurada)' ?></option>
<?php endforeach; ?>
        </select>
        <div class="ad-btnrow" style="margin-top:8px"><button type="button" class="ad-btn ad-btn-light ad-btn-sm" data-import-refresh>Actualizar lista</button><span class="ad-help" data-import-models-info><?= $models ? count($models) . ' modelos con visión' : '' ?></span></div></div>
      <div class="ad-field"><label>Otro modelo <small class="ad-help">(id exacto de OpenRouter; tiene prioridad sobre la lista)</small></label><input type="text" name="model_custom" value="<?= !isset($models[$model]) ? cms_e($model) : '' ?>" placeholder="proveedor/modelo"></div>
    </div>
    <div data-import-if="claude-cli" class="ad-field"><label>Modelo de Claude Code</label>
      <select name="model" data-cli-model disabled>
<?php foreach (['sonnet' => 'Sonnet (recomendado)', 'opus' => 'Opus (diseños complejos)', 'haiku' => 'Haiku (rápido, menos fiel)'] as $k => $l): ?>        <option value="<?= $k ?>"<?= $k === $model ? ' selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
      </select></div>
    <button class="ad-btn" type="submit">Guardar ajustes</button>
  </form>
</details>

<div class="ad-box">
  <h2>Nuevo borrador desde un diseño</h2>
  <form data-import-form class="ad-form-narrow">
    <div class="ad-field ad-dropzone" style="padding:22px;border-radius:12px;text-align:center" data-import-drop>
      <input type="file" name="file" accept="application/pdf,image/png,image/jpeg" data-import-file style="display:none">
      <p style="margin:0"><button type="button" class="ad-btn ad-btn-light" data-import-pick>Elegir archivo</button></p>
      <p class="ad-help">PDF, PNG o JPG. Arrastra aquí el archivo. <span data-import-filename></span></p>
    </div>
    <div class="ad-two">
      <div class="ad-field"><label>Título de la página</label><input type="text" name="title" data-import-title placeholder="Se toma del diseño si lo dejas vacío"></div>
      <div class="ad-field"><label>URL (slug)</label><input type="text" name="slug" data-import-slug placeholder="se genera del título"></div>
    </div>
    <div class="ad-two">
<?php if (count($typeKeys) > 1): ?>
      <div class="ad-field"><label>Crear como</label><select name="type" data-import-type><?php foreach ($targets as $k => $d): ?><option value="<?= cms_e($k) ?>"><?= cms_e($d['label_singular'] ?? $d['label'] ?? $k) ?></option><?php endforeach; ?></select></div>
<?php else: ?>
      <input type="hidden" name="type" value="<?= cms_e($typeKeys[0]) ?>" data-import-type>
<?php endif; ?>
      <div class="ad-field"><label>Idioma del diseño</label><select name="lang"><?php foreach (cms_langs() as $l): ?><option value="<?= $l ?>"<?= $l === cms_default_lang() ? ' selected' : '' ?>><?= strtoupper($l) ?></option><?php endforeach; ?></select></div>
    </div>
<?php foreach ($targets as $tk => $d): ?>
    <div data-import-type-fields="<?= cms_e($tk) ?>" class="ad-two">
<?php if (!empty($d['tree'])): $all = cms_items($tk, false); $paths = []; foreach ($all as $sl => $it) $paths[$sl] = $it['path'] ?? $sl; asort($paths); ?>
      <div class="ad-field"><label>Página padre</label><select name="parent"><option value="">— Raíz del sitio —</option><?php foreach ($paths as $sl => $pth): ?><option value="<?= cms_e($sl) ?>"><?= cms_e(str_repeat('· ', substr_count($pth, '/')) . cms_f($all[$sl], $d['title_field'] ?? 'title', cms_default_lang()) . '  (/' . $pth . ')') ?></option><?php endforeach; ?></select></div>
<?php endif; foreach ((array) ($d['fields'] ?? []) as $name => $fd): if (($fd['type'] ?? '') !== 'select' || empty($fd['sidebar'])) continue; ?>
      <div class="ad-field"><label><?= cms_e($fd['label'] ?? $name) ?></label><select name="f_<?= cms_e($name) ?>"><?php foreach ((array) $fd['options'] as $ov => $ol): ?><option value="<?= cms_e((string) $ov) ?>"<?= (string) $ov === (string) ($fd['default'] ?? '') ? ' selected' : '' ?>><?= cms_e($ol) ?></option><?php endforeach; ?></select></div>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
    <div class="ad-btnrow">
      <button class="ad-btn" type="submit" data-import-go<?= (!$hasKey && $provider === 'openrouter') ? ' disabled title="Guarda primero la clave de OpenRouter"' : '' ?>>Analizar y crear borrador</button>
      <span class="ad-help" data-import-cost>Con <?= cms_e($provider === 'claude-cli' ? 'Claude Code (' . ($model ?: 'sonnet') . ')' : $defaultModel) ?>. Una página de 6 a 8 pantallas cuesta unos centavos y tarda de 1 a 3 minutos.</span>
    </div>
  </form>
  <div data-import-progress hidden style="margin-top:18px">
    <div class="ad-flash ok" data-import-status>Preparando…</div>
    <div data-import-thumbs style="display:flex;gap:6px;flex-wrap:wrap"></div>
  </div>
  <div data-import-result hidden style="margin-top:18px"></div>
</div>

<script>window.CMS_IMPORT = {endpoint: <?= json_encode(admin_url('importar')) ?>, screenW: <?= CMS_IMPORT_SCREEN_W ?>, screenH: <?= CMS_IMPORT_SCREEN_H ?>, pdfjs: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs', pdfjsWorker: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs'};</script>
<script src="<?= CMS_BASE ?>/cms/admin/assets/importar.js?v=<?= CMS_VERSION ?>"></script>
<?php admin_footer();
