<?php
/**
 * Reusable Data Table Component
 * Parameters:
 *  - $tableId (string)
 *  - $columns (array e.g. ['#' => 'w-5', 'Nama Pengguna' => '', 'Email' => '', 'Role' => '', 'Aksi' => 'text-end'])
 *  - $rows (array of row data or empty state)
 *  - $tableTitle (string)
 *  - $searchable (bool)
 */
$tableId    = $tableId ?? 'dataTable_' . rand(100, 999);
$tableTitle = $tableTitle ?? 'Daftar Data';
$columns    = $columns ?? [];
$rows       = $rows ?? [];
$searchable = $searchable ?? true;
?>
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <?php if (!empty($tableTitle) || $searchable): ?>
        <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-xs">
                <i class="bi bi-table text-primary"></i>
                <span><?= e($tableTitle) ?></span>
            </h6>
            <?php if ($searchable): ?>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <span class="input-group-text bg-light border-end-0 border-slate-200 text-muted"><i class="bi bi-search fs-3xs"></i></span>
                        <input type="text" class="form-control border-start-0 border-slate-200 ps-0 fs-xs" placeholder="Cari data..." onkeyup="App.filterTable('<?= $tableId ?>', this.value)">
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="<?= $tableId ?>">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <?php foreach ($columns as $colName => $colClass): ?>
                        <th class="<?= is_string($colClass) ? $colClass : '' ?> py-3 px-3"><?= e($colName) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="fs-xs divide-y">
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= max(count($columns), 1) ?>" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <span>Belum ada data yang tersedia untuk ditampilkan.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $cell): ?>
                                <td class="px-3 py-3"><?= $cell ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
