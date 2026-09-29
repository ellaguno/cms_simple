<?php
/**
 * cms_simple — acceso programático al contenido: tokens de API y operaciones comunes a la API HTTP
 * (/admin/api/…, cms/admin/api.php) y a la línea de comandos (cms/cli.php).
 *
 * Los tokens viven en data/api-tokens.json (solo su hash SHA-256); cada uno pertenece a un usuario del panel y
 * tiene un alcance: todos los tipos de contenido ('*') o una lista. Se crean y revocan en Usuarios.
 * Crear o actualizar pasa por admin_read_item(), así que la validación y el saneado son los mismos del formulario.
 */
declare(strict_types=1);

function api_tokens_file(): string { return CMS_DATA . '/api-tokens.json'; }

function api_tokens(): array
{
    return array_values(array_filter((array) cms_json_read(api_tokens_file(), []), 'is_array'));
}

/**
 * Crea un token y devuelve el texto completo (solo se muestra una vez): cms_<id>_<secreto>.
 * $types vacío = todo el contenido; si se piden tipos y ninguno existe, no se crea ('') en vez de dar acceso a todo.
 */
function api_token_create(string $user, string $label, array $types): string
{
    $id = bin2hex(random_bytes(4));
    $secret = bin2hex(random_bytes(20));
    $asked = array_filter(array_map('strval', $types), fn($t) => $t !== '');
    $types = array_values(array_unique(array_filter($asked, fn($t) => $t === '*' || cms_type($t))));
    if ($asked && !$types) return '';
    if (in_array('*', $types, true)) $types = ['*'];
    $all = api_tokens();
    $all[] = ['id' => $id, 'user' => $user, 'label' => mb_substr(trim($label), 0, 60) ?: 'Token', 'types' => $types ?: ['*'],
        'hash' => hash('sha256', $secret), 'created' => date('Y-m-d H:i'), 'last_used' => ''];
    return cms_json_write(api_tokens_file(), $all) ? 'cms_' . $id . '_' . $secret : '';
}

function api_token_revoke(string $id): bool
{
    $all = api_tokens();
    $left = array_values(array_filter($all, fn($t) => ($t['id'] ?? '') !== $id));
    return count($left) < count($all) && cms_json_write(api_tokens_file(), $left);
}

/** Token válido (de un usuario que aún existe) o null. Anota el último uso (una vez por minuto como mucho). */
function api_token_check(string $tok): ?array
{
    if (!preg_match('/^cms_([0-9a-f]{8})_([0-9a-f]{40})$/', trim($tok), $m)) return null;
    $all = api_tokens();
    foreach ($all as $i => $t) {
        if (($t['id'] ?? '') !== $m[1] || !hash_equals((string) ($t['hash'] ?? ''), hash('sha256', $m[2]))) continue;
        if (!array_filter(cms_users(), fn($u) => ($u['user'] ?? '') === ($t['user'] ?? ''))) return null;
        if (substr((string) ($t['last_used'] ?? ''), 0, 16) !== date('Y-m-d H:i')) { $all[$i]['last_used'] = date('Y-m-d H:i'); cms_json_write(api_tokens_file(), $all); }
        return $t;
    }
    return null;
}

/** ¿El token (o la línea de comandos, $tok = null) puede tocar este tipo? */
function api_can(?array $tok, string $type): bool
{
    if ($tok === null) return true;
    $types = (array) ($tok['types'] ?? ['*']);
    return in_array('*', $types, true) || in_array($type, $types, true);
}

/** Campos editables de un tipo, como los ve el editor (los tipos en árbol suman 'parent'). */
function api_fields(array $def): array
{
    $fields = (array) ($def['fields'] ?? []);
    if (!empty($def['tree'])) $fields = ['parent' => ['type' => 'select', 'label' => 'Página padre']] + $fields;
    return $fields;
}

/** Esquema de los tipos de contenido: qué campos tiene cada uno y de qué clase. */
function api_types(?array $tok = null): array
{
    $out = [];
    foreach (array_keys((array) cms_config('types', [])) as $k) {
        $def = cms_type((string) $k);
        if (!$def || !api_can($tok, (string) $k)) continue;
        $f = [];
        foreach (api_fields($def) as $name => $fd) {
            $fd = (array) $fd;
            $f[$name] = array_filter(['type' => $fd['type'] ?? 'text', 'label' => admin_field_label((string) $name, $fd), 'i18n' => !empty($fd['i18n']), 'required' => !empty($fd['required']),
                'options' => isset($fd['options']) && is_array($fd['options']) ? $fd['options'] : null], fn($v) => $v !== null && $v !== false);
        }
        $out[$k] = ['label' => (string) ($def['label'] ?? $k), 'title_field' => $def['title_field'] ?? 'title', 'tree' => !empty($def['tree']), 'fields' => $f];
    }
    return ['langs' => cms_langs(), 'default_lang' => cms_default_lang(), 'types' => $out];
}

function api_status_of(array $it): string
{
    return cms_item_is_live($it) ? 'published' : (cms_item_is_expired($it) ? 'expired' : ((($it['status'] ?? '') === 'published') ? 'scheduled' : 'draft'));
}

/** Resumen de un elemento para los listados. */
function api_summary(string $type, array $def, array $it): array
{
    $dl = cms_default_lang();
    $cf = cms_categories_field($type);
    $cat = $cf !== null ? $it[$cf] ?? '' : '';
    return ['slug' => (string) $it['slug'], 'title' => (string) cms_f($it, $def['title_field'] ?? 'title', $dl), 'status' => api_status_of($it),
        'publish_at' => (string) ($it['publish_at'] ?? ''), 'unpublish_at' => (string) ($it['unpublish_at'] ?? ''),
        'created' => (string) ($it['created'] ?? ''), 'updated' => (string) ($it['updated'] ?? ''),
        'category' => is_array($cat) ? (string) ($cat[$dl] ?? reset($cat)) : (string) $cat,
        'url' => cms_abs_url(cms_item_url($type, $it, $dl))];
}

/** Listado: filtros status (published|scheduled|expired|draft), q (en el título), page y per (máx. 200). */
function api_list(string $type, array $opt = []): array
{
    $def = cms_type($type);
    $status = (string) ($opt['status'] ?? '');
    $q = mb_strtolower(trim((string) ($opt['q'] ?? '')));
    $rows = [];
    foreach (cms_items($type, false) as $it) {
        $s = api_summary($type, $def, $it);
        if ($status !== '' && $s['status'] !== $status) continue;
        if ($q !== '' && mb_strpos(mb_strtolower($s['title'] . ' ' . $s['slug']), $q) === false) continue;
        $rows[] = $s;
    }
    $per = min(200, max(1, (int) ($opt['per'] ?? 50)));
    $pages = max(1, (int) ceil(count($rows) / $per));
    $page = min($pages, max(1, (int) ($opt['page'] ?? 1)));
    return ['type' => $type, 'total' => count($rows), 'page' => $page, 'pages' => $pages, 'per' => $per, 'items' => array_slice($rows, ($page - 1) * $per, $per)];
}

/**
 * Valor guardado → lo que enviaría el formulario para ese campo (etiquetas separadas por coma, líneas, casillas "1",
 * secciones con sus datos y estilos), para pasarlo por admin_read_field() como si viniera del panel.
 */
function api_form_value(array $fd, $v)
{
    if (!empty($fd['i18n'])) {
        if (is_array($v) && cms_is_i18n_value($v)) { $out = []; foreach ($v as $l => $x) $out[$l] = api_form_control($fd, $x); return $out; }
        return [cms_default_lang() => api_form_control($fd, $v)];
    }
    return api_form_control($fd, $v);
}

function api_form_control(array $fd, $v)
{
    switch ($fd['type'] ?? 'text') {
        case 'tags':
            return is_array($v) ? implode(', ', array_map('strval', $v)) : (string) $v;
        case 'lines': case 'images':
            return is_array($v) ? implode("\n", array_map('strval', $v)) : (string) $v;
        case 'checkbox':
            return !empty($v) && $v !== '0' && $v !== 'false' ? '1' : '';
        case 'sections':
            $out = [];
            foreach ((array) $v as $sec) {
                if (!is_array($sec) || !($bd = cms_block((string) ($sec['type'] ?? '')))) continue;
                $data = []; $style = [];
                foreach ((array) ($bd['fields'] ?? []) as $k => $bf) if (array_key_exists($k, (array) ($sec['data'] ?? []))) $data[$k] = api_form_value((array) $bf, $sec['data'][$k]);
                foreach (cms_block_styles($bd) as $k => $sd) if (array_key_exists($k, (array) ($sec['style'] ?? []))) $style[$k] = api_form_value((array) $sd, $sec['style'][$k]);
                foreach ((array) ($sec['style']['fx'] ?? []) as $ek => $opts) foreach (cms_effect_fields((string) $ek) as $fk => $ef) if (array_key_exists($fk, (array) $opts)) $style['fx'][$ek][$fk] = api_form_value((array) $ef, $opts[$fk]);
                $out[] = ['type' => (string) $sec['type'], 'id' => (string) ($sec['id'] ?? ''), 'data' => $data, 'style' => $style, 'hidden' => !empty($sec['hidden']) ? '1' : ''];
            }
            return $out;
        default:
            return is_scalar($v) ? (string) $v : (is_array($v) ? $v : '');
    }
}

/**
 * Crea o actualiza un elemento a partir de un arreglo (el JSON de la API o de la línea de comandos).
 * $in: 'slug' (si existe se actualiza; si no, se crea con ese slug o el derivado del título), 'status' (published|draft),
 * 'publish_at', 'unpublish_at', 'seo_title', 'seo_desc' y los campos del tipo. Los que no vengan conservan su valor.
 * 'new_slug' (solo al actualizar) cambia la URL, como el campo slug del editor: el historial y las hijas la siguen.
 * Un campo bilingüe admite {"es": …, "en": …} o un texto (solo el idioma principal). Las cadenas pueden venir
 * blindadas con =?rb64?= como las del panel (firewalls que rechazan HTML en el cuerpo).
 * Devuelve [código HTTP, respuesta].
 */
function api_put(string $type, array $in, ?array $tok = null): array
{
    $def = cms_type($type);
    if (!$def) return [404, ['ok' => false, 'error' => 'Tipo de contenido desconocido: ' . $type]];
    if (!api_can($tok, $type)) return [403, ['ok' => false, 'error' => 'El token no tiene acceso a «' . $type . '».']];
    admin_unarmor($in);
    $fields = api_fields($def);
    $dl = cms_default_lang();
    $slug = cms_slugify((string) ($in['slug'] ?? ''));
    $existing = $slug !== '' ? cms_item($type, $slug, false) : null;
    $base = $existing;
    if (!$base) {   // mismos valores iniciales que un elemento nuevo del editor
        $base = ['slug' => '', 'status' => 'draft'];
        foreach ($fields as $name => $fd) {
            $d = $fd['default'] ?? (($fd['type'] ?? '') === 'date' ? date('Y-m-d') : '');
            if ($name === 'order' && ($fd['type'] ?? '') === 'number' && !isset($fd['default'])) $d = count(cms_items($type, false)) + 1;
            $base[$name] = !empty($fd['i18n']) ? array_fill_keys(cms_langs(), $d) : $d;
        }
    }
    $rename = $existing ? cms_slugify((string) ($in['new_slug'] ?? '')) : '';
    $post = ['slug' => $rename !== '' ? $rename : $slug];
    foreach (['status', 'publish_at', 'unpublish_at'] as $k) $post[$k] = (string) ($in[$k] ?? ($base[$k] ?? ''));
    $all = $fields + ['seo_title' => ['type' => 'text', 'i18n' => true], 'seo_desc' => ['type' => 'textarea', 'i18n' => true]];
    foreach ($all as $name => $fd) {
        $fd = (array) $fd;
        $old = $base[$name] ?? '';
        if (($fd['type'] ?? '') === 'category') {   // se guarda la etiqueta; el formulario manda el slug o "__new__" con el nombre
            $v = array_key_exists($name, $in) ? $in[$name] : $old;
            $tk = (string) ($fd['_type'] ?? $type);
            $label = is_array($v) ? (string) ($v[$dl] ?? reset($v)) : trim((string) $v);
            $cs = $label === '' ? '' : cms_category_slug_of($tk, $v);
            if ($cs !== '' && !cms_category($tk, $cs)) { $post[$name] = '__new__'; $post[$name . '__new'] = $label; }
            else $post[$name] = $cs;
            continue;
        }
        $cur = api_form_value($fd, $old);
        if (array_key_exists($name, $in)) {
            $new = api_form_value($fd, $in[$name]);
            $cur = !empty($fd['i18n']) && is_array($cur) ? array_merge($cur, $new) : $new;   // bilingüe: solo cambian los idiomas enviados
        }
        $post[$name] = $cur;
    }
    $keep = $_POST;
    $_POST = $post;
    [$item, $errors] = admin_read_item($type, $def, $fields, $existing ?? $base, $existing ? $slug : '');
    $_POST = $keep;
    if ($errors) return [422, ['ok' => false, 'errors' => $errors]];
    unset($item['duplicated_from']);
    $orig = $slug;
    $slug = (string) $item['slug'];
    if (!empty($def['tree'])) { $items = cms_items($type, false); $items[$slug] = $item; $item['path'] = cms_tree_path($type, $items, $slug); }
    if (!cms_item_save($type, $item)) return [500, ['ok' => false, 'error' => 'No se pudo escribir en data/content/' . $type . '/. Revisa permisos.']];
    if ($existing && $orig !== $slug) {   // renombrado: lo mismo que hace el editor
        cms_item_delete($type, $orig);
        if (is_dir(cms_versions_dir($type, $orig)) && !is_dir(cms_versions_dir($type, $slug))) @rename(cms_versions_dir($type, $orig), cms_versions_dir($type, $slug));
        if (!empty($def['tree'])) foreach (cms_items($type, false) as $ch) if (($ch['parent'] ?? '') === $orig) { $ch['parent'] = $slug; cms_json_write(cms_content_dir($type) . '/' . $ch['slug'] . '.json', $ch); }
    }
    if (!empty($def['tree'])) cms_tree_rebuild($type);
    return [$existing ? 200 : 201, ['ok' => true, 'created' => !$existing] + ($existing && $orig !== $slug ? ['renamed_from' => $orig] : []) + ['item' => api_summary($type, $def, $item)]];
}

/** Guarda un archivo en uploads/ con las mismas reglas que el panel (tipos, tamaño, reducción, WebP). */
function api_upload(array $file, bool $local = false): array
{
    [$ok, $res] = media_store($file, $local);
    if (!$ok) return [400, ['ok' => false, 'error' => $res]];
    return [201, ['ok' => true, 'path' => $res, 'url' => CMS_BASE . '/' . $res, 'abs_url' => cms_abs_url(CMS_BASE . '/' . $res), 'type' => media_type_of($res)]];
}
