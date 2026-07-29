<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PurchaseOrder extends Model
{
    protected string $table = 'purchase_orders';

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
            $where .= ' AND po.financial_year_id = ?';
            $params[] = $financialYearId;
        }
        if ($departmentId !== null) {
            $where .= ' AND po.department_id = ?';
            $params[] = $departmentId;
        }
        if ($status !== '') {
            $where .= ' AND po.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (po.po_no LIKE ? OR po.vendor_name LIKE ? OR po.invoice_no LIKE ? OR d.name LIKE ?)';
            $like = "%$search%";
            array_push($params, $like, $like, $like, $like);
        }

        $countStmt = $this->db()->prepare(
            "SELECT COUNT(*) AS c FROM purchase_orders po JOIN departments d ON d.id = po.department_id WHERE $where"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['c'];

        $stmt = $this->db()->prepare(
            "SELECT po.*, d.name AS department_name, fy.label AS financial_year,
                    pr.pr_no, cu.name AS created_by_name
             FROM purchase_orders po
             JOIN departments d      ON d.id = po.department_id
             JOIN financial_years fy ON fy.id = po.financial_year_id
             LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
             LEFT JOIN users cu ON cu.id = po.created_by
             WHERE $where
             ORDER BY po.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function findWithRelations(int $id): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT po.*, d.name AS department_name, fy.label AS financial_year,
                    pr.pr_no, cu.name AS created_by_name
             FROM purchase_orders po
             JOIN departments d      ON d.id = po.department_id
             JOIN financial_years fy ON fy.id = po.financial_year_id
             LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
             LEFT JOIN users cu ON cu.id = po.created_by
             WHERE po.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['items']   = $this->items($id);
        $row['history'] = $this->history($id);
        return $row;
    }

    public function items(int $poId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM po_items WHERE purchase_order_id = ? ORDER BY id');
        $stmt->execute([$poId]);
        return $stmt->fetchAll();
    }

    public function history(int $poId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT h.*, u.name AS changed_by_name
             FROM po_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.purchase_order_id = ?
             ORDER BY h.created_at, h.id'
        );
        $stmt->execute([$poId]);
        return $stmt->fetchAll();
    }

    public function replaceItems(\PDO $pdo, int $poId, array $items): void
    {
        $pdo->prepare('DELETE FROM po_items WHERE purchase_order_id = ?')->execute([$poId]);
        $ins = $pdo->prepare(
            'INSERT INTO po_items (purchase_order_id, item_name, description, quantity, unit_price, amount)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $qty   = (float) ($item['quantity'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $ins->execute([
                $poId,
                (string) ($item['item_name'] ?? ''),
                $item['description'] ?? null,
                $qty,
                $price,
                round($qty * $price, 2),
            ]);
        }
    }

    public function addHistory(\PDO $pdo, int $poId, string $status, ?int $userId, ?string $remarks = null): void
    {
        $pdo->prepare(
            'INSERT INTO po_status_history (purchase_order_id, status, changed_by, remarks) VALUES (?, ?, ?, ?)'
        )->execute([$poId, $status, $userId, $remarks]);
    }

    public function countAll(?int $financialYearId = null, ?int $departmentId = null): int
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
        return $this->count($where, $params);
    }
}
