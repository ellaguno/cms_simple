<?php /** Video con carga al clic. $b: title, subtitle, url, poster */ declare(strict_types=1);
$url = trim((string) $b['url']); $id = ''; $prov = '';
if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([\w-]{6,})~', $url, $m)) { $prov = 'youtube'; $id = $m[1]; }
elseif (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) { $prov = 'vimeo'; $id = $m[1]; }
if ($id === '') return;
$embed = $prov === 'youtube' ? 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0' : 'https://player.vimeo.com/video/' . $id . '?autoplay=1';
$poster = !empty($b['poster']) ? cms_img((string) $b['poster']) : ($prov === 'youtube' ? 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' : ''); ?>
<div class="<?= cms_e(cms_block_class('container')) ?>">
  <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
  <div class="med-video" data-med-video="<?= cms_e($embed) ?>"<?= $poster ? ' style="background-image:url(' . cms_e($poster) . ')"' : '' ?>>
    <button type="button" class="med-play" aria-label="Reproducir video"><svg viewBox="0 0 24 24" width="34" height="34"><path fill="currentColor" d="M8 5v14l11-7z"/></svg></button>
  </div>
</div>
