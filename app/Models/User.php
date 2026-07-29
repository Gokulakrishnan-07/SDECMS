<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function recordLogin(int $userId, string $ip, string $userAgent): void
    {
        $this->db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
        $this->db()->prepare('INSERT INTO login_history (user_id, ip_address, user_agent) VALUES (?, ?, ?)')
            ->execute([$userId, $ip, $userAgent]);
    }

    /**
     * Paginated user list with department names and optional search.
     */
    public function paginate(int $offset, int $limit, string $search = '', string $role = ''): array
    {
        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like);
        }
        if ($role !== '') {
            $where .= ' AND u.role = ?';
            $params[] = $role;
        }

        $total = (int) $this->fetchOne(
            "SELECT COUNT(*) AS c FROM users u WHERE $where", $params
        )['c'];

        $stmt = $this->db()->prepare(
            "SELECT u.id, u.name, u.email, u.role, u.department_id, u.phone, u.is_active,
                    u.last_login_at, u.created_at, d.name AS department_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE $where
             ORDER BY u.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function loginHistory(int $userId, int $limit = 25): array
    {
        $stmt = $this->db()->prepare(
            'SELECT ip_address, user_agent, logged_in_at
             FROM login_history WHERE user_id = ?
             ORDER BY logged_in_at DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Users holding any of the given roles (for notification fan-out).
     */
    public function idsByRoles(array $roles): array
    {
        if ($roles === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($roles), '?'));
        $stmt  = $this->db()->prepare("SELECT id FROM users WHERE role IN ($marks) AND is_active = 1");
        $stmt->execute($roles);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    public function headsOfDepartment(int $departmentId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT id FROM users WHERE role = 'department_head' AND department_id = ? AND is_active = 1"
        );
        $stmt->execute([$departmentId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private function fetchOne(string $sql, array $params): array
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: ['c' => 0];
    }
}
