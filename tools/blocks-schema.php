<?php
/**
 * Exporta el catálogo de bloques del sitio (tema + paquetes) para el importador de diseños (cms/lib/import.php).
 *
 *   php tools/blocks-schema.php            → JSON: 'catalog' (Markdown para el prompt), 'schema' (JSON Schema de la salida),
 *                                            'meta' (campos bilingües y de líneas por bloque), 'rules' (prompt sin capa de texto)
 *   php tools/blocks-schema.php catalog    → solo el catálogo en Markdown
 *   CMS_ROOT=/ruta/a/otro/sitio php tools/blocks-schema.php   → el catálogo de otro sitio (katapolt)
 *
 * Lo usa tools/import-design.py (experimento desde la línea de comandos); el panel usa la biblioteca directamente.
 */
declare(strict_types=1);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$root = getenv('CMS_ROOT') ?: dirname(__DIR__);
require $root . '/cms/bootstrap.php';
require_once CMS_DIR . '/lib/import.php';

$cat = cms_import_catalog();
if (($argv[1] ?? '') === 'catalog') { echo $cat['catalog']; exit; }
$screens = (int) ($argv[1] ?? 0);
$files = array_slice($argv, 2);
echo json_encode([
    'site' => cms_settings()['site_name'] ?? cms_config('name', basename($root)),
    'catalog' => $cat['catalog'], 'schema' => $cat['schema'], 'meta' => $cat['meta'],
    'prompt' => $screens > 0 ? cms_import_prompt($screens, '__TEXTO__', $files) : '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
