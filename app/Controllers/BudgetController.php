<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Budget;
use App\Models\Department;
use App\Models\FinancialYear;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\MaintenanceHierarchy;

class BudgetController extends Controller
{
    public function page(): void
    {
        $this->view('budgets.index', ['pageTitle' => 'Budget Allocation']);
    }

    /**
     * GET /api/budget — paginated, filterable budget list.
     */
    public function index(): void
    {
        $p  = $this->pageParams();
        $fy = Request::query('financial_year_id') ? (int) Request::query('financial_year_id') : null;
        if ($fy === null) {
            $active = (new FinancialYear())->active();
            $fy     = $active ? (int) $active['id'] : null;
        }

        $dept = Request::query('department_id') ? (int) Request::query('department_id') : null;
        if (Auth::role() === 'department_head') {
            $dept = Auth::departmentId(); // scope to own department
        }

        $result = (new Budget())->paginate(
            $p['offset'], $p['perPage'], $p['search'],
            $fy, $dept, (string) Request::query('approval_status', ''), (string) Request::query('work_location', '')
        );

        Response::paginated($result['items'], $result['total'], $p['page'], $p['perPage']);
    }

    /**
     * POST /api/budget — allocate a budget for a department + financial year.
     */
    public function store(): void
    {
        $v = Validator::make(Request::all(), [
            'department_id'     => 'required|integer',
            'financial_year_id' => 'required|integer',
            'allocated_amount'  => 'required|numeric|min:0',
            'remarks'           => 'max:500',
            'work_location'     => 'max:30',
            'other_work_location' => 'max:255',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $periodInput = Request::input('periods');
        $quarterly = is_array($periodInput) && $periodInput !== []
            ? $this->quarterlyAllocation((int) $data['financial_year_id'], $periodInput)
            : ['periods' => [], 'total' => round((float) $data['allocated_amount'], 2)];
        if ($quarterly === null) {
            Response::error('Quarter values cannot be negative.', 422, ['periods' => 'Quarter values cannot be negative.']);
        }
        $data['allocated_amount'] = $quarterly['total'];

        $location = MaintenanceHierarchy::isHousekeeping((int) $data['department_id'])
            ? MaintenanceHierarchy::resolveForDepartment((int) $data['department_id'], $data['work_location'] ?? null, $data['other_work_location'] ?? null)
            : null;
        if (MaintenanceHierarchy::isHousekeeping((int) $data['department_id']) && $location === null) {
            Response::error('Housekeeping Work Location / Service Area is required.', 422, ['work_location' => 'Select a location or Others with a description.']);
        }

        $budget = new Budget();
        if ($budget->findByDeptFy((int) $data['department_id'], (int) $data['financial_year_id'])) {
            Response::error('A budget already exists for this department and financial year.', 409);
        }

        $id = $budget->insert([
            'department_id'     => (int) $data['department_id'],
            'work_location_type' => $location['type'] ?? null,
            'work_location_id' => $location['id'] ?? null,
            'other_work_location' => $location['other'] ?? null,
            'financial_year_id' => (int) $data['financial_year_id'],
            'allocated_amount'  => (float) $data['allocated_amount'],
            'remarks'           => $data['remarks'] ?? null,
            'created_by'        => Auth::id(),
        ]);

        $budget->replacePeriods($id, $quarterly['periods']);

        $dept = (new Department())->find((int) $data['department_id']);
        AuditService::log('create', 'budgets', $id, 'Budget allocated for ' . ($dept['name'] ?? '?'));
        NotificationService::notifyRoles(
            ['administrator', 'principal'],
            'budget_updated',
            'Budget Allocated',
            sprintf('%s allocated for %s.', money((float) $data['allocated_amount']), $dept['name'] ?? 'department'),
            '/budgets'
        );

        Response::json(['id' => $id], 201, 'Budget created.');
    }

    /**
     * PUT /api/budget/{id}
     */
    public function update(string $id): void
    {
        $budget = new Budget();
        $row    = $budget->find((int) $id);
        if ($row === null) {
            Response::error('Budget not found.', 404);
        }

        $v = Validator::make(Request::all(), [
            'allocated_amount' => 'required|numeric|min:0',
            'status'           => 'in:active,inactive',
            'remarks'          => 'max:500',
            'work_location'    => 'max:30',
            'other_work_location' => 'max:255',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $quarterly = null;
        if (array_key_exists('periods', Request::all())) {
            $quarterly = $this->quarterlyAllocation((int) $row['financial_year_id'], Request::input('periods'));
            if ($quarterly === null) {
                Response::error('Quarter values cannot be negative.', 422, ['periods' => 'Quarter values cannot be negative.']);
            }
            $data['allocated_amount'] = $quarterly['total'];
        }

        $location = null;
        if (MaintenanceHierarchy::isHousekeeping((int) $row['department_id']) && (array_key_exists('work_location', Request::all()) || array_key_exists('other_work_location', Request::all()))) {
            $location = MaintenanceHierarchy::resolveForDepartment((int) $row['department_id'], $data['work_location'] ?? null, $data['other_work_location'] ?? null);
            if ($location === null) Response::error('Housekeeping Work Location / Service Area is required.', 422, ['work_location' => 'Select a location or Others with a description.']);
        }

        $spoken = (float) $row['committed_amount'] + (float) $row['used_amount'];
        if ((float) $data['allocated_amount'] < $spoken) {
            Response::error('Allocated amount cannot be less than the amount already committed and used (' . money($spoken) . ').', 422);
        }

        $budget->update((int) $id, [
            'allocated_amount' => (float) $data['allocated_amount'],
            'status'           => $data['status'] ?? $row['status'],
            'remarks'          => $data['remarks'] ?? $row['remarks'],
            ...($location !== null ? ['work_location_type' => $location['type'], 'work_location_id' => $location['id'], 'other_work_location' => $location['other']] : []),
        ]);

        if ($quarterly !== null) {
            $budget->replacePeriods((int) $id, $quarterly['periods']);
        }

        AuditService::log('update', 'budgets', (int) $id, 'Budget updated');
        NotificationService::notifyDepartmentHeads(
            (int) $row['department_id'],
            'budget_updated',
            'Budget Updated',
            'Your department budget was updated to ' . money((float) $data['allocated_amount']) . '.',
            '/budgets'
        );

        Response::json(null, 200, 'Budget updated.');
    }

    /**
     * POST /api/budget/{id}/approve  body: {decision: approved|rejected}
     */
    public function approve(string $id): void
    {
        $budget = new Budget();
        $row    = $budget->find((int) $id);
        if ($row === null) {
            Response::error('Budget not found.', 404);
        }

        $decision = (string) Request::input('decision', 'approved');
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            Response::error('Decision must be "approved" or "rejected".', 422);
        }

        $budget->update((int) $id, [
            'approval_status' => $decision,
            'approved_by'     => Auth::id(),
            'approved_at'     => date('Y-m-d H:i:s'),
        ]);

        AuditService::log($decision === 'approved' ? 'approve' : 'reject', 'budgets', (int) $id, "Budget $decision");
        NotificationService::notifyDepartmentHeads(
            (int) $row['department_id'],
            'budget_' . $decision,
            'Budget ' . ucfirst($decision),
            'Your department budget has been ' . $decision . '.',
            '/budgets'
        );

        Response::json(null, 200, 'Budget ' . $decision . '.');
    }

    /**
     * DELETE /api/budget/{id} — administrator only.
     */
    public function destroy(string $id): void
    {
        $budget = new Budget();
        $row    = $budget->find((int) $id);
        if ($row === null) {
            Response::error('Budget not found.', 404);
        }
        if ((float) $row['used_amount'] > 0) {
            Response::error('Cannot delete a budget that already has recorded spending.', 422);
        }

        $budget->delete((int) $id);
        AuditService::log('delete', 'budgets', (int) $id, 'Budget deleted');
        Response::json(null, 200, 'Budget deleted.');
    }

    /**
     * GET /api/budget/export?format=csv|excel
     */
    public function export(): void
    {
        $fyModel = new FinancialYear();
        $fy      = $fyModel->resolve(Request::query('financial_year_id') ? (int) Request::query('financial_year_id') : null);
        if ($fy === null) {
            Response::error('No financial year found.', 422);
        }

        $dept = Auth::role() === 'department_head' ? Auth::departmentId() : null;
        $rows = (new Budget())->paginate(0, 10000, '', (int) $fy['id'], $dept)['items'];

        $headers = ['Department', 'Work Location / Service Area', 'Financial Year', 'Allocated', 'Used', 'Remaining', 'Utilization %', 'Status', 'Approval'];
        $data    = array_map(static fn ($r) => [
            $r['department_name'], $r['work_location_name'] ?? '', $r['financial_year'],
            $r['allocated_amount'], $r['used_amount'], $r['remaining_amount'],
            $r['utilization'], $r['status'], $r['approval_status'],
        ], $rows);

        $filename = 'budgets-' . $fy['label'];
        if (Request::query('format') === 'excel') {
            Response::excel($filename . '.xls', $headers, $data, 'Budget Allocation — FY ' . $fy['label']);
        }
        Response::csv($filename . '.csv', $headers, $data);
    }

    private function quarterlyAllocation(int $financialYearId, mixed $input): ?array
    {
        if (!is_array($input)) return null;
        $valid = [];
        foreach ((new FinancialYear())->periods($financialYearId) as $period) $valid[(string) $period['id']] = true;
        $periods = [];
        $total = 0.0;
        foreach ($input as $period) {
            $id = (string) ($period['period_id'] ?? '');
            $amount = (float) ($period['allocated_amount'] ?? 0);
            if ($id === '' || !isset($valid[$id]) || $amount < 0) return null;
            $periods[] = ['period_id' => (int) $id, 'allocated_amount' => $amount];
            $total += $amount;
        }
        return ['periods' => $periods, 'total' => round($total, 2)];
    }
}
