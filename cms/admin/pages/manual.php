<?php
/** Manual embebido: capítulos en Markdown en cms/manual/*.md (núcleo) y site/manual/*.md (propios del sitio). */
declare(strict_types=1);

function admin_manual_chapters(): array
{
    $out = [];
    foreach ([[CMS_DIR . '/manual', CMS_BASE . '/cms/manual', 'core'], [CMS_SITE . '/manual', CMS_BASE . '/site/manual', 'site']] as [$dir, $url, $src]) {
        foreach (glob($dir . '/*.md') ?: [] as $f) {
            $slug = basename($f, '.md');
            $raw = (string) file_get_contents($f);
            $title = preg_match('/^#\s+(.+)$/m', $raw, $m) ? trim($m[1]) : $slug;
            $desc = preg_match('/^>\s+(.+)$/m', $raw, $m) ? trim($m[1]) : '';
            $out[$slug] = ['slug' => $slug, 'title' => $title, 'desc' => $desc, 'file' => $f, 'url' => $url, 'src' => $src];
        }
    }
    ksort($out);
    return $out;
}

$chapters = admin_manual_chapters();
$c = (string) ($_GET['c'] ?? '');
$cur = $chapters[$c] ?? null;
admin_header($cur ? $cur['title'] : 'Manual', 'manual');
$keys = array_keys($chapters);
?>
<div class="ad-manual">
  <nav class="ad-manual-toc">
    <a href="<?= admin_url('manual') ?>"<?= !$cur ? ' class="on"' : '' ?>>Índice</a>
<?php foreach ($chapters as $ch): ?>
    <a href="<?= admin_url('manual', ['c' => $ch['slug']]) ?>"<?= $cur && $cur['slug'] === $ch['slug'] ? ' class="on"' : '' ?>><?= cms_e(preg_replace('/^\d+[.)]?\s*/', '', $ch['title'])) ?></a>
<?php endforeach; ?>
  </nav>
  <article class="ad-manual-body">
<?php if (!$cur): ?>
    <p class="ad-help">Cómo construir y cuidar un sitio con este panel, paso a paso. Empieza por el principio si es tu primera vez; cada capítulo es corto y se puede leer por separado.</p>
    <div class="ad-manual-cards">
<?php foreach ($chapters as $ch): ?>
      <a class="ad-manual-card" href="<?= admin_url('manual', ['c' => $ch['slug']]) ?>"><strong><?= cms_e($ch['title']) ?></strong><?php if ($ch['desc']): ?><span><?= cms_e($ch['desc']) ?></span><?php endif; ?></a>
<?php endforeach; ?>
    </div>
<?php else:
    $raw = (string) file_get_contents($cur['file']);
    $raw = preg_replace('/^#\s+.+\n/', '', $raw, 1);
    $raw = preg_replace('/^>\s+.+\n/m', '', $raw, 1);
    // imágenes relativas → carpeta del manual; enlaces a otros capítulos [texto](cap:slug)
    $raw = preg_replace('#\]\((img/[^)]+)\)#', '](' . $cur['url'] . '/$1)', $raw);
    $raw = preg_replace_callback('#\]\(cap:([a-z0-9_-]+)\)#', fn($m) => '](' . admin_url('manual', ['c' => $m[1]]) . ')', $raw);
    $raw = preg_replace_callback('#\]\(admin:([a-z]+)\)#', fn($m) => '](' . admin_url($m[1]) . ')', $raw);
    $html = cms_md($raw);
    // los bloques disponibles en este sitio, si el capítulo lo pide
    if (strpos($html, '{{bloques}}') !== false) {
        $list = '';
        $groups = [];
        foreach (cms_blocks() as $k => $bd) $groups[(string) ($bd['group'] ?? 'Bloques')][] = $bd;
        foreach ($groups as $g => $bs) { $list .= '<h4>' . cms_e($g) . '</h4><ul>'; foreach ($bs as $bd) $list .= '<li><strong>' . cms_e($bd['label']) . '</strong>' . (!empty($bd['desc']) ? ': ' . cms_e($bd['desc']) : '') . ' <code>' . cms_e($bd['key']) . '</code></li>'; $list .= '</ul>'; }
        if (cms_effects()) { $list .= '<h4>Efectos (pestaña Estilo)</h4><ul>'; foreach (cms_effects() as $e) $list .= '<li><strong>' . cms_e($e['label']) . '</strong>' . (!empty($e['desc']) ? ': ' . cms_e($e['desc']) : '') . '</li>'; $list .= '</ul>'; }
        $html = str_replace('{{bloques}}', $list, $html);
    }
    echo $html;
    $i = array_search($cur['slug'], $keys, true);
    echo '<div class="ad-manual-nav">';
    if ($i > 0) echo '<a class="ad-btn ad-btn-light" href="' . admin_url('manual', ['c' => $keys[$i - 1]]) . '">← ' . cms_e(preg_replace('/^\d+[.)]?\s*/', '', $chapters[$keys[$i - 1]]['title'])) . '</a>';
    if ($i < count($keys) - 1) echo '<a class="ad-btn" href="' . admin_url('manual', ['c' => $keys[$i + 1]]) . '">' . cms_e(preg_replace('/^\d+[.)]?\s*/', '', $chapters[$keys[$i + 1]]['title'])) . ' →</a>';
    echo '</div>';
endif; ?>
  </article>
</div>
<?php admin_footer();
