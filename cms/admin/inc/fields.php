<?php
/**
 * cms_simple admin — campos guiados por esquema.
 *
 * Definición de campo (site/config.php):
 *   'campo' => ['type' => text|textarea|html|date|number|url|email|select|checks|checkbox|image|images|lines|tags,
 *               (checks: casillas de varias opciones de 'options' u 'options_from'; guarda la lista de claves marcadas; 1.51),
 *               (rows: filas repetibles con sus propios subcampos en 'fields'; ver admin_rows_control(); 1.55),
 *               'label' => 'Etiqueta', 'help' => 'Ayuda', 'i18n' => true|false, 'required' => bool,
 *               'options' => [...] (select), 'options_from' => 'tipo' (select con los elementos de esa colección), 'rows' => n, 'sidebar' => true (columna derecha), 'placeholder' => '',
 *               'show_if' => ['otro_campo' => 'valor'] (solo se muestra cuando ese control vale eso; 'valor' puede ser una lista)]
 * Los tipos de contenido siempre tienen además: slug, status, seo_title, seo_desc, created, updated.
 */
declare(strict_types=1);

function admin_field_label(string $name, array $def): string
{
    return (string) ($def['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $name)));
}

/**
 * Opciones de un selector: 'options' y, con 'options_from' => 'tipo' (1.41), los elementos de esa colección
 * (slug => título), p. ej. el curso al que pertenece una lección.
 */
function admin_select_options(array $def): array
{
    $opts = (array) ($def['options'] ?? []);
    if (!empty($def['options_from']) && ($fd = cms_type((string) $def['options_from']))) {
        // 'options_suffix' => 'campo' (1.44): añade ese campo a la etiqueta ("Lección 3 · iu-102"), para distinguir títulos repetidos
        $sfx = (string) ($def['options_suffix'] ?? '');
        $items = cms_items((string) $def['options_from'], false);
        if ($sfx !== '') uasort($items, fn($a, $b) => strcmp((string) ($a[$sfx] ?? ''), (string) ($b[$sfx] ?? '')) ?: ((float) ($a['order'] ?? 0) <=> (float) ($b['order'] ?? 0)));
        foreach ($items as $it) $opts[(string) $it['slug']] = (string) (cms_f($it, $fd['title_field'] ?? 'title', cms_default_lang()) ?: $it['slug']) . ($sfx !== '' && (string) ($it[$sfx] ?? '') !== '' ? ' · ' . $it[$sfx] : '');
    }
    return $opts;
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
            $out = '<select name="' . cms_e($inputName) . '"' . $req . '>';
            $opts = admin_select_options($def);
            if (!empty($def['options_from']) && (string) $value !== '' && !isset($opts[(string) $value])) $opts[(string) $value] = $value . ' (no existe)';
            foreach ($opts as $k => $lab) {
                $val = is_int($k) ? $lab : $k;
                $out .= '<option value="' . cms_e($val) . '"' . ((string) $value === (string) $val ? ' selected' : '') . '>' . cms_e($lab) . '</option>';
            }
            return $out . '</select>';
        case 'checks':   // varias opciones a la vez (1.51); las marcadas que ya no están en la lista se ven, para poder quitarlas
            $opts = admin_select_options($def);
            $sel = array_values(array_filter(array_map(fn($x) => is_scalar($x) ? (string) $x : '', (array) $value), fn($x) => $x !== ''));
            foreach ($sel as $x) if (!isset($opts[$x])) $opts[$x] = $x . ' (ya no está en la lista)';
            if (!$opts) return '<p class="ad-help">' . cms_e((string) ($def['empty'] ?? 'Todavía no hay opciones.')) . '</p><input type="hidden" name="' . cms_e($inputName) . '[]" value="">';
            $out = '<div class="ad-checks"><input type="hidden" name="' . cms_e($inputName) . '[]" value="">';
            foreach ($opts as $k => $lab) {
                $val = is_int($k) ? (string) $lab : (string) $k;
                $out .= '<label class="ad-check"><input type="checkbox" name="' . cms_e($inputName) . '[]" value="' . cms_e($val) . '"' . (in_array($val, $sel, true) ? ' checked' : '') . '> ' . cms_e((string) $lab) . '</label>';
            }
            return $out . '</div>';
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
        case 'rows':
            return admin_rows_control($inputName, $def, is_array($value) ? $value : []);
        case 'category':   // selector del registro de categorías del tipo (+ nueva); el valor guardado sigue siendo la etiqueta
            $tk = (string) ($def['_type'] ?? '');
            $cur = $tk !== '' ? cms_category_slug_of($tk, $value) : '';
            if ($tk !== '' && $cur !== '' && !cms_category($tk, $cur)) $cur = cms_category_create($tk, is_array($value) ? (string) (cms_f(['v' => $value], 'v', cms_default_lang()) ?: reset($value)) : (string) $value);   // valor viejo sin registrar
            $out = '<div class="ad-category" data-category><select name="' . cms_e($inputName) . '" data-category-select><option value="">— Sin categoría —</option>';
            foreach ($tk !== '' ? cms_categories($tk) : [] as $slug => $c) $out .= '<option value="' . cms_e((string) $slug) . '"' . ((string) $slug === $cur ? ' selected' : '') . '>' . cms_e(cms_category_label($c, cms_default_lang())) . '</option>';
            $out .= '<option value="__new__">+ Nueva categoría…</option></select>';
            $out .= '<input type="text" name="' . cms_e($inputName) . '__new" placeholder="Nombre de la nueva categoría" data-category-new hidden></div>';
            if ($tk !== '') $out .= '<p class="ad-help">Se administran en <a href="' . ADMIN_URL . '/?p=categorias&type=' . rawurlencode($tk) . '">Diseño → Categorías</a> (nombre por idioma, orden, descripción, fusionar).</p>';
            return $out;
        case 'code':
            return '<textarea name="' . cms_e($inputName) . '" rows="' . (int) ($def['rows'] ?? 8) . '" class="ad-code"' . $ph . ' spellcheck="false">' . cms_e((string) $value) . '</textarea>';
        case 'color':
            $v = preg_match('/^#[0-9a-f]{6}$/i', (string) $value) ? strtolower((string) $value) : '';
            return '<div class="ad-color-row"><input type="color" value="' . ($v ?: '#888888') . '" data-color-pick' . ($v ? '' : ' class="is-empty"') . '>'
                . '<input type="text" name="' . cms_e($inputName) . '" value="' . cms_e($v) . '" placeholder="' . cms_e($def['placeholder'] ?? '#rrggbb (vacío = el del tema)') . '" pattern="#[0-9a-fA-F]{6}" data-color-hex ' . $extra . '>'
                . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-color-clear title="Volver al color del tema">×</button></div>';
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
    // 'show_if' => ['otro_campo' => 'valor' | ['a', 'b']]: el panel muestra el campo solo cuando ese control tiene ese valor (JS)
    $showIf = !empty($def['show_if']) && is_array($def['show_if']) ? ' data-show-if=\'' . cms_e(json_encode($def['show_if'], JSON_UNESCAPED_UNICODE)) . '\'' : '';
    echo '<div class="ad-field ad-field-' . cms_e($def['type'] ?? 'text') . (!empty($def['half']) ? ' ad-field-half' : '') . '"' . $showIf . '><label>' . cms_e($label) . (!empty($def['required']) ? ' <span class="ad-req">*</span>' : '') . '</label>';
    if (!empty($def['help'])) echo '<p class="ad-help"' . (strpos((string) $def['help'], "\n") !== false ? ' style="white-space:pre-line"' : '') . '>' . cms_e($def['help']) . '</p>';   // con saltos de línea: se respetan (formato de las evaluaciones del aula)
    if (!empty($def['i18n']) && !in_array($def['type'] ?? '', ['category', 'rows'], true)) {
        echo '<div class="ad-langs">';
        foreach (cms_langs() as $l) {
            $v = is_array($value) && !isset($value[0]) ? ($value[$l] ?? '') : ($l === cms_default_lang() ? $value : '');
            // "obligatorio" solo en el idioma predeterminado: los demás pueden quedar vacíos (se muestra el predeterminado)
            $d = $l === cms_default_lang() ? $def : ['required' => false] + $def;
            echo '<div class="ad-lang" data-lang="' . $l . '"><span class="ad-lang-tag">' . strtoupper($l) . '</span>' . admin_control($name . '[' . $l . ']', $d, $v) . '</div>';
        }
        echo '</div>';
    } else {
        echo admin_control($name, $def, $value);
    }
    echo '</div>';
}

/**
 * Campos de la columna principal del editor, agrupados (1.55): los campos seguidos con el mismo 'fieldset' => 'Nombre'
 * van en un bloque plegable con ese título (el primero abierto; 'fieldset_open' => true en su primer campo lo abre
 * también). Sin 'fieldset' se dibujan como siempre. Sirve para tipos con muchos campos (una landing por sector).
 */
function admin_fields_grouped(array $fields, array $item): void
{
    $cur = null; $n = 0;
    foreach ($fields as $name => $fd) {
        $fs = isset($fd['fieldset']) ? trim((string) $fd['fieldset']) : '';
        if ($fs !== ($cur ?? '')) {
            if ($cur !== null && $cur !== '') echo '</div></details>';
            if ($fs !== '') {
                $open = $n === 0 || !empty($fd['fieldset_open']);
                echo '<details class="ad-fieldset"' . ($open ? ' open' : '') . '><summary>' . cms_e($fs) . '</summary><div class="ad-fieldset-body">';
                $n++;
            }
            $cur = $fs;
        }
        admin_field($name, $fd, $item[$name] ?? '');
    }
    if ($cur !== null && $cur !== '') echo '</div></details>';
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
        case 'checks':
            return array_values(array_unique(array_filter(array_map(fn($x) => is_string($x) ? trim($x) : '', is_array($raw) ? $raw : []), fn($x) => $x !== '')));
        case 'checkbox':
            return !empty($raw) && $raw !== '0';
        case 'sections':
            return admin_read_sections($raw);
        case 'rows':
            return admin_read_rows($def, $raw);
        case 'code':
            return is_string($raw) ? str_replace("\r\n", "\n", $raw) : '';
        case 'number':
            return is_numeric($raw) ? $raw + 0 : ($raw === '' || $raw === null ? '' : (string) $raw);
        case 'color':
            $c = strtolower(trim(is_string($raw) ? $raw : ''));
            if (preg_match('/^#?([0-9a-f]{3})$/', $c, $m)) $c = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
            elseif (preg_match('/^([0-9a-f]{6})$/', $c)) $c = '#' . $c;
            return preg_match('/^#[0-9a-f]{6}$/', $c) ? $c : '';
        default:
            return is_string($raw) ? trim(str_replace("\r\n", "\n", $raw)) : '';
    }
}

function admin_read_field(string $name, array $def, ?array $src = null)
{
    $src = $src ?? $_POST;
    if (($def['type'] ?? '') === 'category') return admin_read_category($name, $def, $src);
    if (!empty($def['i18n']) && ($def['type'] ?? '') !== 'rows') {
        $out = [];
        foreach (cms_langs() as $l) $out[$l] = admin_read_control($def, $src[$name][$l] ?? null);
        return $out;
    }
    return admin_read_control($def, $src[$name] ?? null);
}

/* ------------------------------------------------------------------ filas repetibles ('rows', 1.55) */

/**
 * Subcampos de un campo 'rows'. Admite los mismos tipos que un campo normal salvo los que necesitan su propio
 * editor de página completa: 'sections', 'rows' (no se anidan) y 'category'; 'html' se dibuja como área de texto.
 */
function admin_rows_fields(array $def): array
{
    $out = [];
    foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
        if (!is_array($fd) || !preg_match('/^[a-z0-9_]+$/i', (string) $k)) continue;
        $t = (string) ($fd['type'] ?? 'text');
        if (in_array($t, ['sections', 'rows', 'category'], true)) continue;
        if ($t === 'html') $fd['type'] = 'textarea';
        unset($fd['sidebar'], $fd['show_if']);
        $out[(string) $k] = $fd;
    }
    return $out;
}

/** ¿Tiene contenido un valor leído (texto, lista, casilla marcada o algún idioma lleno)? */
function admin_value_filled($v): bool
{
    if (is_array($v)) { foreach ($v as $x) if (admin_value_filled($x)) return true; return false; }
    if (is_bool($v)) return $v;
    return $v !== null && trim((string) $v) !== '';
}

/** Una fila del campo 'rows' ($key es su clave en el formulario; '__ROW__' en la plantilla para filas nuevas). */
function admin_row_html(string $name, array $fields, string $key, array $row): string
{
    $h = '<div class="ad-row" data-row data-row-key="' . cms_e($key) . '"><div class="ad-row-head">'
        . '<span class="ad-row-num" data-row-num></span><span class="ad-row-title" data-row-label></span><span class="ad-row-tools">'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-row-up title="Subir">↑</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-row-down title="Bajar">↓</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-row-dup title="Duplicar">⧉</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-row-del title="Quitar">✕</button>'
        . '</span></div><div class="ad-row-body">';
    ob_start();
    foreach ($fields as $k => $fd) admin_field($name . '[' . $key . '][' . $k . ']', ['label' => admin_field_label($k, $fd)] + $fd, array_key_exists($k, $row) ? $row[$k] : ($fd['default'] ?? ''));
    return $h . ob_get_clean() . '</div></div>';
}

/**
 * Campo 'rows': una lista de filas que se agregan, reordenan, duplican y quitan, cada una con sus subcampos.
 *   'items' => ['type' => 'rows', 'label' => 'Capacidades', 'fields' => [
 *       'title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
 *       'module' => ['type' => 'select', 'options_from' => 'modulos', 'label' => 'Módulo']],
 *     'add' => 'Agregar capacidad', 'max' => 12, 'row_title' => 'title']
 * Se guarda como lista de objetos ([{title: {es, en}, module: 'tickets'}, …]); cada subcampo es bilingüe si declara
 * 'i18n'. Las filas sin nada escrito se descartan al guardar. En las vistas: cms_rows($item, 'items').
 */
function admin_rows_control(string $name, array $def, array $rows): string
{
    $fields = admin_rows_fields($def);
    if (!$fields) return '<p class="ad-help">Este campo no declara subcampos (\'fields\').</p>';
    $max = max(0, (int) ($def['max'] ?? 0));
    $titleKey = (string) ($def['row_title'] ?? array_key_first($fields));
    $h = '<div class="ad-rows" data-rows="' . cms_e($name) . '"' . ($max ? ' data-max="' . $max . '"' : '') . ' data-row-title="' . cms_e($titleKey) . '">';
    $h .= '<div class="ad-rows-list" data-rows-list>';
    foreach (array_values(array_filter($rows, 'is_array')) as $i => $row) $h .= admin_row_html($name, $fields, 'r' . $i, $row);
    $h .= '</div><p class="ad-help ad-rows-empty" data-rows-empty>' . cms_e((string) ($def['empty'] ?? 'Todavía no hay filas.')) . '</p>';
    $h .= '<template data-row-template>' . admin_row_html($name, $fields, '__ROW__', []) . '</template>';
    $h .= '<button type="button" class="ad-btn ad-btn-sm" data-row-add>+ ' . cms_e((string) ($def['add'] ?? 'Agregar')) . '</button>';
    if ($max) $h .= ' <span class="ad-help">Máximo ' . $max . '.</span>';
    return $h . '</div>';
}

/** Lee un campo 'rows' (del formulario o de la API): conserva el orden, limpia cada subcampo y quita las filas vacías. */
function admin_read_rows(array $def, $raw): array
{
    if (!is_array($raw)) return [];
    $fields = admin_rows_fields($def);
    $out = [];
    foreach ($raw as $k => $r) {
        if (!is_array($r) || (string) $k === '__ROW__') continue;
        $row = [];
        foreach ($fields as $sk => $sd) $row[$sk] = admin_read_field($sk, $sd, $r);
        if (admin_value_filled($row)) $out[] = $row;
    }
    $max = max(0, (int) ($def['max'] ?? 0));
    return $max ? array_slice($out, 0, $max) : $out;
}

/** Campo 'category': el select trae el slug (o "__new__" con el nombre en <campo>__new); se guarda la etiqueta del registro. */
function admin_read_category(string $name, array $def, array $src)
{
    $tk = (string) ($def['_type'] ?? '');
    $empty = !empty($def['i18n']) ? array_fill_keys(cms_langs(), '') : '';
    $raw = trim((string) ($src[$name] ?? ''));
    if ($tk === '' || $raw === '') return $empty;
    if ($raw === '__new__') { $raw = cms_category_create($tk, (string) ($src[$name . '__new'] ?? '')); if ($raw === '') return $empty; }
    $cat = cms_category($tk, $raw);
    return $cat ? cms_category_value($tk, $cat) : $empty;
}

/** Lee un campo 'sections' del POST: valida tipo de bloque y limpia cada dato según la definición del bloque. */
function admin_read_sections($raw): array
{
    if (!is_array($raw)) return [];
    $out = [];
    $styles = cms_section_styles();
    foreach ($raw as $r) {
        if (!is_array($r)) continue;
        $type = preg_replace('/[^a-z0-9_\/-]/i', '', (string) ($r['type'] ?? ''));
        $def = cms_block($type);
        if (!$def) continue;
        $type = (string) $def['key'];
        $id = preg_replace('/[^a-z0-9]/i', '', (string) ($r['id'] ?? ''));
        if ($id === '') $id = cms_section_id();
        $data = [];
        foreach ((array) ($def['fields'] ?? []) as $k => $fd) $data[$k] = admin_read_field($k, (array) $fd, (array) ($r['data'] ?? []));
        $style = [];
        foreach (cms_block_styles($def) as $k => $sd) {
            $v = admin_read_field($k, $sd, (array) ($r['style'] ?? []));
            if ($v !== '' && $v !== false && $v !== null && $v !== []) $style[$k] = $v;
        }
        // ajustes del efecto elegido (los de los demás no se guardan)
        foreach (cms_section_effect_list($style) as $ek) {
            $ff = cms_effect_fields($ek);
            if (!$ff) continue;
            $src = (array) ((($r['style']['fx'] ?? [])[$ek]) ?? []);
            $opts = [];
            foreach ($ff as $fk => $fd) {
                $v = admin_read_field($fk, (array) $fd, $src);
                if ($v !== '' && $v !== null && $v !== []) $opts[$fk] = $v;
            }
            if ($opts) $style['fx'][$ek] = $opts;
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
    $new['unpublish_at'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', admin_post('unpublish_at')) ? admin_post('unpublish_at') : '';
    if ($new['unpublish_at'] !== '' && $new['publish_at'] !== '' && $new['unpublish_at'] <= $new['publish_at']) $errors[] = 'La fecha de retiro debe ser posterior a la de publicación.';
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

/** ¿$slug sigue siendo el que Duplicar le puso a la copia de $from («<from>-copia» o «<from>-copia-N»)? */
function admin_is_copy_slug(string $slug, string $from): bool
{
    return $from !== '' && (bool) preg_match('/^' . preg_quote($from, '/') . '-copia(-\d+)?$/', $slug);
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
        if ($bd) { $sec['type'] = $bd['key']; $h .= admin_section_card($name, (string) $i, $sec, $bd); }
    }
    $h .= '</div>';
    $h .= '<p class="ad-sections-empty ad-help"' . ($sections ? ' hidden' : '') . '>Esta página aún no tiene secciones. Añade la primera.</p>';
    $h .= '<div class="ad-sections-add"><button type="button" class="ad-btn" data-add-section>+ Añadir sección</button></div>';
    // selector
    $groups = [];
    foreach ($blocks as $k => $bd) $groups[(string) ($bd['group'] ?? 'Bloques')][$k] = $bd;
    $packs = cms_packs();
    $h .= '<div class="ad-modal" data-section-picker hidden><div class="ad-modal-box"><div class="ad-modal-head"><h3>Añadir sección</h3>'
        . '<input type="search" class="ad-picker-search" data-picker-search placeholder="Buscar bloque… (nombre, qué hace, paquete)" aria-label="Buscar bloque" autocomplete="off">'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-close>Cerrar</button></div><div class="ad-modal-body ad-picker-body">';
    foreach ($groups as $g => $list) {
        $h .= '<section class="ad-picker-group" data-picker-group><h4>' . cms_e($g) . '</h4><div class="ad-picker-grid">';
        foreach ($list as $k => $bd) {
            $demo = cms_demo_url($k); $pv = $demo === '' ? cms_block_preview($bd) : '';
            $pack = isset($bd['pack'], $packs[$bd['pack']]) ? (string) $packs[$bd['pack']]['label'] : '';
            $hay = mb_strtolower(trim($bd['label'] . ' ' . ($bd['desc'] ?? '') . ' ' . $g . ' ' . $k . ' ' . $pack . ' ' . ($bd['pack'] ?? '')));
            $h .= '<button type="button" class="ad-picker-item" data-block="' . cms_e($k) . '" data-search="' . cms_e($hay) . '"' . ($demo ? ' data-demo="' . cms_e($demo) . '"' : '') . ($pv ? ' data-preview="' . cms_e($pv) . '"' : '')
                . '><strong>' . cms_e($bd['label']) . ($pack !== '' ? ' <small class="ad-picker-pack">' . cms_e($pack) . '</small>' : '') . '</strong>' . (!empty($bd['desc']) ? '<span>' . cms_e($bd['desc']) . '</span>' : '') . '</button>';
        }
        $h .= '</div></section>';
    }
    $h .= '<p class="ad-help ad-picker-none" data-picker-none hidden>Ningún bloque coincide. Prueba con otra palabra: "precios", "galería", "aviso"…</p>';
    $h .= '</div><div class="ad-picker-preview" data-picker-preview hidden><div class="ad-demo" data-demo-box></div><img alt="" hidden><p></p></div></div></div>';
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
    $h = '<div class="ad-sec' . ($hidden ? ' ad-sec-hidden' : '') . '" data-sec data-sec-type="' . cms_e($type) . '" data-sec-id="' . cms_e($id) . '">';
    $h .= '<input type="hidden" name="' . cms_e($n . '[type]') . '" value="' . cms_e($type) . '"><input type="hidden" name="' . cms_e($n . '[id]') . '" value="' . cms_e($id) . '">';
    $h .= '<div class="ad-sec-head"><span class="ad-sec-grip" title="Arrastra para cambiar de lugar" draggable="true">⋮⋮</span>'
        . '<button type="button" class="ad-sec-toggle" data-sec-toggle aria-label="Plegar o desplegar"></button>'
        . '<span class="ad-sec-kind">' . cms_e($bd['label']) . '</span><span class="ad-sec-title" data-sec-title>' . cms_e($summary) . '</span>'
        . '<span class="ad-sec-tools">'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-first title="Mover al principio">⤒</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-up title="Subir">↑</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-down title="Bajar">↓</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-last title="Mover al final">⤓</button>'
        . '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-dup title="Duplicar">⧉</button>'
        . (cms_block('estructura/html') && $type !== 'estructura/html' ? '<button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-sec-html title="Convertir a HTML libre: el bloque se sustituye por su HTML tal como se ve, para retocarlo a mano">&lt;/&gt;</button>' : '')
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
        foreach ($styles as $k => $sd) {
            admin_field($n . '[style][' . $k . ']', $sd, $style[$k] ?? '');
            if ($k === 'effect') {
                $pv = []; foreach (cms_effects() as $ek => $ed) if (($u = cms_demo_url($ek, true) ?: cms_effect_preview($ed)) !== '') $pv[$ek] = $u;
                echo '<div class="ad-field ad-effect-preview" data-effect-preview=\'' . cms_e(json_encode($pv, JSON_UNESCAPED_SLASHES)) . '\' hidden><label>Así se ve el efecto</label><div class="ad-demo" data-demo-box></div><img alt="" hidden></div>';
                // ajustes del efecto: se dibujan los de todos y el JS muestra los del elegido
                foreach (cms_effects() as $ek => $ed) {
                    $ff = cms_effect_fields($ek);
                    if (!$ff) continue;
                    $saved = (array) (($style['fx'] ?? [])[$ek] ?? []);
                    echo '<div class="ad-field ad-fx-fields" data-fx-fields="' . cms_e($ek) . '" hidden><label>Ajustes del efecto</label><div class="ad-two">';
                    foreach ($ff as $fk => $fd) admin_field($n . '[style][fx][' . $ek . '][' . $fk . ']', ['label' => admin_field_label($fk, (array) $fd)] + (array) $fd, $saved[$fk] ?? ($fd['default'] ?? ''));
                    echo '</div></div>';
                }
            }
        }
        echo '</div></div>';
    }
    $h .= ob_get_clean();
    return $h . '</div></div>';
}
