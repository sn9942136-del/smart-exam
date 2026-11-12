<!DOCTYPE html>
<html lang="id" class="h-100" data-bs-theme="<?= theme_mode() ?>" data-theme="<?= theme_mode() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="Smart Exam — Sistem Ujian Online Berbasis AI dan Dual Camera">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="base-url" content="<?= base_url() ?>">
    <meta name="user-id" content="<?= (string)(user_id() ?? '') ?>">

    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . app_name() : app_name() ?></title>

    <!-- Anti-flicker Instant Theme Script -->
    <script>
        (function() {
            try {
                var uid = '<?= (string)(user_id() ?? "") ?>';
                var saved = uid ? localStorage.getItem('smart_exam_theme_' + uid) : localStorage.getItem('smart_exam_theme_global');
                var serverTheme = '<?= theme_mode() ?>';
                var theme = serverTheme || saved || 'light';
                document.documentElement.setAttribute('data-bs-theme', theme);
                document.documentElement.setAttribute('data-theme', theme);
            } catch(e) {}
        })();
    </script>

    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom Modern Styling -->
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
</head>
<body class="d-flex flex-column h-100 bg-body-tertiary">

    <!-- Global Top Navigation -->
    <?php include __DIR__ . '/../partials/navbar.php'; ?>

    <!-- Flash Alerts Container -->
    <?php include __DIR__ . '/../partials/alerts.php'; ?>

    <!-- Main View Content -->
    <main class="flex-shrink-0">
        <?= $content ?>
    </main>

    <!-- Global Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <!-- Reusable Modals & Spinners -->
    <?php include __DIR__ . '/../components/modal.php'; ?>
    <?php include __DIR__ . '/../components/spinner.php'; ?>

    <!-- Toast Notification Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <!-- Bootstrap 5.3.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Custom Vanilla JS Application Core -->
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
