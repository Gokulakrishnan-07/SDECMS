<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;

/**
 * Creates in-app notifications (one row per recipient).
 */
class NotificationService
{
    public static function notifyUsers(array $userIds, string $type, string $title, string $message, ?string $link = null): void
    {
        if ($userIds === []) {
            return;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
        );
        foreach (array_unique($userIds) as $id) {
            $stmt->execute([(int) $id, $type, substr($title, 0, 150), substr($message, 0, 500), $link]);
        }
    }

    /**
     * Notify every active user holding one of the given roles.
     */
    public static function notifyRoles(array $roles, string $type, string $title, string $message, ?string $link = null): void
    {
        self::notifyUsers((new User())->idsByRoles($roles), $type, $title, $message, $link);
    }

    /**
     * Notify the head(s) of a department.
     */
    public static function notifyDepartmentHeads(int $departmentId, string $type, string $title, string $message, ?string $link = null): void
    {
        self::notifyUsers((new User())->headsOfDepartment($departmentId), $type, $title, $message, $link);
    }
}
