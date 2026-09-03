<?php /** Portada. Variables: $lang, $S, $t, $page */ declare(strict_types=1);
$posts = array_slice(cms_items('posts'), 0, (int) ($S['home_posts'] ?? 3), true); ?>
<section class="hero wrap">
  <div>
    <h1><?= cms_e($t('hero_title', $S['site_name'] ?? '')) ?></h1>
    <p class="lead"><?= cms_e($t('hero_text')) ?></p>
    <?php if ($t('hero_button')): ?><a class="btn" href="<?= cms_url('page:contact', $lang) ?>"><?= cms_e($t('hero_button')) ?></a><?php endif; ?>
  </div>
  <?php if (!empty($S['hero_image'])): ?><div class="hero-img"><?= cms_picture((string) $S['hero_image'], '', '', true) ?></div><?php endif; ?>
</section>
<?php if ($posts): ?>
<section class="wrap">
  <h2><?= cms_e($t('latest_posts')) ?></h2>
  <div class="grid">
<?php foreach ($posts as $p): $u = cms_url('item:posts', $lang, $p['slug']); ?>
    <article class="card">
      <?php if (!empty($p['image'])): ?><a href="<?= $u ?>"><?= cms_picture((string) $p['image'], (string) cms_f($p, 'title', $lang)) ?></a><?php endif; ?>
      <div class="card-b">
        <small><?= cms_e(cms_f($p, 'category', $lang)) ?> · <?= cms_date($p['date'] ?? '', $lang) ?></small>
        <h3><a href="<?= $u ?>"><?= cms_e(cms_f($p, 'title', $lang)) ?></a></h3>
        <p><?= cms_e(cms_f($p, 'excerpt', $lang)) ?></p>
      </div>
    </article>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
