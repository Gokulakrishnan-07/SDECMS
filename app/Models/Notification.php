<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Notification extends Model
{
    protected string $table = 'notifications';

    public function forUser(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $where = 'user_id = ?' . ($unreadOnly ? ' AND is_read = 0' : '');
        $stmt  = $this->db()->prepare(
            "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT " . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        return $this->count('user_id = ? AND is_read = 0', [$userId]);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
            ->execute([$id, $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $this->db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
            ->execute([$userId]);
    }
}
