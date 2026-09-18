<?php
/**
 * Dibuja una sección del constructor tal como quedaría en el sitio y devuelve su HTML (para "Convertir a HTML":
 * el constructor la sustituye por un bloque "HTML libre" con ese marcado). POST con los campos de la tarjeta
 * renombrados a sec[0][…]; responde texto plano.
 */
declare(strict_types=1);
if (!admin_is_post()) { http_response_code(400); exit('Petición inválida.'); }
admin_csrf_check();
header('Content-Type: text/plain; charset=utf-8');
$secs = admin_read_sections($_POST['sec'] ?? null);
if (!$secs) { http_response_code(422); exit('Bloque desconocido.'); }
$lang = in_array((string) ($_POST['lang'] ?? ''), cms_langs(), true) ? (string) $_POST['lang'] : cms_default_lang();
$GLOBALS['cms_render_lang'] = $lang;
$sec = $secs[0];
// campos por idioma: el valor de ese idioma (o del predeterminado), como hace cms_localize al leer un elemento
$def = cms_block($sec['type']);
foreach ((array) ($def['fields'] ?? []) as $k => $fd) if (!empty($fd['i18n']) && is_array($sec['data'][$k] ?? null) && cms_is_i18n_value($sec['data'][$k])) $sec['data'][$k] = $sec['data'][$k][$lang] ?? ($sec['data'][$k][cms_default_lang()] ?? '');
$page = ['lang' => $lang, 'path' => '', 'route' => 'render', 'sections' => [$sec]];
$GLOBALS['cms_current'] = ['type' => cms_builder_type(), 'item' => null, 'page' => $page, 'lang' => $lang];
echo cms_sections_render([$sec], ['lang' => $lang, 'S' => cms_settings(), 'page' => $page, 'builder' => false]);
