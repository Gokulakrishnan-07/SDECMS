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
use App\Models\SanctionAttachment;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\NumberService;
use App\Services\UploadService;

class SanctionController extends Controller
{
    public function page(): void
    {
        $this->view('sanctions.index', ['pageTitle' => 'Sanction Amount']);
    }

    /** GET-only, department-scoped sanction detail screen. */
    public function viewDetail(string $id): void
    {
        $sanction = (new Sanction())->findWithRelations((int) $id);
        if ($sanction === null) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }
        if (!Auth::canAccessDepartment((int) $sanction['department_id'])) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            return;
        }

        $this->view('sanctions.view', [
            'pageTitle' => 'View Sanction Request',
            'sanction'  => $sanction,
            'budget'    => (new Budget())->summary((int) $sanction['department_id'], (int) $sanction['financial_year_id']),
            'attachments' => (new SanctionAttachment())->bySanctionId((int) $id),
        ]);
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

        // Reject an invalid attachment batch before creating a sanction or
        // consuming a permanent sanction number.
        try {
            $this->validateAttachments();
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }

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

        // ── Attachments (optional, multi-file) ────────────────────────
        try {
            $this->saveAttachments($id);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }

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

        // ── Additional attachments ──────────────────────────────────
        try {
            $this->saveAttachments((int) $id);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }

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

    /** Open a safe preview for PDFs/images; other allowed files download. */
    public function viewAttachment(string $id, string $attachmentId): void
    {
        $this->streamAttachment((int) $id, (int) $attachmentId, false);
    }

    /** Download an attachment after the same department-scoped check. */
    public function download(string $id, string $attachmentId): void
    {
        $this->streamAttachment((int) $id, (int) $attachmentId, true);
    }

    private function streamAttachment(int $sanctionId, int $attachmentId, bool $forceDownload): void
    {
        $sanction = (new Sanction())->find($sanctionId);
        if ($sanction === null) {
            http_response_code(404);
            exit('Sanction not found.');
        }
        if (!Auth::canAccessDepartment((int) $sanction['department_id'])) {
            http_response_code(403);
            exit('Forbidden.');
        }

        $att = (new SanctionAttachment())->find($attachmentId);
        if ($att === null || (int) $att['sanction_id'] !== $sanctionId) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        $fullPath = rtrim(config('uploads.path'), '/\\') . DIRECTORY_SEPARATOR
                  . str_replace('/', DIRECTORY_SEPARATOR, $att['file_path']);
        if (!is_file($fullPath)) {
            http_response_code(404);
            exit('File not found on disk.');
        }

        $previewable = in_array($att['mime_type'], ['application/pdf', 'image/png', 'image/jpeg'], true);
        $disposition = (!$forceDownload && $previewable) ? 'inline' : 'attachment';
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $att['mime_type']);
        header('Content-Disposition: ' . $disposition . '; filename="' . self::downloadFilename($att['original_filename']) . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
        exit;
    }

    private static function downloadFilename(string $name): string
    {
        return str_replace(['"', "\r", "\n"], '_', basename($name));
    }

    /**
     * GET /api/sanctions/{id}/attachments — JSON list of attachments.
     */
    public function attachments(string $id): void
    {
        $sanction = (new Sanction())->find((int) $id);
        if ($sanction === null) {
            Response::error('Sanction not found.', 404);
        }
        if (!Auth::canAccessDepartment((int) $sanction['department_id'])) {
            Response::error('Forbidden.', 403);
        }

        $list = (new SanctionAttachment())->bySanctionId((int) $id);
        Response::json($list);
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

        $attachments = (new SanctionAttachment())->bySanctionId((int) $id);
        $model->delete((int) $id);
        foreach ($attachments as $attachment) {
            UploadService::deleteStored($attachment['file_path']);
        }
        AuditService::log('delete', 'sanctions', (int) $id, 'Sanction ' . $row['sanction_no'] . ' deleted');
        Response::json(null, 200, 'Sanction deleted.');
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Process a fully validated file batch. Metadata writes are transactional;
     * files moved before a failed database write are removed again.
     */
    private function saveAttachments(int $sanctionId): void
    {
        if (empty($_FILES['attachments']['name'][0])) {
            return;
        }

        $files = $_FILES['attachments'];
        $count = count($files['name']);
        $batch = [];

        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            // Skip empty slots (browser may send blank entries)
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            UploadService::validateSanctionAttachment($file);
            $batch[] = $file;
        }
        if ($batch === []) {
            return;
        }

        $stored = [];
        try {
            Database::transaction(function ($pdo) use ($batch, $sanctionId, &$stored) {
                $stmt = $pdo->prepare(
                    'INSERT INTO sanction_attachments
                        (sanction_id, original_filename, stored_filename, file_path, file_extension,
                         mime_type, file_size, uploaded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($batch as $file) {
                    $meta = UploadService::storeSanctionAttachment($file);
                    $stored[] = $meta['file_path'];
                    $stmt->execute([
                        $sanctionId, $meta['original_filename'], $meta['stored_filename'],
                        $meta['file_path'], $meta['file_extension'], $meta['mime_type'],
                        $meta['file_size'], Auth::id(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                UploadService::deleteStored($path);
            }
            throw $e;
        }
    }

    /** Validate each populated multi-file field without persisting anything. */
    private function validateAttachments(): void
    {
        if (empty($_FILES['attachments']['name'][0])) {
            return;
        }
        $files = $_FILES['attachments'];
        foreach ($files['name'] as $i => $_name) {
            $file = [
                'name'     => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                UploadService::validateSanctionAttachment($file);
            }
        }
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
