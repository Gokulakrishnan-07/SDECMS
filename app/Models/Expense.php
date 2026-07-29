<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Expense extends Model
{
    protected string $table = 'expenses';

    private function scope(?int $financialYearId, ?int $departmentId): array
    {
        $where  = '1=1';
        $params = [];
        if ($financialYearId !== null) {
            $where .= ' AND financial_year_id = ?';
            $params[] = $financialYearId;
        }
        if ($departmentId !== null) {
            $where .= ' AND department_id = ?';
            $params[] = $departmentId;
        }
        return [$where, $params];
    }

    /**
     * Total spent in the current calendar month.
     */
    public function currentMonthTotal(?int $financialYearId = null, ?int $departmentId = null): float
    {
        [$where, $params] = $this->scope($financialYearId, $departmentId);
        $stmt = $this->db()->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS t FROM expenses
             WHERE $where AND DATE_FORMAT(expense_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
        );
        $stmt->execute($params);
        return (float) $stmt->fetch()['t'];
    }

    /**
     * Month-by-month totals: [{ym: '2025-06', total: 123}, ...]
     */
    public function monthlyTotals(?int $financialYearId = null, ?int $departmentId = null): array
    {
        [$where, $params] = $this->scope($financialYearId, $departmentId);
        $stmt = $this->db()->prepare(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
             FROM expenses WHERE $where GROUP BY ym ORDER BY ym"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Indian FY quarters: Q1 Apr–Jun, Q2 Jul–Sep, Q3 Oct–Dec, Q4 Jan–Mar.
     */
    public function quarterlyTotals(?int $financialYearId = null, ?int $departmentId = null): array
    {
        [$where, $params] = $this->scope($financialYearId, $departmentId);
        $stmt = $this->db()->prepare(
            "SELECT CASE
                        WHEN MONTH(expense_date) BETWEEN 4 AND 6  THEN 'Q1'
                        WHEN MONTH(expense_date) BETWEEN 7 AND 9  THEN 'Q2'
                        WHEN MONTH(expense_date) BETWEEN 10 AND 12 THEN 'Q3'
                        ELSE 'Q4'
                    END AS quarter,
                    COALESCE(SUM(amount), 0) AS total
             FROM expenses WHERE $where
             GROUP BY quarter
             ORDER BY FIELD(quarter, 'Q1','Q2','Q3','Q4')"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Totals per financial year (yearly spending chart).
     */
    public function yearlyTotals(?int $departmentId = null): array
    {
        $where  = '1=1';
        $params = [];
        if ($departmentId !== null) {
            $where .= ' AND e.department_id = ?';
            $params[] = $departmentId;
        }
        $stmt = $this->db()->prepare(
            "SELECT fy.label, COALESCE(SUM(e.amount), 0) AS total
             FROM expenses e
             JOIN financial_years fy ON fy.id = e.financial_year_id
             WHERE $where
             GROUP BY fy.id, fy.label
             ORDER BY fy.start_date"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function byDepartment(int $financialYearId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT d.name, COALESCE(SUM(e.amount), 0) AS total
             FROM expenses e
             JOIN departments d ON d.id = e.department_id
             WHERE e.financial_year_id = ?
             GROUP BY d.id, d.name
             ORDER BY total DESC'
        );
        $stmt->execute([$financialYearId]);
        return $stmt->fetchAll();
    }

    public function byCategory(?int $financialYearId = null, ?int $departmentId = null): array
    {
        [$where, $params] = $this->scope($financialYearId, $departmentId);
        $stmt = $this->db()->prepare(
            "SELECT category, COALESCE(SUM(amount), 0) AS total
             FROM expenses WHERE $where GROUP BY category ORDER BY total DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Detailed expense rows for report exports.
     */
    public function listForReport(?int $financialYearId, ?int $departmentId, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->scope($financialYearId, $departmentId);
        $where = str_replace(['financial_year_id', 'department_id'], ['e.financial_year_id', 'e.department_id'], $where);
        if ($from !== null) {
            $where .= ' AND e.expense_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND e.expense_date <= ?';
            $params[] = $to;
        }
        $stmt = $this->db()->prepare(
            "SELECT e.expense_date, d.name AS department, e.category, e.description, e.amount,
                    po.po_no
             FROM expenses e
             JOIN departments d ON d.id = e.department_id
             LEFT JOIN purchase_orders po ON po.id = e.purchase_order_id
             WHERE $where
             ORDER BY e.expense_date DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
