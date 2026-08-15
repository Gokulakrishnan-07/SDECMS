<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Sanction extends Model
{
    protected string $table = 'sanctions';

    public function paginate(
        int $offset,
        int $limit,
        string $search = '',
        ?int $financialYearId = null,
        ?int $departmentId = null,
        string $status = '', string $maintenanceCategory = '', string $workLocation = ''
    ): array {
        $where  = '1=1';
        $params = [];

        if ($financialYearId !== null) {
            $where .= ' AND s.financial_year_id = ?';
            $params[] = $financialYearId;
        }
        if ($departmentId !== null) {
            $where .= ' AND s.department_id = ?';
            $params[] = $departmentId;
        }
        if ($status !== '') {
            $where .= ' AND s.status = ?';
            $params[] = $status;
        }
        if ($maintenanceCategory !== '') { $where .= ' AND s.maintenance_category = ?'; $params[] = $maintenanceCategory; }
        if (preg_match('/^(department|unit):(\d+)$/', $workLocation, $m)) { $where .= ' AND s.work_location_type = ? AND s.work_location_id = ?'; array_push($params, $m[1], (int) $m[2]); }
        if ($workLocation === 'other') { $where .= ' AND s.work_location_type = "other"'; }
        if ($search !== '') {
            $where .= ' AND (s.sanction_no LIKE ? OR s.purpose LIKE ? OR d.name LIKE ? OR s.maintenance_category LIKE ? OR s.other_work_location LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like, $like, $like);
        }

        $countStmt = $this->db()->prepare(
            "SELECT COUNT(*) AS c FROM sanctions s JOIN departments d ON d.id = s.department_id WHERE $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['c'];

        $stmt = $this->db()->prepare(
            "SELECT s.*, d.name AS department_name, d.code AS department_code, d.workflow_type,
                    fy.label AS financial_year, COALESCE(wd.name, wu.unit_name, CASE WHEN s.work_location_type = 'other' THEN s.other_work_location END) AS work_location_name,
                    (s.amount - s.requisitioned_amount) AS balance_amount,
                    cu.name AS created_by_name, au.name AS approved_by_name, vu.name AS verified_by_name, ru.name AS rejected_by_name
             FROM sanctions s
             JOIN departments d      ON d.id = s.department_id
             JOIN financial_years fy ON fy.id = s.financial_year_id
             LEFT JOIN users cu ON cu.id = s.created_by
             LEFT JOIN users au ON au.id = s.approved_by
             LEFT JOIN users vu ON vu.id = s.verified_by
             LEFT JOIN users ru ON ru.id = s.rejected_by
             LEFT JOIN departments wd ON s.work_location_type = 'department' AND wd.id = s.work_location_id
             LEFT JOIN department_units wu ON s.work_location_type = 'unit' AND wu.id = s.work_location_id
             WHERE $where
             ORDER BY s.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Approved sanctions for a department + FY that still have balance —
     * these are what a Purchase Requisition can be created under.
     */
    public function approvedForDepartment(int $departmentId, int $financialYearId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT s.id, s.sanction_no, s.amount, s.requisitioned_amount, s.purpose,
                    (s.amount - s.requisitioned_amount) AS balance_amount
             FROM sanctions s
             WHERE s.department_id = ? AND s.financial_year_id = ? AND s.status = "approved"
             ORDER BY s.sanction_no'
        );
        $stmt->execute([$departmentId, $financialYearId]);
        return $stmt->fetchAll();
    }

    public function findWithRelations(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT s.*, d.name AS department_name, d.code AS department_code,
                    fy.label AS financial_year, COALESCE(wd.name, wu.unit_name, CASE WHEN s.work_location_type = "other" THEN s.other_work_location END) AS work_location_name,
                    (s.amount - s.requisitioned_amount) AS balance_amount,
                    cu.name AS created_by_name, au.name AS approved_by_name, vu.name AS verified_by_name, ru.name AS rejected_by_name
             FROM sanctions s
             JOIN departments d      ON d.id = s.department_id
             JOIN financial_years fy ON fy.id = s.financial_year_id
            LEFT JOIN users cu ON cu.id = s.created_by
            LEFT JOIN users au ON au.id = s.approved_by
            LEFT JOIN users vu ON vu.id = s.verified_by
            LEFT JOIN users ru ON ru.id = s.rejected_by
            LEFT JOIN departments wd ON s.work_location_type = "department" AND wd.id = s.work_location_id
            LEFT JOIN department_units wu ON s.work_location_type = "unit" AND wu.id = s.work_location_id
             WHERE s.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row !== false) $row['approval_history'] = \App\Services\ApprovalHistoryService::for('sanction', $id);
        return $row === false ? null : $row;
    }

    public function totalAmount(int $financialYearId, ?int $departmentId = null): float
    {
        $where  = "financial_year_id = ? AND status = 'approved'";
        $params = [$financialYearId];
        if ($departmentId !== null) {
            $where .= ' AND department_id = ?';
            $params[] = $departmentId;
        }
        $stmt = $this->db()->prepare("SELECT COALESCE(SUM(amount), 0) AS t FROM sanctions WHERE $where");
        $stmt->execute($params);
        return (float) $stmt->fetch()['t'];
    }

    /**
     * Monthly sanction totals for trend charts.
     */
    public function monthlyTrend(int $financialYearId, ?int $departmentId = null): array
    {
        $where  = 's.financial_year_id = ?';
        $params = [$financialYearId];
        if ($departmentId !== null) {
            $where .= ' AND s.department_id = ?';
            $params[] = $departmentId;
        }
        $stmt = $this->db()->prepare(
            "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS ym, COALESCE(SUM(s.amount), 0) AS total
             FROM sanctions s WHERE $where GROUP BY ym ORDER BY ym"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Attachments linked to a sanction.
     */
    public function attachments(int $sanctionId): array
    {
        return (new SanctionAttachment())->bySanctionId($sanctionId);
    }
}
