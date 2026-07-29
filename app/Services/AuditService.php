<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Writes rows to audit_logs. Never throws — auditing must not break requests.
 */
class AuditService
{
    public static function log(string $action, string $table, ?int $recordId = null, ?string $description = null): void
    {
        try {
            Database::connection()->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, description, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                Session::get('user_id'),
                $action,
                $table,
                $recordId,
                $description !== null ? substr($description, 0, 500) : null,
                Request::ip(),
            ]);
        } catch (\Throwable) {
            error_log('Audit log failed: ' . $action . ' ' . $table);
        }
    }
}
