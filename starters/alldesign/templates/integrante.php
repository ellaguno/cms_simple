<?php /** Una persona del equipo (no se enlaza desde el sitio; existe para vistas previas del panel). */ declare(strict_types=1); ?>
<section class="sec ag-team-sec"><div class="ad-container"><div class="ag-team ag-gray"><div class="ag-team-track">
  <article class="ag-member is-active">
    <div class="ag-member-photo"><?= !empty($item['photo']) ? cms_picture((string) $item['photo'], (string) cms_f($item, 'title', $lang), '', true) : '' ?></div>
    <div class="ag-member-body"><h1 class="ag-member-name"><?= cms_e(cms_f($item, 'title', $lang)) ?></h1><div class="ag-member-bio ad-prose"><?= cms_content((string) cms_f($item, 'body', $lang)) ?></div><div class="ag-member-foot"><span></span><span class="ag-member-role"><?= cms_e(cms_f($item, 'role', $lang)) ?></span></div></div>
  </article>
</div></div></div></section>
