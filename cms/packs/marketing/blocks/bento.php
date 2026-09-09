<?php /** Cuadrícula bento. $b: title, subtitle, items, columns */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$sizes = ['normal' => '', 'ancho' => 'mk-bento-w', 'alto' => 'mk-bento-h', 'grande' => 'mk-bento-l', 'wide' => 'mk-bento-w', 'tall' => 'mk-bento-h', 'large' => 'mk-bento-l'];
$items = [];
foreach ((array) $b['items'] as $l) { [$t, $x, $v, $s] = mk_split((string) $l, 4); if ($t !== '' || $x !== '') $items[] = [$t, $x, $v, $sizes[strtolower($s)] ?? '']; }
if (!$items) return;
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="mk-bento mk-bento-<?= (int) $b['columns'] ?>">
<?php foreach ($items as [$t, $x, $v, $cls]): $isImg = mk_is_image($v); ?>
                <article class="mk-bento-item<?= $cls ? ' ' . $cls : '' ?><?= $isImg ? ' mk-bento-has-img' : '' ?>">
<?php if ($v !== ''): ?>                    <div class="mk-bento-visual"><?= mk_visual($v, $t) ?></div>
<?php endif; ?>
                    <div class="mk-bento-body"><?php if ($t !== ''): ?><h3><?= cms_e($t) ?></h3><?php endif; ?><?php if ($x !== ''): ?><p><?= cms_e($x) ?></p><?php endif; ?></div>
                </article>
<?php endforeach; ?>
            </div>
        </div>
