<?php
/**
 * Paquete "audio": texto a voz.
 *
 * Flujo: el texto del elemento (título opcional, cuerpo y, en páginas del constructor, los textos de sus secciones) se
 * limpia de HTML, se parte en trozos por oraciones según el límite del proveedor, cada trozo se convierte por HTTP y
 * los MP3 se concatenan en uploads/audio/<tipo>/<slug>-<idioma>.mp3. La ruta queda en el campo `audio` del elemento
 * (por idioma). El reproductor se inserta al dibujar (gancho 'content') al principio o al final del cuerpo, o lo pone
 * el tema con au_player() / el bloque "Reproductor". Proveedores por HTTP puro: OpenAI, ElevenLabs, Azure, Google;
 * y "prueba", que devuelve un MP3 en silencio sin clave para probar el flujo.
 */
declare(strict_types=1);
if (!function_exists('au_settings')) {

    /** Ajustes del paquete con sus valores por defecto. */
    function au_settings(): array
    {
        static $o = null;
        if ($o !== null) return $o;
        $S = cms_settings();
        $g = fn(string $k, string $d = '') => trim((string) (($S[$k] ?? '') === '' ? $d : $S[$k]));
        return $o = [
            'provider' => $g('audio_provider', 'prueba'),
            'openai_key' => $g('audio_openai_key'), 'openai_voice' => $g('audio_openai_voice', 'nova'), 'openai_model' => $g('audio_openai_model', 'gpt-4o-mini-tts'), 'openai_instructions' => $g('audio_openai_instructions'),
            'elevenlabs_key' => $g('audio_elevenlabs_key'), 'elevenlabs_voice' => $g('audio_elevenlabs_voice', 'pNInz6obpgDQGcFmaJgB'), 'elevenlabs_model' => $g('audio_elevenlabs_model', 'eleven_multilingual_v2'),
            'azure_key' => $g('audio_azure_key'), 'azure_region' => $g('audio_azure_region', 'eastus'), 'azure_voice' => $g('audio_azure_voice', 'es-MX-DaliaNeural'),
            'google_key' => $g('audio_google_key'), 'google_voice' => $g('audio_google_voice', 'es-US-Neural2-A'),
            'types' => array_values(array_filter(array_map('trim', explode(',', $g('audio_types'))))),
            'player' => in_array($g('audio_player', 'top'), ['top', 'bottom', 'none'], true) ? $g('audio_player', 'top') : 'top',
            'auto' => !empty($S['audio_auto']),
            'title' => !isset($S['audio_title']) || !empty($S['audio_title']),
            'max_chars' => max(500, min(100000, (int) $g('audio_max_chars', '15000'))),
        ];
    }

    function au_provider_label(string $p): string
    {
        return ['prueba' => 'Prueba (silencio)', 'openai' => 'OpenAI', 'elevenlabs' => 'ElevenLabs', 'azure' => 'Azure', 'google' => 'Google Cloud'][$p] ?? $p;
    }

    /** ¿El paquete atiende este tipo de contenido? */
    function au_type_ok(?string $type): bool
    {
        $o = au_settings();
        return $type !== null && $type !== '' && (!$o['types'] || in_array($type, $o['types'], true));
    }

    /** Ruta del MP3 de un elemento en un idioma ('' si no tiene; sin respaldo a otro idioma: un audio en español no va en la página en inglés). */
    function au_path(array $item, string $lang): string
    {
        $v = $item['audio'] ?? '';
        if (is_array($v)) $v = (string) ($v[$lang] ?? '');
        return trim((string) $v);
    }

    /**
     * El elemento con todos sus idiomas (sin localizar): al dibujar el sitio, cms_current()['item'] ya viene resuelto al
     * idioma de la petición y, si en ese idioma no hay audio, traería el del predeterminado por el respaldo bilingüe.
     */
    function au_raw_item(string $type, string $slug): ?array
    {
        $it = $GLOBALS['cms_item_override'][$type][$slug] ?? null;
        if ($it === null) $it = cms_json_read(cms_content_dir($type) . '/' . cms_slugify($slug) . '.json', null);
        return is_array($it) ? $it : null;
    }

    /** Texto plano a partir de HTML: párrafos y títulos como pausas, sin URL ni espacios repetidos. */
    function au_plain(string $html): string
    {
        $s = preg_replace('~<(script|style|figure|figcaption|iframe|svg)\b[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
        $s = preg_replace('~</(p|div|h[1-6]|li|blockquote|tr|section|article)>|<br\s*/?>~i', ". \n", $s) ?? $s;
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('~https?://\S+~i', '', $s) ?? $s;
        $s = preg_replace('~[ \t]+~', ' ', $s) ?? $s;
        $s = preg_replace('~\s*\.\s*\.(\s*\.)*~', '.', $s) ?? $s;       // ". ." → "."
        $s = preg_replace('~\n\s*\n+~', "\n", $s) ?? $s;
        return trim($s);
    }

    /** Texto que se leerá de un elemento (título opcional, cuerpo y textos de sus secciones), recortado al máximo. */
    function au_text(string $type, array $item, string $lang): string
    {
        $o = au_settings();
        $def = cms_type($type) ?? [];
        $parts = [];
        $title = trim((string) cms_f($item, $def['title_field'] ?? 'title', $lang));
        if ($o['title'] && $title !== '') $parts[] = rtrim($title, '.!?') . '.';
        foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
            $t = $fd['type'] ?? 'text';
            if ($t === 'html') $parts[] = au_plain(au_content_raw((string) cms_f($item, $k, $lang)));
            elseif ($t === 'sections') foreach ((array) cms_f($item, $k, $lang, []) as $sec) $parts[] = au_section_text((array) $sec, $lang);
        }
        $text = trim(implode("\n", array_filter($parts, fn($p) => trim((string) $p) !== '')));
        if (mb_strlen($text) > $o['max_chars']) {
            $text = mb_substr($text, 0, $o['max_chars']);
            $cut = max(mb_strrpos($text, '. ') ?: 0, mb_strrpos($text, "\n") ?: 0);
            if ($cut > $o['max_chars'] * 0.6) $text = mb_substr($text, 0, $cut + 1);
        }
        return $text;
    }

    /** HTML del editor o Markdown heredado, sin pasar por los ganchos (para no leer los enlaces automáticos, etc.). */
    function au_content_raw(string $text): string
    {
        if (trim($text) === '') return '';
        return preg_match('/^\s*</', $text) ? $text : cms_md($text);
    }

    /** Textos legibles de una sección del constructor, en el orden de los campos del bloque. */
    function au_section_text(array $sec, string $lang): string
    {
        if (!empty($sec['hidden'])) return '';
        $def = cms_block((string) ($sec['type'] ?? ''));
        if (!$def) return '';
        $out = [];
        foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
            $t = $fd['type'] ?? 'text';
            if (!in_array($t, ['text', 'textarea', 'html', 'lines'], true)) continue;
            $v = cms_f((array) ($sec['data'] ?? []), $k, $lang, '');
            if (is_array($v)) $v = implode(". \n", array_map('strval', $v));
            $v = trim((string) $v);
            if ($v === '' || preg_match('~^(https?:)?//|^[a-z0-9_/.-]+\.(png|jpe?g|webp|svg|gif|mp4|pdf)$~i', $v)) continue;
            $out[] = $t === 'html' ? au_plain(au_content_raw($v)) : rtrim($v, '.!?') . '.';
        }
        return implode("\n", $out);
    }

    /** Parte el texto por oraciones en trozos de hasta $limit bytes (los proveedores limitan por petición). */
    function au_chunks(string $text, int $limit): array
    {
        if (strlen($text) <= $limit) return [$text];
        $sentences = preg_split('/(?<!\b(?:Sr|Sra|Dr|Dra|Lic|Ing|Prof|No|Núm|pág|etc|[A-ZÁÉÍÓÚÑ]))(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $chunks = []; $cur = '';
        foreach ($sentences as $s) {
            $s = trim($s);
            if ($s === '') continue;
            if (strlen($s) > $limit) {   // una oración larguísima: por palabras
                if ($cur !== '') { $chunks[] = $cur; $cur = ''; }
                $w = '';
                foreach (preg_split('/\s+/u', $s) ?: [] as $word) {
                    if (strlen($w . ' ' . $word) > $limit && $w !== '') { $chunks[] = trim($w); $w = ''; }
                    $w .= ($w === '' ? '' : ' ') . $word;
                }
                if ($w !== '') $chunks[] = trim($w);
                continue;
            }
            if (strlen($cur . ' ' . $s) > $limit && $cur !== '') { $chunks[] = $cur; $cur = $s; }
            else $cur .= ($cur === '' ? '' : ' ') . $s;
        }
        if ($cur !== '') $chunks[] = $cur;
        return $chunks;
    }

    /** Límite de bytes por petición de cada proveedor (con margen). */
    function au_limit(string $provider): int
    {
        return ['openai' => 4000, 'elevenlabs' => 2500, 'azure' => 8000, 'google' => 4500, 'prueba' => 100000][$provider] ?? 3000;
    }

    /** Convierte un trozo de texto en bytes MP3 con el proveedor configurado. Lanza RuntimeException si falla. */
    function au_synth(string $text, ?string $lang = null): string
    {
        $o = au_settings();
        $lang = $lang ?? cms_default_lang();
        switch ($o['provider']) {
            case 'prueba':
                return (string) file_get_contents(__DIR__ . '/assets/silencio.mp3');
            case 'openai':
                if ($o['openai_key'] === '') throw new RuntimeException('Falta la clave de OpenAI en Ajustes → Audio.');
                $body = ['model' => $o['openai_model'], 'input' => $text, 'voice' => $o['openai_voice'], 'response_format' => 'mp3'];
                if ($o['openai_instructions'] !== '' && strpos($o['openai_model'], 'gpt-4o') === 0) $body['instructions'] = $o['openai_instructions'];
                return cms_http_post('https://api.openai.com/v1/audio/speech', $body, ['Authorization: Bearer ' . $o['openai_key']], 180);
            case 'elevenlabs':
                if ($o['elevenlabs_key'] === '') throw new RuntimeException('Falta la clave de ElevenLabs en Ajustes → Audio.');
                return cms_http_post('https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($o['elevenlabs_voice']) . '?output_format=mp3_44100_128',
                    ['text' => $text, 'model_id' => $o['elevenlabs_model']], ['xi-api-key: ' . $o['elevenlabs_key'], 'Accept: audio/mpeg'], 180);
            case 'azure':
                if ($o['azure_key'] === '') throw new RuntimeException('Falta la clave de Azure en Ajustes → Audio.');
                $voice = $o['azure_voice'];
                $xmlLang = preg_match('/^([a-z]{2}-[A-Z]{2})/', $voice, $m) ? $m[1] : ($lang === 'en' ? 'en-US' : 'es-MX');
                $ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="' . $xmlLang . '"><voice name="' . htmlspecialchars($voice, ENT_QUOTES) . '">' . htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</voice></speak>';
                $region = preg_replace('/[^a-z0-9]/', '', strtolower($o['azure_region'])) ?: 'eastus';
                return cms_http_post('https://' . $region . '.tts.speech.microsoft.com/cognitiveservices/v1', $ssml,
                    ['Ocp-Apim-Subscription-Key: ' . $o['azure_key'], 'Content-Type: application/ssml+xml', 'X-Microsoft-OutputFormat: audio-24khz-96kbitrate-mono-mp3', 'User-Agent: cms_simple'], 180);
            case 'google':
                if ($o['google_key'] === '') throw new RuntimeException('Falta la clave de Google Cloud en Ajustes → Audio.');
                $voice = $o['google_voice'];
                $code = preg_match('/^([a-z]{2}-[A-Z]{2})/', $voice, $m) ? $m[1] : ($lang === 'en' ? 'en-US' : 'es-US');
                $raw = cms_http_post('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . rawurlencode($o['google_key']),
                    ['input' => ['text' => $text], 'voice' => ['languageCode' => $code, 'name' => $voice], 'audioConfig' => ['audioEncoding' => 'MP3']], [], 180);
                $j = json_decode($raw, true);
                if (empty($j['audioContent'])) throw new RuntimeException('Google no devolvió audio.');
                return (string) base64_decode((string) $j['audioContent'], true);
        }
        throw new RuntimeException('Proveedor de voz desconocido: ' . $o['provider']);
    }

    /** Carpeta y ruta relativa del MP3 de un elemento. */
    function au_file(string $type, string $slug, string $lang): string
    {
        return 'uploads/audio/' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '/' . cms_slugify($slug) . '-' . preg_replace('/[^a-z]/', '', $lang) . '.mp3';
    }

    /**
     * Genera el audio de un elemento en un idioma y guarda la ruta en su campo `audio`.
     * Devuelve [ok, mensaje, ruta]. Lee el elemento sin localizar (con todos los idiomas) para no tocar los demás.
     */
    function au_generate(string $type, string $slug, string $lang): array
    {
        $o = au_settings();
        if (!au_type_ok($type)) return [false, 'La colección "' . $type . '" no está entre las que llevan audio (Ajustes → Audio).', ''];
        $prevLang = $GLOBALS['cms_render_lang'] ?? null;
        unset($GLOBALS['cms_render_lang']);
        cms_items_flush();
        $item = cms_item($type, $slug, false);
        if ($prevLang !== null) $GLOBALS['cms_render_lang'] = $prevLang;
        if (!$item) return [false, 'El elemento no existe.', ''];
        $text = au_text($type, $item, $lang);
        if (mb_strlen($text) < 20) return [false, 'El elemento no tiene texto suficiente para leer' . (count(cms_langs()) > 1 ? ' en ' . strtoupper($lang) : '') . '.', ''];
        @set_time_limit(600);
        $mp3 = '';
        $chunks = au_chunks($text, au_limit($o['provider']));
        try {
            foreach ($chunks as $c) $mp3 .= au_synth($c, $lang);
        } catch (\Throwable $e) {
            return [false, au_provider_label($o['provider']) . ': ' . $e->getMessage(), ''];
        }
        if (strlen($mp3) < 100) return [false, 'El proveedor devolvió un archivo vacío.', ''];
        $rel = au_file($type, $slug, $lang);
        $abs = CMS_ROOT . '/' . $rel;
        if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0755, true)) return [false, 'No se pudo crear uploads/audio/. Revisa permisos.', ''];
        if (@file_put_contents($abs, $mp3) === false) return [false, 'No se pudo escribir el MP3 en uploads/audio/.', ''];
        @chmod($abs, 0644);
        $audio = is_array($item['audio'] ?? null) ? $item['audio'] : [];
        if (!is_array($item['audio'] ?? null) && trim((string) ($item['audio'] ?? '')) !== '') $audio[cms_default_lang()] = (string) $item['audio'];
        $audio[$lang] = $rel;
        $item['audio'] = $audio;
        $GLOBALS['au_saving'] = true;
        $ok = cms_item_save($type, $item);
        $GLOBALS['au_saving'] = false;
        if (!$ok) return [false, 'Se generó el MP3 pero no se pudo guardar el elemento.', $rel];
        $kb = (int) round(strlen($mp3) / 1024);
        return [true, 'Audio generado con ' . au_provider_label($o['provider']) . ' (' . count($chunks) . ' ' . (count($chunks) === 1 ? 'petición' : 'peticiones') . ', ' . $kb . ' KB, ' . mb_strlen($text) . ' caracteres).', $rel];
    }

    /** Quita el audio de un idioma (borra el archivo si está en uploads/audio/). */
    function au_remove(string $type, string $slug, string $lang): bool
    {
        $prevLang = $GLOBALS['cms_render_lang'] ?? null;
        unset($GLOBALS['cms_render_lang']);
        cms_items_flush();
        $item = cms_item($type, $slug, false);
        if ($prevLang !== null) $GLOBALS['cms_render_lang'] = $prevLang;
        if (!$item) return false;
        $path = au_path($item, $lang);
        if ($path !== '' && strpos($path, 'uploads/audio/') === 0 && is_file(CMS_ROOT . '/' . $path)) @unlink(CMS_ROOT . '/' . $path);
        if (is_array($item['audio'] ?? null)) $item['audio'][$lang] = ''; else $item['audio'] = '';
        $GLOBALS['au_saving'] = true;
        $ok = cms_item_save($type, $item);
        $GLOBALS['au_saving'] = false;
        return $ok;
    }

    /** HTML del reproductor. $path relativa (uploads/…) o URL. */
    function au_player(string $path, string $lang, string $label = ''): string
    {
        if (trim($path) === '') return '';
        $S = cms_settings();
        if ($label === '') $label = trim((string) cms_localize($S['audio_label'] ?? '', $lang)) ?: ($lang === 'en' ? 'Listen to this article' : 'Escucha este artículo');
        $url = cms_img($path);
        return '<figure class="cms-audio"><figcaption class="cms-audio-label">' . cms_e($label) . '</figcaption>'
            . '<audio class="cms-audio-el" controls preload="none" src="' . cms_e($url) . '"></audio>'
            . '<a class="cms-audio-dl" href="' . cms_e($url) . '" download>MP3</a></figure>';
    }

    /** CSS mínimo del reproductor (el tema puede sobrescribir .cms-audio). */
    function au_css(): string
    {
        return '.cms-audio{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;margin:0 0 1.5em;padding:12px 16px;border:1px solid var(--cms-line,rgba(0,0,0,.12));border-radius:var(--cms-radius,12px);background:var(--cms-soft,rgba(0,0,0,.03))}'
            . '.cms-audio-label{font-weight:600;font-size:.95em}.cms-audio-el{flex:1 1 240px;min-width:0;height:36px}.cms-audio-dl{font-size:.8em;opacity:.7;text-decoration:none}.cms-audio-dl:hover{opacity:1}';
    }

    /* ------------------------------------------------------------------ ganchos */

    // reproductor dentro del cuerpo del elemento (antes que otros filtros, para comparar con el HTML original)
    cms_on('content', function (string $html, array $ctx): string {
        $o = au_settings();
        if ($o['player'] === 'none' || empty($ctx['item']) || !au_type_ok((string) ($ctx['type'] ?? ''))) return $html;
        if (function_exists('cms_is_demo') && cms_is_demo()) return $html;
        static $done = false;
        if ($done) return $html;
        $lang = (string) ($ctx['lang'] ?? cms_default_lang());
        $raw = au_raw_item((string) $ctx['type'], (string) ($ctx['item']['slug'] ?? ''));
        $path = $raw ? au_path($raw, $lang) : '';
        if ($path === '') return $html;
        // solo el cuerpo principal: el primer campo html del tipo cuyo HTML coincide con lo que se está dibujando
        $def = cms_type((string) $ctx['type']) ?? [];
        $isBody = false;
        foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
            if (($fd['type'] ?? '') !== 'html') continue;
            $raw = str_replace('{{base}}', CMS_BASE, au_content_raw((string) cms_f($ctx['item'], $k, $lang)));
            if (trim($raw) !== '' && trim($raw) === trim($html)) { $isBody = true; break; }
        }
        if (!$isBody) return $html;
        $done = true;
        $player = au_player($path, $lang);
        return $o['player'] === 'bottom' ? $html . $player : $player . $html;
    }, 5);

    // CSS del reproductor solo en páginas cuyo elemento tiene audio (el bloque carga assets/audio.css por su cuenta)
    cms_on('head', function (array $page): void {
        $cur = cms_current();
        $raw = !empty($cur['item']) ? au_raw_item((string) $cur['type'], (string) ($cur['item']['slug'] ?? '')) : null;
        if (!$raw || au_path($raw, (string) $cur['lang']) === '') return;
        echo '<style>' . au_css() . '</style>' . "\n";
    });

    // generación automática al publicar (opcional)
    cms_on('item.save', function (string $type, array $item): void {
        $o = au_settings();
        if (!$o['auto'] || !empty($GLOBALS['au_saving']) || ($item['status'] ?? '') !== 'published' || !au_type_ok($type)) return;
        foreach (cms_active_langs() as $l) if (au_path($item, $l) === '') au_generate($type, (string) $item['slug'], $l);
    });

    // botones en la barra lateral del editor
    cms_on('admin.item.sidebar', function (string $type, array $item): void {
        if (!au_type_ok($type)) return;
        $o = au_settings();
        $back = admin_url('edit', ['type' => $type, 'slug' => $item['slug']]);
        echo '<div class="ad-field au-box"><label>Audio · ' . cms_e(au_provider_label($o['provider'])) . '</label>';
        foreach (cms_active_langs() as $l) {
            $path = au_path($item, $l);
            $tag = count(cms_langs()) > 1 ? strtoupper($l) . ': ' : '';
            echo '<div class="au-row">';
            if ($path !== '') echo '<audio controls preload="none" src="' . cms_e(cms_img($path)) . '" style="width:100%;height:32px"></audio>';
            echo '<form method="post" action="' . admin_url('pack:audio') . '" class="ad-inline">' . admin_csrf_field()
                . '<input type="hidden" name="action" value="generate"><input type="hidden" name="type" value="' . cms_e($type) . '"><input type="hidden" name="slug" value="' . cms_e($item['slug']) . '"><input type="hidden" name="lang" value="' . cms_e($l) . '"><input type="hidden" name="back" value="' . cms_e($back) . '">'
                . '<button class="ad-btn ad-btn-sm' . ($path !== '' ? ' ad-btn-light' : '') . '" type="submit" title="Convierte el texto guardado; guarda antes tus cambios">' . $tag . ($path !== '' ? 'Volver a generar' : 'Generar audio') . '</button></form> ';
            if ($path !== '') echo '<form method="post" action="' . admin_url('pack:audio') . '" class="ad-inline" data-confirm="¿Quitar el audio' . ($tag ? ' ' . strtoupper($l) : '') . '?">' . admin_csrf_field()
                . '<input type="hidden" name="action" value="remove"><input type="hidden" name="type" value="' . cms_e($type) . '"><input type="hidden" name="slug" value="' . cms_e($item['slug']) . '"><input type="hidden" name="lang" value="' . cms_e($l) . '"><input type="hidden" name="back" value="' . cms_e($back) . '">'
                . '<button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Quitar</button></form>';
            echo '</div>';
        }
        echo '<p class="ad-help">Convierte el texto tal como está guardado. Tarda unos segundos por cada 4,000 caracteres.</p></div>';
    });
}
