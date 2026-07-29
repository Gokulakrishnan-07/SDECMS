<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;

class UserController extends Controller
{
    public function page(): void
    {
        $this->view('users.index', ['pageTitle' => 'User Management']);
    }

    public function auditPage(): void
    {
        $this->view('users.audit', ['pageTitle' => 'Audit Logs']);
    }

    /**
     * GET /api/users
     */
    public function index(): void
    {
        $p      = $this->pageParams();
        $result = (new User())->paginate($p['offset'], $p['perPage'], $p['search'], (string) Request::query('role', ''));
        Response::paginated($result['items'], $result['total'], $p['page'], $p['perPage']);
    }

    /**
     * POST /api/users
     */
    public function store(): void
    {
        $v = Validator::make(Request::all(), [
            'name'          => 'required|max:100',
            'email'         => 'required|email|max:150',
            'password'      => 'required|minlen:8',
            'role'          => 'required|in:administrator,principal,accounts,department_head,viewer',
            'department_id' => 'integer',
            'phone'         => 'max:20',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $user = new User();
        if ($user->findByEmail($data['email'])) {
            Response::error('A user with this email already exists.', 409);
        }
        if ($data['role'] === 'department_head' && empty($data['department_id'])) {
            Response::error('Department heads must be assigned to a department.', 422);
        }

        $id = $user->insert([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'          => $data['role'],
            'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
            'phone'         => $data['phone'] ?? null,
        ]);

        AuditService::log('create', 'users', $id, 'User ' . $data['email'] . ' created with role ' . $data['role']);
        Response::json(['id' => $id], 201, 'User created.');
    }

    /**
     * PUT /api/users/{id}
     */
    public function update(string $id): void
    {
        $user = new User();
        $row  = $user->find((int) $id);
        if ($row === null) {
            Response::error('User not found.', 404);
        }

        $v = Validator::make(Request::all(), [
            'name'          => 'required|max:100',
            'email'         => 'required|email|max:150',
            'role'          => 'required|in:administrator,principal,accounts,department_head,viewer',
            'department_id' => 'integer',
            'phone'         => 'max:20',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $existing = $user->findByEmail($data['email']);
        if ($existing !== null && (int) $existing['id'] !== (int) $id) {
            Response::error('Another user already uses this email.', 409);
        }
        if ($data['role'] === 'department_head' && empty($data['department_id'])) {
            Response::error('Department heads must be assigned to a department.', 422);
        }
        if ((int) $id === Auth::id() && $data['role'] !== 'administrator') {
            Response::error('You cannot demote your own administrator account.', 422);
        }

        $user->update((int) $id, [
            'name'          => $data['name'],
            'email'         => $data['email'],
            'role'          => $data['role'],
            'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
            'phone'         => $data['phone'] ?? null,
        ]);

        AuditService::log('update', 'users', (int) $id, 'User ' . $data['email'] . ' updated');
        Response::json(null, 200, 'User updated.');
    }

    /**
     * DELETE /api/users/{id}
     */
    public function destroy(string $id): void
    {
        if ((int) $id === Auth::id()) {
            Response::error('You cannot delete your own account.', 422);
        }

        $user = new User();
        $row  = $user->find((int) $id);
        if ($row === null) {
            Response::error('User not found.', 404);
        }

        $user->delete((int) $id);
        AuditService::log('delete', 'users', (int) $id, 'User ' . $row['email'] . ' deleted');
        Response::json(null, 200, 'User deleted.');
    }

    /**
     * POST /api/users/{id}/reset-password  {password}
     */
    public function resetPassword(string $id): void
    {
        $user = new User();
        $row  = $user->find((int) $id);
        if ($row === null) {
            Response::error('User not found.', 404);
        }

        $password = (string) Request::input('password', '');
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters.', 422);
        }

        $user->update((int) $id, ['password' => password_hash($password, PASSWORD_DEFAULT)]);
        AuditService::log('update', 'users', (int) $id, 'Password reset for ' . $row['email']);
        Response::json(null, 200, 'Password reset.');
    }

    /**
     * POST /api/users/{id}/toggle — enable/disable the account.
     */
    public function toggleActive(string $id): void
    {
        if ((int) $id === Auth::id()) {
            Response::error('You cannot disable your own account.', 422);
        }

        $user = new User();
        $row  = $user->find((int) $id);
        if ($row === null) {
            Response::error('User not found.', 404);
        }

        $newState = (int) $row['is_active'] === 1 ? 0 : 1;
        $user->update((int) $id, ['is_active' => $newState]);

        AuditService::log('update', 'users', (int) $id, 'User ' . $row['email'] . ($newState ? ' enabled' : ' disabled'));
        Response::json(['is_active' => $newState], 200, $newState ? 'User enabled.' : 'User disabled.');
    }

    /**
     * GET /api/users/{id}/login-history
     */
    public function loginHistory(string $id): void
    {
        Response::json((new User())->loginHistory((int) $id));
    }

    /**
     * GET /api/audit-logs
     */
    public function auditLogs(): void
    {
        $p      = $this->pageParams(15);
        $result = (new AuditLog())->paginate($p['offset'], $p['perPage'], $p['search']);
        Response::paginated($result['items'], $result['total'], $p['page'], $p['perPage']);
    }
}
