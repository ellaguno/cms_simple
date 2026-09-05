<?php
/**
 * Vista previa en vivo del constructor: recibe el formulario del editor (POST, sin guardar), inyecta el elemento en
 * memoria y deja que el enrutador público lo dibuje con el tema, dentro del iframe del panel.
 */
declare(strict_types=1);
$type = (string) ($_GET['type'] ?? '');
$def = cms_type($type);
if (!$def || !admin_is_post()) { http_response_code(400); exit('Vista previa: petición inválida.'); }
admin_csrf_check();

$fields = (array) ($def['fields'] ?? []);
if (!empty($def['tree'])) $fields = ['parent' => ['type' => 'select']] + $fields;
$orig = cms_slugify((string) ($_GET['slug'] ?? ''));
$existing = $orig ? (cms_item($type, $orig, false) ?? []) : [];
[$item, $errors] = admin_read_item($type, $def, $fields, $existing, $orig);
if ($item['slug'] === '') $item['slug'] = 'vista-previa';
if (!empty($def['tree'])) { $all = cms_items($type, false); if ($orig && $orig !== $item['slug']) unset($all[$orig]); $all[$item['slug']] = $item; $item['path'] = cms_tree_path($type, $all, $item['slug']); }
// el elemento en edición sustituye al guardado (o existe solo en memoria si es nuevo); el original renombrado desaparece
$GLOBALS['cms_item_override'] = [$type => [$item['slug'] => $item]];
if ($orig && $orig !== $item['slug']) $GLOBALS['cms_item_override'][$type][$orig] = ['slug' => $orig, 'status' => 'draft', 'title' => '', 'hidden_in_preview' => true];
$GLOBALS['cms_builder'] = true;
cms_items_flush();

$lang = cms_default_lang();
$url = cms_url('item:' . $type, $lang, $item['slug']);
$path = trim((string) parse_url($url, PHP_URL_PATH), '/');
if (CMS_BASE !== '' && strpos('/' . $path, CMS_BASE) === 0) $path = trim(substr('/' . $path, strlen(CMS_BASE)), '/');
$_GET = ['p' => $path, 'preview' => cms_preview_token($type, $item['slug'])];
$_SERVER['REQUEST_METHOD'] = 'GET';
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy: frame-ancestors \'self\'');
require CMS_DIR . '/router.php';
