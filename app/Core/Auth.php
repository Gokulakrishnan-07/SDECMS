<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Services\AuditService;

/**
 * Session-based authentication and role-based authorisation gate.
 */
class Auth
{
    public const ROLES = ['administrator', 'principal', 'accounts', 'department_head', 'viewer'];

    /**
     * Permission map per role. Administrator implicitly has every permission.
     */
    private const PERMISSIONS = [
        'principal' => [
            'dashboard.view', 'departments.view_all',
            'budgets.view', 'budgets.approve', 'sanctions.view', 'sanctions.approve',
            'purchase_requests.view', 'purchase_requests.approve',
            'purchase_orders.view',
            'reports.view', 'reports.export', 'notifications.view',
        ],
        'accounts' => [
            'dashboard.view', 'departments.view_all',
            'budgets.view', 'budgets.manage',
            'sanctions.view', 'sanctions.create', 'sanctions.verify',
            'purchase_requests.view',
            'purchase_orders.view', 'purchase_orders.manage',
            'reports.view', 'reports.export', 'notifications.view',
        ],
        'department_head' => [
            'dashboard.view',
            'budgets.view', 'sanctions.view',
            'sanctions.create','purchase_orders.create',
            'purchase_requests.view', 'purchase_requests.create',
            'purchase_orders.view',
            'reports.view', 'notifications.view',
        ],
        'viewer' => [
            'dashboard.view', 'departments.view_all',
            'budgets.view', 'sanctions.view',
            'purchase_requests.view', 'purchase_orders.view',
            'reports.view', 'notifications.view',
        ],
    ];

    public static function attempt(string $email, string $password): bool
    {
        $user = (new User())->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        if ((int) $user['is_active'] !== 1) {
            return false;
        }

        Session::regenerate();
        Session::put('user_id', (int) $user['id']);

        (new User())->recordLogin((int) $user['id'], Request::ip(), Request::userAgent());
        AuditService::log('login', 'users', (int) $user['id'], 'User logged in');

        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditService::log('logout', 'users', self::id(), 'User logged out');
        }
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function id(): int
    {
        return (int) Session::get('user_id', 0);
    }

    /**
     * Currently authenticated user row (cached per request).
     */
    public static function user(): ?array
    {
        static $cached = null;
        if ($cached === null && self::check()) {
            $cached = (new User())->find(self::id());
            if ($cached && (int) $cached['is_active'] !== 1) {
                Session::destroy();
                $cached = null;
            }
        }
        return $cached;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function departmentId(): ?int
    {
        $dept = self::user()['department_id'] ?? null;
        return $dept !== null ? (int) $dept : null;
    }

    /**
     * Whether the current user holds a permission.
     */
    public static function can(string $permission): bool
    {
        $role = self::role();
        if ($role === null) {
            return false;
        }
        if ($role === 'administrator') {
            return true;
        }
        return in_array($permission, self::PERMISSIONS[$role] ?? [], true);
    }

    /**
     * Department-scoped access: dept heads may only see their own department.
     */
    public static function canAccessDepartment(int $departmentId): bool
    {
        if (self::role() === 'department_head') {
            return self::departmentId() === $departmentId;
        }
        return self::check();
    }
}
