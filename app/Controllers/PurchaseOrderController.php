<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Budget;
use App\Models\FinancialYear;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\NumberService;

class PurchaseOrderController extends Controller
{
    private const STATUSES = ['draft', 'issued', 'received', 'paid', 'cancelled'];

    public function page(): void
    {
        $this->view('purchase_orders.index', ['pageTitle' => 'Purchase Orders']);
    }

    /**
     * GET /api/purchase-orders
     */
    public function index(): void
    {
        $p    = $this->pageParams();
        $fy   = Request::query('financial_year_id')
            ? (int) Request::query('financial_year_id')
            : (($a = (new FinancialYear())->active()) ? (int) $a['id'] : null);
        $dept = Request::query('department_id') ? (int) Request::query('department_id') : null;
        if (Auth::role() === 'department_head') {
            $dept = Auth::departmentId();
        }

        $result = (new PurchaseOrder())->paginate(
            $p['offset'], $p['perPage'], $p['search'],
            $fy, $dept, (string) Request::query('status', '')
        );

        Response::paginated($result['items'], $result['total'], $p['page'], $p['perPage']);
    }

    /**
     * GET /api/purchase-orders/{id} — includes items and status history.
     */
    public function show(string $id): void
    {
        $row = (new PurchaseOrder())->findWithRelations((int) $id);
        if ($row === null) {
            Response::error('Purchase order not found.', 404);
        }
        if (!Auth::canAccessDepartment((int) $row['department_id'])) {
            Response::error('Forbidden.', 403);
        }
        Response::json($row);
    }

    /**
     * POST /api/purchase-orders — vendor, items[], GST. Totals are computed
     * server-side; the PO number is auto-generated.
     */
    public function store(): void
    {
        $v = Validator::make(Request::all(), [
            'department_id'       => 'required|integer',
            'financial_year_id'   => 'integer',
            'purchase_request_id' => 'integer',
            'vendor_name'         => 'required|max:200',
            'vendor_gstin'        => 'max:20',
            'vendor_address'      => 'max:500',
            'gst_percent'         => 'numeric|min:0|max:100',
            'invoice_no'          => 'max:50',
            'remarks'             => 'max:500',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data  = $v->validated();
        $items = $this->validItems();

        $fy = (new FinancialYear())->resolve(isset($data['financial_year_id']) ? (int) $data['financial_year_id'] : null);
        if ($fy === null) {
            Response::error('No active financial year configured.', 422);
        }

        // Optional link to an approved purchase request
        if (!empty($data['purchase_request_id'])) {
            $pr = (new PurchaseRequest())->find((int) $data['purchase_request_id']);
            if ($pr === null || $pr['status'] !== 'approved') {
                Response::error('Purchase orders can only be linked to approved purchase requests.', 422);
            }
        }

        $subtotal   = array_sum(array_map(
            static fn ($i) => (float) ($i['quantity'] ?? 1) * (float) ($i['unit_price'] ?? 0),
            $items
        ));
        $gstPercent = (float) ($data['gst_percent'] ?? 0);
        $gstAmount  = round($subtotal * $gstPercent / 100, 2);

        $poNo = null;
        $id = Database::transaction(function ($pdo) use ($data, $fy, $items, $subtotal, $gstPercent, $gstAmount, &$poNo) {
            $poNo = NumberService::nextPoNo((int) $fy['id']);
            $stmt = $pdo->prepare(
                'INSERT INTO purchase_orders
                    (po_no, purchase_request_id, department_id, financial_year_id, vendor_name, vendor_gstin,
                     vendor_address, subtotal, gst_percent, gst_amount, total_amount, invoice_no, remarks, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $poNo,
                !empty($data['purchase_request_id']) ? (int) $data['purchase_request_id'] : null,
                (int) $data['department_id'],
                (int) $fy['id'],
                $data['vendor_name'],
                $data['vendor_gstin'] ?? null,
                $data['vendor_address'] ?? null,
                round($subtotal, 2),
                $gstPercent,
                $gstAmount,
                round($subtotal + $gstAmount, 2),
                $data['invoice_no'] ?? null,
                $data['remarks'] ?? null,
                Auth::id(),
            ]);
            $poId = (int) $pdo->lastInsertId();

            $po = new PurchaseOrder();
            $po->replaceItems($pdo, $poId, $items);
            $po->addHistory($pdo, $poId, 'draft', Auth::id(), 'Purchase order created');
            return $poId;
        });

        AuditService::log('create', 'purchase_orders', $id, "Purchase order $poNo created");
        Response::json(['id' => $id, 'po_no' => $poNo], 201, 'Purchase order created.');
    }

    /**
     * PUT /api/purchase-orders/{id} — drafts only.
     */
    public function update(string $id): void
    {
        $po  = new PurchaseOrder();
        $row = $po->find((int) $id);
        if ($row === null) {
            Response::error('Purchase order not found.', 404);
        }
        if ($row['status'] !== 'draft') {
            Response::error('Only draft purchase orders can be edited.', 422);
        }

        $v = Validator::make(Request::all(), [
            'vendor_name'    => 'required|max:200',
            'vendor_gstin'   => 'max:20',
            'vendor_address' => 'max:500',
            'gst_percent'    => 'numeric|min:0|max:100',
            'invoice_no'     => 'max:50',
            'remarks'        => 'max:500',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data  = $v->validated();
        $items = $this->validItems();

        $subtotal   = array_sum(array_map(
            static fn ($i) => (float) ($i['quantity'] ?? 1) * (float) ($i['unit_price'] ?? 0),
            $items
        ));
        $gstPercent = (float) ($data['gst_percent'] ?? 0);
        $gstAmount  = round($subtotal * $gstPercent / 100, 2);

        Database::transaction(function ($pdo) use ($id, $data, $items, $subtotal, $gstPercent, $gstAmount, $po) {
            $stmt = $pdo->prepare(
                'UPDATE purchase_orders SET vendor_name = ?, vendor_gstin = ?, vendor_address = ?,
                        subtotal = ?, gst_percent = ?, gst_amount = ?, total_amount = ?, invoice_no = ?, remarks = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $data['vendor_name'],
                $data['vendor_gstin'] ?? null,
                $data['vendor_address'] ?? null,
                round($subtotal, 2),
                $gstPercent,
                $gstAmount,
                round($subtotal + $gstAmount, 2),
                $data['invoice_no'] ?? null,
                $data['remarks'] ?? null,
                (int) $id,
            ]);
            $po->replaceItems($pdo, (int) $id, $items);
        });

        AuditService::log('update', 'purchase_orders', (int) $id, "Purchase order {$row['po_no']} updated");
        Response::json(null, 200, 'Purchase order updated.');
    }

    /**
     * POST /api/purchase-orders/{id}/status  body: {status, remarks}
     * Marking a PO as "paid" posts an expense and consumes budget atomically.
     */
    public function setStatus(string $id): void
    {
        $po  = new PurchaseOrder();
        $row = $po->find((int) $id);
        if ($row === null) {
            Response::error('Purchase order not found.', 404);
        }

        $status = (string) Request::input('status', '');
        if (!in_array($status, self::STATUSES, true)) {
            Response::error('Invalid status. Allowed: ' . implode(', ', self::STATUSES), 422);
        }
        if ($row['status'] === $status) {
            Response::error('The purchase order is already ' . $status . '.', 422);
        }
        if (in_array($row['status'], ['paid', 'cancelled'], true)) {
            Response::error('A ' . $row['status'] . ' purchase order can no longer change status.', 422);
        }

        $remarks = substr((string) Request::input('remarks', ''), 0, 500) ?: null;

        // Payment books the expense. In the full workflow (School/College) the
        // funds were already COMMITTED at sanction approval, so payment moves
        // committed → used. In the simple workflow the expense was already
        // booked when the requisition was approved, so payment does NOT deduct
        // again (avoids double deduction).
        $budget    = new Budget();
        $summary   = $budget->summary((int) $row['department_id'], (int) $row['financial_year_id']);
        $isFull    = $summary['workflow_type'] === 'full';
        $total     = (float) $row['total_amount'];

        if ($status === 'paid' && $isFull && $total > $summary['committed'] + 0.001) {
            Response::error(
                'Payment ' . money($total) . ' exceeds the committed funds ' . money($summary['committed']) .
                ' for this department. Check the sanction and requisition amounts.',
                422
            );
        }

        Database::transaction(function ($pdo) use ($id, $row, $status, $remarks, $po, $budget, $isFull, $total) {
            $pdo->prepare('UPDATE purchase_orders SET status = ? WHERE id = ?')->execute([$status, (int) $id]);
            $po->addHistory($pdo, (int) $id, $status, Auth::id(), $remarks);

            if ($status === 'paid' && $isFull) {
                // Move the amount from committed to actual expense.
                $pdo->prepare(
                    'INSERT INTO expenses (department_id, financial_year_id, purchase_order_id, category, description, amount, expense_date, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)'
                )->execute([
                    (int) $row['department_id'],
                    (int) $row['financial_year_id'],
                    (int) $id,
                    'Purchase',
                    'Payment for PO ' . $row['po_no'] . ' — ' . $row['vendor_name'],
                    $total,
                    Auth::id(),
                ]);
                $budget->spendFromCommitment($pdo, (int) $row['department_id'], (int) $row['financial_year_id'], $total);
            }
        });

        AuditService::log('status', 'purchase_orders', (int) $id, "Purchase order {$row['po_no']} → $status");

        if ($status === 'paid' && $isFull) {
            $this->alertIfBudgetLow((int) $row['department_id'], (int) $row['financial_year_id']);
        }

        Response::json(null, 200, 'Purchase order marked as ' . $status . '.');
    }

    /**
     * DELETE /api/purchase-orders/{id} — administrator only, drafts only.
     */
    public function destroy(string $id): void
    {
        $po  = new PurchaseOrder();
        $row = $po->find((int) $id);
        if ($row === null) {
            Response::error('Purchase order not found.', 404);
        }
        if ($row['status'] !== 'draft') {
            Response::error('Only draft purchase orders can be deleted.', 422);
        }

        $po->delete((int) $id);
        AuditService::log('delete', 'purchase_orders', (int) $id, "Purchase order {$row['po_no']} deleted");
        Response::json(null, 200, 'Purchase order deleted.');
    }

    /**
     * GET /purchase-orders/{id}/print — printable PO (browser print-to-PDF).
     */
    public function printView(string $id): void
    {
        $row = (new PurchaseOrder())->findWithRelations((int) $id);
        if ($row === null) {
            http_response_code(404);
            exit('Purchase order not found.');
        }
        if (!Auth::canAccessDepartment((int) $row['department_id'])) {
            http_response_code(403);
            exit('Forbidden.');
        }
        $this->view('purchase_orders.print', ['po' => $row], '');
    }

    /**
     * GET /api/purchase-orders/export?format=csv|excel
     */
    public function export(): void
    {
        $dept = Auth::role() === 'department_head' ? Auth::departmentId() : null;
        $rows = (new PurchaseOrder())->paginate(0, 10000, '', null, $dept)['items'];

        $headers = ['PO No', 'Department', 'Vendor', 'Subtotal', 'GST %', 'GST Amount', 'Total', 'Invoice', 'Status', 'Created At'];
        $data    = array_map(static fn ($r) => [
            $r['po_no'], $r['department_name'], $r['vendor_name'],
            $r['subtotal'], $r['gst_percent'], $r['gst_amount'], $r['total_amount'],
            $r['invoice_no'], $r['status'], $r['created_at'],
        ], $rows);

        if (Request::query('format') === 'excel') {
            Response::excel('purchase-orders.xls', $headers, $data, 'Purchase Orders');
        }
        Response::csv('purchase-orders.csv', $headers, $data);
    }

    // ------------------------------------------------------------------ utils

    /** Validated items[] payload (at least one line with a name). */
    private function validItems(): array
    {
        $items = Request::input('items', []);
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }
        $items = array_values(array_filter(
            is_array($items) ? $items : [],
            static fn ($i) => is_array($i) && trim((string) ($i['item_name'] ?? '')) !== ''
        ));
        if ($items === []) {
            Response::error('At least one line item is required.', 422);
        }
        foreach ($items as $i) {
            if ((float) ($i['quantity'] ?? 0) <= 0 || (float) ($i['unit_price'] ?? -1) < 0) {
                Response::error('Each item needs a positive quantity and a non-negative unit price.', 422);
            }
        }
        return $items;
    }

    private function alertIfBudgetLow(int $departmentId, int $financialYearId): void
    {
        $budget = (new Budget())->findByDeptFy($departmentId, $financialYearId);
        if ($budget === null || (float) $budget['allocated_amount'] <= 0) {
            return;
        }
        $utilization = (float) $budget['used_amount'] / (float) $budget['allocated_amount'] * 100;
        if ($utilization >= 80) {
            NotificationService::notifyRoles(
                ['administrator', 'accounts'],
                'budget_alert',
                'Low Budget Alert',
                sprintf('A department has used %.1f%% of its allocated budget.', $utilization),
                '/budgets'
            );
            NotificationService::notifyDepartmentHeads(
                $departmentId,
                'budget_alert',
                'Low Budget Alert',
                sprintf('Your department has used %.1f%% of its allocated budget.', $utilization),
                '/budgets'
            );
        }
    }
}
