<?php

/**
 * Route definitions — web pages and REST API.
 * Middleware: 'auth', 'guest', 'can:<permission>'. CSRF is automatic on
 * POST/PUT/DELETE.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\BudgetController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentController;
use App\Controllers\FinancialYearController;
use App\Controllers\NotificationController;
use App\Controllers\PurchaseOrderController;
use App\Controllers\PurchaseRequestController;
use App\Controllers\ReportController;
use App\Controllers\SanctionController;
use App\Controllers\SettingsController;
use App\Controllers\UserController;

// ---------------------------------------------------------------------------
// Web pages
// ---------------------------------------------------------------------------
$router->get('/',      [DashboardController::class, 'home']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/dashboard',         [DashboardController::class, 'index'],  ['auth', 'can:dashboard.view']);
$router->get('/budgets',           [BudgetController::class, 'page'],      ['auth', 'can:budgets.view']);
$router->get('/sanctions',         [SanctionController::class, 'page'],    ['auth', 'can:sanctions.view']);
$router->get('/purchase-requests', [PurchaseRequestController::class, 'page'], ['auth', 'can:purchase_requests.view']);
$router->get('/purchase-orders',   [PurchaseOrderController::class, 'page'], ['auth', 'can:purchase_orders.view']);
$router->get('/reports',           [ReportController::class, 'page'],      ['auth', 'can:reports.view']);
$router->get('/financial-years',   [FinancialYearController::class, 'page'], ['auth', 'can:financial_years.manage']);
$router->get('/notifications',     [NotificationController::class, 'page'], ['auth', 'can:notifications.view']);
$router->get('/users',             [UserController::class, 'page'],        ['auth', 'can:users.manage']);
$router->get('/audit-logs',        [UserController::class, 'auditPage'],   ['auth', 'can:audit_logs.view']);
$router->get('/settings',          [SettingsController::class, 'page'],    ['auth']);
$router->get('/sanctions/{id}/print', [SanctionController::class, 'printView'], ['auth', 'can:sanctions.view']);
$router->get('/purchase-orders/{id}/print', [PurchaseOrderController::class, 'printView'], ['auth', 'can:purchase_orders.view']);

// ---------------------------------------------------------------------------
// REST API
// ---------------------------------------------------------------------------
$router->post('/api/login',  [AuthController::class, 'apiLogin'], ['guest']);
$router->post('/api/logout', [AuthController::class, 'apiLogout'], ['auth']);

$router->get('/api/dashboard', [DashboardController::class, 'apiStats'], ['auth', 'can:dashboard.view']);

// Departments
$router->get('/api/departments', [DepartmentController::class, 'index'], ['auth']);
$router->get('/api/departments/{id}/units', [DepartmentController::class, 'units'], ['auth']);
$router->get('/api/departments/{id}/budget-summary', [DepartmentController::class, 'budgetSummary'], ['auth']);

// Budgets  (spec: /api/budget)
$router->get('/api/budget/export',  [BudgetController::class, 'export'],  ['auth', 'can:reports.export']);
$router->get('/api/budget',         [BudgetController::class, 'index'],   ['auth', 'can:budgets.view']);
$router->post('/api/budget',        [BudgetController::class, 'store'],   ['auth', 'can:budgets.manage']);
$router->put('/api/budget/{id}',    [BudgetController::class, 'update'],  ['auth', 'can:budgets.manage']);
$router->delete('/api/budget/{id}', [BudgetController::class, 'destroy'], ['auth', 'can:budgets.delete']);
$router->post('/api/budget/{id}/approve', [BudgetController::class, 'approve'], ['auth', 'can:budgets.approve']);

// Sanctions
$router->get('/api/sanctions/export',  [SanctionController::class, 'export'],  ['auth', 'can:reports.export']);
$router->get('/api/sanctions',         [SanctionController::class, 'index'],   ['auth', 'can:sanctions.view']);
$router->post('/api/sanctions',        [SanctionController::class, 'store'],   ['auth', 'can:sanctions.create']);
$router->put('/api/sanctions/{id}',    [SanctionController::class, 'update'],  ['auth', 'can:sanctions.create']);
$router->delete('/api/sanctions/{id}', [SanctionController::class, 'destroy'], ['auth', 'can:sanctions.delete']);
$router->post('/api/sanctions/{id}/approve', [SanctionController::class, 'approve'], ['auth', 'can:sanctions.approve']);
$router->post('/api/sanctions/{id}/reject',  [SanctionController::class, 'reject'],  ['auth', 'can:sanctions.approve']);
$router->post('/api/sanctions/{id}/verify',  [SanctionController::class, 'verify'],  ['auth', 'can:sanctions.verify']);
$router->get('/api/sanctions/{id}/attachments', [SanctionController::class, 'attachments'], ['auth', 'can:sanctions.view']);
$router->get('/sanctions/{id}/attachments/{attachmentId}/view', [SanctionController::class, 'viewAttachment'], ['auth', 'can:sanctions.view']);
$router->get('/sanctions/{id}/attachments/{attachmentId}/download', [SanctionController::class, 'download'], ['auth', 'can:sanctions.view']);

// Purchase Requests
$router->get('/api/purchase-requests/export', [PurchaseRequestController::class, 'export'], ['auth', 'can:reports.export']);
$router->get('/api/purchase-requests',        [PurchaseRequestController::class, 'index'],  ['auth', 'can:purchase_requests.view']);
$router->post('/api/purchase-requests',       [PurchaseRequestController::class, 'store'],  ['auth', 'can:purchase_requests.create']);
$router->get('/api/purchase-requests/{id}',   [PurchaseRequestController::class, 'show'],   ['auth', 'can:purchase_requests.view']);
$router->put('/api/purchase-requests/{id}',   [PurchaseRequestController::class, 'update'], ['auth', 'can:purchase_requests.create']);
$router->delete('/api/purchase-requests/{id}', [PurchaseRequestController::class, 'destroy'], ['auth', 'can:purchase_requests.delete']);
$router->post('/api/purchase-requests/{id}/submit',  [PurchaseRequestController::class, 'submit'],  ['auth', 'can:purchase_requests.create']);
$router->post('/api/purchase-requests/{id}/approve', [PurchaseRequestController::class, 'approve'], ['auth', 'can:purchase_requests.approve']);
$router->post('/api/purchase-requests/{id}/reject',  [PurchaseRequestController::class, 'reject'],  ['auth', 'can:purchase_requests.approve']);

// Purchase Orders
$router->get('/api/purchase-orders/export', [PurchaseOrderController::class, 'export'], ['auth', 'can:reports.export']);
$router->get('/api/purchase-orders',        [PurchaseOrderController::class, 'index'],  ['auth', 'can:purchase_orders.view']);
$router->post('/api/purchase-orders',       [PurchaseOrderController::class, 'store'],  ['auth', 'can:purchase_orders.create']);
$router->get('/api/purchase-orders/{id}',   [PurchaseOrderController::class, 'show'],   ['auth', 'can:purchase_orders.view']);
$router->put('/api/purchase-orders/{id}',   [PurchaseOrderController::class, 'update'], ['auth', 'can:purchase_orders.create']);
$router->delete('/api/purchase-orders/{id}', [PurchaseOrderController::class, 'destroy'], ['auth', 'can:purchase_orders.delete']);
$router->post('/api/purchase-orders/{id}/status', [PurchaseOrderController::class, 'setStatus'], ['auth', 'can:purchase_orders.create']);

// Reports
$router->get('/api/reports',        [ReportController::class, 'data'],   ['auth', 'can:reports.view']);
$router->get('/api/reports/export', [ReportController::class, 'export'], ['auth', 'can:reports.export']);

// Financial Years
$router->get('/api/financial-years',  [FinancialYearController::class, 'index'], ['auth']);
$router->post('/api/financial-years', [FinancialYearController::class, 'store'], ['auth', 'can:financial_years.manage']);
$router->put('/api/financial-years/{id}', [FinancialYearController::class, 'update'], ['auth', 'can:financial_years.manage']);
$router->post('/api/financial-years/{id}/activate', [FinancialYearController::class, 'activate'], ['auth', 'can:financial_years.manage']);
$router->get('/api/financial-years/{id}/periods',  [FinancialYearController::class, 'periods'], ['auth']);
$router->post('/api/financial-years/{id}/periods', [FinancialYearController::class, 'savePeriods'], ['auth', 'can:financial_years.manage']);

// Notifications
$router->get('/api/notifications',  [NotificationController::class, 'index'], ['auth', 'can:notifications.view']);
$router->post('/api/notifications/read-all', [NotificationController::class, 'readAll'], ['auth']);
$router->post('/api/notifications/{id}/read', [NotificationController::class, 'read'], ['auth']);

// Users & audit
$router->get('/api/users',         [UserController::class, 'index'],   ['auth', 'can:users.manage']);
$router->post('/api/users',        [UserController::class, 'store'],   ['auth', 'can:users.manage']);
$router->put('/api/users/{id}',    [UserController::class, 'update'],  ['auth', 'can:users.manage']);
$router->delete('/api/users/{id}', [UserController::class, 'destroy'], ['auth', 'can:users.manage']);
$router->post('/api/users/{id}/reset-password', [UserController::class, 'resetPassword'], ['auth', 'can:users.manage']);
$router->post('/api/users/{id}/toggle',         [UserController::class, 'toggleActive'],  ['auth', 'can:users.manage']);
$router->get('/api/users/{id}/login-history',   [UserController::class, 'loginHistory'],  ['auth', 'can:users.manage']);
$router->get('/api/audit-logs', [UserController::class, 'auditLogs'], ['auth', 'can:audit_logs.view']);

// Settings
$router->post('/api/settings/profile',     [SettingsController::class, 'updateProfile'],  ['auth']);
$router->post('/api/settings/password',    [SettingsController::class, 'changePassword'], ['auth']);
$router->post('/api/settings/preferences', [SettingsController::class, 'updatePreferences'], ['auth']);
