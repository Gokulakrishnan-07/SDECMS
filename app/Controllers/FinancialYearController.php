<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\FinancialYear;
use App\Services\AuditService;

class FinancialYearController extends Controller
{
    public function page(): void
    {
        $this->view('financial_years.index', ['pageTitle' => 'Financial Year Management']);
    }

    /**
     * GET /api/financial-years
     */
    public function index(): void
    {
        Response::json((new FinancialYear())->all('start_date', 'DESC'));
    }

    /**
     * POST /api/financial-years  {label, year_code, start_date, end_date}
     */
    public function store(): void
    {
        $v = Validator::make(Request::all(), [
            'label'      => 'required|max:20',
            'year_code'  => 'required|integer|min:2000|max:2999',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
            Response::error('End date must be after the start date.', 422);
        }

        $model = new FinancialYear();
        if ($model->count('label = ?', [$data['label']]) > 0) {
            Response::error('A financial year with this label already exists.', 409);
        }

        $id = $model->insert([
            'label'      => $data['label'],
            'year_code'  => (int) $data['year_code'],
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
        ]);

        AuditService::log('create', 'financial_years', $id, 'Financial year ' . $data['label'] . ' created');
        Response::json(['id' => $id], 201, 'Financial year created.');
    }

    /**
     * PUT /api/financial-years/{id}
     */
    public function update(string $id): void
    {
        $model = new FinancialYear();
        $row   = $model->find((int) $id);
        if ($row === null) {
            Response::error('Financial year not found.', 404);
        }

        $v = Validator::make(Request::all(), [
            'label'      => 'required|max:20',
            'year_code'  => 'required|integer|min:2000|max:2999',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();

        $model->update((int) $id, [
            'label'      => $data['label'],
            'year_code'  => (int) $data['year_code'],
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
        ]);

        AuditService::log('update', 'financial_years', (int) $id, 'Financial year updated');
        Response::json(null, 200, 'Financial year updated.');
    }

    /**
     * POST /api/financial-years/{id}/activate — exactly one FY is active.
     */
    public function activate(string $id): void
    {
        $model = new FinancialYear();
        $row   = $model->find((int) $id);
        if ($row === null) {
            Response::error('Financial year not found.', 404);
        }

        $model->activate((int) $id);
        AuditService::log('update', 'financial_years', (int) $id, 'Financial year ' . $row['label'] . ' activated');
        Response::json(null, 200, $row['label'] . ' is now the active financial year.');
    }

    /**
     * GET /api/financial-years/{id}/periods
     */
    public function periods(string $id): void
    {
        Response::json((new FinancialYear())->periods((int) $id));
    }

    /**
     * POST /api/financial-years/{id}/periods
     * Body: {preset: 'quarters'|'halves'} or {names: ["Quarter 1", ...]}
     */
    public function savePeriods(string $id): void
    {
        $model = new FinancialYear();
        if ($model->find((int) $id) === null) {
            Response::error('Financial year not found.', 404);
        }

        $preset = (string) Request::input('preset', '');
        $names  = Request::input('names', null);

        if ($preset === 'quarters') {
            $names = ['Quarter 1', 'Quarter 2', 'Quarter 3', 'Quarter 4'];
        } elseif ($preset === 'halves') {
            $names = ['Half Year 1', 'Half Year 2'];
        } elseif ($preset === 'none') {
            $names = [];
        }

        if (!is_array($names)) {
            Response::error('Provide a preset (quarters/halves/none) or a list of period names.', 422);
        }

        $model->setPeriods((int) $id, $names);
        AuditService::log('update', 'financial_years', (int) $id, 'Financial year periods updated');
        Response::json(null, 200, 'Periods saved.');
    }
}
