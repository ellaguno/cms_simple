<?php /** Banda de aviso. $b: text, icon, link_text, link_url, closable, key */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
if (trim((string) $b['text']) === '') return;
$key = cms_slugify((string) $b['key']) ?: 'aviso';
?>
        <div class="ct-band" data-ct-band="<?= cms_e($key) ?>"<?= empty($b['closable']) ? '' : ' hidden' ?>>
            <p>
<?php if (trim((string) $b['icon']) !== ''): ?><span class="ct-band-icon"><?= ct_icon((string) $b['icon']) ?></span> <?php endif; ?><?= ct_rich((string) $b['text']) ?>
<?php if (trim((string) $b['link_text']) !== ''): ?> <a href="<?= cms_e($b['link_url'] !== '' ? $b['link_url'] : '#') ?>"><?= cms_e($b['link_text']) ?></a><?php endif; ?>
            </p>
<?php if (!empty($b['closable'])): ?>            <button type="button" class="ct-band-close" aria-label="Cerrar el aviso">&times;</button>
<?php endif; ?>
        </div>
