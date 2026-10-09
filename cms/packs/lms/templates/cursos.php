<?php
/**
 * Paquete lms — listado de cursos (/cursos), al estilo del catálogo de capacitación: encabezado con etiqueta, título
 * con *énfasis*, texto y cifras; rejilla de tarjetas con tapa; y un bloque opcional "Cómo están hechos". Los textos
 * se editan en Ajustes → Aula. Con muchos cursos: buscador, filtros por track y subtrack, industria y nivel
 * (?track=, ?industria=, ?nivel=, ?q=)
 * y páginas (?pg=). El tema puede reemplazarlo con templates/cursos.php o templates/lms/cursos.php.
 * Disponibles: $lang, $page, $type, $def, $t (textos), $S (ajustes).
 */
$user = lms_user();
$courses = array_values(lms_visible_courses());
$S0 = cms_settings();
$kicker = lms_setting_text('lms_hero_kicker', 'hero_kicker');
$title = lms_setting_text('lms_hero_title', 'hero_title');
$lead = lms_setting_text('lms_hero_lead', 'hero_lead');
$numbers = (!isset($S0['lms_hero_numbers']) || !empty($S0['lms_hero_numbers'])) && $courses ? lms_catalog_numbers($courses) : [];
$listLabel = $t($type . '_title', lms_tx('courses'));
// buscador y filtros: solo lo que tiene más de una opción (industrias con cursos, niveles distintos; buscador con más de 6 cursos)
$base = cms_url('list:' . $type, $lang);
$filtersOn = !isset($S0['lms_filters']) || !empty($S0['lms_filters']);
$indCount = []; $levels = [];
foreach ($courses as $c) {
    foreach (lms_course_industries($c, $lang) as $k => $n) $indCount[$k] = ($indCount[$k] ?? 0) + 1;
    if (($lv = trim((string) cms_f($c, 'level', $lang))) !== '') $levels[lms_fold($lv)] = $lv;
}
$inds = array_intersect_key(lms_industries($lang), $indCount);
// tracks: cada curso cuenta en su subtrack y en el track de arriba
$trackCount = [];
foreach ($courses as $c) {
    $ks = [];
    foreach (array_keys(lms_course_tracks($c, $lang)) as $k) { $ks[$k] = 1; if (strpos($k, '/') !== false) $ks[strtok($k, '/')] = 1; }
    foreach ($ks as $k => $one) $trackCount[$k] = ($trackCount[$k] ?? 0) + 1;
}
$tracks = array_intersect_key(lms_tracks($lang), $trackCount);
$topTracks = array_filter($tracks, fn($x) => $x['parent'] === '');
$showTracks = $filtersOn && $topTracks;
$tracksLabel = lms_setting_text('lms_tracks_label') ?: lms_tx('tracks');
// quien no ha entrado y no ve los cursos que piden cuenta: se le invita a entrar
$hiddenForGuest = !$user && !lms_staff() && !empty($S0['lms_hide_private']) && count(cms_items(lms_course_type())) > count($courses);
$showSearch = $filtersOn && count($courses) > 6;
$showLevels = $filtersOn && count($levels) > 1;
$showInds = $filtersOn && $inds;
[$shown, $f] = $filtersOn ? lms_catalog_filter($courses, $lang) : [$courses, ['track' => '', 'industria' => '', 'nivel' => '', 'q' => '']];
$filtered = $f['track'] !== '' || $f['industria'] !== '' || $f['nivel'] !== '' || $f['q'] !== '';
$curTop = $f['track'] !== '' ? (string) strtok($f['track'], '/') : '';
$subTracks = $curTop !== '' ? array_filter($tracks, fn($x) => $x['parent'] === $curTop) : [];
$per = (int) ($S0['lms_per_page'] ?? 12);
$pg = $per > 0 ? cms_paginate(array_values($shown), $per) : ['items' => array_values($shown), 'page' => 1, 'pages' => 1, 'total' => count($shown)];
$qs = fn(array $over) => (($q = http_build_query(array_filter(array_merge(array_diff_key($f, ['pg' => 1]), $over), fn($v) => $v !== ''))) !== '' ? '?' . $q : '');
$features = [];
foreach (cms_lines(lms_setting_text('lms_features')) as $line) {
    $p = array_map('trim', explode('|', $line, 3));
    if (count($p) === 3) $features[] = $p;
    elseif (count($p) === 2) $features[] = ['', $p[0], $p[1]];
}
?>
<section class="lms-hero">
  <div class="wrap lms-hero-in">
    <div class="lms-hero-top">
      <nav class="crumbs" aria-label="Ruta"><a href="<?= cms_e(cms_url('home', $lang)) ?>"><?= cms_e($t('crumb_home', $lang === 'en' ? 'Home' : 'Inicio')) ?></a><span>/</span><em style="font-style:normal"><?= cms_e($listLabel) ?></em></nav>
<?php if ($user): ?>
      <a class="lms-hero-user" href="<?= cms_e(lms_url()) ?>"><?= lms_icon('user') ?> <?= cms_e(lms_tx('my_classroom')) ?></a>
<?php else: ?>
      <a class="lms-hero-user" href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= lms_icon('user') ?> <?= cms_e(lms_tx('login')) ?></a>
<?php endif; ?>
    </div>
    <?php if ($kicker !== ''): ?><span class="lms-kicker-pill"><?= cms_e($kicker) ?></span><?php endif; ?>
    <h1><?= lms_emph($title) ?></h1>
    <?php if ($lead !== ''): ?><p class="lead"><?= cms_e($lead) ?></p><?php endif; ?>
<?php if ($numbers): ?>
    <div class="lms-numbers">
<?php foreach ($numbers as [$n, $label]): ?>      <div><b><?= cms_e((string) $n) ?></b><span><?= cms_e($label) ?></span></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>
<section class="sec lms-catalog">
  <div class="wrap">
    <h2 class="lms-eyebrow"><?= cms_e($listLabel) ?></h2>
<?php if ($hiddenForGuest): ?>
    <p class="lms-results"><?= cms_e(lms_tx('login_more')) ?> <a href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= cms_e(lms_tx('login')) ?></a></p>
<?php endif; ?>
<?php if ($showTracks || $showInds || $showLevels || $showSearch): ?>
    <form class="lms-filters" method="get" action="<?= cms_e($base) ?>" role="search">
<?php if ($showTracks): ?>
      <div class="lms-chips lms-chips-tracks" aria-label="<?= cms_e($tracksLabel) ?>">
        <span class="lms-chips-label"><?= cms_e($tracksLabel) ?></span>
        <a class="lms-chip<?= $f['track'] === '' ? ' is-on' : '' ?>" href="<?= cms_e($base . $qs(['track' => ''])) ?>"><?= cms_e(lms_tx('all_tracks')) ?> <span><?= count($courses) ?></span></a>
<?php foreach ($topTracks as $k => $tr): ?>        <a class="lms-chip<?= $curTop === $k ? ' is-on' : '' ?>" href="<?= cms_e($base . $qs(['track' => $k])) ?>"><?= cms_e($tr['name']) ?> <span><?= (int) $trackCount[$k] ?></span></a>
<?php endforeach; ?>
      </div>
<?php if ($subTracks): ?>
      <div class="lms-chips lms-chips-sub" aria-label="<?= cms_e($tracks[$curTop]['name'] ?? '') ?>">
        <a class="lms-chip<?= $f['track'] === $curTop ? ' is-on' : '' ?>" href="<?= cms_e($base . $qs(['track' => $curTop])) ?>"><?= cms_e(lms_tx('all_track', $tracks[$curTop]['name'] ?? '')) ?></a>
<?php foreach ($subTracks as $k => $tr): ?>        <a class="lms-chip<?= $f['track'] === $k ? ' is-on' : '' ?>" href="<?= cms_e($base . $qs(['track' => $k])) ?>"><?= cms_e($tr['name']) ?> <span><?= (int) $trackCount[$k] ?></span></a>
<?php endforeach; ?>
      </div>
<?php endif; ?>
      <?php if ($f['track'] !== ''): ?><input type="hidden" name="track" value="<?= cms_e($f['track']) ?>"><?php endif; ?>
<?php endif; ?>
<?php if ($showInds): ?>
      <div class="lms-chips" aria-label="<?= cms_e(lms_tx('by_industry')) ?>">
<?php if ($showTracks): ?>        <span class="lms-chips-label"><?= cms_e(lms_tx('by_industry')) ?></span>
<?php endif; ?>
        <a class="lms-chip<?= $f['industria'] === '' ? ' is-on' : '' ?>" href="<?= cms_e($base . $qs(['industria' => ''])) ?>"><?= cms_e(lms_tx('all_industries')) ?> <span><?= count($courses) ?></span></a>
<?php foreach ($inds as $k => $n): ?>        <a class="lms-chip<?= $f['industria'] === $k ? ' is-on' : '' ?>" href="<?= cms_e($base . $qs(['industria' => $k])) ?>"><?= cms_e($n) ?> <span><?= (int) $indCount[$k] ?></span></a>
<?php endforeach; ?>
      </div>
      <?php if ($f['industria'] !== ''): ?><input type="hidden" name="industria" value="<?= cms_e($f['industria']) ?>"><?php endif; ?>
<?php endif; ?>
<?php if ($showSearch || $showLevels): ?>
      <div class="lms-filter-row">
<?php if ($showSearch): ?>        <input type="search" name="q" value="<?= cms_e($f['q']) ?>" placeholder="<?= cms_e(lms_tx('search_courses')) ?>" aria-label="<?= cms_e(lms_tx('search_courses')) ?>">
<?php endif; ?>
<?php if ($showLevels): ?>        <select name="nivel" aria-label="<?= cms_e(lms_tx('all_levels')) ?>" onchange="this.form.submit()">
          <option value=""><?= cms_e(lms_tx('all_levels')) ?></option>
<?php foreach ($levels as $lk => $lv): ?>          <option value="<?= cms_e($lv) ?>"<?= lms_fold($f['nivel']) === $lk ? ' selected' : '' ?>><?= cms_e($lv) ?></option>
<?php endforeach; ?>
        </select>
<?php endif; ?>
        <button class="btn btn-primary btn-sm" type="submit"><?= cms_e(lms_tx('search')) ?></button>
      </div>
<?php endif; ?>
    </form>
<?php endif; ?>
<?php if ($filtered): ?>
    <p class="lms-results"><?= cms_e(count($shown) === 1 ? lms_tx('n_found_1') : lms_tx('n_found', count($shown))) ?> · <a href="<?= cms_e($base) ?>"><?= cms_e(lms_tx('clear_filters')) ?></a></p>
<?php endif; ?>
<?php if (!$courses): ?>
    <p class="lead"><?= cms_e(lms_tx('courses_empty')) ?></p>
<?php elseif (!$shown): ?>
    <p class="lead"><?= cms_e(lms_tx('no_match')) ?></p>
<?php else: ?>
    <div class="lms-grid-c">
<?php foreach ($pg['items'] as $c) echo lms_course_card($c, $lang, $user); ?>
    </div>
    <?= $pg['pages'] > 1 ? cms_pager($pg, $base, $f) : '' ?>
<?php endif; ?>
<?php if ($features): ?>
    <div class="lms-features" style="--n:<?= min(4, count($features)) ?>">
      <div class="lms-features-head">
        <h2><?= cms_e(lms_setting_text('lms_features_title') ?: ($lang === 'en' ? 'How they are made' : 'Cómo están hechos')) ?></h2>
        <?php if (($fs = lms_setting_text('lms_features_sub')) !== ''): ?><p><?= cms_e($fs) ?></p><?php endif; ?>
      </div>
<?php foreach ($features as [$ico, $ft, $fx]): ?>
      <div class="lms-feature"><?php if (($svg = lms_svg($ico)) !== ''): ?><span class="lms-feature-ico"><?= $svg ?></span><?php endif; ?><h3><?= cms_e($ft) ?></h3><p><?= cms_e($fx) ?></p></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>
