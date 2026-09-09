<?php /** Cinta de logotipos. $b: title, images, speed, gray */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$logos = [];
foreach ((array) $b['images'] as $l) { [$src, $name] = mk_split((string) $l, 2); if ($src !== '') $logos[] = [cms_img($src), $name]; }
if (!$logos) return;
?>
<?php if (trim((string) $b['title']) !== ''): ?>        <div class="<?= cms_e(cms_block_class('container')) ?>"><p class="mk-logos-title"><?= cms_e($b['title']) ?></p></div>
<?php endif; ?>
        <div class="mk-ticker-wrap mk-ticker-<?= cms_e((string) $b['speed']) ?><?= !empty($b['gray']) ? ' mk-logos-gray' : '' ?>">
            <div class="mk-ticker mk-logos"><div class="mk-ticker-track"><?php foreach ($logos as [$src, $name]): ?><span class="mk-logo"><img src="<?= cms_e($src) ?>" alt="<?= cms_e($name) ?>" loading="lazy"<?= $name !== '' ? ' title="' . cms_e($name) . '"' : '' ?>></span><?php endforeach; ?></div></div>
        </div>
