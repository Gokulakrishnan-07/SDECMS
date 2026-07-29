<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Department;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Sanction;

/**
 * Aggregations that power the dashboard and the Reports module.
 * All figures respect department scoping for department heads.
 */
class ReportService
{
    private ?int $scopeDept;

    public function __construct()
    {
        $this->scopeDept = Auth::role() === 'department_head' ? Auth::departmentId() : null;
    }

    /**
     * Everything the dashboard needs in one payload.
     */
    public function dashboard(int $fyId): array
    {
        $budget   = new Budget();
        $sanction = new Sanction();
        $pr       = new PurchaseRequest();
        $po       = new PurchaseOrder();
        $expense  = new Expense();
        $dept     = $this->scopeDept;

        $totals = $budget->totals($fyId, $dept);

        return [
            'stats' => [
                'total_budget'      => $totals['allocated'],
                'allocated_budget'  => $totals['allocated'],
                'committed_budget'  => $totals['committed'],
                'used_budget'       => $totals['used'],
                'remaining_budget'  => $totals['remaining'],
                'utilization'       => $totals['utilization'],
                'total_sanctions'   => $sanction->totalAmount($fyId, $dept),
                'pending_requests'  => $pr->countByStatus('submitted', $fyId, $dept),
                'approved_requests' => $pr->countByStatus('approved', $fyId, $dept),
                'purchase_orders'   => $po->countAll($fyId, $dept),
                'monthly_expense'   => $expense->currentMonthTotal($fyId, $dept),
            ],
            'charts' => [
                'budget_vs_actual'  => $this->budgetVsActual($fyId),
                'monthly_expenses'  => $expense->monthlyTotals($fyId, $dept),
                'department_budget' => $this->departmentBudget($fyId),
                'quarterly'         => $expense->quarterlyTotals($fyId, $dept),
                'yearly'            => $expense->yearlyTotals($dept),
                'expense_trend'     => $expense->monthlyTotals($fyId, $dept),
                'sanction_trend'    => $sanction->monthlyTrend($fyId, $dept),
            ],
            'departments' => (new Department())->withBudgets($fyId),
            'widgets' => [
                'low_budget'        => $budget->lowBudgetDepartments($fyId, $this->threshold()),
                'recent_activities' => (new AuditLog())->recent(8),
                'budget_health'     => $budget->healthByDepartment($fyId, $dept),
            ],
        ];
    }

    /**
     * Allocated vs used per department (Budget vs Actual chart).
     */
    public function budgetVsActual(int $fyId): array
    {
        $rows = (new Department())->withBudgets($fyId);
        if ($this->scopeDept !== null) {
            $rows = array_values(array_filter($rows, fn ($r) => (int) $r['id'] === $this->scopeDept));
        }
        return array_map(static fn ($r) => [
            'department' => $r['name'],
            'allocated'  => (float) $r['allocated_amount'],
            'used'       => (float) $r['used_amount'],
        ], $rows);
    }

    public function departmentBudget(int $fyId): array
    {
        return $this->budgetVsActual($fyId);
    }

    /**
     * Report payload by type: monthly | quarterly | half_yearly | annual |
     * budget_vs_actual | department | sanction | purchase | expense_analysis
     */
    public function build(string $type, int $fyId, ?int $departmentId = null): array
    {
        $dept    = $this->scopeDept ?? $departmentId;
        $expense = new Expense();

        return match ($type) {
            'monthly'   => ['title' => 'Monthly Report',      'rows' => $expense->monthlyTotals($fyId, $dept)],
            'quarterly' => ['title' => 'Quarterly Report',    'rows' => $expense->quarterlyTotals($fyId, $dept)],
            'half_yearly' => ['title' => 'Half-Yearly Report','rows' => $this->halfYearly($fyId, $dept)],
            'annual'    => ['title' => 'Annual Report',       'rows' => $expense->yearlyTotals($dept)],
            'budget_vs_actual' => ['title' => 'Budget vs Actual', 'rows' => $this->budgetVsActual($fyId)],
            'department' => ['title' => 'Department Report',  'rows' => $expense->byDepartment($fyId)],
            'sanction'  => ['title' => 'Sanction Report',     'rows' => (new Sanction())->paginate(0, 1000, '', $fyId, $dept)['items']],
            'purchase'  => ['title' => 'Purchase Report',     'rows' => (new PurchaseOrder())->paginate(0, 1000, '', $fyId, $dept)['items']],
            'expense_analysis' => ['title' => 'Expense Analysis', 'rows' => $expense->byCategory($fyId, $dept)],
            default     => ['title' => 'Report', 'rows' => []],
        };
    }

    private function halfYearly(int $fyId, ?int $dept): array
    {
        $quarters = (new Expense())->quarterlyTotals($fyId, $dept);
        $halves   = ['H1 (Apr–Sep)' => 0.0, 'H2 (Oct–Mar)' => 0.0];
        foreach ($quarters as $q) {
            $key = in_array($q['quarter'], ['Q1', 'Q2'], true) ? 'H1 (Apr–Sep)' : 'H2 (Oct–Mar)';
            $halves[$key] += (float) $q['total'];
        }
        return array_map(
            static fn ($label, $total) => ['period' => $label, 'total' => $total],
            array_keys($halves),
            array_values($halves)
        );
    }

    private function threshold(): float
    {
        try {
            $stmt = \App\Core\Database::connection()
                ->prepare('SELECT `value` FROM system_settings WHERE `key` = ?');
            $stmt->execute(['low_budget_threshold']);
            return (float) ($stmt->fetch()['value'] ?? 80);
        } catch (\Throwable) {
            return 80.0;
        }
    }
}
