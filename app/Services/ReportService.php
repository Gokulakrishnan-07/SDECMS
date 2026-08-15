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
    public function build(string $type, int $fyId, ?int $departmentId = null, string $category = '', string $location = ''): array
    {
        $dept    = $this->scopeDept ?? $departmentId;
        $expense = new Expense();

        return match ($type) {
            'monthly'   => ['title' => 'Monthly Report',      'rows' => $expense->monthlyTotals($fyId, $dept)],
            'quarterly' => ['title' => 'Quarterly Report',    'rows' => $this->quarterlyBudget($fyId, $dept)],
            'half_yearly' => ['title' => 'Half-Yearly Report','rows' => $this->halfYearly($fyId, $dept)],
            'annual'    => ['title' => 'Annual Report',       'rows' => $expense->yearlyTotals($dept)],
            'budget_vs_actual' => ['title' => 'Budget vs Actual', 'rows' => $this->budgetVsActual($fyId)],
            'department' => ['title' => 'Department Report',  'rows' => $expense->byDepartment($fyId)],
            'sanction'  => ['title' => 'Sanction Report',     'rows' => (new Sanction())->paginate(0, 1000, '', $fyId, $dept, '', $category, $location)['items']],
            'purchase'  => ['title' => 'Purchase Report',     'rows' => (new PurchaseOrder())->paginate(0, 1000, '', $fyId, $dept, '', $category, $location)['items']],
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

    private function quarterlyBudget(int $fyId, ?int $dept): array
    {
        $db = \App\Core\Database::connection();
        $rows = [];
        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q) $rows[$q] = ['quarter' => $q, 'total_budget' => 0.0, 'sanction_amount' => 0.0, 'purchase_request_amount' => 0.0, 'purchase_order_amount' => 0.0, 'actual_spending' => 0.0];

        $stmt = $db->prepare('SELECT p.name, COALESCE(SUM(bp.allocated_amount),0) total_budget FROM budget_periods bp JOIN budgets b ON b.id=bp.budget_id JOIN financial_year_periods p ON p.id=bp.period_id WHERE b.financial_year_id=?' . ($dept !== null ? ' AND b.department_id=?' : '') . ' GROUP BY p.name');
        $stmt->execute($dept !== null ? [$fyId, $dept] : [$fyId]);
        foreach ($stmt->fetchAll() as $r) if (isset($rows[$r['name']])) $rows[$r['name']]['total_budget'] = (float) $r['total_budget'];
        // Preserve totals for legacy budgets created before quarter rows existed.
        $legacy = $db->prepare('SELECT COALESCE(SUM(b.allocated_amount),0) total_budget FROM budgets b WHERE b.financial_year_id=? AND NOT EXISTS (SELECT 1 FROM budget_periods bp WHERE bp.budget_id=b.id)' . ($dept !== null ? ' AND b.department_id=?' : ''));
        $legacy->execute($dept !== null ? [$fyId, $dept] : [$fyId]);
        $rows['Q1']['total_budget'] += (float) ($legacy->fetch()['total_budget'] ?? 0);

        $queries = [
            ['sanction_amount', 'SELECT CASE WHEN TIMESTAMPDIFF(MONTH,fy.start_date,s.created_at) BETWEEN 0 AND 2 THEN "Q1" WHEN TIMESTAMPDIFF(MONTH,fy.start_date,s.created_at) BETWEEN 3 AND 5 THEN "Q2" WHEN TIMESTAMPDIFF(MONTH,fy.start_date,s.created_at) BETWEEN 6 AND 8 THEN "Q3" ELSE "Q4" END q, COALESCE(SUM(s.amount),0) total FROM sanctions s JOIN financial_years fy ON fy.id=s.financial_year_id WHERE s.financial_year_id=? AND s.status="approved"', 's.department_id'],
            ['purchase_request_amount', 'SELECT CASE WHEN TIMESTAMPDIFF(MONTH,fy.start_date,pr.created_at) BETWEEN 0 AND 2 THEN "Q1" WHEN TIMESTAMPDIFF(MONTH,fy.start_date,pr.created_at) BETWEEN 3 AND 5 THEN "Q2" WHEN TIMESTAMPDIFF(MONTH,fy.start_date,pr.created_at) BETWEEN 6 AND 8 THEN "Q3" ELSE "Q4" END q, COALESCE(SUM(pr.amount),0) total FROM purchase_requests pr JOIN financial_years fy ON fy.id=pr.financial_year_id WHERE pr.financial_year_id=? AND pr.status="approved"', 'pr.department_id'],
            ['purchase_order_amount', 'SELECT CASE WHEN TIMESTAMPDIFF(MONTH,fy.start_date,po.created_at) BETWEEN 0 AND 2 THEN "Q1" WHEN TIMESTAMPDIFF(MONTH,fy.start_date,po.created_at) BETWEEN 3 AND 5 THEN "Q2" WHEN TIMESTAMPDIFF(MONTH,fy.start_date,po.created_at) BETWEEN 6 AND 8 THEN "Q3" ELSE "Q4" END q, COALESCE(SUM(po.total_amount),0) total FROM purchase_orders po JOIN financial_years fy ON fy.id=po.financial_year_id WHERE po.financial_year_id=? AND po.status <> "cancelled"', 'po.department_id'],
            ['actual_spending', 'SELECT CASE WHEN MONTH(e.expense_date) BETWEEN 4 AND 6 THEN "Q1" WHEN MONTH(e.expense_date) BETWEEN 7 AND 9 THEN "Q2" WHEN MONTH(e.expense_date) BETWEEN 10 AND 12 THEN "Q3" ELSE "Q4" END q, COALESCE(SUM(e.amount),0) total FROM expenses e WHERE e.financial_year_id=?', 'e.department_id'],
        ];
        foreach ($queries as [$field, $sql, $deptColumn]) {
            if ($dept !== null) $sql .= ' AND ' . $deptColumn . '=?';
            $sql .= ' GROUP BY q';
            $stmt = $db->prepare($sql); $stmt->execute($dept !== null ? [$fyId, $dept] : [$fyId]);
            foreach ($stmt->fetchAll() as $r) if (isset($rows[$r['q']])) $rows[$r['q']][$field] = (float) $r['total'];
        }
        foreach ($rows as &$row) $row['remaining_budget'] = $row['total_budget'] - $row['actual_spending'];
        return array_values($rows);
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
