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
use App\Models\Sanction;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\NumberService;

class SanctionController extends Controller
{
    public function page(): void
    {
        $this->view('sanctions.index', ['pageTitle' => 'Sanction Amount']);
    }

    /**
     * GET /api/sanctions
     */
    public function index(): void
    {
        $p  = $this->pageParams();
        $fy = Request::query('financial_year_id')
            ? (int) Request::query('financial_year_id')
            : (($a = (new FinancialYear())->active()) ? (int) $a['id'] : null);

        $dept = Request::query('department_id') ? (int) Request::query('department_id') : null;
        if (Auth::role() === 'department_head') {
            $dept = Auth::departmentId();
        }

        $result = (new Sanction())->paginate(
            $p['offset'], $p['perPage'], $p['search'],
            $fy, $dept, (string) Request::query('status', '')
        );

        Response::paginated($result['items'], $result['total'], $p['page'], $p['perPage']);
    }

    /**
     * POST /api/sanctions — the sanction number is generated atomically inside
     * a transaction (DEPT-YEAR-NNN) and is read-only afterwards.
     */
    public function store(): void
    {
        $v = Validator::make(Request::all(), [
            'department_id'     => 'required|integer',
            'financial_year_id' => 'integer',
            'amount'            => 'required|numeric|min:1',
            'purpose'           => 'required|max:255',
            'remarks'           => 'max:500',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $fy = (new FinancialYear())->resolve(isset($data['financial_year_id']) ? (int) $data['financial_year_id'] : null);
        if ($fy === null) {
            Response::error('No active financial year configured.', 422);
        }

        $sanctionNo = null;
        $id = Database::transaction(function ($pdo) use ($data, $fy, &$sanctionNo) {
            $sanctionNo = NumberService::nextSanctionNo($pdo, (int) $data['department_id'], (int) $fy['id']);
            $stmt = $pdo->prepare(
                'INSERT INTO sanctions (sanction_no, department_id, financial_year_id, amount, purpose, remarks, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $sanctionNo,
                (int) $data['department_id'],
                (int) $fy['id'],
                (float) $data['amount'],
                $data['purpose'],
                $data['remarks'] ?? null,
                Auth::id(),
            ]);
            return (int) $pdo->lastInsertId();
        });

        AuditService::log('create', 'sanctions', $id, "Sanction $sanctionNo created");
        NotificationService::notifyRoles(
            ['administrator', 'principal'],
            'sanction_new',
            'Sanction Pending Approval',
            "Sanction $sanctionNo (" . money((float) $data['amount']) . ') awaits approval.',
            '/sanctions'
        );

        Response::json(['id' => $id, 'sanction_no' => $sanctionNo], 201, 'Sanction created.');
    }

    /**
     * PUT /api/sanctions/{id} — only while still pending; number never changes.
     */
    public function update(string $id): void
    {
        $model = new Sanction();
        $row   = $model->find((int) $id);
        if ($row === null) {
            Response::error('Sanction not found.', 404);
        }
        if ($row['status'] !== 'pending') {
            Response::error('Only pending sanctions can be edited.', 422);
        }

        $v = Validator::make(Request::all(), [
            'amount'  => 'required|numeric|min:1',
            'purpose' => 'required|max:255',
            'remarks' => 'max:500',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $model->update((int) $id, [
            'amount'  => (float) $data['amount'],
            'purpose' => $data['purpose'],
            'remarks' => $data['remarks'] ?? $row['remarks'],
        ]);

        AuditService::log('update', 'sanctions', (int) $id, 'Sanction ' . $row['sanction_no'] . ' updated');
        Response::json(null, 200, 'Sanction updated.');
    }

    /**
     * POST /api/sanctions/{id}/verify — Accounts marks the sanction verified.
     */
    public function verify(string $id): void
    {
        $this->transition((int) $id, from: ['pending'], to: 'verified', field: 'verified');
    }

    /**
     * POST /api/sanctions/{id}/approve — Principal/Administrator approval.
     * For full-workflow departments (School/College) approval COMMITS the
     * sanctioned amount against the department budget (available drops).
     */
    public function approve(string $id): void
    {
        $model = new Sanction();
        $row   = $model->find((int) $id);
        if ($row === null) {
            Response::error('Sanction not found.', 404);
        }
        if (!in_array($row['status'], ['pending', 'verified'], true)) {
            Response::error("Cannot approve a {$row['status']} sanction.", 422);
        }

        $budget  = new Budget();
        $summary = $budget->summary((int) $row['department_id'], (int) $row['financial_year_id']);
        if (!$summary['has_budget']) {
            Response::error('No budget is allocated for this department in the active financial year. Allocate a budget before approving sanctions.', 422);
        }

        $isFull = $summary['workflow_type'] === 'full';
        // Never allow the budget to go negative.
        if ($isFull && (float) $row['amount'] > $summary['available'] + 0.001) {
            Response::error(
                'Insufficient budget balance. The sanctioned amount ' . money((float) $row['amount']) .
                ' exceeds the available budget of ' . money($summary['available']) . '.',
                422
            );
        }

        Database::transaction(function ($pdo) use ($model, $row, $id, $isFull, $budget) {
            $model->update((int) $id, [
                'status'      => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
            if ($isFull) {
                $budget->commit($pdo, (int) $row['department_id'], (int) $row['financial_year_id'], (float) $row['amount']);
            }
        });

        AuditService::log('approve', 'sanctions', (int) $id, "Sanction {$row['sanction_no']} approved");
        $this->notifyDecision($row, 'approved');
        Response::json(null, 200, 'Sanction approved. Requisitions can now be raised under ' . $row['sanction_no'] . '.');
    }

    /**
     * POST /api/sanctions/{id}/reject
     */
    public function reject(string $id): void
    {
        $this->transition((int) $id, from: ['pending', 'verified'], to: 'rejected', field: 'approved');
    }

    private function transition(int $id, array $from, string $to, string $field): void
    {
        $model = new Sanction();
        $row   = $model->find($id);
        if ($row === null) {
            Response::error('Sanction not found.', 404);
        }
        if (!in_array($row['status'], $from, true)) {
            Response::error("Cannot mark a {$row['status']} sanction as $to.", 422);
        }

        $model->update($id, [
            'status'          => $to,
            $field . '_by'    => Auth::id(),
            $field . '_at'    => date('Y-m-d H:i:s'),
        ]);

        AuditService::log($to === 'rejected' ? 'reject' : 'approve', 'sanctions', $id, "Sanction {$row['sanction_no']} $to");
        $this->notifyDecision($row, $to);
        Response::json(null, 200, 'Sanction ' . $to . '.');
    }

    private function notifyDecision(array $row, string $to): void
    {
        $creator = $row['created_by'] !== null ? [(int) $row['created_by']] : [];
        NotificationService::notifyUsers(
            $creator,
            'sanction_' . $to,
            'Sanction ' . ucfirst($to),
            "Sanction {$row['sanction_no']} has been $to.",
            '/sanctions'
        );
        NotificationService::notifyDepartmentHeads(
            (int) $row['department_id'],
            'sanction_' . $to,
            'Sanction ' . ucfirst($to),
            "Sanction {$row['sanction_no']} has been $to.",
            '/sanctions'
        );
    }

    /**
     * DELETE /api/sanctions/{id} — administrator only. Approved sanctions that
     * hold budget commitments or have requisitions cannot be deleted.
     */
    public function destroy(string $id): void
    {
        $model = new Sanction();
        $row   = $model->find((int) $id);
        if ($row === null) {
            Response::error('Sanction not found.', 404);
        }
        if ($row['status'] === 'approved') {
            Response::error('An approved sanction cannot be deleted because it may hold budget commitments and requisitions.', 422);
        }

        $model->delete((int) $id);
        AuditService::log('delete', 'sanctions', (int) $id, 'Sanction ' . $row['sanction_no'] . ' deleted');
        Response::json(null, 200, 'Sanction deleted.');
    }

    /**
     * GET /sanctions/{id}/print — printable sanction sheet (also "PDF" via
     * the browser's print-to-PDF).
     */
    public function printView(string $id): void
    {
        $row = (new Sanction())->findWithRelations((int) $id);
        if ($row === null) {
            http_response_code(404);
            exit('Sanction not found.');
        }
        if (!Auth::canAccessDepartment((int) $row['department_id'])) {
            http_response_code(403);
            exit('Forbidden.');
        }
        $this->view('sanctions.print', ['sanction' => $row], '');
    }

    /**
     * GET /api/sanctions/export?format=csv|excel
     */
    public function export(): void
    {
        $fy   = Request::query('financial_year_id') ? (int) Request::query('financial_year_id') : null;
        $dept = Auth::role() === 'department_head' ? Auth::departmentId() : null;
        $rows = (new Sanction())->paginate(0, 10000, '', $fy, $dept)['items'];

        $headers = ['Sanction No', 'Department', 'Financial Year', 'Amount', 'Purpose', 'Status', 'Created By', 'Created At'];
        $data    = array_map(static fn ($r) => [
            $r['sanction_no'], $r['department_name'], $r['financial_year'],
            $r['amount'], $r['purpose'], $r['status'], $r['created_by_name'], $r['created_at'],
        ], $rows);

        if (Request::query('format') === 'excel') {
            Response::excel('sanctions.xls', $headers, $data, 'Sanctions');
        }
        Response::csv('sanctions.csv', $headers, $data);
    }
}
