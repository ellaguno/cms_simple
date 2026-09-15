<?php /** Reproductor: $b['file'] o el audio del elemento actual. */ declare(strict_types=1);
$file = trim((string) $b['file']);
if ($file === '' && is_array($item ?? null) && !empty($page['route']) && strpos((string) $page['route'], 'item:') === 0) { $raw = au_raw_item(substr((string) $page['route'], 5), (string) ($item['slug'] ?? '')); if ($raw) $file = au_path($raw, $lang); }
if ($file === '') { if (cms_is_demo() || !empty($GLOBALS['cms_builder'])) echo '<p class="cms-audio-empty">Esta página aún no tiene audio: genera uno desde la barra lateral o indica un archivo.</p>'; return; }
echo au_player($file, $lang, trim((string) $b['label']));
