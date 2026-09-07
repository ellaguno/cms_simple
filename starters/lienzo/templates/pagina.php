<?php /** Página del constructor. Variables: $lang, $S, $t, $page, $item, $def */ declare(strict_types=1);
$sections = array_values(array_filter((array) ($item['sections'] ?? []), fn($x) => is_array($x) && empty($x['hidden'])));
[$sharedHdr, $sharedFtr] = lz_shared();
if ($sharedHdr || $sharedFtr) $sections = array_values(array_filter($sections, fn($x) => !in_array($x['type'] ?? '', ['cabecera', 'pie'], true)));
if (!$sections) { echo '<main class="lz-empty"><h1>', cms_e($item['title'] ?? ''), '</h1><p>Esta página todavía no tiene secciones.</p></main>'; return; }
$ctx = ['lang' => $lang, 'S' => $S, 't' => $t, 'page' => $page, 'item' => $item];
$crumbs = lz_crumbs($page);
echo '<main class="lz-page">';
// las migas van después de la cabecera (propia o compartida) y antes del contenido
if ($crumbs !== '' && !$sharedHdr && ($sections[0]['type'] ?? '') === 'cabecera') { echo cms_sections_render([array_shift($sections)], $ctx), $crumbs; }
elseif ($crumbs !== '') echo $crumbs;
echo cms_sections_render($sections, $ctx), '</main>';
