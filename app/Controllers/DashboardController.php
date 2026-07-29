<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\FinancialYear;
use App\Services\ReportService;

class DashboardController extends Controller
{
    public function home(): void
    {
        redirect(Auth::check() ? 'dashboard' : 'login');
    }

    public function index(): void
    {
        $fy = (new FinancialYear())->active();
        $this->view('dashboard.index', [
            'pageTitle'     => 'Dashboard',
            'financialYear' => $fy,
        ]);
    }

    /**
     * GET /api/dashboard — stats, chart datasets, department showcase, widgets.
     */
    public function apiStats(): void
    {
        $fy = (new FinancialYear())->resolve(
            Request::query('financial_year_id') ? (int) Request::query('financial_year_id') : null
        );

        if ($fy === null) {
            Response::error('No active financial year configured.', 422);
        }

        $payload = (new ReportService())->dashboard((int) $fy['id']);
        $payload['financial_year'] = ['id' => (int) $fy['id'], 'label' => $fy['label']];

        Response::json($payload);
    }
}
