<?php
/**
 * Estadísticas (1.40): visitas, páginas más vistas, descargas, procedencia, países y dispositivos, por periodo.
 * Los datos los anota lib/stats.php en data/stats/AAAA-MM.json; aquí solo se leen y se suman.
 */
declare(strict_types=1);

$periods = ['mes' => 'Este mes', 'anterior' => 'Mes anterior', '3m' => 'Últimos 3 meses', '12m' => 'Últimos 12 meses'];
$per = (string) ($_GET['per'] ?? 'mes');
if (!isset($periods[$per])) $per = 'mes';
$now = date('Y-m');
[$from, $to] = [
    'mes' => [$now, $now],
    'anterior' => [date('Y-m', strtotime(date('Y-m-01') . ' -1 month')), date('Y-m', strtotime(date('Y-m-01') . ' -1 month'))],
    '3m' => [date('Y-m', strtotime(date('Y-m-01') . ' -2 months')), $now],
    '12m' => [date('Y-m', strtotime(date('Y-m-01') . ' -11 months')), $now],
][$per];
$months = cms_stats_load($from, $to);
$s = cms_stats_sum($months);
$on = cms_stats_enabled();
$ht = cms_stats_htaccess_state();

/** Nombre legible de una ruta contada: el título del elemento, "Portada" o la ruta. */
$pageLabel = function (string $path) use ($s): array {
    $t = $s['t'][$path] ?? null;
    if (is_array($t) && ($def = cms_type((string) $t[0])) && ($it = cms_item((string) $t[0], (string) $t[1], false))) {
        return [(string) (cms_f($it, $def['title_field'] ?? 'title', cms_default_lang()) ?: $it['slug']), admin_url('edit', ['type' => $t[0], 'slug' => $t[1]])];
    }
    $lang = cms_default_lang(); $bare = trim($path, '/');
    foreach (cms_langs() as $l) if ($l !== $lang && ($bare === $l || strpos($bare, $l . '/') === 0)) { $lang = $l; $bare = trim(substr($bare, strlen($l)), '/'); break; }
    $suffix = $lang !== cms_default_lang() ? ' (' . strtoupper($lang) . ')' : '';
    if ($bare === '') return ['Portada' . $suffix, null];
    if (strpos($bare, '/') === false) {   // índice de una colección o página estática: su nombre
        foreach (cms_config('types') as $k => $d) if (empty($d['internal']) && empty($d['no_list']) && cms_segment($d + ['key' => $k], $lang) === $bare) return [(string) ($d['label'] ?? $k) . $suffix, admin_url('content', ['type' => $k])];
        foreach (cms_config('pages') as $k => $d) if (cms_segment($d + ['key' => $k], $lang) === $bare) return [(string) ($d['label'] ?? $k) . $suffix, null];
    }
    return [$path, null];
};
$refLabel = function (string $h): string {
    if ($h === '') return 'Directo o desconocido';
    if ($h === '(otros)') return 'Otros';
    $known = ['/^google\./' => 'Google', '/^bing\.com$/' => 'Bing', '/^duckduckgo\.com$/' => 'DuckDuckGo', '/^search\.yahoo\./' => 'Yahoo',
        '/^(facebook\.com|fb\.me)$/' => 'Facebook', '/^instagram\.com$/' => 'Instagram', '/^(t\.co|x\.com|twitter\.com)$/' => 'X (Twitter)',
        '/^(linkedin\.com|lnkd\.in)$/' => 'LinkedIn', '/^(youtube\.com|youtu\.be)$/' => 'YouTube', '/^(chatgpt\.com|chat\.openai\.com)$/' => 'ChatGPT',
        '/^perplexity\.ai$/' => 'Perplexity', '/^claude\.ai$/' => 'Claude', '/^com\.google\.android\./' => 'Google (app)', '/^(wa\.me|web\.whatsapp\.com)$/' => 'WhatsApp'];
    foreach ($known as $re => $name) if (preg_match($re, $h)) return $name . ($name === 'Google' && $h !== 'google.com' ? ' · ' . $h : '');
    return $h;
};
$countryLabel = function (string $c): string {
    if ($c === '(otros)') return 'Otros';
    $flag = preg_match('/^[A-Z]{2}$/', $c) ? mb_chr(0x1F1E6 + ord($c[0]) - 65) . mb_chr(0x1F1E6 + ord($c[1]) - 65) . ' ' : '';
    $name = class_exists('Locale') ? (string) Locale::getDisplayRegion('-' . $c, 'es') : '';
    return $flag . ($name !== '' && $name !== $c ? $name : $c);
};
/** Lista con barra proporcional: [[etiqueta (HTML), número], …]. */
$bars = function (array $rows, string $empty = 'Sin datos en este periodo.'): void {
    if (!$rows) { echo '<p class="ad-help">' . cms_e($empty) . '</p>'; return; }
    $max = max(1, ...array_column($rows, 1));
    echo '<ol class="ad-stat-list">';
    foreach ($rows as [$label, $n]) echo '<li><span class="ad-stat-bar" style="width:' . round($n / $max * 100, 1) . '%"></span><span class="ad-stat-k">' . $label . '</span><b>' . number_format($n) . '</b></li>';
    echo '</ol>';
};
$top = fn(array $map, int $n = 15) => array_slice($map, 0, $n, true);

admin_header('Estadísticas', 'estadisticas');
?>
<div class="ad-stat-head">
  <nav class="ad-tabs" aria-label="Periodo"><?php foreach ($periods as $k => $label): ?><a href="<?= admin_url('estadisticas', ['per' => $k]) ?>" class="<?= $k === $per ? 'on' : '' ?>"><?= cms_e($label) ?></a><?php endforeach; ?></nav>
</div>

<?php if (!$on): ?>
<div class="ad-flash err">Las estadísticas están apagadas: el sitio no está contando visitas. Enciéndelas en <a href="<?= admin_url('settings', ['tab' => 'marca']) ?>">Ajustes → Marca y SEO → Estadísticas</a>.</div>
<?php elseif ($ht !== 'ok'): ?>
<div class="ad-flash err">
  <?php if ($ht === 'none'): ?>No hay <code>.htaccess</code> en la raíz del sitio (¿el servidor no es Apache?), así que las páginas se cuentan pero <strong>las descargas no</strong>.
  <?php else: ?>No se pudo escribir el <code>.htaccess</code> de la raíz, así que las páginas se cuentan pero <strong>las descargas no</strong>. Dale permiso de escritura o pega este bloque al principio del archivo:<?php endif; ?>
  <?php if ($ht !== 'none'): ?><pre class="ad-pre"><?= cms_e(cms_stats_htaccess_block()) ?></pre><?php endif; ?>
</div>
<?php endif; ?>

<div class="ad-cards">
  <div class="ad-card"><strong><?= number_format($s['u']) ?></strong><span>Visitas (personas distintas cada día)</span></div>
  <div class="ad-card"><strong><?= number_format($s['v']) ?></strong><span>Páginas vistas<?= $s['u'] ? ' · ' . number_format($s['v'] / $s['u'], 1) . ' por visita' : '' ?></span></div>
  <div class="ad-card"><strong><?= number_format($s['dl']) ?></strong><span>Descargas de documentos y audio</span></div>
  <div class="ad-card"><strong><?= count(array_filter($s['days'], fn($d) => $d['u'] > 0)) ? number_format($s['u'] / max(1, count(array_filter($s['days'], fn($d) => $d['u'] > 0)))) : '0' ?></strong><span>Visitas por día, en promedio</span></div>
</div>

<section class="ad-box">
  <h2><?= $per === '12m' ? 'Por mes' : 'Por día' ?></h2>
<?php
// serie: por día (cada día del periodo, aunque no tenga datos) o por mes en el periodo de 12 meses
$series = [];
if ($per === '12m') {
    for ($i = 11; $i >= 0; $i--) { $ym = date('Y-m', strtotime(date('Y-m-01') . " -$i months")); $series[$ym] = ['v' => 0, 'u' => 0, 'dl' => 0]; }
    foreach ($s['days'] as $d => $row) { $ym = substr($d, 0, 7); if (isset($series[$ym])) foreach ($row as $k => $n) $series[$ym][$k] += $n; }
} else {
    $last = $to === $now ? date('Y-m-d') : date('Y-m-t', strtotime($to . '-01'));
    for ($t = strtotime($from . '-01'); date('Y-m-d', $t) <= $last; $t = strtotime('+1 day', $t)) $series[date('Y-m-d', $t)] = $s['days'][date('Y-m-d', $t)] ?? ['v' => 0, 'u' => 0, 'dl' => 0];
}
$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$fmt = fn(string $k) => strlen($k) === 7 ? $mesesCortos[(int) substr($k, 5)] . ' ' . substr($k, 2, 2) : (int) substr($k, 8) . ' ' . $mesesCortos[(int) substr($k, 5, 2)];
$max = max(1, ...array_map(fn($r) => $r['v'], array_values($series)));
$n = count($series); $W = 1000; $H = 220; $pad = 28; $bw = ($W - $pad) / max(1, $n);
?>
  <svg class="ad-chart" viewBox="0 0 <?= $W ?> <?= $H + 24 ?>" role="img" aria-label="Visitas y páginas vistas por <?= $per === '12m' ? 'mes' : 'día' ?>">
    <?php foreach ([0, .5, 1] as $g): $y = $H - $g * ($H - 10); ?><line x1="<?= $pad ?>" x2="<?= $W ?>" y1="<?= $y ?>" y2="<?= $y ?>" class="ad-chart-grid"/><text x="<?= $pad - 6 ?>" y="<?= $y + 4 ?>" text-anchor="end" class="ad-chart-axis"><?= number_format($max * $g) ?></text><?php endforeach; ?>
    <?php $i = 0; foreach ($series as $k => $r): $x = $pad + $i * $bw + $bw * .12; $w = $bw * .76; $hv = $r['v'] / $max * ($H - 10); $hu = $r['u'] / $max * ($H - 10); ?>
    <g><title><?= cms_e($fmt($k)) ?>: <?= number_format($r['u']) ?> visitas, <?= number_format($r['v']) ?> páginas vistas<?= $r['dl'] ? ', ' . number_format($r['dl']) . ' descargas' : '' ?></title>
      <rect x="<?= round($x, 1) ?>" y="<?= round($H - $hv, 1) ?>" width="<?= round($w, 1) ?>" height="<?= round($hv, 1) ?>" class="ad-chart-v" rx="2"/>
      <rect x="<?= round($x, 1) ?>" y="<?= round($H - $hu, 1) ?>" width="<?= round($w, 1) ?>" height="<?= round($hu, 1) ?>" class="ad-chart-u" rx="2"/>
      <rect x="<?= round($pad + $i * $bw, 1) ?>" y="0" width="<?= round($bw, 1) ?>" height="<?= $H ?>" fill="transparent"/></g>
    <?php if ($n <= 14 || $i % (int) ceil($n / 10) === 0): ?><text x="<?= round($x + $w / 2, 1) ?>" y="<?= $H + 18 ?>" text-anchor="middle" class="ad-chart-axis"><?= cms_e($fmt($k)) ?></text><?php endif; ?>
    <?php $i++; endforeach; ?>
  </svg>
  <p class="ad-chart-legend"><span class="ad-dot ad-dot-u"></span> Visitas <span class="ad-dot ad-dot-v"></span> Páginas vistas <small class="ad-help">· pasa el cursor por una barra para ver sus números</small></p>
</section>

<div class="ad-grid2">
  <section class="ad-box">
    <h2>Páginas más vistas</h2>
    <?php $bars(array_map(function ($path, $n) use ($pageLabel) {
        [$label, $edit] = $pageLabel((string) $path);
        $url = $path === '(otros)' ? null : CMS_BASE . $path;
        $html = $url ? '<a href="' . cms_e($url) . '" target="_blank" rel="noopener" title="' . cms_e($path) . '">' . cms_e($path === '(otros)' ? 'Otras páginas' : $label) . '</a>' : cms_e('Otras páginas');
        if ($edit) $html .= ' <a class="ad-stat-edit" href="' . cms_e($edit) . '" title="Editar">✎</a>';
        if ($url && $label !== $path && $path !== '/') $html .= ' <small>' . cms_e($path) . '</small>';
        return [$html, (int) $n];
    }, array_keys($top($s['p'], 20)), $top($s['p'], 20))); ?>
  </section>
  <section class="ad-box">
    <h2>Descargas</h2>
    <?php $bars(array_map(function ($f, $n) {
        if ($f === '(otros)') return ['Otros archivos', (int) $n];
        $exists = is_file(CMS_ROOT . '/' . $f);
        $html = $exists ? '<a href="' . cms_e(CMS_BASE . '/' . implode('/', array_map('rawurlencode', explode('/', (string) $f)))) . '?cmsdirect=1" target="_blank" rel="noopener">' . cms_e(basename((string) $f)) . '</a>' : cms_e(basename((string) $f)) . ' <small>(ya no está en Medios)</small>';
        if (dirname((string) $f) !== 'uploads') $html .= ' <small>' . cms_e(substr(dirname((string) $f), 8)) . '/</small>';
        return [$html, (int) $n];
    }, array_keys($top($s['dl_files'], 20)), $top($s['dl_files'], 20)), 'Sin descargas en este periodo. Cuentan los PDF, documentos de Office, comprimidos y audio de Medios.'); ?>
  </section>
  <section class="ad-box">
    <h2>De dónde llegan</h2>
    <?php $bars(array_map(fn($h, $n) => [cms_e($refLabel((string) $h)), (int) $n], array_keys($top($s['r'])), $top($s['r']))); ?>
    <p class="ad-help">Por visita: el sitio que enlazó la primera página que vio cada persona ese día.</p>
  </section>
  <section class="ad-box">
    <h2>Países</h2>
    <?php $bars(array_map(fn($c, $n) => [cms_e($countryLabel((string) $c)), (int) $n], array_keys($top($s['c'])), $top($s['c'])), 'Sin datos de país. Se toman de Cloudflare (cabecera CF-IPCountry); sin Cloudflare no se registran.'); ?>
  </section>
  <section class="ad-box">
    <h2>Dispositivos</h2>
    <?php $devNames = ['m' => 'Celular', 't' => 'Tableta', 'd' => 'Computadora']; $bars(array_map(fn($k, $n) => [cms_e($devNames[$k] ?? $k), (int) $n], array_keys($s['dev']), $s['dev'])); ?>
  </section>
<?php if (count(cms_langs()) > 1): ?>
  <section class="ad-box">
    <h2>Idiomas</h2>
    <?php $bars(array_map(fn($k, $n) => [cms_e(strtoupper((string) $k)), (int) $n], array_keys($s['l']), $s['l'])); ?>
    <p class="ad-help">Páginas vistas en cada idioma del sitio.</p>
  </section>
<?php endif; ?>
</div>

<section class="ad-box">
  <h2>Cómo se cuenta</h2>
  <ul class="ad-list">
    <li><strong>Visitas</strong>: personas distintas por día. El sitio no usa cookies para esto; reconoce a quien vuelve el mismo día por una huella que cambia cada día y no guarda direcciones IP.</li>
    <li><strong>No cuentan</strong> los buscadores y otros robots, las vistas previas ni quien entra al panel (este navegador ya está marcado; tampoco cuenta después de cerrar la sesión).</li>
    <li><strong>Descargas</strong>: PDF, documentos de Office y OpenDocument, comprimidos y audio (MP3 y otros) de Medios, una por persona, archivo y día. Las imágenes y los videos no se cuentan: cada página los carga al verse.</li>
    <li>Son cifras aproximadas y para orientarse. Para análisis de campañas, embudos o eventos usa Google Analytics (Ajustes → Marca y SEO).</li>
    <li>Se guardan <?= cms_stats_months() ?> meses en <code>data/stats/</code>; entran en los respaldos.</li>
  </ul>
</section>
<?php admin_footer();
