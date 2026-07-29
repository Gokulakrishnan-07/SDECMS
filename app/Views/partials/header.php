<?php $__activeFy = (new App\Models\FinancialYear())->active(); ?>
<header class="app-header glass">
    <button class="icon-btn d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="fa-solid fa-bars"></i>
    </button>
    <button class="icon-btn d-none d-lg-inline-flex" id="sidebarCollapse" aria-label="Collapse sidebar">
        <i class="fa-solid fa-bars-staggered"></i>
    </button>

    <div class="header-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" id="globalSearch" placeholder="Search this page…" autocomplete="off">
    </div>

    <div class="header-right">
        <?php if ($__activeFy): ?>
        <span class="fy-chip d-none d-md-inline-flex" title="Active financial year — the whole system uses this year">
            <i class="fa-solid fa-calendar-check"></i> FY <?= e($__activeFy['label']) ?>
        </span>
        <?php endif; ?>
        <div class="header-clock d-none d-lg-flex">
            <span id="clockDate"></span>
            <span id="clockTime"></span>
        </div>

        <button class="icon-btn" id="themeToggle" aria-label="Toggle theme">
            <i class="fa-solid fa-moon"></i>
        </button>

        <div class="dropdown">
            <button class="icon-btn position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-badge d-none" id="notifBadge">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end notif-menu glass-card p-0">
                <div class="notif-head">
                    <strong>Notifications</strong>
                    <button class="btn btn-link btn-sm p-0" id="notifReadAll">Mark all read</button>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-empty">No notifications yet</div>
                </div>
                <a class="notif-foot" href="<?= base_url('notifications') ?>">View all</a>
            </div>
        </div>

        <div class="dropdown">
            <button class="user-chip" data-bs-toggle="dropdown">
                <span class="avatar avatar-sm"><?= e(strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?></span>
                <span class="d-none d-md-inline"><?= e($user['name'] ?? '') ?></span>
                <i class="fa-solid fa-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end glass-card">
                <li><span class="dropdown-header"><?= e(role_label($user['role'] ?? '')) ?></span></li>
                <li><a class="dropdown-item" href="<?= base_url('settings') ?>"><i class="fa-solid fa-user me-2"></i>Profile &amp; Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= base_url('logout') ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
