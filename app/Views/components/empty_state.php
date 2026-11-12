<?php
/**
 * Reusable Empty State Component
 *
 * Parameters:
 *  - $emptyIcon (string) : icon name without 'bi-', e.g. 'inbox', 'journal-x', 'people', 'question-circle'
 *  - $emptyTitle (string) : Headline for empty state
 *  - $emptyDesc (string) : Brief description / guidance
 *  - $emptyActionUrl (string|null) : Optional CTA URL
 *  - $emptyActionText (string|null) : CTA button label
 *  - $emptyActionIcon (string|null) : Optional CTA icon
 *  - $emptyTheme (string) : Color theme ('secondary', 'primary', 'info', 'warning')
 */
$emptyIcon       = $emptyIcon ?? 'inbox';
$emptyTitle      = $emptyTitle ?? 'Belum Ada Data';
$emptyDesc       = $emptyDesc ?? 'Tidak ada data yang tersedia untuk ditampilkan saat ini.';
$emptyActionUrl  = $emptyActionUrl ?? null;
$emptyActionText = $emptyActionText ?? null;
$emptyActionIcon = $emptyActionIcon ?? 'plus-lg';
$emptyTheme      = $emptyTheme ?? 'primary';
?>

<div class="empty-state py-5 px-3 text-center">
    <div class="empty-state-icon mx-auto mb-3 bg-<?= e($emptyTheme) ?>-subtle text-<?= e($emptyTheme) ?> shadow-2xs">
        <i class="bi bi-<?= e($emptyIcon) ?> fs-2"></i>
    </div>
    <h6 class="empty-state-title fs-6 fw-bold text-dark mb-1"><?= e($emptyTitle) ?></h6>
    <p class="empty-state-desc text-muted fs-xs mb-3 mx-auto"><?= e($emptyDesc) ?></p>
    <?php if (!empty($emptyActionUrl) && !empty($emptyActionText)): ?>
        <a href="<?= e($emptyActionUrl) ?>" class="btn btn-sm btn-<?= e($emptyTheme) ?> rounded-pill px-3 shadow-xs">
            <i class="bi bi-<?= e($emptyActionIcon) ?> me-1"></i> <?= e($emptyActionText) ?>
        </a>
    <?php endif; ?>
</div>
