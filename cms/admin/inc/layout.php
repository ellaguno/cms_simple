<?php
/** cms_simple admin — layout (cabecera con navegación, pie con scripts). */
declare(strict_types=1);

/**
 * Entradas del menú lateral (1.29: cinco grupos plegables). Cada entrada es [label, href] o un grupo
 * ['group' => label, 'items' => [clave => [label, href], …], 'collapsed' => bool]. Las colecciones van en "Contenido",
 * salvo las que declaren 'group' => 'Nombre' en site/config.php, que conservan su grupo propio.
 */
function admin_nav(): array
{
    $nav = ['dashboard' => ['Inicio', admin_url('dashboard')]];
    // Contenido: las colecciones sin grupo propio, Medios y Mapa del sitio; las colecciones con 'group' conservan su grupo
    $content = []; $groups = [];
    $internal = [];
    foreach (cms_config('types') as $k => $def) {
        $entry = [$def['label'] ?? $k, admin_url('content', ['type' => $k])];
        if (!empty($def['internal'])) { $internal['content:' . $k] = $entry; continue; }   // cabeceras y pies: en Diseño
        $g = trim((string) ($def['group'] ?? ''));
        if ($g === '' || cms_slugify($g) === 'contenido') { $content['content:' . $k] = $entry; continue; }   // "Contenido" es el grupo por defecto
        $gk = 'group:' . cms_slugify($g);
        if (!isset($groups[$gk])) $groups[$gk] = ['group' => $g, 'items' => []];
        $groups[$gk]['items']['content:' . $k] = $entry;
    }
    $content['media'] = ['Medios', admin_url('media')];
    $content['map'] = ['Mapa del sitio', admin_url('map')];
    // páginas propias de los paquetes activos: 'admin' => ['label' => …, 'file' => …, 'group' => contenido|diseno|ajustes|sistema]
    // (por defecto en Contenido: Audio, Redacción IA… producen contenido; sus ajustes ya están en Ajustes)
    $packPages = ['contenido' => [], 'diseno' => [], 'ajustes' => [], 'sistema' => []];
    foreach (cms_packs() as $pn => $pk) {
        if (empty($pk['admin']['file'])) continue;
        $g = (string) ($pk['admin']['group'] ?? 'contenido');
        $packPages[isset($packPages[$g]) ? $g : 'contenido']['pack:' . $pn] = [(string) ($pk['admin']['label'] ?? $pk['label']), admin_url('pack:' . $pn)];
    }
    $nav['group:contenido'] = ['group' => 'Contenido', 'items' => $content + $packPages['contenido']];
    $nav += $groups;
    // Diseño: temas y variaciones, menú, textos, código del tema e importar (solo si el tema tiene constructor)
    $design = ['diseno' => ['Diseño', admin_url('diseno')], 'menu' => ['Menú', admin_url('menu')], 'strings' => ['Textos del sitio', admin_url('strings')]];
    $design += $internal;
    $design['categorias'] = ['Categorías', admin_url('categorias')];
    if (cms_config('code_editor', true) !== false) $design['code'] = ['Código del tema', admin_url('code')];
    if (cms_config('importer', true) !== false && cms_builder_type() !== null) $design['importar'] = ['Importar diseño', admin_url('importar')];
    $nav['group:diseno'] = ['group' => 'Diseño', 'items' => $design + $packPages['diseno']];
    // Ajustes: ajustes y redirecciones (los ajustes de cada paquete son una pestaña de Ajustes)
    $nav['group:ajustes'] = ['group' => 'Ajustes', 'items' => ['settings' => ['Ajustes', admin_url('settings')], 'redirects' => ['Redirecciones 301', admin_url('redirects')]] + $packPages['ajustes']];
    // Sistema: lo que se abre pocas veces; plegado por defecto
    $nav['group:sistema'] = ['group' => 'Sistema', 'collapsed' => true, 'items' => [
        'catalogo' => ['Temas y paquetes', admin_url('catalogo')], 'backup' => ['Respaldos', admin_url('backup')],
        'users' => ['Usuarios', admin_url('users')], 'actualizar' => ['Actualizar', admin_url('actualizar')],
    ] + $packPages['sistema']];
    $nav['manual'] = ['Manual', admin_url('manual')];
    return $nav;
}

function admin_header(string $title, string $active = ''): void
{
    $u = admin_user();
    $S = cms_settings();
    $site = $S['site_name'] ?? cms_config('name');
    $logo = cms_config('admin_logo') ?: ($S['logo'] ?? '');
    $assets = CMS_BASE . '/cms/admin/assets';
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= cms_e($title) ?> · Admin <?= cms_e($site) ?></title>
<link rel="icon" href="<?= !empty($S['favicon']) ? cms_e(cms_img($S['favicon'])) : 'data:,' ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<?php if ($active === 'code'): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css">
<?php endif; ?>
<link rel="stylesheet" href="<?= $assets ?>/admin.css?v=<?= CMS_VERSION ?>">
</head>
<body>
<div class="ad-shell">
<?php if ($u): ?>
  <aside class="ad-side">
    <a class="ad-brand" href="<?= admin_url() ?>"><?php if ($logo): ?><img src="<?= cms_e(cms_img($logo)) ?>" alt="<?= cms_e($site) ?>"><?php else: ?><strong><?= cms_e($site) ?></strong><?php endif; ?><span>Admin</span></a>
    <nav class="ad-nav">
<?php foreach (admin_nav() as $k => $entry): if (isset($entry['group'])): $inside = isset($entry['items'][$active]) || ($active === 'password' && isset($entry['items']['users'])); ?>
      <details class="ad-nav-group" data-nav-group="<?= cms_e($k) ?>"<?= $inside ? ' open data-active' : '' ?><?= !empty($entry['collapsed']) ? ' data-collapsed' : '' ?>>
        <summary><?= cms_e($entry['group']) ?></summary>
<?php foreach ($entry['items'] as $ik => [$label, $href]): ?>
        <a href="<?= cms_e($href) ?>"<?= $active === $ik ? ' class="on"' : '' ?>><?= cms_e($label) ?></a>
<?php endforeach; ?>
      </details>
<?php else: [$label, $href] = $entry; ?>
      <a href="<?= cms_e($href) ?>"<?= $active === $k ? ' class="on"' : '' ?>><?= cms_e($label) ?></a>
<?php endif; endforeach; ?>
    </nav>
    <div class="ad-side-foot">
<?php foreach (cms_active_langs() as $l): ?>
      <a href="<?= cms_url('home', $l) ?>" target="_blank" rel="noopener">Ver sitio <?= strtoupper($l) ?> ↗</a>
<?php endforeach; ?>
      <span class="ad-user"><?= cms_e($u['name'] ?? $u['user']) ?></span>
      <a href="<?= admin_url('logout') ?>">Salir</a>
      <small class="ad-version">cms_simple <?= CMS_VERSION ?><?php [$u] = cms_update_check(false); if ($u && $u['newer']): ?> · <a href="<?= admin_url('actualizar') ?>">actualizar a <?= cms_e($u['version']) ?></a><?php endif; ?></small>
    </div>
  </aside>
<?php endif; ?>
  <main class="ad-main">
    <h1 class="ad-title"><?= cms_e($title) ?></h1>
<?php foreach (admin_flashes() as $f): ?>
    <div class="ad-flash <?= cms_e($f['type']) ?>"><?= cms_e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    $assets = CMS_BASE . '/cms/admin/assets';
    $code = ($_GET['p'] ?? '') === 'code';
    ?>
  </main>
</div>
<script>window.CMS_ADMIN = {base: <?= json_encode(CMS_BASE) ?>, upload: <?= json_encode(admin_url('upload')) ?>, media: <?= json_encode(admin_url('media', ['json' => 1])) ?>, csrf: <?= json_encode(admin_csrf()) ?>, langs: <?= json_encode(cms_langs()) ?>, defaultLang: <?= json_encode(cms_default_lang()) ?>};</script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<?php if ($code): $cm = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16'; foreach (['codemirror.min.js', 'mode/xml/xml.min.js', 'mode/javascript/javascript.min.js', 'mode/css/css.min.js', 'mode/htmlmixed/htmlmixed.min.js', 'mode/clike/clike.min.js', 'mode/php/php.min.js', 'mode/markdown/markdown.min.js', 'addon/edit/matchbrackets.min.js', 'addon/edit/closebrackets.min.js', 'addon/edit/closetag.min.js', 'addon/selection/active-line.min.js'] as $f): ?>
<script src="<?= $cm . '/' . $f ?>"></script>
<?php endforeach; endif; ?>
<script src="<?= $assets ?>/admin.js?v=<?= CMS_VERSION ?>"></script>
<script src="<?= $assets ?>/builder.js?v=<?= CMS_VERSION ?>"></script>
</body>
</html>
<?php
}
