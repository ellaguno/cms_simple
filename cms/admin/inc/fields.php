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
    $label = admin_field_label($name, $def);
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
        case 'number':
            return is_numeric($raw) ? $raw + 0 : ($raw === '' || $raw === null ? '' : (string) $raw);
        default:
            return is_string($raw) ? trim(str_replace("\r\n", "\n", $raw)) : '';
    }
}

function admin_read_field(string $name, array $def)
{
    if (!empty($def['i18n'])) {
        $out = [];
        foreach (cms_langs() as $l) $out[$l] = admin_read_control($def, $_POST[$name][$l] ?? null);
        return $out;
    }
    return admin_read_control($def, $_POST[$name] ?? null);
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
