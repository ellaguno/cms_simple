<?php
/**
 * cms_simple — importador de diseños: de un PDF o imagen de una página a un borrador del constructor.
 *
 * Flujo (ver cms/admin/pages/importar.php y cms/admin/assets/importar.js):
 *   1. El navegador rasteriza el archivo (pdf.js) en "pantallas" de 1400 px de ancho y extrae su capa de texto.
 *   2. Este archivo arma el prompt con el catálogo de bloques del sitio (cms_blocks()) y un JSON Schema de la
 *      salida, y llama al modelo: OpenRouter (cualquier modelo con visión, clave en Ajustes) o la CLI de Claude Code
 *      instalada en el equipo (desarrollo local, sin clave).
 *   3. La respuesta se convierte en secciones {id, type, data, style, hidden} y se guarda como borrador.
 *
 * Ajustes (data/settings.json): import_provider (openrouter | claude-cli), import_model, openrouter_key.
 * Desactivar la función: 'importer' => false en site/config.php.
 */
declare(strict_types=1);

define('CMS_IMPORT_SCREEN_W', 1400);
define('CMS_IMPORT_SCREEN_H', 1100);

/* ------------------------------------------------------------------ catálogo y schema */

/** Traduce un campo del CMS a [propiedad JSON Schema, descripción legible para el prompt]. */
function cms_import_field(string $name, array $f): array
{
    $t = (string) ($f['type'] ?? 'text');
    $label = trim((string) ($f['label'] ?? $name));
    $desc = $label;
    if (!empty($f['placeholder'])) $desc .= ' (ej. ' . $f['placeholder'] . ')';
    $opts = (array) ($f['options'] ?? []);
    $optHuman = implode(' | ', array_map(fn($k, $v) => ((string) $k === '' ? '""' : $k) . " ($v)", array_keys($opts), array_values($opts)));
    switch ($t) {
        case 'lines':
            $prop = ['type' => 'array', 'items' => ['type' => 'string'], 'description' => $desc];
            $human = "lista de textos: $label";
            break;
        case 'checkbox':
            $prop = ['type' => 'boolean', 'description' => trim(($f['label'] ?? '') . ': ' . ($f['text'] ?? ''), ': ')];
            $human = 'sí/no: ' . trim((string) ($f['text'] ?? $label));
            break;
        case 'number':
            $prop = ['type' => 'integer', 'description' => $desc . (isset($f['min'], $f['max']) ? " (de {$f['min']} a {$f['max']})" : '')];
            $human = "número: $label";
            break;
        case 'select':
            $prop = ['type' => 'string', 'enum' => array_map('strval', array_keys($opts)), 'description' => $desc . '. Opciones: ' . $optHuman];
            $human = "opción ($label): $optHuman";
            break;
        case 'image':
            $prop = ['type' => 'string', 'description' => $desc . '. Deja vacío; si el diseño trae una imagen aquí, descríbela en "note" de la sección.'];
            $human = "imagen: $label (dejar vacío y describir en note)";
            break;
        case 'html':
        case 'code':
            $prop = ['type' => 'string', 'description' => $desc . ' (HTML sencillo: p, h2, h3, ul, li, strong, em, a)'];
            $human = "HTML: $label";
            break;
        default:
            $prop = ['type' => 'string', 'description' => $desc];
            $human = ($t === 'textarea' ? 'párrafo' : 'texto') . ": $label";
    }
    if (isset($f['default']) && !is_array($f['default'])) $human .= ' [por defecto: ' . (is_bool($f['default']) ? ($f['default'] ? 'sí' : 'no') : $f['default']) . ']';
    if (!empty($f['required'])) $human .= ' (obligatorio)';
    return [$prop, $human];
}

/** Estilos de sección que un diseño estático permite deducir. */
function cms_import_style_keys(): array
{
    return array_diff(array_keys(cms_section_styles()), ['bg_image', 'class', 'hide_mobile', 'effect']);
}

/**
 * Catálogo del sitio para el importador: ['catalog' => Markdown para el prompt, 'schema' => JSON Schema de la
 * respuesta, 'meta' => por bloque: label, campos i18n y campos de líneas].
 */
function cms_import_catalog(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $styles = cms_section_styles();
    $keys = cms_import_style_keys();
    $md = ''; $variants = []; $meta = [];
    foreach (cms_blocks() as $key => $def) {
        if (str_starts_with((string) $key, '_')) continue;
        $props = []; $req = []; $lines = [];
        foreach ((array) ($def['fields'] ?? []) as $fname => $f) {
            [$prop, $human] = cms_import_field((string) $fname, (array) $f);
            $props[$fname] = $prop;
            if (!empty($f['required'])) $req[] = $fname;
            $lines[] = "  - `$fname`: $human";
        }
        $allowed = array_intersect(array_keys(cms_block_styles($def)), $keys);
        $md .= "### `$key` — " . ($def['label'] ?? $key) . ' (' . ($def['group'] ?? 'Bloque') . ")\n" . ($def['desc'] ?? '') . "\n";
        $md .= $lines ? "Campos:\n" . implode("\n", $lines) . "\n" : "Sin campos.\n";
        $md .= 'Estilos permitidos: ' . ($allowed ? implode(', ', $allowed) : 'ninguno') . "\n\n";
        $styleProps = [];
        foreach ($allowed as $sk) { [$sp] = cms_import_field($sk, (array) $styles[$sk]); $styleProps[$sk] = $sp; }
        $meta[$key] = [
            'label' => (string) ($def['label'] ?? $key),
            'i18n'  => array_keys(array_filter((array) ($def['fields'] ?? []), fn($f) => !empty($f['i18n']))),
            'lines' => array_keys(array_filter((array) ($def['fields'] ?? []), fn($f) => ($f['type'] ?? '') === 'lines')),
        ];
        $variants[] = [
            'type' => 'object',
            'properties' => [
                'type'   => ['type' => 'string', 'enum' => [$key]],
                'data'   => ['type' => 'object', 'properties' => $props ?: new stdClass, 'required' => $req, 'additionalProperties' => false],
                'style'  => ['type' => 'object', 'properties' => $styleProps ?: new stdClass, 'additionalProperties' => false],
                'note'   => ['type' => 'string', 'description' => 'Qué del diseño no cupo en este bloque, imágenes que faltan (descríbelas), o dudas. Vacío si todo encajó.'],
                'screen' => ['type' => 'integer', 'description' => 'Número de pantalla (imagen) donde empieza esta sección'],
            ],
            'required' => ['type', 'data', 'style', 'note', 'screen'],
            'additionalProperties' => false,
        ];
    }
    $md .= "Estilos de sección (claves de `style`):\n";
    foreach ($keys as $sk) { [, $h] = cms_import_field($sk, (array) $styles[$sk]); $md .= "- `$sk`: $h\n"; }
    $palette = cms_sections_palette();
    $md .= "\nPaleta de fondos (`style.bg`): " . implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($palette), array_values($palette))) . "\n";
    $schema = [
        'type' => 'object',
        'properties' => [
            'title'    => ['type' => 'string', 'description' => 'Título de la página (para el panel)'],
            'summary'  => ['type' => 'string', 'description' => 'Descripción para buscadores: 1 o 2 frases con el mensaje principal del diseño'],
            'lang'     => ['type' => 'string', 'description' => 'Idioma del texto del diseño: es, en…'],
            'palette'  => ['type' => 'object', 'description' => 'Colores dominantes del diseño en hex', 'properties' => [
                'primary' => ['type' => 'string'], 'accent' => ['type' => 'string'], 'background' => ['type' => 'string'], 'text' => ['type' => 'string'],
            ], 'required' => ['primary', 'accent', 'background', 'text'], 'additionalProperties' => false],
            'fonts'    => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tipografías que se aprecian (nombre o familia)'],
            'sections' => ['type' => 'array', 'items' => ['anyOf' => $variants]],
            'unmapped' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Partes del diseño que no encajaron en ningún bloque, con la pantalla donde están'],
        ],
        'required' => ['title', 'summary', 'lang', 'palette', 'fonts', 'sections', 'unmapped'],
        'additionalProperties' => false,
    ];
    return $cache = ['catalog' => "## Bloques disponibles (" . count($variants) . ")\n\n" . $md, 'schema' => $schema, 'meta' => $meta];
}

/**
 * Prompt del importador. $screens: cuántas pantallas; $files: rutas si el modelo debe leerlas él mismo (CLI);
 * $text: capa de texto extraída (o '' si no hay).
 */
function cms_import_prompt(int $screens, string $text, array $files = []): string
{
    $site = (string) (cms_settings()['site_name'] ?? cms_config('name', ''));
    $how = $files
        ? "Lee TODAS las pantallas con la herramienta Read, en orden, antes de responder:\n" . implode("\n", array_map(fn($i, $p) => '  ' . ($i + 1) . ". $p", array_keys($files), $files))
        : "Las pantallas van adjuntas como imágenes, en orden: pantalla 1, pantalla 2… hasta la $screens.";
    return <<<TXT
Eres el importador de diseños de un CMS. Te doy el diseño de UNA página web como $screens imágenes ("pantallas", de
arriba abajo, con un pequeño solape entre una y la siguiente) y la capa de texto extraída del archivo. Tu tarea:
reconstruir la página como una lista ordenada de secciones usando SOLO los bloques del catálogo, para que quien edita
arranque con la estructura y el texto ya puestos y solo ajuste detalles.

$how

Reglas:
- Una sección por banda visual del diseño, de arriba abajo. No omitas bandas; no inventes contenido que no esté.
  Un título de sección con su texto de apoyo forma parte de la misma sección que las tarjetas, lista o imagen que
  encabeza: no lo separes en un bloque "texto" aparte.
- Ignora la cabecera de navegación (logo + menú) y el pie de página: el tema los pone solo. Si el pie tiene un llamado
  a la acción o un formulario, eso sí es una sección (cta).
- Elige el bloque que mejor represente cada banda. Si dudas entre dos, prefiere el más específico (tarjetas, planes,
  faq, testimonio, cifras…) sobre "texto". Usa "texto" solo para prosa libre y "html" nunca.
- Copia los textos EXACTOS de la capa de texto (acentos, mayúsculas, signos). Si un título tiene una parte resaltada
  en color, envuélvela en <span class="gradient-text">…</span>.
- Botones: "Texto | URL | estilo". URL: la visible; si no se ve, "#". Estilo: primary el principal, secondary o
  outline los demás.
- Listas de tarjetas, insignias o puntos: una entrada por línea con el formato que indica el campo.
- Los bloques planes, faq, equipo y articulos toman su contenido de colecciones del sitio; úsalos si el diseño
  muestra precios, preguntas frecuentes, equipo o artículos, y describe en "note" lo que el diseño muestra (planes,
  precios, preguntas) para cargarlo después en la colección.
- Imágenes: deja el campo vacío y describe en "note" qué imagen va (contenido, orientación, tamaño aproximado).
- style: solo cuando el diseño lo pide claramente. bg según el fondo de la banda; text "light" solo sobre fondos
  oscuros; align "center" si todo el bloque va centrado; pad si la banda es notablemente más alta o más baja de lo
  normal. Deja "" (vacío) para lo que no aplica. En el hero, "shader" solo si el fondo es animado o con ondas o
  degradado; si es plano, false.
- screen: el número de pantalla donde empieza la sección.
- unmapped: partes del diseño que no caben en ningún bloque (con la pantalla). palette y fonts: lo que se aprecie.
- lang: idioma del texto del diseño.
- Responde únicamente con el JSON pedido.

Sitio de destino: $site.

TXT . cms_import_catalog()['catalog'] . "\n## Capa de texto del archivo (en orden, puede traer restos de maquetación)\n\n"
        . ($text !== '' ? $text : '(sin capa de texto: lee el texto de las imágenes)') . "\n";
}

/* ------------------------------------------------------------------ materialización */

/**
 * Convierte la respuesta del modelo en un elemento del tipo $type (borrador). Devuelve [item, notas[]].
 * $extra: campos adicionales del elemento (brand, parent…). $source: nombre del archivo importado.
 */
function cms_import_materialize(array $result, string $type, string $slug, string $lang, array $extra = [], string $source = ''): array
{
    $meta = cms_import_catalog()['meta'];
    $langs = cms_langs();
    $lang = in_array(substr((string) ($result['lang'] ?? ''), 0, 2), $langs, true) ? substr((string) $result['lang'], 0, 2) : $lang;
    $i18n = fn($v) => [$lang => $v];
    $notes = []; $sections = [];
    foreach ((array) ($result['sections'] ?? []) as $s) {
        $t = (string) ($s['type'] ?? '');
        $m = $meta[$t] ?? null;
        if (!$m) { $notes[] = "Bloque desconocido «$t» descartado."; continue; }
        $data = [];
        foreach ((array) ($s['data'] ?? []) as $k => $v) {
            if ($v === '' || $v === null || $v === []) continue;
            if (in_array($k, $m['lines'], true) && is_string($v)) $v = array_values(array_filter(array_map('trim', explode("\n", $v)), 'strlen'));
            $data[$k] = in_array($k, $m['i18n'], true) ? $i18n($v) : $v;
        }
        $style = array_filter((array) ($s['style'] ?? []), fn($v) => $v !== '' && $v !== null && $v !== false);
        $sections[] = ['id' => substr(bin2hex(random_bytes(4)), 0, 6), 'type' => $t, 'data' => $data, 'style' => $style, 'hidden' => false];
        if (!empty($s['note'])) $notes[] = '[' . $m['label'] . '] pantalla ' . (int) ($s['screen'] ?? 0) . ': ' . $s['note'];
    }
    $today = date('Y-m-d');
    $def = cms_type($type) ?: [];
    $titleField = $def['title_field'] ?? 'title';
    $item = ['slug' => $slug, 'status' => 'draft', $titleField => $i18n((string) ($result['title'] ?? $slug)), 'sections' => $sections];
    foreach ((array) ($def['fields'] ?? []) as $name => $fd) {
        if (isset($item[$name]) || isset($extra[$name])) continue;
        if ($name === 'summary') { $item[$name] = $i18n((string) ($result['summary'] ?? '')); continue; }
        $d = $fd['default'] ?? '';
        if ($name === 'order' && ($fd['type'] ?? '') === 'number') $d = count(cms_items($type, false)) + 1;
        $item[$name] = !empty($fd['i18n']) ? array_fill_keys($langs, $d) : $d;
    }
    $item = $extra + $item;
    $item += ['created' => $today, 'updated' => $today];
    $item['import'] = [
        'source' => $source, 'date' => $today, 'lang' => $lang,
        'palette' => $result['palette'] ?? null, 'fonts' => $result['fonts'] ?? [],
        'notes' => $notes, 'unmapped' => (array) ($result['unmapped'] ?? []),
    ];
    return [$item, $notes];
}

/* ------------------------------------------------------------------ proveedores */

/** Proveedores disponibles: clave => etiqueta. La CLI de Claude Code solo si está instalada en el servidor. */
function cms_import_providers(): array
{
    $p = ['openrouter' => 'OpenRouter (cualquier modelo con visión, con clave)'];
    if (cms_import_claude_cli()) $p['claude-cli'] = 'Claude Code en este equipo (sin clave; solo desarrollo local)';
    return $p;
}

/** Ruta de la CLI `claude` si existe y se puede ejecutar desde PHP. */
function cms_import_claude_cli(): string
{
    static $path = null;
    if ($path !== null) return $path;
    $path = '';
    if (!function_exists('proc_open') || !function_exists('shell_exec')) return '';
    foreach ([getenv('HOME') . '/.local/bin/claude', '/usr/local/bin/claude', '/usr/bin/claude'] as $c) if (is_executable($c)) return $path = $c;
    $w = trim((string) @shell_exec('command -v claude 2>/dev/null'));
    return $path = ($w !== '' && is_executable($w)) ? $w : '';
}

/** Modelos con visión de OpenRouter (lista pública), cacheada un día en data/cache/. id => [label, in, out, structured]. */
function cms_import_models(bool $refresh = false): array
{
    $cache = CMS_DATA . '/cache/openrouter-models.json';
    if (!$refresh && is_file($cache) && filemtime($cache) > time() - 86400) return cms_json_read($cache, []);
    $raw = cms_import_http('https://openrouter.ai/api/v1/models', null, [], 20);
    $list = json_decode((string) $raw, true)['data'] ?? null;
    if (!is_array($list)) return is_file($cache) ? cms_json_read($cache, []) : [];
    $out = [];
    foreach ($list as $m) {
        $id = (string) ($m['id'] ?? '');
        $mods = (array) ($m['architecture']['input_modalities'] ?? []);
        if ($id === '' || !in_array('image', $mods, true) || str_contains($id, ':batch') || str_starts_with($id, '~')) continue;
        $p = (array) ($m['pricing'] ?? []);
        $out[$id] = [
            'label' => (string) ($m['name'] ?? $id),
            'in' => round((float) ($p['prompt'] ?? 0) * 1e6, 3), 'out' => round((float) ($p['completion'] ?? 0) * 1e6, 3),
            'structured' => in_array('response_format', (array) ($m['supported_parameters'] ?? []), true),
            'context' => (int) ($m['context_length'] ?? 0),
        ];
    }
    ksort($out);
    cms_json_write($cache, $out);
    return $out;
}

/** Sugerencia de modelo predeterminado: el Sonnet más reciente disponible en OpenRouter. */
function cms_import_default_model(): string
{
    $models = cms_import_models();
    $c = array_filter(array_keys($models), fn($id) => preg_match('#^anthropic/claude-sonnet#', $id));
    rsort($c, SORT_NATURAL);
    return $c ? (string) $c[0] : 'anthropic/claude-sonnet-5';
}

/** Petición HTTP con curl (o streams). $body array → JSON. Devuelve el cuerpo; lanza RuntimeException si falla. */
function cms_import_http(string $url, ?array $body, array $headers = [], int $timeout = 300): string
{
    $json = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== null) $headers[] = 'Content-Type: application/json';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => true]);
        if ($json !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $json); }
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false) throw new RuntimeException('No se pudo conectar: ' . $err);
    } else {
        $ctx = stream_context_create(['http' => ['method' => $json !== null ? 'POST' : 'GET', 'header' => implode("\r\n", $headers), 'content' => $json ?? '', 'timeout' => $timeout, 'ignore_errors' => true]]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) throw new RuntimeException('No se pudo conectar con ' . parse_url($url, PHP_URL_HOST));
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    }
    if ($code >= 400) {
        $j = json_decode((string) $res, true);
        $msg = $j['error']['message'] ?? $j['error'] ?? $j['message'] ?? substr((string) $res, 0, 300);
        throw new RuntimeException("HTTP $code: " . (is_string($msg) ? $msg : json_encode($msg)), $code);
    }
    return (string) $res;
}

/**
 * Ejecuta el análisis. $screens: rutas PNG en orden. Devuelve [resultado (array según el schema), stats].
 * Lanza RuntimeException con un mensaje legible si algo falla.
 */
function cms_import_run(string $provider, string $model, array $screens, string $text): array
{
    if (!$screens) throw new RuntimeException('No hay pantallas que analizar.');
    $t0 = microtime(true);
    if ($provider === 'claude-cli') [$result, $stats] = cms_import_run_cli($model, $screens, $text);
    else [$result, $stats] = cms_import_run_openrouter($model, $screens, $text);
    $stats['seconds'] = round(microtime(true) - $t0, 1);
    $stats['provider'] = $provider; $stats['model'] = $model; $stats['screens'] = count($screens);
    if (!is_array($result) || !isset($result['sections'])) throw new RuntimeException('El modelo no devolvió la estructura esperada.');
    return [$result, $stats];
}

/** Extrae un objeto JSON de una respuesta que puede traer texto o vallas de código alrededor. */
function cms_import_parse_json(string $s): ?array
{
    $s = trim($s);
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $s, $m)) $s = $m[1];
    $j = json_decode($s, true);
    if (is_array($j)) return $j;
    $a = strpos($s, '{'); $b = strrpos($s, '}');
    if ($a !== false && $b !== false && $b > $a) { $j = json_decode(substr($s, $a, $b - $a + 1), true); if (is_array($j)) return $j; }
    return null;
}

function cms_import_run_openrouter(string $model, array $screens, string $text): array
{
    $key = (string) (cms_settings()['openrouter_key'] ?? '');
    if ($key === '') throw new RuntimeException('Falta la clave de OpenRouter en los ajustes del importador.');
    $content = [];
    foreach ($screens as $i => $p) {
        $content[] = ['type' => 'text', 'text' => 'Pantalla ' . ($i + 1) . ' de ' . count($screens)];
        $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . base64_encode((string) file_get_contents($p))]];
    }
    $content[] = ['type' => 'text', 'text' => 'Devuelve ahora el JSON de la página.'];
    $schema = cms_import_catalog()['schema'];
    $base = [
        'model' => $model,
        'messages' => [['role' => 'system', 'content' => cms_import_prompt(count($screens), $text)], ['role' => 'user', 'content' => $content]],
        'max_tokens' => 16000, 'temperature' => 0.2,
        'usage' => ['include' => true],
    ];
    $headers = ['Authorization: Bearer ' . $key, 'HTTP-Referer: ' . cms_origin(), 'X-Title: cms_simple importador'];
    $modes = [
        ['response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'pagina', 'strict' => true, 'schema' => $schema]]],
        ['response_format' => ['type' => 'json_object']],
        [],
    ];
    $last = null; $used = '';
    foreach ($modes as $i => $extra) {
        try {
            $raw = cms_import_http('https://openrouter.ai/api/v1/chat/completions', $base + $extra, $headers, 600);
            $used = ['json_schema', 'json_object', 'texto'][$i];
        } catch (RuntimeException $e) {
            $last = $e;
            if ($e->getCode() >= 400 && $e->getCode() < 500 && $i < 2 && $e->getCode() !== 401 && $e->getCode() !== 402) continue; // el modelo no admite ese formato: probar el siguiente
            throw $e;
        }
        $j = json_decode($raw, true);
        if (!is_array($j)) throw new RuntimeException('Respuesta ilegible de OpenRouter.');
        if (isset($j['error'])) throw new RuntimeException('OpenRouter: ' . (is_string($j['error']) ? $j['error'] : ($j['error']['message'] ?? json_encode($j['error']))));
        $msg = $j['choices'][0]['message']['content'] ?? '';
        if (is_array($msg)) $msg = implode('', array_map(fn($c) => (string) ($c['text'] ?? ''), $msg));
        $result = cms_import_parse_json((string) $msg);
        $u = (array) ($j['usage'] ?? []);
        $stats = ['format' => $used, 'input_tokens' => $u['prompt_tokens'] ?? null, 'output_tokens' => $u['completion_tokens'] ?? null, 'cost_usd' => $u['cost'] ?? null, 'finish' => $j['choices'][0]['finish_reason'] ?? null, 'served_model' => $j['model'] ?? $model];
        if ($result === null) throw new RuntimeException('El modelo respondió sin JSON válido' . (($stats['finish'] ?? '') === 'length' ? ' (se quedó sin espacio de salida: prueba con otro modelo)' : '') . '.');
        return [$result, $stats];
    }
    throw $last ?? new RuntimeException('No se pudo completar la petición.');
}

function cms_import_run_cli(string $model, array $screens, string $text): array
{
    $cli = cms_import_claude_cli();
    if ($cli === '') throw new RuntimeException('La CLI de Claude Code no está disponible en este servidor.');
    $model = preg_replace('/[^a-z0-9._-]/i', '', $model) ?: 'sonnet';
    $work = dirname((string) $screens[0]);
    $prompt = cms_import_prompt(count($screens), $text, array_map('strval', $screens));
    $schema = json_encode(cms_import_catalog()['schema'], JSON_UNESCAPED_UNICODE);
    $cmd = [$cli, '-p', '--model', $model, '--output-format', 'json', '--allowedTools', 'Read', '--add-dir', $work, '--json-schema', $schema];
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $work, array_merge(getenv(), ['HOME' => getenv('HOME') ?: dirname($cli, 3)]));
    if (!is_resource($proc)) throw new RuntimeException('No se pudo ejecutar la CLI.');
    fwrite($pipes[0], $prompt); fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) throw new RuntimeException('La CLI falló (' . $code . '): ' . substr(trim((string) $err) ?: (string) $out, 0, 500));
    $j = json_decode((string) $out, true);
    if (!is_array($j)) throw new RuntimeException('Respuesta ilegible de la CLI.');
    if (!empty($j['is_error'])) throw new RuntimeException('Error del modelo: ' . substr((string) ($j['result'] ?? ''), 0, 500));
    $result = $j['structured_output'] ?? cms_import_parse_json((string) ($j['result'] ?? ''));
    $u = (array) ($j['usage'] ?? []);
    return [$result, ['format' => 'json_schema', 'input_tokens' => ($u['input_tokens'] ?? 0) + ($u['cache_read_input_tokens'] ?? 0), 'output_tokens' => $u['output_tokens'] ?? null, 'cost_usd' => $j['total_cost_usd'] ?? null, 'turns' => $j['num_turns'] ?? null]];
}
