<div class="auth-card">
    <div class="text-center mb-4">
        <img src="<?= asset('img/logo.svg') ?>" alt="Logo" class="auth-logo mb-3">
        <h1 class="h4 fw-bolder mb-1" style="letter-spacing:-.03em">Swami Dayananda</h1>
        <p class="text-secondary small mb-0">Educational Cost Management System</p>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="alert alert-danger py-2 small rounded-4"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= base_url('login') ?>" autocomplete="on">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input type="email" class="form-control" id="email" name="email" required
                   placeholder="you@sdc.edu.in" autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" required
                   minlength="6" placeholder="••••••••">
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">
            Sign in <i class="fa-solid fa-arrow-right ms-1"></i>
        </button>
    </form>

    <p class="text-center text-secondary mt-4 mb-0" style="font-size:.72rem">
        Swami Dayanandha Educational Institutions · Manjakkudi
    </p>
</div>
