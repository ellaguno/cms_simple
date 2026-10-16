<?php
/**
 * Prueba rápida (solo lectura) del campo 'rows' y de las referencias entre colecciones (1.55):
 *   php tools/rows-smoke.php
 * No escribe nada: arma el formulario y los elementos en memoria y comprueba admin_rows_control(), admin_read_rows(),
 * api_form_value() (lo que la API y la línea de comandos pasan por admin_read_field), cms_localize() sobre filas,
 * cms_rows(), cms_import_field() y la lógica de cms_referencing(). Sale con 0 si todo pasa.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REQUEST_URI'] = '/';
require_once __DIR__ . '/../cms/bootstrap.php';
require_once CMS_DIR . '/admin/inc/fields.php';
require_once CMS_DIR . '/admin/inc/api.php';
require_once CMS_DIR . '/lib/import.php';

$def = cms_default_lang();
$other = array_values(array_diff(cms_langs(), [$def]))[0] ?? '';
$fail = 0; $n = 0;
$ok = function (string $what, $got, $want) use (&$fail, &$n): void {
    $n++;
    if ($got === $want) { echo "ok   $what\n"; return; }
    $fail++;
    echo "FAIL $what: " . var_export($got, true) . ' (esperaba ' . var_export($want, true) . ")\n";
};

$field = ['type' => 'rows', 'label' => 'Capacidades', 'max' => 3, 'add' => 'Agregar capacidad', 'row_title' => 'title', 'fields' => [
    'title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
    'icon'  => ['type' => 'text', 'label' => 'Ícono', 'half' => true],
    'on'    => ['type' => 'checkbox', 'label' => 'Destacar', 'half' => true],
    'body'  => ['type' => 'html', 'label' => 'Texto'],
    'skip'  => ['type' => 'sections', 'label' => 'No se admite'],
]];

// 1) subcampos admitidos: sin secciones ni filas anidadas; html → textarea
$sub = admin_rows_fields($field);
$ok('subcampos admitidos', array_keys($sub), ['title', 'icon', 'on', 'body']);
$ok('html se dibuja como textarea', $sub['body']['type'], 'textarea');

// 2) el control dibuja cada fila, la plantilla y el botón
$rows = [['title' => [$def => 'Uno'], 'icon' => 'a', 'on' => true, 'body' => 'x'], ['title' => [$def => 'Dos'], 'icon' => 'b', 'on' => false, 'body' => '']];
$html = admin_rows_control('caps', $field, $rows);
$ok('dos filas dibujadas', substr_count($html, 'data-row data-row-key="r'), 2);
$ok('nombre de subcampo bilingüe', strpos($html, 'name="caps[r0][title][' . $def . ']"') !== false, true);
$ok('plantilla con __ROW__', strpos($html, 'name="caps[__ROW__][icon]"') !== false, true);
$ok('tope declarado', strpos($html, 'data-max="3"') !== false, true);
$ok('valor escapado', strpos(admin_rows_control('caps', $field, [['icon' => '<b>"x"</b>']]), '&lt;b&gt;&quot;x&quot;') !== false, true);

// 3) lectura: orden del formulario, filas vacías fuera, plantilla ignorada, tope
$post = [
    'n2' => ['title' => [$def => 'Segunda'], 'icon' => 'z', 'on' => '0', 'body' => ''],
    'r0' => ['title' => [$def => 'Primera'], 'icon' => '', 'on' => '1', 'body' => "<p>hola</p>"],
    'r1' => ['title' => [$def => ''], 'icon' => '', 'on' => '0', 'body' => ''],   // vacía → fuera
    '__ROW__' => ['title' => [$def => 'plantilla'], 'icon' => 'q'],
];
$read = admin_read_rows($field, $post);
$ok('dos filas leídas', count($read), 2);
$ok('respeta el orden del formulario', $read[0]['title'][$def] ?? null, 'Segunda');
$ok('casilla leída', $read[1]['on'], true);
$ok('casilla apagada', $read[0]['on'], false);
$ok('todos los subcampos presentes', array_keys($read[0]), ['title', 'icon', 'on', 'body']);
$many = []; for ($i = 0; $i < 6; $i++) $many['n' . $i] = ['icon' => 'i' . $i];
$ok('tope de filas', count(admin_read_rows($field, $many)), 3);
$ok('valor que no es lista', admin_read_rows($field, 'texto'), []);
$ok('admin_read_field no lo trata como bilingüe', count(admin_read_field('caps', $field + ['i18n' => true], ['caps' => $post])), 2);

// 4) API: lo guardado vuelve a pasar por el formulario y da lo mismo
$back = admin_read_rows($field, api_form_value($field, $read));
$ok('ida y vuelta por la API', $back, $read);
$ok('API: un texto en un subcampo bilingüe va al idioma principal', admin_read_rows($field, api_form_value($field, [['title' => 'Solo texto']]))[0]['title'][$def] ?? null, 'Solo texto');

// 5) en el sitio público: cms_localize resuelve cada subcampo y cms_rows da la lista
$item = ['slug' => 'x', 'caps' => [['title' => [$def => 'Hola'] + ($other ? [$other => 'Hello'] : []), 'icon' => 'a'], 'basura']];
$loc = cms_localize($item, $other ?: $def);
$ok('subcampo localizado', cms_rows($loc, 'caps')[0]['title'], $other ? 'Hello' : 'Hola');
$ok('cms_rows descarta lo que no es fila', count(cms_rows($loc, 'caps')), 1);
$ok('cms_rows de un campo ausente', cms_rows($item, 'nada'), []);

// 6) valor inicial en bloques y en la muestra
$bd = cms_block_data(['fields' => ['caps' => $field]], []);
$ok('bloque sin dato → lista vacía', $bd['caps'], []);

// 7) esquema del importador: lista de objetos con sus subcampos
[$prop] = cms_import_field('caps', $field);
$ok('esquema: arreglo de objetos', [$prop['type'], $prop['items']['type']], ['array', 'object']);
$ok('esquema: subcampos', array_keys($prop['items']['properties']), ['title', 'icon', 'on', 'body']);

// 8) cms_referencing / cms_refs sin tocar disco: tipo inexistente → vacío
$ok('referencing de tipo inexistente', cms_referencing('no-existe-' . mt_rand(), 'modules', 'tickets', 'module'), []);
$ok('refs de tipo inexistente', cms_refs('no-existe-' . mt_rand(), ['a']), []);

echo "\n" . ($n - $fail) . "/$n" . ($fail ? " — $fail fallaron\n" : " — todo bien\n");
exit($fail ? 1 : 0);
