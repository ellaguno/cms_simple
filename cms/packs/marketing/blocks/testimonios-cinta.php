<?php /** Cinta de testimonios. $b: title, subtitle, items, rows, speed, stars */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$items = [];
foreach ((array) $b['items'] as $l) { [$q, $n, $r, $img] = mk_split((string) $l, 4); if ($q !== '') $items[] = [$q, $n, $r, $img]; }
if (!$items) return;
$rows = (int) $b['rows'] === 2 && count($items) > 1 ? 2 : 1;
$card = function (array $it) use ($b): string {
    [$q, $n, $r, $img] = $it;
    $ini = mb_strtoupper(mb_substr($n, 0, 1)) . (($p = mb_strpos($n, ' ')) !== false ? mb_strtoupper(mb_substr($n, $p + 1, 1)) : '');
    $h = '<figure class="mk-tcard">';
    if (!empty($b['stars'])) $h .= '<div class="mk-stars" aria-label="5 de 5">' . str_repeat('<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M10 1.5l2.6 5.4 5.9.8-4.3 4.1 1.1 5.9L10 14.9l-5.3 2.8 1.1-5.9L1.5 7.7l5.9-.8z"/></svg>', 5) . '</div>';
    $h .= '<blockquote>' . cms_e($q) . '</blockquote><figcaption>';
    $h .= $img !== '' ? '<img src="' . cms_e(cms_img($img)) . '" alt="" loading="lazy">' : '<span class="mk-tcard-ini" aria-hidden="true">' . cms_e($ini) . '</span>';
    $h .= '<span><strong>' . cms_e($n) . '</strong>' . ($r !== '' ? '<small>' . cms_e($r) . '</small>' : '') . '</span></figcaption></figure>';
    return $h;
};
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
        </div>
        <div class="mk-ticker-wrap mk-ticker-<?= cms_e((string) $b['speed']) ?>">
<?php for ($row = 0; $row < $rows; $row++): $list = $row === 1 ? array_reverse($items) : $items; ?>
            <div class="mk-ticker<?= $row === 1 ? ' mk-ticker-reverse' : '' ?>"><div class="mk-ticker-track"><?php foreach ($list as $it) echo $card($it); ?></div></div>
<?php endfor; ?>
        </div>
