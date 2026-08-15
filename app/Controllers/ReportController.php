<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\FinancialYear;
use App\Services\ReportService;

class ReportController extends Controller
{
    private const TYPES = [
        'monthly', 'quarterly', 'half_yearly', 'annual',
        'budget_vs_actual', 'department', 'sanction', 'purchase', 'expense_analysis',
    ];

    public function page(): void
    {
        $this->view('reports.index', ['pageTitle' => 'Reports']);
    }

    /**
     * GET /api/reports?type=monthly&financial_year_id=&department_id=
     */
    public function data(): void
    {
        [$type, $fy, $dept, $category, $location] = $this->params();
        $report = (new ReportService())->build($type, (int) $fy['id'], $dept, $category, $location);
        $report['financial_year'] = $fy['label'];
        Response::json($report);
    }

    /**
     * GET /api/reports/export?type=...&format=csv|excel
     */
    public function export(): void
    {
        [$type, $fy, $dept, $category, $location] = $this->params();
        $report = (new ReportService())->build($type, (int) $fy['id'], $dept, $category, $location);

        $rows = $report['rows'];
        if ($rows === []) {
            Response::error('No data available for this report.', 422);
        }

        // Flatten: use the first row's keys as headers.
        $headers  = array_map(
            static fn ($k) => ucwords(str_replace('_', ' ', (string) $k)),
            array_keys($rows[0])
        );
        $data     = array_map('array_values', $rows);
        $filename = $type . '-report-' . $fy['label'];

        if (Request::query('format') === 'excel') {
            Response::excel($filename . '.xls', $headers, $data, $report['title'] . ' — FY ' . $fy['label']);
        }
        Response::csv($filename . '.csv', $headers, $data);
    }

    /** @return array{0:string, 1:array, 2:?int} */
    private function params(): array
    {
        $type = (string) Request::query('type', 'monthly');
        if (!in_array($type, self::TYPES, true)) {
            Response::error('Unknown report type. Allowed: ' . implode(', ', self::TYPES), 422);
        }

        $fy = (new FinancialYear())->resolve(
            Request::query('financial_year_id') ? (int) Request::query('financial_year_id') : null
        );
        if ($fy === null) {
            Response::error('No active financial year configured.', 422);
        }

        $dept = Request::query('department_id') ? (int) Request::query('department_id') : null;
        return [$type, $fy, $dept, (string) Request::query('maintenance_category', ''), (string) Request::query('work_location', '')];
    }
}
