<?php /** Página del constructor. Variables: $lang, $S, $t, $page, $item, $def */ declare(strict_types=1);
$sections = (array) ($item['sections'] ?? []);
if (!$sections) { echo '<main class="lz-empty"><h1>', cms_e($item['title'] ?? ''), '</h1><p>Esta página todavía no tiene secciones.</p></main>'; return; }
echo '<main class="lz-page">', cms_sections_render($sections, ['lang' => $lang, 'S' => $S, 't' => $t, 'page' => $page, 'item' => $item]), '</main>';
