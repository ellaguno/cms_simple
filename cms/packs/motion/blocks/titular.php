<?php /** Titular grande. $b: text, sub, button_text, button_url, align, reveal */ declare(strict_types=1);
cms_section_effect('motion/reveal', !empty($b['reveal']));
$clean = fn(string $x) => preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', strip_tags($x, '<em><strong><br><span>')) ?? '';
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <div class="mo-statement mo-statement-<?= cms_e($b['align']) ?>">
                <h2 class="mo-statement-text"><?= $clean((string) $b['text']) ?></h2>
<?php if (trim((string) $b['sub']) !== ''): ?>
                <p class="mo-statement-sub"><?= $clean((string) $b['sub']) ?></p>
<?php endif; ?>
<?php if (trim((string) $b['button_text']) !== ''): $u = trim((string) $b['button_url']); $u = preg_match('#^(https?:)?//|^mailto:|^\#|^tel:#i', $u) ? $u : CMS_BASE . '/' . ltrim($u, '/'); ?>
                <p><a class="<?= cms_e(cms_block_class('btn')) ?>" href="<?= cms_e($u) ?>" data-magnetic><?= cms_e($b['button_text']) ?></a></p>
<?php endif; ?>
            </div>
        </div>
