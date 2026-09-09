<?php /** Listado de una colección. $b: title, subtitle, source, count, layout, filter, order, ratio, show_*, more_text, empty */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$type = (string) $b['source'];
$cards = ct_collection_cards($type, $b, $lang);
if (!$cards) {
    if (trim((string) $b['empty']) === '') return;
    echo '<div class="' . cms_e(cms_block_class('container')) . '"><p class="ct-empty">' . cms_e($b['empty']) . '</p></div>';
    return;
}
$layout = (string) $b['layout'];
$ratio = (string) $b['ratio'];
$featured = $layout === 'featured' ? array_shift($cards) : null;
$more = trim((string) $b['more_text']);
$moreUrl = $type !== '' && cms_type($type) && empty(cms_type($type)['no_list']) ? cms_url('list:' . $type, $lang) : '';
$card = function (array $c, bool $big = false) use ($b, $ratio, $lang): string {
    [$url, $title, $text, $img, $date, $cat] = $c;
    $h = '<article class="ct-card' . ($big ? ' ct-card-big' : '') . '"><a class="ct-card-link" href="' . cms_e($url) . '">';
    if ($ratio !== 'none' && $img !== '') $h .= '<div class="ct-card-media ct-r-' . cms_e($ratio) . '"><img src="' . cms_e(cms_img($img)) . '" alt="" loading="lazy"></div>';
    $h .= '<div class="ct-card-body">';
    $meta = [];
    if (!empty($b['show_cat']) && $cat !== '') $meta[] = '<span class="ct-tag">' . cms_e($cat) . '</span>';
    if (!empty($b['show_date']) && $date !== '') $meta[] = '<time datetime="' . cms_e($date) . '">' . cms_e(ct_date($date, $lang)) . '</time>';
    if ($meta) $h .= '<div class="ct-card-meta">' . implode('', $meta) . '</div>';
    $h .= '<h3>' . cms_e($title) . '</h3>';
    if (!empty($b['show_text']) && $text !== '') $h .= '<p>' . cms_e(mb_strimwidth(strip_tags($text), 0, $big ? 220 : 150, '…')) . '</p>';
    return $h . '</div></a></article>';
};
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
<?php if ($featured): ?>
            <div class="ct-featured">
                <?= $card($featured, true) ?>
                <div class="ct-featured-list"><?php foreach ($cards as $c) echo $card($c); ?></div>
            </div>
<?php else: ?>
            <div class="ct-cards ct-<?= cms_e($layout) ?>"><?php foreach ($cards as $c) echo $card($c); ?></div>
<?php endif; ?>
<?php if ($more !== '' && $moreUrl !== ''): ?>
            <p class="ct-more"><a class="<?= cms_e(cms_block_class('btn')) ?> ct-btn-outline" href="<?= cms_e($moreUrl) ?>"><?= cms_e($more) ?></a></p>
<?php endif; ?>
        </div>
