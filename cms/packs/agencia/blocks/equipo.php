<?php /** Equipo (carrusel). $b: title, source, items, count, interval, grayscale, shape */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$people = ag_team_members($b, $lang);
if (!$people) return;
$shape = in_array((string) $b['shape'], ['square', 'circle', 'portrait'], true) ? (string) $b['shape'] : 'square';
?>
<div class="<?= cms_e(cms_block_class('container')) ?>">
  <?= cms_block_header((string) $b['title']) ?>
  <div class="ag-team ag-photo-<?= $shape ?><?= !empty($b['grayscale']) ? ' ag-gray' : '' ?>" data-ag-team data-interval="<?= max(0, (int) $b['interval']) * 1000 ?>">
    <button type="button" class="ag-team-btn ag-team-prev" data-ag-prev aria-label="<?= $lang === 'en' ? 'Previous' : 'Anterior' ?>"><?= ag_svg('chevron-l') ?></button>
    <div class="ag-team-track">
<?php foreach ($people as $i => $p): ?>
      <article class="ag-member<?= $i === 0 ? ' is-active' : '' ?>"<?= $i === 0 ? '' : ' hidden' ?>>
        <div class="ag-member-photo"><?= $p['photo'] !== '' ? cms_picture($p['photo'], $p['name'], '', $i === 0) : '' ?></div>
        <div class="ag-member-body">
          <h3 class="ag-member-name"><?= cms_e($p['name']) ?></h3>
          <div class="ag-member-bio"><?= $p['text'] ?></div>
          <div class="ag-member-foot">
            <div class="ag-member-links"><?php foreach ($p['links'] as [$ic, $u]): ?><a href="<?= cms_e($u) ?>" target="_blank" rel="noopener" aria-label="<?= cms_e($ic) ?>"><?= ag_svg($ic) ?: ag_svg('link') ?></a><?php endforeach; ?></div>
            <span class="ag-member-role"><?= cms_e($p['role']) ?></span>
          </div>
        </div>
      </article>
<?php endforeach; ?>
    </div>
    <button type="button" class="ag-team-btn ag-team-next" data-ag-next aria-label="<?= $lang === 'en' ? 'Next' : 'Siguiente' ?>"><?= ag_svg('chevron-r') ?></button>
  </div>
</div>
