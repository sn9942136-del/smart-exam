<?php
use App\Libraries\Session;

$flashes = Session::getAllFlashes();
?>

<?php if (!empty($flashes)): ?>
<div class="container my-3" id="alert-container">
    <?php foreach ($flashes as $type => $messages): ?>
        <?php
        $alertClass = match($type) {
            'success' => 'alert-success',
            'danger', 'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-primary'
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
            <div class="alert <?= $alertClass ?> alert-dismissible fade show shadow-sm border-0 d-flex align-items-center gap-2 mb-2" role="alert">
                <i class="bi <?= $iconClass ?> fs-5"></i>
                <div class="flex-grow-1"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
