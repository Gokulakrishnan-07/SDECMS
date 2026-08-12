<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model for the sanction_attachments table.
 * Each row represents a single file attached to a sanction request.
 */
class SanctionAttachment extends Model
{
    protected string $table = 'sanction_attachments';

    /**
     * All attachments for a given sanction, ordered by upload date.
     */
    public function bySanctionId(int $sanctionId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, sanction_id, original_filename, stored_filename, file_path,
                    file_extension, mime_type, file_size, uploaded_by, created_at
             FROM sanction_attachments WHERE sanction_id = ? ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$sanctionId]);
        return $stmt->fetchAll();
    }
}
