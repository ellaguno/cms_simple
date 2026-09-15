<?php
/**
 * Paquete "redaccion": artículos y resúmenes de noticias con IA.
 *
 * - rd_chat(): una llamada de chat al proveedor elegido (OpenRouter, OpenAI, DeepSeek con la API compatible con
 *   OpenAI; Anthropic con su API de mensajes; "prueba" devuelve texto de muestra sin red). Devuelve texto y uso.
 * - rd_article($tema): título, cuerpo en HTML limpio, resumen, imagen opcional → elemento nuevo en la colección elegida.
 * - rd_news(): por cada tema, lee el RSS (Google News por defecto), filtra por antigüedad y por URL ya usada, pide un
 *   resumen fiel a los titulares y arma un artículo con una sección por tema y sus fuentes.
 * - Historial en data/redaccion/historial.json; URL usadas en data/redaccion/urls.json; estado de la programación en
 *   data/redaccion/estado.json. El gancho 'cron' ejecuta lo programado cuando toca.
 */
declare(strict_types=1);
if (!function_exists('rd_settings')) {

    function rd_settings(): array
    {
        static $o = null;
        if ($o !== null) return $o;
        $S = cms_settings();
        $g = fn(string $k, string $d = '') => trim((string) (($S[$k] ?? '') === '' ? $d : $S[$k]));
        $types = array_keys((array) cms_config('types', []));
        $type = $g('rd_type', (string) ($types[0] ?? ''));
        if (!in_array($type, $types, true)) $type = (string) ($types[0] ?? '');
        return $o = [
            'provider' => $g('rd_provider', 'prueba'),
            'model' => $g('rd_model'),
            'keys' => ['openrouter' => $g('rd_openrouter_key', $g('openrouter_key')), 'openai' => $g('rd_openai_key'), 'anthropic' => $g('rd_anthropic_key'), 'deepseek' => $g('rd_deepseek_key')],
            'type' => $type,
            'status' => $g('rd_status', 'draft') === 'published' ? 'published' : 'draft',
            'lang' => $g('rd_lang', 'español de México'),
            'category' => $g('rd_category'),
            'topics' => cms_lines($g('rd_topics')),
            'min_words' => max(100, (int) $g('rd_min_words', '800')), 'max_words' => max(150, (int) $g('rd_max_words', '1200')), 'sections' => max(1, (int) $g('rd_sections', '4')),
            'style' => $g('rd_style'),
            'news_topics' => cms_lines($g('rd_news_topics')),
            'news_query' => $g('rd_news_query', 'https://news.google.com/rss/search?q={topic}&hl=es-419&gl=MX&ceid=MX:es-419'),
            'news_hours' => max(6, (int) $g('rd_news_hours', '48')), 'news_max' => max(2, (int) $g('rd_news_max', '8')),
            'news_title' => $g('rd_news_title', 'Resumen de noticias: {fecha}'),
            'news_category' => $g('rd_news_category', 'Noticias'),
            'image' => $g('rd_image', 'none'), 'image_model' => $g('rd_image_model', 'gpt-image-1'),
            'image_prompt' => $g('rd_image_prompt', 'Ilustración editorial moderna y limpia sobre "{topic}", colores sobrios, sin texto ni logotipos.'),
            'sched_articles' => $g('rd_schedule_articles', 'off'), 'sched_news' => $g('rd_schedule_news', 'off'), 'sched_hour' => max(0, min(23, (int) $g('rd_schedule_hour', '7'))),
        ];
    }

    function rd_provider_label(string $p): string
    {
        return ['prueba' => 'Prueba (texto de muestra)', 'openrouter' => 'OpenRouter', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'deepseek' => 'DeepSeek'][$p] ?? $p;
    }

    /** Modelo efectivo (el configurado o el recomendado del proveedor). */
    function rd_model(): string
    {
        $o = rd_settings();
        if ($o['model'] !== '') return $o['model'];
        return ['openrouter' => 'anthropic/claude-sonnet-5', 'openai' => 'gpt-4o-mini', 'anthropic' => 'claude-sonnet-5', 'deepseek' => 'deepseek-chat'][$o['provider']] ?? 'prueba';
    }

    function rd_dir(): string
    {
        $d = CMS_DATA . '/redaccion';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }

    /**
     * Una llamada de chat. Devuelve ['text' => …, 'in' => tokens, 'out' => tokens, 'cost' => USD|null, 'model' => …].
     * Lanza RuntimeException con un mensaje legible.
     */
    function rd_chat(string $system, string $user, int $maxTokens = 4000, float $temperature = 0.5): array
    {
        $o = rd_settings();
        $p = $o['provider'];
        $model = rd_model();
        if ($p === 'prueba') return ['text' => rd_sample($user), 'in' => 0, 'out' => 0, 'cost' => 0.0, 'model' => 'prueba'];
        $key = (string) ($o['keys'][$p] ?? '');
        if ($key === '') throw new RuntimeException('Falta la clave de ' . rd_provider_label($p) . ' en Ajustes → Redacción con IA.');
        if ($p === 'anthropic') {
            $raw = cms_http_post('https://api.anthropic.com/v1/messages',
                ['model' => $model, 'max_tokens' => $maxTokens, 'temperature' => $temperature, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]]],
                ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01'], 300);
            $j = json_decode($raw, true);
            if (!is_array($j)) throw new RuntimeException('Respuesta ilegible de Anthropic.');
            if (isset($j['error'])) throw new RuntimeException('Anthropic: ' . (string) ($j['error']['message'] ?? json_encode($j['error'])));
            $text = '';
            foreach ((array) ($j['content'] ?? []) as $c) if (($c['type'] ?? '') === 'text') $text .= (string) ($c['text'] ?? '');
            return ['text' => trim($text), 'in' => (int) ($j['usage']['input_tokens'] ?? 0), 'out' => (int) ($j['usage']['output_tokens'] ?? 0), 'cost' => null, 'model' => (string) ($j['model'] ?? $model)];
        }
        $url = ['openrouter' => 'https://openrouter.ai/api/v1/chat/completions', 'openai' => 'https://api.openai.com/v1/chat/completions', 'deepseek' => 'https://api.deepseek.com/chat/completions'][$p] ?? '';
        if ($url === '') throw new RuntimeException('Proveedor desconocido: ' . $p);
        $body = ['model' => $model, 'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], 'max_tokens' => $maxTokens, 'temperature' => $temperature];
        $headers = ['Authorization: Bearer ' . $key];
        if ($p === 'openrouter') { $body['usage'] = ['include' => true]; $headers[] = 'HTTP-Referer: ' . cms_origin(); $headers[] = 'X-Title: cms_simple redaccion'; }
        $raw = cms_http_post($url, $body, $headers, 300);
        $j = json_decode($raw, true);
        if (!is_array($j)) throw new RuntimeException('Respuesta ilegible de ' . rd_provider_label($p) . '.');
        if (isset($j['error'])) throw new RuntimeException(rd_provider_label($p) . ': ' . (is_string($j['error']) ? $j['error'] : (string) ($j['error']['message'] ?? json_encode($j['error']))));
        $msg = $j['choices'][0]['message']['content'] ?? '';
        if (is_array($msg)) $msg = implode('', array_map(fn($c) => (string) ($c['text'] ?? ''), $msg));
        $u = (array) ($j['usage'] ?? []);
        return ['text' => trim((string) $msg), 'in' => (int) ($u['prompt_tokens'] ?? 0), 'out' => (int) ($u['completion_tokens'] ?? 0), 'cost' => isset($u['cost']) ? (float) $u['cost'] : null, 'model' => (string) ($j['model'] ?? $model)];
    }

    /** Texto de muestra del proveedor de prueba, según lo que se pide. */
    function rd_sample(string $prompt): string
    {
        if (stripos($prompt, 'Responde SOLO con el título') !== false) return 'Un artículo de muestra escrito sin conectar a ningún proveedor';
        if (stripos($prompt, 'noticias') !== false && stripos($prompt, 'Titulares') !== false) return '<p>Este es un <strong>resumen de prueba</strong>: el proveedor "prueba" no lee las noticias, solo comprueba que el flujo completo funciona.</p><p>Elige OpenRouter, OpenAI, Anthropic o DeepSeek en Ajustes para obtener resúmenes reales.</p>';
        $out = '<p>Este artículo lo generó el <strong>proveedor de prueba</strong> del paquete Redacción con IA. No llamó a ningún modelo: sirve para revisar que el elemento se crea en la colección correcta, con su categoría, su resumen y su estado.</p>';
        for ($i = 1; $i <= 3; $i++) $out .= '<h2>Sección ' . $i . '</h2><p>Texto de relleno de la sección ' . $i . '. Cuando elijas un proveedor real en Ajustes, aquí habrá contenido sobre el tema pedido, con la longitud y el número de secciones configurados.</p>';
        return $out . '<ul><li>Sin clave ni costo.</li><li>Mismo flujo que con un proveedor real.</li></ul>';
    }

    /** HTML limpio para el editor: sin cercas de código, sin <html>/<body>, solo etiquetas de texto; quita un <h1> inicial. */
    function rd_clean_html(string $s): string
    {
        $s = preg_replace('/^\s*```(?:html)?\s*/i', '', $s) ?? $s;
        $s = preg_replace('/\s*```\s*$/', '', $s) ?? $s;
        $s = preg_replace('~<!DOCTYPE[^>]*>|</?(html|head|body|article|main)\b[^>]*>~i', '', $s) ?? $s;
        $s = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $s) ?? $s;
        $s = preg_replace('~^\s*<h1\b[^>]*>.*?</h1>\s*~is', '', $s) ?? $s;
        $s = strip_tags($s, '<p><h2><h3><h4><ul><ol><li><strong><em><b><i><a><blockquote><br><hr><table><thead><tbody><tr><th><td>');
        $s = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s) ?? $s;
        if (strpos($s, '<p') === false && strpos($s, '<h2') === false) {   // el modelo devolvió texto plano o Markdown ligero
            $s = preg_replace('/^#{2,3}\s+(.+)$/m', '<h2>$1</h2>', $s) ?? $s;
            $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s) ?? $s;
            $s = implode('', array_map(fn($p) => preg_match('/^<h[23]>/', trim($p)) ? trim($p) : '<p>' . trim($p) . '</p>', array_filter(preg_split('/\n{2,}/', trim($s)) ?: [], fn($p) => trim($p) !== '')));
        }
        return trim($s);
    }

    /** Resumen de un cuerpo HTML: el primer párrafo, recortado. */
    function rd_excerpt(string $html, int $max = 200): string
    {
        $p = preg_match('~<p\b[^>]*>(.*?)</p>~is', $html, $m) ? $m[1] : $html;
        $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (mb_strlen($t) > $max) { $t = mb_substr($t, 0, $max); $sp = mb_strrpos($t, ' '); if ($sp > $max * 0.6) $t = mb_substr($t, 0, $sp); $t .= '…'; }
        return $t;
    }

    /**
     * Crea un elemento en la colección configurada. $d: title, body, excerpt, category, tags (lista), image, status.
     * Rellena los campos según el esquema del tipo (título, resumen, imagen, primer campo html, categoría y etiquetas si existen).
     * Devuelve [slug, error].
     */
    function rd_save_item(array $d): array
    {
        $o = rd_settings();
        $type = $o['type'];
        $def = cms_type($type);
        if (!$def) return ['', 'No hay una colección válida en Ajustes → Redacción con IA.'];
        $lang = cms_default_lang();
        $fields = (array) ($def['fields'] ?? []);
        $set = function (string $k, $v) use (&$item, $fields, $lang) {
            if (!isset($fields[$k])) return;
            $item[$k] = !empty($fields[$k]['i18n']) ? [$lang => $v] + array_fill_keys(cms_langs(), (($fields[$k]['type'] ?? '') === 'tags') ? [] : '') : $v;
        };
        $base = cms_slugify((string) $d['title']) ?: 'articulo';
        $slug = $base; $n = 2;
        while (is_file(cms_content_dir($type) . '/' . $slug . '.json')) $slug = $base . '-' . $n++;
        $item = ['slug' => $slug, 'status' => ($d['status'] ?? $o['status']) === 'published' ? 'published' : 'draft', 'created' => date('Y-m-d'), 'updated' => date('Y-m-d'), 'publish_at' => '', 'seo_title' => array_fill_keys(cms_langs(), ''), 'seo_desc' => array_fill_keys(cms_langs(), '')];
        $set($def['title_field'] ?? 'title', (string) $d['title']);
        $set($def['excerpt_field'] ?? 'excerpt', (string) ($d['excerpt'] ?? ''));
        if (!empty($d['image'])) $set($def['image_field'] ?? 'image', (string) $d['image']);
        $bodyField = '';
        foreach ($fields as $k => $fd) if (($fd['type'] ?? '') === 'html') { $bodyField = $k; break; }
        if ($bodyField === '') return ['', 'La colección "' . $type . '" no tiene un campo de texto largo (html) donde escribir.'];
        $set($bodyField, (string) $d['body']);
        foreach ($fields as $k => $fd) {
            $t = $fd['type'] ?? 'text';
            if ($t === 'date' && !isset($item[$k])) $item[$k] = date('Y-m-d');
            if ($t === 'tags' && !empty($d['tags'])) $set($k, array_values((array) $d['tags']));
            if (in_array($k, ['category', 'categoria', 'categoría'], true) && !empty($d['category'])) $set($k, (string) $d['category']);
        }
        $item['redaccion'] = ['provider' => $o['provider'], 'model' => rd_model(), 'when' => date('Y-m-d H:i'), 'kind' => (string) ($d['kind'] ?? 'articulo')];
        if (!cms_item_save($type, $item)) return ['', 'No se pudo escribir en data/content/' . $type . '/.'];
        return [$slug, ''];
    }

    /** Guarda una entrada del historial (las últimas 200). */
    function rd_log(array $e): void
    {
        $f = rd_dir() . '/historial.json';
        $h = cms_json_read($f, []);
        array_unshift($h, ['when' => date('Y-m-d H:i:s')] + $e);
        cms_json_write($f, array_slice($h, 0, 200));
    }

    function rd_history(): array { return (array) cms_json_read(rd_dir() . '/historial.json', []); }

    /** Genera una imagen con OpenAI y la guarda en uploads/AAAA/MM/. Devuelve la ruta relativa o '' (nunca lanza). */
    function rd_image(string $topic, string $slug): string
    {
        $o = rd_settings();
        if ($o['image'] !== 'openai') return '';
        $key = (string) $o['keys']['openai'];
        if ($key === '') return '';
        try {
            $model = $o['image_model'] ?: 'gpt-image-1';
            $body = ['model' => $model, 'prompt' => str_replace('{topic}', $topic, $o['image_prompt']), 'n' => 1, 'size' => strpos($model, 'dall-e') === 0 ? '1792x1024' : '1536x1024'];
            if (strpos($model, 'dall-e') === 0) $body['response_format'] = 'b64_json';
            $j = json_decode(cms_http_post('https://api.openai.com/v1/images/generations', $body, ['Authorization: Bearer ' . $key], 300), true);
            $b64 = (string) ($j['data'][0]['b64_json'] ?? '');
            $bin = $b64 !== '' ? base64_decode($b64, true) : '';
            if (!$bin && !empty($j['data'][0]['url'])) [$bin] = cms_http_get((string) $j['data'][0]['url'], 20971520, 60);
            if (!$bin) return '';
            $sub = date('Y/m');
            $dir = CMS_UPLOADS . '/' . $sub;
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return '';
            $ext = substr((string) $bin, 0, 4) === "\x89PNG" ? 'png' : 'jpg';
            $name = 'ia-' . $slug . '.' . $ext;
            if (@file_put_contents($dir . '/' . $name, $bin) === false) return '';
            @chmod($dir . '/' . $name, 0644);
            cms_webp_make($dir . '/' . $name);
            return 'uploads/' . $sub . '/' . $name;
        } catch (\Throwable $e) {
            rd_log(['kind' => 'imagen', 'topic' => $topic, 'ok' => false, 'msg' => $e->getMessage()]);
            return '';
        }
    }

    /** Escribe un artículo sobre un tema y lo guarda. Devuelve [ok, mensaje, slug]. */
    function rd_article(string $topic, ?callable $log = null): array
    {
        $o = rd_settings();
        $topic = trim($topic);
        if ($topic === '') return [false, 'Indica un tema.', ''];
        @set_time_limit(600);
        $t0 = microtime(true);
        $style = $o['style'] !== '' ? "\n\nINSTRUCCIONES DE ESTILO:\n" . $o['style'] : '';
        try {
            $r1 = rd_chat('Eres un experto en crear títulos atractivos para artículos. Respondes solo con el título solicitado, en ' . $o['lang'] . '.',
                'Genera un título atractivo, conciso y profesional para un artículo sobre "' . $topic . '". Debe ser claro, informativo y captar la atención. Responde SOLO con el título, sin comillas ni explicaciones.' . $style, 1000, 0.7);
            $title = trim(str_replace(['"', '“', '”', "'"], '', preg_replace('/^(título|title)\s*:\s*/i', '', $r1['text']) ?? $r1['text']));
            $title = trim(explode("\n", $title)[0]);
            if ($title === '') throw new RuntimeException('El modelo no devolvió un título.');
            $r2 = rd_chat('Eres un escritor experto que genera contenido de alta calidad en ' . $o['lang'] . ', formateado en HTML limpio.',
                "Escribe un artículo completo con el título \"$title\" sobre el tema \"$topic\".\nRequisitos:\n- Longitud: entre {$o['min_words']} y {$o['max_words']} palabras\n- Mínimo {$o['sections']} secciones con subtítulos\n- Un párrafo introductorio atractivo\n- Contenido informativo, bien estructurado y original\n- Tono profesional pero accesible$style\n\nFormato HTML requerido:\n- <h2> para los títulos de sección (NO incluir el título principal)\n- <p> para párrafos, <strong> para énfasis, <ul>/<li> para listas cuando convenga\n- NO incluir <!DOCTYPE>, <html>, <head>, <body> ni bloques de código\n- NO usar Markdown, solo HTML\nEscribe directamente el HTML, sin explicaciones.", 6000, 0.4);
            $body = rd_clean_html($r2['text']);
            if (mb_strlen(strip_tags($body)) < 200) throw new RuntimeException('El modelo devolvió un texto demasiado corto.');
        } catch (\Throwable $e) {
            rd_log(['kind' => 'articulo', 'topic' => $topic, 'ok' => false, 'msg' => $e->getMessage(), 'provider' => $o['provider'], 'model' => rd_model()]);
            return [false, $e->getMessage(), ''];
        }
        $slug = cms_slugify($title) ?: 'articulo';
        $image = rd_image($topic, $slug);
        [$slug, $err] = rd_save_item(['title' => $title, 'body' => $body, 'excerpt' => rd_excerpt($body), 'category' => $o['category'], 'image' => $image, 'kind' => 'articulo']);
        $tok = ['in' => $r1['in'] + $r2['in'], 'out' => $r1['out'] + $r2['out'], 'cost' => ($r1['cost'] === null || $r2['cost'] === null) ? null : $r1['cost'] + $r2['cost']];
        if ($err !== '') { rd_log(['kind' => 'articulo', 'topic' => $topic, 'ok' => false, 'msg' => $err] + $tok); return [false, $err, '']; }
        $words = str_word_count(strip_tags($body));
        $msg = 'Artículo "' . $title . '" guardado como ' . ($o['status'] === 'published' ? 'publicado' : 'borrador') . ' (' . $words . ' palabras' . ($image ? ', con imagen' : '') . ', ' . round(microtime(true) - $t0) . ' s).';
        rd_log(['kind' => 'articulo', 'topic' => $topic, 'ok' => true, 'msg' => $msg, 'slug' => $slug, 'type' => $o['type'], 'provider' => $o['provider'], 'model' => $r2['model']] + $tok);
        if ($log) $log($msg);
        return [true, $msg, $slug];
    }

    /** Lee un RSS 2.0 o Atom. Devuelve [[title, url, date (ts), source], …]. */
    function rd_feed(string $url): array
    {
        if (preg_match('/^https?:\/\//', $url) !== 1) return [];
        [$xml, $err] = cms_http_get(preg_replace('#^http://#i', 'https://', $url) ?? $url, 3145728, 15);
        if (!is_string($xml) || $xml === '') return [];
        $prev = libxml_use_internal_errors(true);
        $doc = @simplexml_load_string(ltrim($xml, "\xEF\xBB\xBF \t\r\n"), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        if (!$doc) return [];
        $clean = fn($v) => trim(html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $out = [];
        if ($doc->getName() === 'feed') {
            foreach ($doc->entry as $e) { $u = ''; foreach ($e->link as $l) if ((string) ($l['rel'] ?? 'alternate') === 'alternate') { $u = (string) $l['href']; break; } $out[] = [$clean($e->title), $u, (int) strtotime((string) ($e->published ?: $e->updated)), $clean($e->author->name ?? '')]; }
            return $out;
        }
        $items = $doc->getName() === 'rss' ? $doc->channel->item : $doc->item;
        foreach ($items as $it) {
            $title = $clean($it->title);
            $source = $clean($it->source ?? '');
            if ($source === '' && preg_match('/ - ([^-]{2,60})$/', $title, $m)) { $source = trim($m[1]); $title = trim(substr($title, 0, -strlen($m[0]))); }
            $out[] = [$title, trim((string) $it->link), (int) strtotime((string) $it->pubDate), $source];
        }
        return $out;
    }

    /** Resumen de noticias del día: una sección por tema con fuentes. Devuelve [ok, mensaje, slug]. */
    function rd_news(?callable $log = null): array
    {
        $o = rd_settings();
        @set_time_limit(600);
        $t0 = microtime(true);
        $usedFile = rd_dir() . '/urls.json';
        $used = array_fill_keys((array) cms_json_read($usedFile, []), true);
        $topics = $o['news_topics'] ?: [''];
        $since = time() - $o['news_hours'] * 3600;
        $sections = []; $sources = []; $tokIn = 0; $tokOut = 0; $cost = 0.0; $costNull = false; $model = rd_model();
        foreach ($topics as $topic) {
            $url = $topic === '' ? preg_replace('~/rss/search\?q=\{topic\}&~', '/rss?', $o['news_query']) : str_replace('{topic}', rawurlencode($topic), $o['news_query']);
            $items = $o['provider'] === 'prueba' ? [['Titular de muestra uno', 'https://example.com/1', time(), 'Fuente A'], ['Titular de muestra dos', 'https://example.com/2', time(), 'Fuente B']] : rd_feed((string) $url);
            $items = array_filter($items, fn($i) => $i[0] !== '' && $i[1] !== '' && ($i[2] === 0 || $i[2] >= $since) && !isset($used[$i[1]]));
            $seen = []; $pick = [];
            foreach ($items as $i) { $k = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $i[0]) ?? $i[0]); if (isset($seen[$k])) continue; $seen[$k] = true; $pick[] = $i; if (count($pick) >= $o['news_max']) break; }
            if (!$pick) { if ($log) $log('Sin noticias nuevas para "' . ($topic ?: 'portada') . '".'); continue; }
            $lines = array_map(fn($i) => '- ' . $i[0] . ($i[3] !== '' ? ' (' . $i[3] . ')' : ''), $pick);
            $style = $o['style'] !== '' ? "\n\nINSTRUCCIONES DE ESTILO:\n" . $o['style'] : '';
            try {
                $r = rd_chat('Eres un periodista experto que resume noticias de forma objetiva y precisa, en ' . $o['lang'] . '. Usas HTML puro, nunca Markdown.',
                    ($topic === '' ? 'Como editor jefe, crea un resumen breve de las noticias más relevantes del día.' : 'Genera un resumen sobre "' . $topic . '" basado ÚNICAMENTE en estas noticias.') .
                    "\nREGLAS:\n- Usa SOLO la información de los titulares; no agregues datos externos ni especulaciones\n- Conserva cifras y nombres tal como aparecen\n- Si los titulares no tratan del tema, dilo en una frase en lugar de inventar\n- 2 a 4 párrafos cortos en <p></p>, <strong> para énfasis, sin títulos ni Markdown$style\n\nTitulares a resumir:\n" . implode("\n", $lines), 2000, 0.4);
            } catch (\Throwable $e) {
                rd_log(['kind' => 'noticias', 'topic' => $topic, 'ok' => false, 'msg' => $e->getMessage(), 'provider' => $o['provider'], 'model' => $model]);
                return [false, $e->getMessage(), ''];
            }
            $tokIn += $r['in']; $tokOut += $r['out']; if ($r['cost'] === null) $costNull = true; else $cost += $r['cost']; $model = $r['model'];
            $html = rd_clean_html($r['text']);
            $srcHtml = '<p class="cms-fuentes"><small>Fuentes: ' . implode(' · ', array_map(fn($i) => '<a href="' . cms_e($i[1]) . '" rel="nofollow noopener" target="_blank">' . cms_e($i[3] !== '' ? $i[3] : parse_url($i[1], PHP_URL_HOST)) . '</a>', $pick)) . '</small></p>';
            $sections[] = ($topic !== '' ? '<h2>' . cms_e(mb_convert_case($topic, MB_CASE_TITLE, 'UTF-8')) . '</h2>' : '') . $html . $srcHtml;
            foreach ($pick as $i) { $used[$i[1]] = true; $sources[] = $i[1]; }
        }
        if (!$sections) return [false, 'No hubo noticias nuevas para resumir (todas las de las últimas ' . $o['news_hours'] . ' horas ya se usaron o los feeds no respondieron).', ''];
        $fecha = cms_date(date('Y-m-d'), cms_default_lang());
        $title = str_replace('{fecha}', $fecha, $o['news_title'] ?: 'Resumen de noticias: {fecha}');
        $body = implode("\n", $sections);
        [$slug, $err] = rd_save_item(['title' => $title, 'body' => $body, 'excerpt' => rd_excerpt($body), 'category' => $o['news_category'], 'kind' => 'noticias']);
        $tok = ['in' => $tokIn, 'out' => $tokOut, 'cost' => $costNull ? null : $cost];
        if ($err !== '') { rd_log(['kind' => 'noticias', 'topic' => implode(', ', $topics), 'ok' => false, 'msg' => $err] + $tok); return [false, $err, '']; }
        cms_json_write($usedFile, array_slice(array_keys($used), -2000));
        $msg = 'Resumen "' . $title . '" guardado como ' . ($o['status'] === 'published' ? 'publicado' : 'borrador') . ' (' . count($sections) . ' ' . (count($sections) === 1 ? 'tema' : 'temas') . ', ' . count($sources) . ' fuentes, ' . round(microtime(true) - $t0) . ' s).';
        rd_log(['kind' => 'noticias', 'topic' => implode(', ', array_filter($topics)) ?: 'portada', 'ok' => true, 'msg' => $msg, 'slug' => $slug, 'type' => $o['type'], 'provider' => $o['provider'], 'model' => $model] + $tok);
        if ($log) $log($msg);
        return [true, $msg, $slug];
    }

    /** Estado de la programación: último artículo, último resumen, índice del siguiente tema. */
    function rd_state(): array { return (array) cms_json_read(rd_dir() . '/estado.json', []) + ['last_article' => '', 'last_news' => '', 'next_topic' => 0]; }
    function rd_state_save(array $s): void { cms_json_write(rd_dir() . '/estado.json', $s); }

    /** ¿Toca ejecutar una tarea con esa frecuencia, dada la última ejecución ('' = nunca)? Espera a la hora configurada. */
    function rd_due(string $freq, string $last): bool
    {
        $o = rd_settings();
        if ($freq === 'off' || (int) date('G') < $o['sched_hour']) return false;
        if ($last === '') return true;
        $days = $freq === 'weekly' ? 7 : 1;
        return strtotime(substr($last, 0, 10)) <= strtotime('today') - $days * 86400 + 1;
    }

    /** Lo que haría el cron ahora mismo (para enseñarlo en la página). */
    function rd_pending(): array
    {
        $o = rd_settings(); $s = rd_state(); $out = [];
        if ($o['sched_articles'] !== 'off') $out[] = ['Artículo (' . ($o['sched_articles'] === 'daily' ? 'diario' : 'semanal') . ')', $o['topics'] ? (rd_due($o['sched_articles'], $s['last_article']) ? 'pendiente: "' . $o['topics'][$s['next_topic'] % count($o['topics'])] . '"' : 'hecho ' . ($s['last_article'] ?: 'nunca')) : 'sin temas en Ajustes'];
        if ($o['sched_news'] !== 'off') $out[] = ['Resumen de noticias (diario)', rd_due($o['sched_news'], $s['last_news']) ? 'pendiente' : 'hecho ' . ($s['last_news'] ?: 'nunca')];
        return $out;
    }

    /* ------------------------------------------------------------------ cron */
    cms_on('cron', function (callable $log): void {
        $o = rd_settings();
        $s = rd_state();
        if ($o['sched_articles'] !== 'off' && $o['topics'] && rd_due($o['sched_articles'], $s['last_article'])) {
            $i = $s['next_topic'] % count($o['topics']);
            $s['last_article'] = date('Y-m-d H:i'); $s['next_topic'] = $i + 1;
            rd_state_save($s);   // antes de generar: si falla, no se reintenta cada 15 minutos
            [$ok, $msg] = rd_article($o['topics'][$i]);
            $log('Redacción, artículo "' . $o['topics'][$i] . '": ' . $msg);
        }
        if ($o['sched_news'] !== 'off' && rd_due($o['sched_news'], $s['last_news'])) {
            $s['last_news'] = date('Y-m-d H:i');
            rd_state_save($s);
            [$ok, $msg] = rd_news();
            $log('Redacción, noticias: ' . $msg);
        }
    });
}
