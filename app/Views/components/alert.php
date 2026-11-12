<?php
use App\Libraries\Session;

/**
 * Reusable Alert Component
 * Supports Flash Messages from Session or Direct Variables ($alertType, $alertMessage)
 */
$flashes = Session::getAllFlashes();
?>

<?php if (!empty($flashes)): ?>
<div class="alert-wrapper mb-3" id="alert-container">
    <?php foreach ($flashes as $type => $messages): ?>
        <?php
        $alertClass = match($type) {
            'success' => 'alert-success border-success-subtle text-success-emphasis',
            'danger', 'error' => 'alert-danger border-danger-subtle text-danger-emphasis',
            'warning' => 'alert-warning border-warning-subtle text-warning-emphasis',
            'info' => 'alert-info border-info-subtle text-info-emphasis',
            default => 'alert-primary border-primary-subtle text-primary-emphasis'
        };
        $iconClass = match($type) {
            'success' => 'bi-check-circle-fill',
            'danger', 'error' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            'info' => 'bi-info-circle-fill',
            default => 'bi-bell-fill'
        };
        ?>
        <?php foreach ($messages as $msg): ?>
            <div class="alert <?= $alertClass ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2 mb-2 rounded-3" role="alert">
                <i class="bi <?= $iconClass ?> fs-5 flex-shrink-0"></i>
                <div class="flex-grow-1 fs-xs"><?= htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($alertMessage)): ?>
    <?php
    $type = $alertType ?? 'info';
    $alertClass = match($type) {
        'success' => 'alert-success border-success-subtle',
        'danger', 'error' => 'alert-danger border-danger-subtle',
        'warning' => 'alert-warning border-warning-subtle',
        default => 'alert-info border-info-subtle'
    };
    ?>
    <div class="alert <?= $alertClass ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2 mb-3 rounded-3" role="alert">
        <div class="flex-grow-1 fs-xs"><?= htmlspecialchars((string)$alertMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
