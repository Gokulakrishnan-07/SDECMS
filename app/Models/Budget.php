<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Budget extends Model
{
    protected string $table = 'budgets';

    public function findByDeptFy(int $departmentId, int $financialYearId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM budgets WHERE department_id = ? AND financial_year_id = ? LIMIT 1'
        );
        $stmt->execute([$departmentId, $financialYearId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Paginated budget list with joins and filters.
     * $departmentId restricts visibility (department heads).
     */
    public function paginate(
        int $offset,
        int $limit,
        string $search = '',
        ?int $financialYearId = null,
        ?int $departmentId = null,
        string $approvalStatus = ''
    ): array {
        $where  = '1=1';
        $params = [];

        if ($financialYearId !== null) {
            $where .= ' AND b.financial_year_id = ?';
            $params[] = $financialYearId;
        }
        if ($departmentId !== null) {
            $where .= ' AND b.department_id = ?';
            $params[] = $departmentId;
        }
        if ($approvalStatus !== '') {
            $where .= ' AND b.approval_status = ?';
            $params[] = $approvalStatus;
        }
        if ($search !== '') {
            $where .= ' AND (d.name LIKE ? OR d.code LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like);
        }

        $countStmt = $this->db()->prepare(
            "SELECT COUNT(*) AS c FROM budgets b JOIN departments d ON d.id = b.department_id WHERE $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['c'];

        $stmt = $this->db()->prepare(
            "SELECT b.*, d.name AS department_name, d.code AS department_code, d.icon AS department_icon,
                    d.workflow_type,
                    fy.label AS financial_year,
                    (b.allocated_amount - b.committed_amount - b.used_amount) AS remaining_amount,
                    (b.committed_amount + b.used_amount) AS consumed_amount,
                    CASE WHEN b.allocated_amount > 0
                         THEN ROUND((b.committed_amount + b.used_amount) / b.allocated_amount * 100, 1)
                         ELSE 0 END AS utilization
             FROM budgets b
             JOIN departments d      ON d.id = b.department_id
             JOIN financial_years fy ON fy.id = b.financial_year_id
             WHERE $where
             ORDER BY d.name
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Live budget summary for one department + financial year — powers the
     * real-time budget panel shown inside sanction/requisition forms.
     */
    public function summary(int $departmentId, int $financialYearId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT b.allocated_amount, b.committed_amount, b.used_amount, b.approval_status,
                    d.workflow_type
             FROM budgets b JOIN departments d ON d.id = b.department_id
             WHERE b.department_id = ? AND b.financial_year_id = ? LIMIT 1'
        );
        $stmt->execute([$departmentId, $financialYearId]);
        $row = $stmt->fetch();

        $allocated = $row ? (float) $row['allocated_amount'] : 0.0;
        $committed = $row ? (float) $row['committed_amount'] : 0.0;
        $used      = $row ? (float) $row['used_amount'] : 0.0;

        return [
            'has_budget'      => $row !== false && $row !== null,
            'approval_status' => $row['approval_status'] ?? null,
            'workflow_type'   => $row['workflow_type'] ?? 'simple',
            'allocated'       => $allocated,
            'committed'       => $committed,
            'used'            => $used,
            'available'       => $allocated - $committed - $used,
        ];
    }

    /**
     * Aggregate totals for a financial year (optionally one department).
     */
    public function totals(int $financialYearId, ?int $departmentId = null): array
    {
        $where  = 'financial_year_id = ?';
        $params = [$financialYearId];
        if ($departmentId !== null) {
            $where .= ' AND department_id = ?';
            $params[] = $departmentId;
        }

        $stmt = $this->db()->prepare(
            "SELECT COALESCE(SUM(allocated_amount), 0) AS total_allocated,
                    COALESCE(SUM(committed_amount), 0)  AS total_committed,
                    COALESCE(SUM(used_amount), 0)      AS total_used
             FROM budgets WHERE $where"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        $allocated = (float) $row['total_allocated'];
        $committed = (float) $row['total_committed'];
        $used      = (float) $row['total_used'];
        $consumed  = $committed + $used;

        return [
            'allocated'   => $allocated,
            'committed'   => $committed,
            'used'        => $used,
            'consumed'    => $consumed,
            'remaining'   => $allocated - $consumed,
            'utilization' => $allocated > 0 ? round($consumed / $allocated * 100, 1) : 0.0,
        ];
    }

    /**
     * Replace the optional per-period allocation breakdown for a budget.
     *
     * @param array<int, array{period_id:int, allocated_amount:float}> $periods
     */
    public function replacePeriods(int $budgetId, array $periods): void
    {
        \App\Core\Database::transaction(function ($pdo) use ($budgetId, $periods) {
            $pdo->prepare('DELETE FROM budget_periods WHERE budget_id = ?')->execute([$budgetId]);
            $ins = $pdo->prepare(
                'INSERT INTO budget_periods (budget_id, period_id, allocated_amount) VALUES (?, ?, ?)'
            );
            foreach ($periods as $p) {
                $pid = (int) ($p['period_id'] ?? 0);
                $amt = (float) ($p['allocated_amount'] ?? 0);
                if ($pid > 0 && $amt > 0) {
                    $ins->execute([$budgetId, $pid, $amt]);
                }
            }
        });
    }

    public function periodsFor(int $budgetId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT bp.period_id, bp.allocated_amount, p.name
             FROM budget_periods bp JOIN financial_year_periods p ON p.id = bp.period_id
             WHERE bp.budget_id = ? ORDER BY p.sort_order, p.id'
        );
        $stmt->execute([$budgetId]);
        return $stmt->fetchAll();
    }

    /**
     * Atomically add to a budget's used amount (simple-workflow expense).
     */
    public function addUsage(\PDO $pdo, int $departmentId, int $financialYearId, float $amount): void
    {
        $pdo->prepare(
            'UPDATE budgets SET used_amount = used_amount + ?
             WHERE department_id = ? AND financial_year_id = ?'
        )->execute([$amount, $departmentId, $financialYearId]);
    }

    /**
     * Reserve budget when a sanction is approved (full workflow):
     * committed += amount (available drops). Call inside a transaction.
     */
    public function commit(\PDO $pdo, int $departmentId, int $financialYearId, float $amount): void
    {
        $pdo->prepare(
            'UPDATE budgets SET committed_amount = committed_amount + ?
             WHERE department_id = ? AND financial_year_id = ?'
        )->execute([$amount, $departmentId, $financialYearId]);
    }

    /**
     * Release a reservation without spending (e.g. sanction rejected/closed).
     */
    public function releaseCommitment(\PDO $pdo, int $departmentId, int $financialYearId, float $amount): void
    {
        $pdo->prepare(
            'UPDATE budgets SET committed_amount = GREATEST(committed_amount - ?, 0)
             WHERE department_id = ? AND financial_year_id = ?'
        )->execute([$amount, $departmentId, $financialYearId]);
    }

    /**
     * Convert a reservation into actual expense when a PO is paid (full
     * workflow): committed -= amount, used += amount. Available is unchanged.
     */
    public function spendFromCommitment(\PDO $pdo, int $departmentId, int $financialYearId, float $amount): void
    {
        $pdo->prepare(
            'UPDATE budgets
             SET committed_amount = GREATEST(committed_amount - ?, 0),
                 used_amount      = used_amount + ?
             WHERE department_id = ? AND financial_year_id = ?'
        )->execute([$amount, $amount, $departmentId, $financialYearId]);
    }

    /**
     * Per-department budget health for the dashboard widget (active FY).
     * consumed = committed + used; available = allocated - consumed.
     */
    public function healthByDepartment(int $financialYearId, ?int $departmentId = null): array
    {
        $where  = 'b.financial_year_id = ?';
        $params = [$financialYearId];
        if ($departmentId !== null) {
            $where .= ' AND b.department_id = ?';
            $params[] = $departmentId;
        }

        $stmt = $this->db()->prepare(
            "SELECT d.name AS department, d.code, d.workflow_type,
                    b.allocated_amount, b.committed_amount, b.used_amount,
                    (b.committed_amount + b.used_amount) AS consumed_amount,
                    (b.allocated_amount - b.committed_amount - b.used_amount) AS remaining_amount,
                    CASE WHEN b.allocated_amount > 0
                         THEN ROUND((b.committed_amount + b.used_amount) / b.allocated_amount * 100, 1)
                         ELSE 0 END AS usage_percent
             FROM budgets b
             JOIN departments d ON d.id = b.department_id
             WHERE $where AND b.allocated_amount > 0
             ORDER BY usage_percent DESC, d.name"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['health'] = self::healthLabel(
                (float) $r['usage_percent'],
                (float) $r['used_amount'] + (float) $r['committed_amount'],
                (float) $r['allocated_amount']
            );
        }
        return $rows;
    }

    /**
     * Budget health classification per spec.
     */
    public static function healthLabel(float $usagePercent, float $consumed, float $allocated): string
    {
        if ($consumed > $allocated) {
            return 'exceeded';
        }
        if ($usagePercent > 90) {
            return 'critical';
        }
        if ($usagePercent >= 75) {
            return 'warning';
        }
        return 'healthy';
    }

    /**
     * Departments whose consumption crossed the alert threshold.
     */
    public function lowBudgetDepartments(int $financialYearId, float $thresholdPercent = 80): array
    {
        $stmt = $this->db()->prepare(
            'SELECT d.name, d.code, b.allocated_amount,
                    (b.committed_amount + b.used_amount) AS used_amount,
                    ROUND((b.committed_amount + b.used_amount) / b.allocated_amount * 100, 1) AS utilization
             FROM budgets b
             JOIN departments d ON d.id = b.department_id
             WHERE b.financial_year_id = ? AND b.allocated_amount > 0
               AND ((b.committed_amount + b.used_amount) / b.allocated_amount * 100) >= ?
             ORDER BY utilization DESC'
        );
        $stmt->execute([$financialYearId, $thresholdPercent]);
        return $stmt->fetchAll();
    }
}
