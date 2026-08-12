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
use App\Models\Department;
use App\Models\DepartmentUnit;
use App\Models\FinancialYear;
use App\Models\PurchaseRequest;
use App\Models\Sanction;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\NumberService;
use App\Services\UploadService;

/**
 * Purchase Requisitions are always raised UNDER an approved Sanction and get
 * an automatic subdivision code (COL-2026-001-A, -B …). The budget effect of
 * approval depends on the department workflow:
 *   full   (School/College) — draws the parent sanction's balance only
 *                             (budget already committed at sanction approval).
 *   simple (service depts)  — books the amount as an actual expense
 *                             (used += amount, available drops) immediately.
 */
class PurchaseRequestController extends Controller
{
    public function page(): void
    {
        $this->view('purchase_requests.index', ['pageTitle' => 'Purchase Requisitions']);
    }

    /** GET-only, department-scoped purchase requisition detail screen. */
    public function viewDetail(string $id): void
    {
        $request = (new PurchaseRequest())->findWithRelations((int) $id);
        if ($request === null) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }
        if (!Auth::canAccessDepartment((int) $request['department_id'])) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            return;
        }

        $this->view('purchase_requests.view', [
            'pageTitle' => 'View Purchase Request',
            'request'   => $request,
            'budget'    => (new Budget())->summary((int) $request['department_id'], (int) $request['financial_year_id']),
        ]);
    }

    /**
     * GET /api/purchase-requests
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

        $result = (new PurchaseRequest())->paginate(
            $p['offset'], $p['perPage'], $p['search'],
            $fy, $dept, (string) Request::query('status', '')
        );

        Response::paginated($result['items'], $result['total'], $p['page'], $p['perPage']);
    }

    public function show(string $id): void
    {
        $row = $this->findOrFail((int) $id);
        Response::json($row);
    }

    /**
     * POST /api/purchase-requests — raised under an approved sanction.
     * Supports multipart (attachment) or JSON.
     */
    public function store(): void
    {
        $v = Validator::make(Request::all(), [
            'sanction_id' => 'required|integer',
            'unit_id'     => 'integer',
            'title'       => 'required|max:200',
            'description' => 'max:5000',
            'amount'      => 'required|numeric|min:1',
            'remarks'     => 'max:500',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $sanction = (new Sanction())->find((int) $data['sanction_id']);
        if ($sanction === null || $sanction['status'] !== 'approved') {
            Response::error('A requisition can only be raised under an approved sanction.', 422);
        }
        if (!Auth::canAccessDepartment((int) $sanction['department_id'])) {
            Response::error('You cannot raise requisitions for another department.', 403);
        }

        $deptId = (int) $sanction['department_id'];
        $fyId   = (int) $sanction['financial_year_id'];

        $unitId = $this->resolveUnit($deptId, $data['unit_id'] ?? null);

        // Soft guard on creation: never exceed the remaining sanction balance.
        $balance = (float) $sanction['amount'] - (float) $sanction['requisitioned_amount'];
        if ((float) $data['amount'] > $balance + 0.001) {
            Response::error(
                'The amount ' . money((float) $data['amount']) .
                ' exceeds the remaining sanction balance of ' . money($balance) . '.',
                422
            );
        }

        [$attachmentPath, $attachmentName] = $this->handleAttachment();

        $result = Database::transaction(function ($pdo) use ($data, $sanction, $deptId, $fyId, $unitId, $attachmentPath, $attachmentName) {
            [$code, $prNo] = NumberService::nextSubdivision($pdo, (int) $sanction['id']);

            $stmt = $pdo->prepare(
                'INSERT INTO purchase_requests
                    (pr_no, sanction_id, subdivision_code, unit_id, department_id, financial_year_id,
                     title, description, amount, remarks, attachment_path, attachment_name, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $prNo, (int) $sanction['id'], $code, $unitId, $deptId, $fyId,
                $data['title'], $data['description'] ?? null, (float) $data['amount'],
                $data['remarks'] ?? null, $attachmentPath, $attachmentName, Auth::id(),
            ]);
            return ['id' => (int) $pdo->lastInsertId(), 'pr_no' => $prNo];
        });

        AuditService::log('create', 'purchase_requests', $result['id'], "Purchase requisition {$result['pr_no']} created");
        Response::json($result, 201, 'Purchase requisition saved as draft.');
    }

    /**
     * PUT /api/purchase-requests/{id} — drafts/rejected only, owner or admin.
     * Sanction, subdivision code and department are fixed after creation.
     */
    public function update(string $id): void
    {
        $row = $this->findOrFail((int) $id);
        if (!in_array($row['status'], ['draft', 'rejected'], true)) {
            Response::error('Only draft or rejected requisitions can be edited.', 422);
        }
        $this->assertOwnership($row);

        $v = Validator::make(Request::all(), [
            'unit_id'     => 'integer',
            'title'       => 'required|max:200',
            'description' => 'max:5000',
            'amount'      => 'required|numeric|min:1',
            'remarks'     => 'max:500',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $unitId = $this->resolveUnit((int) $row['department_id'], $data['unit_id'] ?? $row['unit_id']);

        // Requisition amount cannot exceed the sanction balance excluding itself.
        $sanction = (new Sanction())->find((int) $row['sanction_id']);
        if ($sanction !== null) {
            $balance = (float) $sanction['amount'] - (float) $sanction['requisitioned_amount'];
            if ((float) $data['amount'] > $balance + 0.001) {
                Response::error('The amount exceeds the remaining sanction balance of ' . money($balance) . '.', 422);
            }
        }

        [$attachmentPath, $attachmentName] = $this->handleAttachment();

        $update = [
            'unit_id'     => $unitId,
            'title'       => $data['title'],
            'description' => $data['description'] ?? $row['description'],
            'amount'      => (float) $data['amount'],
            'remarks'     => $data['remarks'] ?? $row['remarks'],
            'status'      => 'draft',
        ];
        if ($attachmentPath !== null) {
            $update['attachment_path'] = $attachmentPath;
            $update['attachment_name'] = $attachmentName;
        }

        (new PurchaseRequest())->update((int) $id, $update);
        AuditService::log('update', 'purchase_requests', (int) $id, "Purchase requisition {$row['pr_no']} updated");
        Response::json(null, 200, 'Purchase requisition updated.');
    }

    /**
     * POST /api/purchase-requests/{id}/submit — sends a draft for approval.
     */
    public function submit(string $id): void
    {
        $row = $this->findOrFail((int) $id);
        if ($row['status'] !== 'draft') {
            Response::error('Only draft requisitions can be submitted.', 422);
        }
        $this->assertOwnership($row);

        $error = $this->budgetGuard($row);
        if ($error !== null) {
            NotificationService::notifyRoles(
                ['administrator'],
                'budget_alert',
                'Budget Limit Exceeded',
                "Requisition {$row['pr_no']} ({$row['title']}) could not be submitted: $error",
                '/purchase-requests'
            );
            Response::error('Submission blocked: ' . $error . ' The administrator has been notified.', 422);
        }

        (new PurchaseRequest())->update((int) $id, ['status' => 'submitted']);
        AuditService::log('submit', 'purchase_requests', (int) $id, "Purchase requisition {$row['pr_no']} submitted");
        NotificationService::notifyRoles(
            ['administrator', 'principal'],
            'pr_new',
            'New Purchase Requisition',
            "{$row['pr_no']} ({$row['title']}) awaits approval.",
            '/purchase-requests'
        );

        Response::json(null, 200, 'Purchase requisition submitted for approval.');
    }

    /**
     * POST /api/purchase-requests/{id}/approve — runs the budget engine.
     */
    public function approve(string $id): void
    {
        $row = $this->findOrFail((int) $id);
        if ($row['status'] !== 'submitted') {
            Response::error('Only submitted requisitions can be approved.', 422);
        }

        $error = $this->budgetGuard($row);
        if ($error !== null) {
            Response::error('Approval blocked: ' . $error, 422);
        }

        $budget   = new Budget();
        $summary  = $budget->summary((int) $row['department_id'], (int) $row['financial_year_id']);
        $isSimple = $summary['workflow_type'] !== 'full';

        Database::transaction(function ($pdo) use ($row, $id, $budget, $isSimple) {
            // Draw the parent sanction's balance (both workflows).
            $pdo->prepare('UPDATE sanctions SET requisitioned_amount = requisitioned_amount + ? WHERE id = ?')
                ->execute([(float) $row['amount'], (int) $row['sanction_id']]);

            $pdo->prepare(
                'UPDATE purchase_requests SET status = "approved", approved_by = ?, approved_at = ? WHERE id = ?'
            )->execute([Auth::id(), date('Y-m-d H:i:s'), (int) $id]);

            // Simple workflow books the expense immediately.
            if ($isSimple) {
                $budget->addUsage($pdo, (int) $row['department_id'], (int) $row['financial_year_id'], (float) $row['amount']);
                $pdo->prepare(
                    'INSERT INTO expenses (department_id, financial_year_id, category, description, amount, expense_date, created_by)
                     VALUES (?, ?, ?, ?, ?, CURDATE(), ?)'
                )->execute([
                    (int) $row['department_id'], (int) $row['financial_year_id'], 'Requisition',
                    "Requisition {$row['pr_no']} — {$row['title']}", (float) $row['amount'], Auth::id(),
                ]);
            }
        });

        AuditService::log('approve', 'purchase_requests', (int) $id, "Purchase requisition {$row['pr_no']} approved");
        $this->notifyDecision($row, 'approved');

        $msg = $isSimple
            ? 'Requisition approved and recorded as an expense.'
            : 'Requisition approved. A purchase order can now be raised against it.';
        Response::json(null, 200, $msg);
    }

    /**
     * POST /api/purchase-requests/{id}/reject  body: {reason}
     */
    public function reject(string $id): void
    {
        $row = $this->findOrFail((int) $id);
        if ($row['status'] !== 'submitted') {
            Response::error('Only submitted requisitions can be rejected.', 422);
        }

        (new PurchaseRequest())->update((int) $id, [
            'status'        => 'rejected',
            'approved_by'   => Auth::id(),
            'approved_at'   => date('Y-m-d H:i:s'),
            'reject_reason' => substr((string) Request::input('reason', ''), 0, 500) ?: null,
        ]);
        AuditService::log('reject', 'purchase_requests', (int) $id, "Purchase requisition {$row['pr_no']} rejected");
        $this->notifyDecision($row, 'rejected');
        Response::json(null, 200, 'Purchase requisition rejected.');
    }

    /**
     * DELETE /api/purchase-requests/{id} — draft/rejected only (approved
     * requisitions have consumed sanction balance / budget).
     */
    public function destroy(string $id): void
    {
        $row = $this->findOrFail((int) $id);
        if (!in_array($row['status'], ['draft', 'rejected'], true)) {
            Response::error('Only draft or rejected requisitions can be deleted.', 422);
        }
        (new PurchaseRequest())->delete((int) $id);
        AuditService::log('delete', 'purchase_requests', (int) $id, "Purchase requisition {$row['pr_no']} deleted");
        Response::json(null, 200, 'Purchase requisition deleted.');
    }

    /**
     * GET /api/purchase-requests/export?format=csv|excel
     */
    public function export(): void
    {
        $dept = Auth::role() === 'department_head' ? Auth::departmentId() : null;
        $rows = (new PurchaseRequest())->paginate(0, 10000, '', null, $dept)['items'];

        $headers = ['Requisition No', 'Sanction', 'Department', 'Unit', 'Title', 'Amount', 'Status', 'Created By', 'Created At'];
        $data    = array_map(static fn ($r) => [
            $r['pr_no'], $r['parent_sanction_no'] ?? '', $r['department_name'], $r['unit_name'] ?? '',
            $r['title'], $r['amount'], $r['status'], $r['created_by_name'], $r['created_at'],
        ], $rows);

        if (Request::query('format') === 'excel') {
            Response::excel('purchase-requisitions.xls', $headers, $data, 'Purchase Requisitions');
        }
        Response::csv('purchase-requisitions.csv', $headers, $data);
    }

    // ------------------------------------------------------------------ utils

    /**
     * Returns an error string when the requisition would breach a limit, or
     * null when it is safe to submit/approve.
     */
    private function budgetGuard(array $row): ?string
    {
        $sanction = (new Sanction())->find((int) $row['sanction_id']);
        if ($sanction === null || $sanction['status'] !== 'approved') {
            return 'the parent sanction is no longer approved.';
        }
        $balance = (float) $sanction['amount'] - (float) $sanction['requisitioned_amount'];
        if ((float) $row['amount'] > $balance + 0.001) {
            return 'the requested amount ' . money((float) $row['amount']) .
                ' exceeds the remaining sanction balance of ' . money($balance) . '.';
        }

        // Simple workflow spends available budget directly — guard it too.
        $summary = (new Budget())->summary((int) $row['department_id'], (int) $row['financial_year_id']);
        if ($summary['workflow_type'] !== 'full' && (float) $row['amount'] > $summary['available'] + 0.001) {
            return 'the requested amount ' . money((float) $row['amount']) .
                ' exceeds the available department budget of ' . money($summary['available']) . '.';
        }
        return null;
    }

    private function resolveUnit(int $departmentId, mixed $unitId): ?int
    {
        $dept = (new Department())->find($departmentId);
        if ($dept === null || (int) $dept['has_units'] !== 1) {
            return null; // department has no units — ignore any supplied value
        }
        if (empty($unitId)) {
            Response::error('Please select a ' . $dept['name'] . ' unit for this requisition.', 422);
        }
        if (!(new DepartmentUnit())->belongsToDepartment((int) $unitId, $departmentId)) {
            Response::error('The selected unit does not belong to this department.', 422);
        }
        return (int) $unitId;
    }

    private function notifyDecision(array $row, string $decision): void
    {
        $targets = $row['created_by'] !== null ? [(int) $row['created_by']] : [];
        NotificationService::notifyUsers(
            $targets,
            'pr_' . $decision,
            'Requisition ' . ucfirst($decision),
            "{$row['pr_no']} ({$row['title']}) has been $decision.",
            '/purchase-requests'
        );
    }

    private function findOrFail(int $id): array
    {
        $row = (new PurchaseRequest())->find($id);
        if ($row === null) {
            Response::error('Purchase requisition not found.', 404);
        }
        if (!Auth::canAccessDepartment((int) $row['department_id'])) {
            Response::error('You cannot access requisitions of another department.', 403);
        }
        return $row;
    }

    private function assertOwnership(array $row): void
    {
        if (Auth::role() === 'department_head' && (int) $row['created_by'] !== Auth::id()) {
            Response::error('You can only modify your own requisitions.', 403);
        }
    }

    /** @return array{0:?string, 1:?string} stored path and original filename */
    private function handleAttachment(): array
    {
        $file = Request::file('attachment');
        if ($file === null) {
            return [null, null];
        }
        try {
            return UploadService::store($file, 'purchase-requests');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }
    }
}
