<?php
/** cms_simple — SEO: metadatos del <head>, JSON-LD, sitemap, robots. */
declare(strict_types=1);

function cms_jsonld_org(): array
{
    $S = cms_settings();
    $site = $S['site_name'] ?? cms_config('name');
    $same = array_values(array_filter(array_map(fn($l) => $l['url'], cms_social_links())));
    foreach ((array) ($S['other_sites'] ?? []) as $os) if (!empty($os['url'])) $same[] = $os['url'];
    $org = ['@type' => 'Organization', '@id' => cms_site_url() . '/#organization', 'name' => $site, 'url' => cms_site_url() . '/'];
    if (!empty($S['logo'])) $org['logo'] = cms_abs_url(cms_img($S['logo']));
    if (!empty($S['email'])) $org['email'] = $S['email'];
    if ($same) $org['sameAs'] = array_values(array_unique($same));
    if (!empty($S['phone_href'])) $org['telephone'] = $S['phone_href'];
    if (!empty($S['whatsapp'])) $org['contactPoint'] = [['@type' => 'ContactPoint', 'contactType' => 'sales', 'telephone' => '+' . preg_replace('/\D/', '', $S['whatsapp']), 'availableLanguage' => cms_active_langs()]];
    if (!empty($S['country'])) $org['areaServed'] = $S['country'];
    return $org;
}

function cms_jsonld_breadcrumbs(array $items): array
{
    $list = [];
    foreach ($items as $i => [$name, $url]) $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => cms_abs_url($url)];
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $list];
}

function cms_jsonld_graph(?array ...$nodes): array
{
    return ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($nodes))];
}

/** JSON-LD de un elemento según el esquema del tipo (schema => Article | CreativeWork | Product | Event …). */
function cms_jsonld_item(array $def, array $item, string $lang, string $url): ?array
{
    $schema = $def['schema'] ?? null;
    if (!$schema) return null;
    $S = cms_settings();
    $site = $S['site_name'] ?? cms_config('name');
    $img = $def['image_field'] ?? 'image';
    $title = (string) cms_f($item, $def['title_field'] ?? 'title', $lang);
    $desc = (string) cms_f($item, $def['excerpt_field'] ?? 'excerpt', $lang);
    $node = ['@type' => $schema, 'name' => $title, 'description' => $desc, 'url' => cms_abs_url($url), 'inLanguage' => $lang === 'en' ? 'en' : $lang . '-MX'];
    if (!empty($item[$img])) $node['image'] = [cms_abs_url(cms_img((string) $item[$img]))];
    if ($schema === 'Article' || $schema === 'BlogPosting' || $schema === 'NewsArticle') {
        $node['headline'] = $title;
        $node['datePublished'] = $item['date'] ?? ($item['created'] ?? '');
        $node['dateModified'] = $item['updated'] ?? ($item['date'] ?? '');
        $node['mainEntityOfPage'] = cms_abs_url($url);
        $node['author'] = ['@type' => 'Organization', 'name' => $S['author_name'] ?? $site, 'url' => cms_site_url() . '/'];
        $node['publisher'] = ['@id' => cms_site_url() . '/#organization'];
        $tags = cms_f($item, 'tags', $lang, []);
        if ($tags) $node['keywords'] = implode(', ', (array) $tags);
        if ($c = cms_f($item, 'category', $lang)) $node['articleSection'] = $c;
    } else {
        $node['creator'] = ['@id' => cms_site_url() . '/#organization'];
        if (!empty($item['year'])) $node['dateCreated'] = (string) $item['year'];
        if ($c = cms_f($item, 'category', $lang)) $node['genre'] = $c;
    }
    return $node;
}

/** Metadatos del <head>: title, description, canonical, hreflang, robots, OG, Twitter, JSON-LD. El tema lo llama dentro de <head>. */
function cms_head(array $page): void
{
    $S = cms_settings();
    $lang = $page['lang'];
    $site = $S['site_name'] ?? cms_config('name');
    $title = $page['title'] ?? $site;
    $desc = $page['desc'] ?? '';
    $alt = $page['alt'] ?? [];
    $og = cms_abs_url(cms_img(!empty($page['og_image']) ? (string) $page['og_image'] : (string) ($S['og_image'] ?? ($S['logo'] ?? ''))));
    echo '<meta charset="utf-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '<title>' . cms_e($title) . '</title>' . "\n";
    echo '<meta name="description" content="' . cms_e($desc) . '">' . "\n";
    if (!empty($page['canonical'])) echo '<link rel="canonical" href="' . cms_e($page['canonical']) . '">' . "\n";
    if (!empty($page['noindex'])) echo '<meta name="robots" content="noindex, follow">' . "\n";
    if (count($alt) > 1) {
        foreach ($alt as $l => $u) echo '<link rel="alternate" hreflang="' . cms_e($l) . '" href="' . cms_e(cms_abs_url($u)) . '">' . "\n";
        echo '<link rel="alternate" hreflang="x-default" href="' . cms_e(cms_abs_url($alt[cms_default_lang()] ?? reset($alt))) . '">' . "\n";
    }
    echo '<meta property="og:title" content="' . cms_e($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . cms_e($desc) . '">' . "\n";
    if ($og) echo '<meta property="og:image" content="' . cms_e($og) . '">' . "\n";
    echo '<meta property="og:url" content="' . cms_e($page['canonical'] ?? cms_abs_url(cms_url('home', $lang))) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . cms_e($site) . '">' . "\n";
    echo '<meta property="og:type" content="' . cms_e($page['og_type'] ?? 'website') . '">' . "\n";
    echo '<meta property="og:locale" content="' . ($lang === 'en' ? 'en_US' : cms_e($lang) . '_MX') . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . cms_e($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . cms_e($desc) . '">' . "\n";
    if ($og) echo '<meta name="twitter:image" content="' . cms_e($og) . '">' . "\n";
    if (!empty($S['google_verification'])) echo '<meta name="google-site-verification" content="' . cms_e($S['google_verification']) . '">' . "\n";
    echo '<link rel="icon" href="' . (!empty($S['favicon']) ? cms_e(cms_img($S['favicon'])) : 'data:,') . '">' . "\n";
    echo '<meta name="generator" content="cms_simple ' . CMS_VERSION . '">' . "\n";
    if (!empty($page['preview'])) {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.createElement("div");b.textContent="Vista previa · este contenido aún no es público";'
            . 'b.style.cssText="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:99999;background:#111;color:#fff;font:600 13px/1 system-ui,sans-serif;padding:10px 16px;border-radius:30px;box-shadow:0 8px 24px rgba(0,0,0,.25)";document.body.appendChild(b);});</script>' . "\n";
    }
    foreach ((array) ($page['jsonld'] ?? []) as $ld) {
        echo '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}

function cms_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $langs = cms_active_langs();
    $types = cms_config('types');
    $pages = cms_config('pages');
    $latest = '';
    $all = [];
    foreach ($types as $k => $def) { $all[$k] = cms_items($k); foreach ($all[$k] as $it) $latest = max($latest, (string) ($it['updated'] ?? $it['date'] ?? '')); }
    $rows = [];
    foreach ($langs as $l) {
        $rows[] = [cms_url('home', $l), $latest, '1.0'];
        foreach ($types as $k => $def) {
            if (!empty($def['no_list'])) continue;
            $rows[] = [cms_url('list:' . $k, $l), $latest, '0.8'];
        }
        foreach ($pages as $k => $def) if (empty($def['noindex'])) $rows[] = [cms_url('page:' . $k, $l), $latest, '0.8'];
        foreach ($types as $k => $def) { if (!empty($def['noindex'])) continue; foreach ($all[$k] as $it) $rows[] = [cms_url('item:' . $k, $l, $it['slug']), $it['updated'] ?? ($it['date'] ?? ''), '0.7']; }
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($rows as [$u, $mod, $prio]) {
        echo '  <url><loc>' . cms_e(cms_abs_url($u)) . '</loc>'
            . ($mod && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $mod) ? '<lastmod>' . cms_e(substr((string) $mod, 0, 10)) . '</lastmod>' : '')
            . '<priority>' . $prio . '</priority></url>' . "\n";
    }
    echo "</urlset>\n";
}

function cms_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: " . CMS_BASE . "/admin/\nDisallow: " . CMS_BASE . "/data/\nDisallow: " . CMS_BASE . "/cms/\nAllow: /\n\nSitemap: " . cms_site_url() . "/sitemap.xml\n";
}
