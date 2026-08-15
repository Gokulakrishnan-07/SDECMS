<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Budget;
use App\Models\Department;
use App\Models\DepartmentUnit;
use App\Models\FinancialYear;
use App\Services\MaintenanceHierarchy;

class DepartmentController extends Controller
{
    /**
     * GET /api/departments — active departments (dept heads see only theirs).
     */
    public function index(): void
    {
        $departments = (new Department())->allActive();

        if (Auth::role() === 'department_head') {
            $own = Auth::departmentId();
            $departments = array_values(array_filter(
                $departments,
                static fn ($d) => (int) $d['id'] === $own
            ));
        }

        Response::json($departments);
    }

    /**
     * GET /api/departments/{id}/units — sub-units (Transport / Civil Works).
     */
    public function units(string $id): void
    {
        Response::json((new DepartmentUnit())->forDepartment((int) $id));
    }

    /** GET /api/work-locations - departments and units available as locations. */
    public function workLocations(): void
    {
        Response::json(MaintenanceHierarchy::locations());
    }

    /**
     * GET /api/departments/{id}/budget-summary — live budget figures for the
     * active financial year, shown inside sanction/requisition forms.
     */
    public function budgetSummary(string $id): void
    {
        if (!Auth::canAccessDepartment((int) $id)) {
            Response::error('You cannot view this department.', 403);
        }

        $fy = (new FinancialYear())->active();
        if ($fy === null) {
            Response::error('No active financial year configured.', 422);
        }

        $summary = (new Budget())->summary((int) $id, (int) $fy['id']);
        $summary['financial_year'] = $fy['label'];
        Response::json($summary);
    }
}
