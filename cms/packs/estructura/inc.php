<?php
/** Helpers del paquete estructura (cabecera y pie genéricos). */
declare(strict_types=1);

/** "a | b | c" → [a, b, c] con $n partes. */
function es_split(string $line, int $n): array { return array_pad(array_map('trim', explode('|', $line, $n)), $n, ''); }

/** "Texto > URL" → [texto, url]. */
function es_link(string $cell): array { $p = array_map('trim', explode('>', $cell, 2)); return [$p[0], $p[1] ?? '']; }

/** URL de un enlace del bloque: vacío → #, relativa al sitio → con la base, #ancla y absolutas tal cual. */
function es_href(string $url, string $lang): string
{
    $url = trim($url);
    if ($url === '') return '#';
    if ($url[0] === '#' || preg_match('#^(https?:|mailto:|tel:|//)#i', $url)) return $url;
    return cms_menu_url($url, $lang);
}

/**
 * Entradas de menú a partir de las líneas del campo: [['label', 'url', 'children' => [...]], …].
 * "- Texto | URL" cuelga de la entrada anterior; "@menu" inserta el menú de Diseño → Menú.
 */
function es_menu(array $lines, string $lang): array
{
    $out = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '') continue;
        if ($line === '@menu') { foreach (cms_menu($lang) as $m) $out[] = ['label' => (string) ($m['label'] ?? ''), 'url' => cms_menu_url((string) ($m['url'] ?? '/'), $lang), 'new_tab' => !empty($m['new_tab']), 'children' => []]; continue; }
        $child = false;
        if (preg_match('/^[-•*]\s+/u', $line)) { $child = true; $line = preg_replace('/^[-•*]\s+/u', '', $line); }
        [$txt, $url] = es_split($line, 2);
        if ($txt === '') continue;
        $entry = ['label' => $txt, 'url' => es_href($url, $lang), 'new_tab' => false, 'children' => []];
        if ($child && $out) $out[count($out) - 1]['children'][] = $entry; else $out[] = $entry;
    }
    return $out;
}

/** Datos de contacto: "@ajustes" → correo, teléfono y WhatsApp de Ajustes; el resto tal cual. Devuelve [[texto, href], …]. */
function es_contact(array $lines): array
{
    $S = cms_settings(); $out = [];
    foreach ($lines as $c) {
        $c = trim((string) $c);
        if ($c === '') continue;
        if ($c === '@ajustes') {
            if (!empty($S['email'])) $out[] = [(string) $S['email'], 'mailto:' . $S['email']];
            if (!empty($S['phone'])) $out[] = [(string) $S['phone'], cms_tel_href()];
            if (!empty($S['whatsapp'])) $out[] = ['WhatsApp', cms_whatsapp_url()];
            if (!empty($S['address'])) $out[] = [(string) $S['address'], ''];
            continue;
        }
        $h = filter_var($c, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $c : (preg_match('/^\+?[\d\s()-]{7,}$/', $c) ? 'tel:' . preg_replace('/[^\d+]/', '', $c) : '');
        $out[] = [$c, $h];
    }
    return $out;
}

/** Redes: "@ajustes" → las de Ajustes con su icono; "Nombre | URL" → texto. Devuelve HTML de enlaces. */
function es_social(array $lines): string
{
    $h = '';
    foreach ($lines as $l) {
        $l = trim((string) $l);
        if ($l === '') continue;
        if ($l === '@ajustes') { $h .= cms_social_html('es-social-icons'); continue; }
        [$n, $u] = es_split($l, 2);
        if ($n !== '') $h .= '<a href="' . cms_e($u !== '' ? $u : '#') . '" target="_blank" rel="noopener">' . cms_e($n) . '</a>';
    }
    return $h;
}
