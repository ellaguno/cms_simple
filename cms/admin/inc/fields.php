<?php
/**
 * cms_simple admin — campos guiados por esquema.
 *
 * Definición de campo (site/config.php):
 *   'campo' => ['type' => text|textarea|html|date|number|url|email|select|checkbox|image|images|lines|tags,
 *               'label' => 'Etiqueta', 'help' => 'Ayuda', 'i18n' => true|false, 'required' => bool,
 *               'options' => [...] (select), 'rows' => n, 'sidebar' => true (columna derecha), 'placeholder' => '']
 * Los tipos de contenido siempre tienen además: slug, status, seo_title, seo_desc, created, updated.
 */
declare(strict_types=1);

function admin_field_label(string $name, array $def): string
{
    return (string) ($def['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $name)));
}

/** Un control HTML (sin envoltorio). $value ya es el valor de ese idioma. */
function admin_control(string $inputName, array $def, $value, string $extra = ''): string
{
    $type = $def['type'] ?? 'text';
    $ph = isset($def['placeholder']) ? ' placeholder="' . cms_e($def['placeholder']) . '"' : '';
    $req = !empty($def['required']) ? ' required' : '';
    switch ($type) {
        case 'textarea':
            return '<textarea name="' . cms_e($inputName) . '" rows="' . (int) ($def['rows'] ?? 4) . '"' . $ph . $req . ' ' . $extra . '>' . cms_e(is_array($value) ? implode("\n", $value) : (string) $value) . '</textarea>';
        case 'html':
            $v = (string) $value;
            if ($v !== '' && !preg_match('/^\s*</', $v)) $v = cms_md($v); // Markdown heredado
            return '<textarea name="' . cms_e($inputName) . '" data-html="1" data-size="' . cms_e($def['size'] ?? 'md') . '" hidden>' . cms_e($v) . '</textarea>';
        case 'lines':
        case 'images':
            $v = is_array($value) ? implode("\n", $value) : (string) $value;
            $out = '<textarea name="' . cms_e($inputName) . '" rows="' . (int) ($def['rows'] ?? 4) . '"' . ($type === 'images' ? ' data-image-list' . (isset($def['suffix']) ? ' data-image-suffix="' . cms_e($def['suffix']) . '"' : '') : '') . $ph . '>' . cms_e($v) . '</textarea>';
            if ($type === 'images') $out .= '<div class="ad-btnrow"><button type="button" class="ad-btn ad-btn-sm" data-upload-append>Subir y agregar</button> <button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-pick-append="image">Biblioteca</button></div>';
            return $out;
        case 'tags':
            return '<input type="text" name="' . cms_e($inputName) . '" value="' . cms_e(is_array($value) ? implode(', ', $value) : (string) $value) . '" placeholder="separadas por coma">';
        case 'select':
            $out = '<select name="' . cms_e($inputName) . '">';
            foreach ((array) ($def['options'] ?? []) as $k => $lab) {
                $val = is_int($k) ? $lab : $k;
                $out .= '<option value="' . cms_e($val) . '"' . ((string) $value === (string) $val ? ' selected' : '') . '>' . cms_e($lab) . '</option>';
            }
            return $out . '</select>';
        case 'checkbox':
            return '<label class="ad-check"><input type="hidden" name="' . cms_e($inputName) . '" value="0"><input type="checkbox" name="' . cms_e($inputName) . '" value="1"' . ($value ? ' checked' : '') . '> ' . cms_e($def['text'] ?? 'Sí') . '</label>';
        case 'image':
            $v = (string) $value;
            return '<div class="ad-image-row"><input type="text" name="' . cms_e($inputName) . '" value="' . cms_e($v) . '" data-image-input' . $ph . '>'
                . '<button type="button" class="ad-btn ad-btn-sm" data-upload>Subir imagen</button>'
                . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-pick="image">Biblioteca</button>'
                . '<img class="ad-thumb" src="' . ($v ? cms_e(cms_img($v)) : '') . '" alt="" data-preview' . ($v ? '' : ' hidden') . '></div>';
        case 'sections':
            return admin_sections_control($inputName, $def, is_array($value) ? $value : []);
        case 'code':
            return '<textarea name="' . cms_e($inputName) . '" rows="' . (int) ($def['rows'] ?? 8) . '" class="ad-code"' . $ph . ' spellcheck="false">' . cms_e((string) $value) . '</textarea>';
        case 'date': case 'number': case 'url': case 'email':
            $attrs = '';
            foreach (['min', 'max', 'step'] as $a) if (isset($def[$a])) $attrs .= ' ' . $a . '="' . cms_e($def[$a]) . '"';
            return '<input type="' . $type . '" name="' . cms_e($inputName) . '" value="' . cms_e((string) $value) . '"' . $ph . $req . $attrs . ' ' . $extra . '>';
        default:
            $attrs = isset($def['maxlength']) ? ' maxlength="' . (int) $def['maxlength'] . '"' : '';
            return '<input type="text" name="' . cms_e($inputName) . '" value="' . cms_e(is_array($value) ? implode(', ', $value) : (string) $value) . '"' . $ph . $req . $attrs . ' ' . $extra . '>';
    }
}

/** Campo completo (etiqueta, ayuda, control), bilingüe si i18n. */
function admin_field(string $name, array $def, $value): void
{
    $label = admin_field_label(preg_replace('/^.*\[([^\]]+)\]$/', '$1', $name), $def);
    echo '<div class="ad-field ad-field-' . cms_e($def['type'] ?? 'text') . '"><label>' . cms_e($label) . (!empty($def['required']) ? ' <span class="ad-req">*</span>' : '') . '</label>';
    if (!empty($def['help'])) echo '<p class="ad-help">' . cms_e($def['help']) . '</p>';
    if (!empty($def['i18n'])) {
        echo '<div class="ad-langs">';
        foreach (cms_langs() as $l) {
            $v = is_array($value) && !isset($value[0]) ? ($value[$l] ?? '') : ($l === cms_default_lang() ? $value : '');
            echo '<div class="ad-lang" data-lang="' . $l . '"><span class="ad-lang-tag">' . strtoupper($l) . '</span>' . admin_control($name . '[' . $l . ']', $def, $v) . '</div>';
        }
        echo '</div>';
    } else {
        echo admin_control($name, $def, $value);
    }
    echo '</div>';
}

/** Lee un valor del POST según la definición del campo. */
function admin_read_control(array $def, $raw)
{
    $type = $def['type'] ?? 'text';
    switch ($type) {
        case 'lines': case 'images':
            return cms_lines(is_string($raw) ? str_replace("\r\n", "\n", $raw) : '');
        case 'tags':
            return array_values(array_filter(array_map('trim', explode(',', is_string($raw) ? $raw : '')), fn($x) => $x !== ''));
        case 'html':
            return admin_clean_html(is_string($raw) ? str_replace("\r\n", "\n", $raw) : '');
        case 'checkbox':
            return !empty($raw) && $raw !== '0';
        case 'sections':
            return admin_read_sections($raw);
        case 'code':
            return is_string($raw) ? str_replace("\r\n", "\n", $raw) : '';
        case 'number':
            return is_numeric($raw) ? $raw + 0 : ($raw === '' || $raw === null ? '' : (string) $raw);
        default:
            return is_string($raw) ? trim(str_replace("\r\n", "\n", $raw)) : '';
    }
}

function admin_read_field(string $name, array $def, ?array $src = null)
{
    $src = $src ?? $_POST;
    if (!empty($def['i18n'])) {
        $out = [];
        foreach (cms_langs() as $l) $out[$l] = admin_read_control($def, $src[$name][$l] ?? null);
        return $out;
    }
    return admin_read_control($def, $src[$name] ?? null);
}

/** Lee un campo 'sections' del POST: valida tipo de bloque y limpia cada dato según la definición del bloque. */
function admin_read_sections($raw): array
{
    if (!is_array($raw)) return [];
    $out = [];
    $styles = cms_section_styles();
    foreach ($raw as $r) {
        if (!is_array($r)) continue;
        $type = preg_replace('/[^a-z0-9_-]/i', '', (string) ($r['type'] ?? ''));
        $def = cms_block($type);
        if (!$def) continue;
        $id = preg_replace('/[^a-z0-9]/i', '', (string) ($r['id'] ?? ''));
        if ($id === '') $id = cms_section_id();
        $data = [];
        foreach ((array) ($def['fields'] ?? []) as $k => $fd) $data[$k] = admin_read_field($k, (array) $fd, (array) ($r['data'] ?? []));
        $style = [];
        foreach (cms_block_styles($def) as $k => $sd) {
            $v = admin_read_field($k, $sd, (array) ($r['style'] ?? []));
            if ($v !== '' && $v !== false && $v !== null && $v !== []) $style[$k] = $v;
        }
        $out[] = ['id' => $id, 'type' => $type, 'data' => $data, 'style' => $style, 'hidden' => !empty($r['hidden']) && $r['hidden'] !== '0'];
    }
    return $out;
}

/** Lee del POST todos los campos de un elemento (slug, estado, campos del tipo, SEO, programación). Devuelve [item, errores]. */
function admin_read_item(string $type, array $def, array $fields, array $existing, string $orig = ''): array
{
    $dl = cms_default_lang();
    $titleField = $def['title_field'] ?? 'title';
    $errors = [];
    $new = ['slug' => '', 'status' => admin_post('status') === 'published' ? 'published' : 'draft'];
    $new['publish_at'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', admin_post('publish_at')) && admin_post('publish_at') > date('Y-m-d') ? admin_post('publish_at') : '';
    foreach ($fields as $name => $fd) $new[$name] = admin_read_field($name, (array) $fd);
    $titleVal = $new[$titleField] ?? '';
    $titleMain = is_array($titleVal) ? ($titleVal[$dl] ?? '') : (string) $titleVal;
    $slug = cms_slugify(admin_post('slug') ?: $titleMain);
    if ($titleMain === '') $errors[] = 'El campo "' . admin_field_label($titleField, (array) ($fields[$titleField] ?? [])) . '" es obligatorio' . (count(cms_langs()) > 1 ? ' en ' . strtoupper($dl) : '') . '.';
    foreach ($fields as $name => $fd) {
        if (empty($fd['required']) || $name === $titleField) continue;
        $v = $new[$name]; $v = is_array($v) && !isset($v[0]) ? ($v[$dl] ?? '') : $v;
        if ($v === '' || $v === []) $errors[] = 'El campo "' . admin_field_label($name, (array) $fd) . '" es obligatorio.';
    }
    if ($slug === '') $errors[] = 'No se pudo generar la URL (slug).';
    if ($slug && $slug !== $orig && is_file(cms_content_dir($type) . '/' . $slug . '.json')) $errors[] = 'Ya existe un elemento con la URL "' . $slug . '".';
    if (!empty($def['tree'])) {
        if (($new['parent'] ?? '') === '' && in_array($slug, cms_reserved_segments(), true)) $errors[] = 'La URL "' . $slug . '" está reservada por otra sección del sitio; elige otra o ponla bajo una página padre.';
        if (($new['parent'] ?? '') === $slug) $new['parent'] = '';
    }
    $new['slug'] = $slug;
    $new['seo_title'] = admin_read_field('seo_title', ['type' => 'text', 'i18n' => true]);
    $new['seo_desc'] = admin_read_field('seo_desc', ['type' => 'textarea', 'i18n' => true]);
    $new['created'] = $existing['created'] ?? date('Y-m-d');
    $new['updated'] = date('Y-m-d');
    return [$new + $existing, $errors];
}

/** Conmutador de idioma para formularios con campos bilingües. */
function admin_lang_switch(): void
{
    if (count(cms_langs()) < 2) return;
    $names = ['es' => 'Español', 'en' => 'English', 'fr' => 'Français', 'pt' => 'Português', 'de' => 'Deutsch', 'it' => 'Italiano'];
    echo '<div class="ad-lang-switch" data-lang-switch><span class="ad-help">Editando:</span>';
    foreach (cms_langs() as $i => $l) echo '<button type="button" data-set-lang="' . $l . '"' . ($i === 0 ? ' class="on"' : '') . '>' . cms_e($names[$l] ?? strtoupper($l)) . '</button>';
    echo '<span class="ad-help ad-lang-hint">Los campos muestran un idioma a la vez; todos se guardan.</span></div>';
}

/** Campos SEO estándar (título y descripción) de un elemento. */
function admin_seo_fields(array $item): void
{
    echo '<details class="ad-seo"><summary>SEO (opcional): título y descripción para buscadores</summary>';
    admin_field('seo_title', ['type' => 'text', 'label' => 'Título SEO (máx. 60 caracteres; vacío = título normal)', 'i18n' => true], $item['seo_title'] ?? []);
    admin_field('seo_desc', ['type' => 'textarea', 'label' => 'Descripción SEO (máx. 160; vacío = resumen)', 'i18n' => true, 'rows' => 3], $item['seo_desc'] ?? []);
    echo '</details>';
}

/* ------------------------------------------------------------------ secciones (constructor de páginas) */

/** Campo 'sections': lista de tarjetas (una por sección), selector de bloques y plantillas para añadir. */
function admin_sections_control(string $name, array $def, array $sections): string
{
    $blocks = cms_blocks();
    if (!$blocks) return '<p class="ad-help">Este tema no define bloques (site/blocks.php).</p>';
    $h = '<div class="ad-sections" data-sections="' . cms_e($name) . '">';
    $h .= '<div class="ad-sections-list" data-sections-list>';
    foreach (array_values($sections) as $i => $sec) {
        $bd = cms_block((string) ($sec['type'] ?? ''));
        if ($bd) $h .= admin_section_card($name, (string) $i, $sec, $bd);
    }
    $h .= '</div>';
    $h .= '<p class="ad-sections-empty ad-help"' . ($sections ? ' hidden' : '') . '>Esta página aún no tiene secciones. Añade la primera.</p>';
    $h .= '<div class="ad-sections-add"><button type="button" class="ad-btn" data-add-section>+ Añadir sección</button></div>';
    // selector
    $groups = [];
    foreach ($blocks as $k => $bd) $groups[(string) ($bd['group'] ?? 'Bloques')][$k] = $bd;
    $h .= '<div class="ad-modal" data-section-picker hidden><div class="ad-modal-box"><div class="ad-modal-head"><h3>Añadir sección</h3><button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-close>Cerrar</button></div><div class="ad-modal-body ad-picker-body">';
    foreach ($groups as $g => $list) {
        $h .= '<h4>' . cms_e($g) . '</h4><div class="ad-picker-grid">';
        foreach ($list as $k => $bd) $h .= '<button type="button" class="ad-picker-item" data-block="' . cms_e($k) . '"><strong>' . cms_e($bd['label']) . '</strong>' . (!empty($bd['desc']) ? '<span>' . cms_e($bd['desc']) . '</span>' : '') . '</button>';
        $h .= '</div>';
    }
    $h .= '</div></div></div>';
    // plantillas (una por bloque) para clonar desde JS
    foreach ($blocks as $k => $bd) $h .= '<template data-section-tpl="' . cms_e($k) . '">' . admin_section_card($name, '__IDX__', ['id' => '__ID__', 'type' => $k, 'data' => [], 'style' => []], $bd) . '</template>';
    return $h . '</div>';
}

/** Tarjeta de una sección: cabecera con herramientas y dos pestañas (contenido / estilo). */
function admin_section_card(string $name, string $idx, array $sec, array $bd): string
{
    $n = $name . '[' . $idx . ']';
    $type = (string) $bd['key'];
    $id = (string) ($sec['id'] ?? cms_section_id());
    $data = cms_block_data($bd, (array) ($sec['data'] ?? []));
    $style = (array) ($sec['style'] ?? []);
    $hidden = !empty($sec['hidden']);
    $summary = cms_section_summary($sec);
    $h = '<div class="ad-sec' . ($hidden ? ' ad-sec-hidden' : '') . '" data-sec data-sec-type="' . cms_e($type) . '" data-sec-id="' . cms_e($id) . '" draggable="true">';
    $h .= '<input type="hidden" name="' . cms_e($n . '[type]') . '" value="' . cms_e($type) . '"><input type="hidden" name="' . cms_e($n . '[id]') . '" value="' . cms_e($id) . '">';
    $h .= '<div class="ad-sec-head"><span class="ad-sec-grip" title="Arrastra para reordenar">⋮⋮</span>'
        . '<button type="button" class="ad-sec-toggle" data-sec-toggle aria-label="Plegar o desplegar"></button>'
        . '<span class="ad-sec-kind">' . cms_e($bd['label']) . '</span><span class="ad-sec-title" data-sec-title>' . cms_e($summary) . '</span>'
        . '<span class="ad-sec-tools">'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-up title="Subir">↑</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-down title="Bajar">↓</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-dup title="Duplicar">⧉</button>'
        . '<label class="ad-sec-hide" title="Guardar la sección pero no mostrarla"><input type="hidden" name="' . cms_e($n . '[hidden]') . '" value="0"><input type="checkbox" name="' . cms_e($n . '[hidden]') . '" value="1"' . ($hidden ? ' checked' : '') . '> oculta</label>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-danger" data-sec-del title="Quitar">×</button>'
        . '</span></div>';
    $h .= '<div class="ad-sec-body"><div class="ad-sec-tabs"><button type="button" class="on" data-sec-tab="content">Contenido</button>';
    $styles = cms_block_styles($bd);
    if ($styles) $h .= '<button type="button" data-sec-tab="style">Estilo</button>';
    $h .= '</div>';
    ob_start();
    echo '<div class="ad-sec-pane" data-sec-pane="content">';
    if (!empty($bd['help'])) echo '<p class="ad-help">' . cms_e($bd['help']) . '</p>';
    $fields = (array) ($bd['fields'] ?? []);
    $half = array_filter($fields, fn($f) => !empty($f['half']));
    foreach ($fields as $k => $fd) admin_field($n . '[data][' . $k . ']', ['label' => admin_field_label($k, (array) $fd)] + (array) $fd, $data[$k] ?? '');
    echo '</div>';
    if ($styles) {
        echo '<div class="ad-sec-pane ad-sec-style" data-sec-pane="style" hidden><div class="ad-two">';
        foreach ($styles as $k => $sd) admin_field($n . '[style][' . $k . ']', $sd, $style[$k] ?? '');
        echo '</div></div>';
    }
    $h .= ob_get_clean();
    return $h . '</div></div>';
}
