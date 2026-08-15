<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PurchaseRequest extends Model
{
    protected string $table = 'purchase_requests';

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
            $where .= ' AND pr.financial_year_id = ?';
            $params[] = $financialYearId;
        }
        if ($departmentId !== null) {
            $where .= ' AND pr.department_id = ?';
            $params[] = $departmentId;
        }
        if ($status !== '') {
            $where .= ' AND pr.status = ?';
            $params[] = $status;
        }
        if ($maintenanceCategory !== '') { $where .= ' AND pr.maintenance_category = ?'; $params[] = $maintenanceCategory; }
        if (preg_match('/^(department|unit):(\d+)$/', $workLocation, $m)) { $where .= ' AND pr.work_location_type = ? AND pr.work_location_id = ?'; array_push($params, $m[1], (int) $m[2]); }
        if ($workLocation === 'other') { $where .= ' AND pr.work_location_type = "other"'; }
        if ($search !== '') {
            $where .= ' AND (pr.pr_no LIKE ? OR pr.title LIKE ? OR d.name LIKE ? OR pr.maintenance_category LIKE ? OR pr.other_work_location LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like, $like, $like);
        }

        $countStmt = $this->db()->prepare(
            "SELECT COUNT(*) AS c FROM purchase_requests pr JOIN departments d ON d.id = pr.department_id WHERE $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['c'];

        $stmt = $this->db()->prepare(
            "SELECT pr.*, d.name AS department_name, d.code AS department_code, d.workflow_type,
                    fy.label AS financial_year,
                    s.sanction_no AS parent_sanction_no,
                    du.unit_name, du.unit_code, COALESCE(wd.name, wu.unit_name, CASE WHEN pr.work_location_type = 'other' THEN pr.other_work_location END) AS work_location_name,
                    cu.name AS created_by_name, au.name AS approved_by_name, ru.name AS rejected_by_name
             FROM purchase_requests pr
             JOIN departments d      ON d.id = pr.department_id
             JOIN financial_years fy ON fy.id = pr.financial_year_id
             LEFT JOIN sanctions s        ON s.id = pr.sanction_id
             LEFT JOIN department_units du ON du.id = pr.unit_id
             LEFT JOIN departments wd ON pr.work_location_type = \"department\" AND wd.id = pr.work_location_id
             LEFT JOIN department_units wu ON pr.work_location_type = \"unit\" AND wu.id = pr.work_location_id
             LEFT JOIN users cu ON cu.id = pr.created_by
             LEFT JOIN users au ON au.id = pr.approved_by
             LEFT JOIN users ru ON ru.id = pr.rejected_by
             WHERE $where
             ORDER BY pr.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function countByStatus(string $status, ?int $financialYearId = null, ?int $departmentId = null): int
    {
        $where  = 'status = ?';
        $params = [$status];
        if ($financialYearId !== null) {
            $where .= ' AND financial_year_id = ?';
            $params[] = $financialYearId;
        }
        if ($departmentId !== null) {
            $where .= ' AND department_id = ?';
            $params[] = $departmentId;
        }
        return $this->count($where, $params);
    }

    /** One requisition with the same display relations used by its listing. */
    public function findWithRelations(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT pr.*, d.name AS department_name, d.code AS department_code, d.workflow_type,
                    fy.label AS financial_year, s.sanction_no AS parent_sanction_no,
                    s.amount AS sanction_amount, s.requisitioned_amount, du.unit_name, du.unit_code, COALESCE(wd.name, wu.unit_name, CASE WHEN pr.work_location_type = "other" THEN pr.other_work_location END) AS work_location_name,
                    cu.name AS created_by_name, au.name AS approved_by_name, ru.name AS rejected_by_name
             FROM purchase_requests pr
             JOIN departments d ON d.id = pr.department_id
             JOIN financial_years fy ON fy.id = pr.financial_year_id
             LEFT JOIN sanctions s ON s.id = pr.sanction_id
             LEFT JOIN department_units du ON du.id = pr.unit_id
             LEFT JOIN departments wd ON pr.work_location_type = "department" AND wd.id = pr.work_location_id
             LEFT JOIN department_units wu ON pr.work_location_type = "unit" AND wu.id = pr.work_location_id
             LEFT JOIN users cu ON cu.id = pr.created_by
             LEFT JOIN users au ON au.id = pr.approved_by
             LEFT JOIN users ru ON ru.id = pr.rejected_by
             WHERE pr.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row !== false) $row['approval_history'] = \App\Services\ApprovalHistoryService::for('purchase_request', $id);
        return $row === false ? null : $row;
    }
}
