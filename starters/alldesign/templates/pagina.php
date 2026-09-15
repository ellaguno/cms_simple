<?php /** Página del constructor (portada, Nosotros, Servicios, Portafolio y sus categorías, Contacto…). Variables: $lang, $S, $t, $page, $item, $def */ declare(strict_types=1);
$sections = array_values(array_filter((array) ($item['sections'] ?? []), fn($x) => is_array($x) && empty($x['hidden'])));
if (!$sections) { echo '<div class="ad-container ad-empty-page"><h1 class="ad-h-light">', cms_e(cms_f($item, 'title', $lang)), '</h1><p>Esta página todavía no tiene secciones.</p></div>'; return; }
echo cms_sections_render($sections, ['lang' => $lang, 'S' => $S, 't' => $t, 'page' => $page, 'item' => $item]);
