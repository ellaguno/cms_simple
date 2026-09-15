<?php
/** Página "Redacción IA" del panel: generar ahora, estado de la programación, cron e historial. $pack trae el manifiesto. */
declare(strict_types=1);
$o = rd_settings();
$def = cms_type($o['type']);

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    if ($action === 'article') {
        $topic = trim(admin_post('topic_custom')) !== '' ? trim(admin_post('topic_custom')) : trim(admin_post('topic'));
        [$ok, $msg, $slug] = rd_article($topic);
        admin_flash($msg, $ok ? 'ok' : 'err');
        if ($ok) admin_redirect(admin_url('edit', ['type' => $o['type'], 'slug' => $slug]));
    } elseif ($action === 'news') {
        [$ok, $msg, $slug] = rd_news();
        admin_flash($msg, $ok ? 'ok' : 'err');
        if ($ok) admin_redirect(admin_url('edit', ['type' => $o['type'], 'slug' => $slug]));
    } elseif ($action === 'test') {
        try {
            $r = rd_chat('Responde en una sola frase corta.', 'Di hola y el nombre del modelo que eres.', 200, 0.2);
            admin_flash('Respuesta de ' . rd_provider_label($o['provider']) . ' (' . $r['model'] . '): ' . mb_strimwidth($r['text'], 0, 200, '…'));
        } catch (\Throwable $e) {
            admin_flash(rd_provider_label($o['provider']) . ': ' . $e->getMessage(), 'err');
        }
    } elseif ($action === 'cron') {
        $log = [];
        try { cms_do('cron', function (string $m) use (&$log) { $log[] = $m; }); } catch (\Throwable $e) { $log[] = 'ERROR: ' . $e->getMessage(); }
        admin_flash($log ? implode(' · ', $log) : 'Nada pendiente en la programación.');
    } elseif ($action === 'clear') {
        cms_json_write(rd_dir() . '/historial.json', []);
        admin_flash('Historial borrado.');
    }
    admin_redirect(admin_url('pack:redaccion'));
}

$keyOk = $o['provider'] === 'prueba' || ($o['keys'][$o['provider']] ?? '') !== '';
$cron = cms_json_read(CMS_DATA . '/cron.json', []);
$hist = rd_history();
$month = date('Y-m'); $cost = 0.0; $costKnown = false; $tokens = 0;
foreach ($hist as $h) if (strpos((string) ($h['when'] ?? ''), $month) === 0) { $tokens += (int) ($h['in'] ?? 0) + (int) ($h['out'] ?? 0); if (isset($h['cost']) && $h['cost'] !== null) { $cost += (float) $h['cost']; $costKnown = true; } }
admin_header('Redacción con IA', 'pack:redaccion');
?>
<div class="ad-grid2">
  <section class="ad-box">
    <h2>Proveedor</h2>
    <p><strong><?= cms_e(rd_provider_label($o['provider'])) ?></strong> · <?= cms_e(rd_model()) ?><?php if (!$keyOk): ?> <span class="ad-pill warn">falta la clave</span><?php endif; ?></p>
    <p class="ad-help">Guarda en <strong><?= cms_e($def['label'] ?? $o['type']) ?></strong> como <?= $o['status'] === 'published' ? 'publicado' : 'borrador' ?> · <?= cms_e($o['lang']) ?> · <?= $o['min_words'] ?>–<?= $o['max_words'] ?> palabras · imagen: <?= $o['image'] === 'none' ? 'no' : 'OpenAI ' . cms_e($o['image_model']) ?>. Se cambia en <a href="<?= admin_url('settings') ?>">Ajustes → Redacción con IA</a>.</p>
    <p class="ad-help">Este mes: <?= number_format($tokens) ?> tokens<?= $costKnown ? ' · ' . number_format($cost, 4) . ' USD (según lo que informa el proveedor; OpenRouter lo incluye, los demás no)' : '' ?>.</p>
    <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="test"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Probar el proveedor</button></form>
  </section>
  <section class="ad-box">
    <h2>Programación y cron</h2>
<?php $pend = rd_pending(); if ($pend): ?>
    <ul class="ad-list"><?php foreach ($pend as [$what, $st]): ?><li><?= cms_e($what) ?>: <strong><?= cms_e($st) ?></strong></li><?php endforeach; ?></ul>
<?php else: ?>
    <p class="ad-help">Nada programado. Se activa en Ajustes → Redacción con IA (artículos diarios o semanales, resumen de noticias diario).</p>
<?php endif; ?>
    <p class="ad-help">Para que lo programado ocurra solo, el hosting debe llamar cada 15 o 30 minutos a esta dirección (cPanel → Trabajos de cron):</p>
    <p><code style="user-select:all;word-break:break-all"><?= cms_e(cms_cron_url()) ?></code></p>
    <p class="ad-help">Línea para el cron: <code>*/30 * * * * curl -s "<?= cms_e(cms_cron_url()) ?>" &gt;/dev/null</code>. Última ejecución: <?= !empty($cron['last']) ? cms_e($cron['last']) . ' (' . cms_e((string) $cron['seconds']) . ' s)' : 'nunca' ?>.</p>
    <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="cron"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Ejecutar lo pendiente ahora</button></form>
  </section>
</div>
<div class="ad-grid2">
  <section class="ad-box">
    <h2>Escribir un artículo</h2>
    <form method="post">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="article">
<?php if ($o['topics']): ?>
      <div class="ad-field"><label>Tema de la lista</label><select name="topic"><option value="">— elige —</option><?php foreach ($o['topics'] as $t): ?><option value="<?= cms_e($t) ?>"><?= cms_e($t) ?></option><?php endforeach; ?></select></div>
<?php endif; ?>
      <div class="ad-field"><label>O un tema nuevo</label><input type="text" name="topic_custom" placeholder="Cómo elegir un CMS para una agencia"></div>
      <p class="ad-help">Tarda entre 20 segundos y 2 minutos. Al terminar se abre el borrador para revisarlo.</p>
      <button class="ad-btn" type="submit">Escribir artículo</button>
    </form>
  </section>
  <section class="ad-box">
    <h2>Resumen de noticias</h2>
    <p class="ad-help">Temas: <?= $o['news_topics'] ? cms_e(implode(', ', $o['news_topics'])) : 'portada general' ?> · últimas <?= $o['news_hours'] ?> horas · hasta <?= $o['news_max'] ?> noticias por tema. Cada noticia se usa una sola vez; las fuentes quedan enlazadas al final de cada sección.</p>
    <form method="post"><?= admin_csrf_field() ?><input type="hidden" name="action" value="news"><button class="ad-btn" type="submit">Resumir las noticias de hoy</button></form>
  </section>
</div>
<section class="ad-box">
  <h2>Historial <small class="ad-help"><?= count($hist) ?> registros</small></h2>
<?php if (!$hist): ?><p class="ad-help">Aún no se ha generado nada.</p><?php else: ?>
  <table class="ad-table">
    <thead><tr><th>Cuándo</th><th>Qué</th><th>Tema</th><th>Resultado</th><th>Tokens</th><th></th></tr></thead>
    <tbody>
<?php foreach (array_slice($hist, 0, 60) as $h): ?>
      <tr>
        <td><small><?= cms_e((string) ($h['when'] ?? '')) ?></small></td>
        <td><?= cms_e((string) ($h['kind'] ?? '')) ?></td>
        <td><?= cms_e(mb_strimwidth((string) ($h['topic'] ?? ''), 0, 60, '…')) ?></td>
        <td><span class="ad-pill <?= !empty($h['ok']) ? 'on' : 'warn' ?>"><?= !empty($h['ok']) ? 'ok' : 'error' ?></span> <small class="ad-help"><?= cms_e(mb_strimwidth((string) ($h['msg'] ?? ''), 0, 140, '…')) ?></small></td>
        <td><small><?= (int) ($h['in'] ?? 0) + (int) ($h['out'] ?? 0) ?><?= isset($h['cost']) && $h['cost'] !== null ? ' · ' . number_format((float) $h['cost'], 4) . ' USD' : '' ?></small></td>
        <td><?php if (!empty($h['slug']) && !empty($h['type'])): ?><a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('edit', ['type' => $h['type'], 'slug' => $h['slug']]) ?>">Abrir</a><?php endif; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" class="ad-inline" data-confirm="¿Borrar el historial?"><?= admin_csrf_field() ?><input type="hidden" name="action" value="clear"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Borrar historial</button></form>
<?php endif; ?>
</section>
<?php admin_footer();
