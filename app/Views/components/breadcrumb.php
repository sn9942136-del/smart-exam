<?php
/**
 * Reusable Breadcrumb Component
 * Usage: include component with $breadcrumbs array e.g. [['title' => 'Dashboard', 'url' => base_url('admin/dashboard')], ['title' => 'Manajemen Pengguna']]
 */
$breadcrumbs = $breadcrumbs ?? [
    ['title' => 'Beranda', 'url' => base_url()],
    ['title' => $pageTitle ?? 'Dashboard']
];
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 py-2 px-3 bg-white rounded-3 border border-slate-200 shadow-2xs fs-xs">
        <?php foreach ($breadcrumbs as $index => $item): ?>
            <?php if ($index === count($breadcrumbs) - 1 || empty($item['url'])): ?>
                <li class="breadcrumb-item active text-slate-900 fw-semibold" aria-current="page">
                    <?= e($item['title']) ?>
                </li>
            <?php else: ?>
                <li class="breadcrumb-item">
                    <a href="<?= e($item['url']) ?>" class="text-decoration-none text-slate-600 fw-medium">
                        <?= e($item['title']) ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
