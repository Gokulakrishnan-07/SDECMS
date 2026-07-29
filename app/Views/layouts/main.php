<?php /** @var string $content */ $user = auth_user(); ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(base_url('')) ?>">
    <title><?= e($pageTitle ?? 'SECMS') ?> · <?= e(config('app.short')) ?></title>
    <link rel="icon" href="<?= asset('img/logo.svg') ?>" type="image/svg+xml">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.11/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>

    <div class="app-main">
        <?php require BASE_PATH . '/app/Views/partials/header.php'; ?>

        <main class="app-content" id="appContent">
            <?= $content ?>
        </main>
    </div>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.11/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.1/dist/sweetalert2.all.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
