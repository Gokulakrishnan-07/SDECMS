<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class ApprovalHistoryService
{
    public static function add(string $type, int $id, string $action, ?string $reason = null): void
    {
        Database::connection()->prepare('INSERT INTO approval_history (request_type, request_id, action, reason, user_id) VALUES (?, ?, ?, ?, ?)')
            ->execute([$type, $id, $action, $reason !== null ? trim($reason) : null, \App\Core\Auth::id() ?: null]);
    }

    public static function for(string $type, int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT h.*, u.name AS user_name FROM approval_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.request_type=? AND h.request_id=? ORDER BY h.created_at, h.id');
        $stmt->execute([$type, $id]);
        return $stmt->fetchAll();
    }
}
