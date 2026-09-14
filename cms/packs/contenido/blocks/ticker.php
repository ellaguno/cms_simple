<?php /** Ticker de novedades. $b: source, collection, filter, feed_url, feed_category, refresh, items, count, mode, speed, label, icon, icon_custom, show_date, more_text, more_url, closable, key */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$r = ct_ticker_items($b, $lang);
$items = $r['items'];
$builder = !empty($GLOBALS['cms_builder']);
if (!$items) {
    if ($builder && $r['note'] !== '') echo '<div class="' . cms_e(cms_block_class('container')) . '"><p class="ct-empty ct-ticker-note">' . cms_e($r['note']) . '</p></div>';
    return;
}
$key = cms_slugify((string) $b['key']) ?: 'ticker';
$mode = in_array($b['mode'], ['scroll', 'rotate', 'still'], true) ? (string) $b['mode'] : 'scroll';
$speed = in_array($b['speed'], ['slow', 'normal', 'fast'], true) ? (string) $b['speed'] : 'normal';
$icon = ct_ticker_icon((string) $b['icon'], (string) $b['icon_custom']);
$label = trim((string) $b['label']);
$moreText = trim((string) $b['more_text']);
$moreUrl = trim((string) $b['more_url']) !== '' ? trim((string) $b['more_url']) : (string) $r['more'];
$ext = fn(string $u) => preg_match('#^https?://#i', $u) && strpos($u, (string) cms_site_url()) !== 0 ? ' target="_blank" rel="noopener"' : '';
?>
        <div class="ct-ticker ct-ticker-<?= $mode ?> ct-ticker-<?= $speed ?>" data-ct-ticker="<?= $mode ?>" data-ct-band="<?= cms_e($key) ?>"<?= empty($b['closable']) ? '' : ' hidden' ?> aria-label="<?= cms_e($label !== '' ? $label : 'Novedades') ?>">
<?php if ($icon !== '' || $label !== ''): ?>
            <div class="ct-ticker-label"><?php if ($icon !== ''): ?><span class="ct-band-icon"><?= $icon ?></span><?php endif; ?><?php if ($label !== ''): ?><span class="ct-ticker-text"><?= cms_e($label) ?></span><?php endif; ?></div>
<?php endif; ?>
            <div class="ct-ticker-view">
                <ul class="ct-ticker-track">
<?php foreach ($items as $i => [$title, $url, $date]): ?>
                    <li class="ct-ticker-item<?= $i === 0 ? ' is-active' : '' ?>"><?= $url !== '' ? '<a href="' . cms_e($url) . '"' . $ext($url) . '>' : '<span>' ?><?php if (!empty($b['show_date']) && $date !== ''): ?><time datetime="<?= cms_e(substr($date, 0, 10)) ?>"><?= cms_e(ct_date_short($date, $lang)) ?></time> <?php endif; ?><?= cms_e($title) ?><?= $url !== '' ? '</a>' : '</span>' ?></li>
<?php endforeach; ?>
                </ul>
            </div>
<?php if ($moreText !== '' && $moreUrl !== ''): ?>
            <a class="ct-ticker-more" href="<?= cms_e($moreUrl) ?>"<?= $ext($moreUrl) ?>><?= cms_e($moreText) ?> <span aria-hidden="true">&rarr;</span></a>
<?php endif; ?>
<?php if (!empty($b['closable'])): ?>            <button type="button" class="ct-band-close" aria-label="Cerrar">&times;</button>
<?php endif; ?>
        </div>
