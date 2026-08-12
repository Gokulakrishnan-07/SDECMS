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
        string $status = ''
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
        if ($search !== '') {
            $where .= ' AND (pr.pr_no LIKE ? OR pr.title LIKE ? OR d.name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like);
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
                    du.unit_name, du.unit_code,
                    cu.name AS created_by_name, au.name AS approved_by_name
             FROM purchase_requests pr
             JOIN departments d      ON d.id = pr.department_id
             JOIN financial_years fy ON fy.id = pr.financial_year_id
             LEFT JOIN sanctions s        ON s.id = pr.sanction_id
             LEFT JOIN department_units du ON du.id = pr.unit_id
             LEFT JOIN users cu ON cu.id = pr.created_by
             LEFT JOIN users au ON au.id = pr.approved_by
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
                    s.amount AS sanction_amount, s.requisitioned_amount, du.unit_name, du.unit_code,
                    cu.name AS created_by_name, au.name AS approved_by_name
             FROM purchase_requests pr
             JOIN departments d ON d.id = pr.department_id
             JOIN financial_years fy ON fy.id = pr.financial_year_id
             LEFT JOIN sanctions s ON s.id = pr.sanction_id
             LEFT JOIN department_units du ON du.id = pr.unit_id
             LEFT JOIN users cu ON cu.id = pr.created_by
             LEFT JOIN users au ON au.id = pr.approved_by
             WHERE pr.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
