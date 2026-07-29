<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    public function paginate(int $offset, int $limit, string $search = ''): array
    {
        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where .= ' AND (a.action LIKE ? OR a.table_name LIKE ? OR a.description LIKE ? OR u.name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like, $like);
        }

        $countStmt = $this->db()->prepare(
            "SELECT COUNT(*) AS c FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['c'];

        $stmt = $this->db()->prepare(
            "SELECT a.*, u.name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE $where
             ORDER BY a.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function recent(int $limit = 10): array
    {
        return $this->db()->query(
            'SELECT a.*, u.name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT ' . $limit
        )->fetchAll();
    }
}
