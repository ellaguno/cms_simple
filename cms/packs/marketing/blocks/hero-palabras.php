<?php /** Hero con palabras rotativas. $b: badge, before, words, after, subtitle, buttons, image, align, gradient */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$words = array_values(array_filter(array_map('trim', (array) $b['words']), fn($w) => $w !== ''));
if (!$words) $words = ['—'];
$img = trim((string) $b['image']);
?>
        <div class="<?= cms_e(cms_block_class('container')) ?> mk-hero-in mk-hero-<?= cms_e((string) $b['align']) ?>">
<?php if (trim((string) $b['badge']) !== ''): ?>            <span class="mk-badge"><?= cms_e($b['badge']) ?></span>
<?php endif; ?>
            <h1 class="mk-hero-title"><?= mk_inline((string) $b['before']) ?> <span class="mk-rotate" data-words="<?= cms_e(json_encode($words, JSON_UNESCAPED_UNICODE)) ?>"><span class="mk-rotate-word<?= !empty($b['gradient']) ? ' mk-gradient-text' : '' ?>"><?= cms_e($words[0]) ?></span></span><?php if (trim((string) $b['after']) !== ''): ?> <?= mk_inline((string) $b['after']) ?><?php endif; ?></h1>
<?php if (trim((string) $b['subtitle']) !== ''): ?>            <p class="mk-hero-sub"><?= nl2br(cms_e($b['subtitle'])) ?></p>
<?php endif; ?>
<?php if ((array) $b['buttons']): ?>            <div class="mk-btns"><?php foreach ((array) $b['buttons'] as $l) { [$t2, $u, $s] = mk_split((string) $l, 3); echo mk_btn($t2, $u, $s ?: 'primary'); } ?></div>
<?php endif; ?>
<?php if ($img !== ''): ?>            <div class="mk-hero-media"><div class="mk-hero-glow" aria-hidden="true"></div><img src="<?= cms_e(cms_img($img)) ?>" alt="" loading="lazy"></div>
<?php endif; ?>
        </div>
