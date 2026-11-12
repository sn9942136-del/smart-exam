<?php
/**
 * Reusable Pagination Component
 * Parameters: $currentPage, $totalPages, $baseUrl, $totalItems
 */
$currentPage = (int) ($currentPage ?? 1);
$totalPages  = (int) ($totalPages ?? 1);
$totalItems  = (int) ($totalItems ?? 0);
$perPage     = (int) ($perPage ?? 10);
$startItem   = $totalItems > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
$endItem     = min($currentPage * $perPage, $totalItems);

$queryParams = $_GET ?? [];
$getPageUrl = function($page) use ($queryParams) {
    $params = array_merge($queryParams, ['page' => $page]);
    return '?' . http_build_query($params);
};
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 border-top border-slate-100">
    <div class="fs-xs text-slate-700">
        Menampilkan <strong class="text-slate-900"><?= $startItem ?></strong>-<strong class="text-slate-900"><?= $endItem ?></strong> dari <strong class="text-slate-900"><?= $totalItems ?></strong> data
    </div>
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage > 1) ? $getPageUrl($currentPage - 1) : '#' ?>" aria-label="Previous">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= ($p === $currentPage) ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $getPageUrl($p) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= ($currentPage < $totalPages) ? $getPageUrl($currentPage + 1) : '#' ?>" aria-label="Next">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

