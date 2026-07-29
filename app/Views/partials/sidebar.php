<?php
use App\Core\Auth;
use App\Core\Request;

$currentPath = Request::path();

$menu = [
    ['label' => 'Dashboard',                 'path' => '/dashboard',         'icon' => 'fa-house',            'perm' => 'dashboard.view'],
    ['label' => 'Budget Allocation',         'path' => '/budgets',           'icon' => 'fa-sack-dollar',      'perm' => 'budgets.view'],
    ['label' => 'Sanction Requisition',      'path' => '/sanctions',         'icon' => 'fa-stamp',            'perm' => 'sanctions.view'],
    ['label' => 'Purchase Requisition',      'path' => '/purchase-requests', 'icon' => 'fa-file-signature',   'perm' => 'purchase_requests.view'],
    ['label' => 'Purchase Order',            'path' => '/purchase-orders',   'icon' => 'fa-file-invoice',     'perm' => 'purchase_orders.view'],
    ['label' => 'Reports',                   'path' => '/reports',           'icon' => 'fa-chart-pie',        'perm' => 'reports.view'],
    ['label' => 'Financial Year Management', 'path' => '/financial-years',   'icon' => 'fa-calendar-days',    'perm' => 'financial_years.manage'],
    ['label' => 'Notifications',             'path' => '/notifications',     'icon' => 'fa-bell',             'perm' => 'notifications.view'],
    ['label' => 'User Management',           'path' => '/users',             'icon' => 'fa-users-gear',       'perm' => 'users.manage'],
    ['label' => 'Audit Logs',                'path' => '/audit-logs',        'icon' => 'fa-clipboard-list',   'perm' => 'audit_logs.view'],
    ['label' => 'Settings',                  'path' => '/settings',          'icon' => 'fa-gear',             'perm' => null],
];
?>
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <img src="<?= asset('img/logo.svg') ?>" alt="Logo" class="sidebar-logo">
        <div class="sidebar-brand-text">
            <span class="brand-title">Swami Dayananda</span>
            <span class="brand-subtitle">Educational Cost Management</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $item): ?>
            <?php if ($item['perm'] !== null && !Auth::can($item['perm'])) continue; ?>
            <a href="<?= base_url($item['path']) ?>"
               class="sidebar-link <?= $currentPath === $item['path'] ? 'active' : '' ?>">
                <span class="sidebar-icon"><i class="fa-solid <?= e($item['icon']) ?>"></i></span>
                <span class="sidebar-label"><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= e(strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?></div>
            <div class="sidebar-user-meta">
                <span class="sidebar-user-name"><?= e($user['name'] ?? '') ?></span>
                <span class="sidebar-user-role"><?= e(role_label($user['role'] ?? '')) ?></span>
            </div>
        </div>
    </div>
</aside>
