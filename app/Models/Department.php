<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Department extends Model
{
    protected string $table = 'departments';

    public function allActive(): array
    {
        return $this->db()->query(
            'SELECT * FROM departments WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    /**
     * Departments with their budget figures for a financial year — powers the
     * dashboard orbit showcase and department-wise charts.
     * remaining = allocated - committed - used (true available).
     */
    public function withBudgets(int $financialYearId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT d.id, d.name, d.code, d.icon, d.workflow_type, d.has_units,
                    COALESCE(b.allocated_amount, 0) AS allocated_amount,
                    COALESCE(b.committed_amount, 0) AS committed_amount,
                    COALESCE(b.used_amount, 0)      AS used_amount,
                    COALESCE(b.allocated_amount, 0) - COALESCE(b.committed_amount, 0) - COALESCE(b.used_amount, 0) AS remaining_amount,
                    COALESCE(b.approval_status, "pending") AS approval_status,
                    COALESCE(b.status, "inactive")  AS budget_status
             FROM departments d
             LEFT JOIN budgets b
               ON b.department_id = d.id AND b.financial_year_id = ?
             WHERE d.is_active = 1
             ORDER BY d.name'
        );
        $stmt->execute([$financialYearId]);
        return $stmt->fetchAll();
    }
}
