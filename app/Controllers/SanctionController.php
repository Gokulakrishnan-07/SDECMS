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
use App\Services\MaintenanceHierarchy;

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
        if (Auth::role() === 'accounts' && $sanction['status'] !== 'approved') {
            http_response_code(404); require BASE_PATH . '/app/Views/errors/404.php'; return;
        }
        if (!Auth::canAccessDepartment((int) $sanction['department_id']) || (Auth::role() === 'accounts' && $sanction['status'] !== 'approved')) {
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

        $status = Auth::role() === 'accounts' ? 'approved' : (string) Request::query('status', '');
        $result = (new Sanction())->paginate(
            $p['offset'], $p['perPage'], $p['search'],
            $fy, $dept, $status,
            (string) Request::query('maintenance_category', ''), (string) Request::query('work_location', '')
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
            'maintenance_category' => 'in:Civil,Electrical,Plumbing',
            'work_location' => 'max:30',
            'other_work_location' => 'max:255',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
        }
        $data = $v->validated();
        $departmentId = (int) $data['department_id'];
        $this->validateMaintenanceFields($departmentId, $data['maintenance_category'] ?? null, $data['work_location'] ?? null);
        $location = MaintenanceHierarchy::isHousekeeping($departmentId)
            ? MaintenanceHierarchy::resolveForDepartment($departmentId, $data['work_location'] ?? null, $data['other_work_location'] ?? null)
            : MaintenanceHierarchy::resolve($data['work_location'] ?? null);
        if (MaintenanceHierarchy::isHousekeeping($departmentId) && $location === null) {
            Response::error('Housekeeping Work Location / Service Area is required.', 422, ['work_location' => 'Select a location or Others with a description.']);
        }

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
        $id = Database::transaction(function ($pdo) use ($data, $fy, $location, &$sanctionNo) {
            $sanctionNo = NumberService::nextSanctionNo($pdo, (int) $data['department_id'], (int) $fy['id']);
            $stmt = $pdo->prepare(
            'INSERT INTO sanctions (sanction_no, department_id, maintenance_category, work_location_type, work_location_id, other_work_location, financial_year_id, amount, purpose, remarks, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $sanctionNo,
                (int) $data['department_id'],
                $data['maintenance_category'] ?? null,
                $location['type'] ?? null, $location['id'] ?? null,
                $location['other'] ?? null,
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
        \App\Services\ApprovalHistoryService::add('sanction', $id, 'submitted');
        NotificationService::notifyRoles(
            ['administrator', 'principal'],
            'sanction_new',
            'Sanction Pending Approval',
            "Sanction $sanctionNo (" . money((float) $data['amount']) . ') awaits approval.',
            '/sanctions'
        );

        Response::json(['id' => $id, 'sanction_no' => $sanctionNo], 201, 'Sanction created.');
    }

    private function validateMaintenanceFields(int $departmentId, ?string $category, ?string $locationValue): void
    {
        if (!MaintenanceHierarchy::isMaintenance($departmentId)) return;
        if (!in_array($category, MaintenanceHierarchy::CATEGORIES, true)) Response::error('Maintenance Category is required.', 422, ['maintenance_category' => 'Select Civil, Electrical, or Plumbing.']);
        if (MaintenanceHierarchy::resolve($locationValue) === null) Response::error('Work Location is required.', 422, ['work_location' => 'Select a valid work location.']);
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
        if (!in_array($row['status'], ['pending', 'rejected'], true)) {
            Response::error('Only pending or rejected sanctions can be edited.', 422);
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
            'status'  => $row['status'] === 'rejected' ? 'pending' : $row['status'],
        ]);

        // ── Additional attachments ──────────────────────────────────
        try {
            $this->saveAttachments((int) $id);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }

        AuditService::log('update', 'sanctions', (int) $id, 'Sanction ' . $row['sanction_no'] . ' updated');
        \App\Services\ApprovalHistoryService::add('sanction', (int) $id, $row['status'] === 'rejected' ? 'resubmitted' : 'modified');
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
        \App\Services\ApprovalHistoryService::add('sanction', (int) $id, 'approved');
        $this->notifyDecision($row, 'approved');
        Response::json(null, 200, 'Sanction approved. Requisitions can now be raised under ' . $row['sanction_no'] . '.');
    }

    /**
     * POST /api/sanctions/{id}/reject
     */
    public function reject(string $id): void
    {
        $reason = trim((string) Request::input('reason', ''));
        if ($reason === '') Response::error('A rejection reason is required.', 422, ['reason' => 'Enter a clear rejection reason.']);
        $model = new Sanction();
        $row = $model->find((int) $id);
        if ($row === null) Response::error('Sanction not found.', 404);
        if (!in_array($row['status'], ['pending', 'verified'], true)) Response::error("Cannot reject a {$row['status']} sanction.", 422);
        $model->update((int) $id, ['status' => 'rejected', 'rejected_by' => Auth::id(), 'rejected_at' => date('Y-m-d H:i:s'), 'reject_reason' => substr($reason, 0, 500)]);
        AuditService::log('reject', 'sanctions', (int) $id, "Sanction {$row['sanction_no']} rejected");
        \App\Services\ApprovalHistoryService::add('sanction', (int) $id, 'rejected', $reason);
        $this->notifyDecision($row, 'rejected', $reason);
        Response::json(null, 200, 'Sanction rejected.');
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
        \App\Services\ApprovalHistoryService::add('sanction', $id, $to);
        $this->notifyDecision($row, $to);
        Response::json(null, 200, 'Sanction ' . $to . '.');
    }

    private function notifyDecision(array $row, string $to, ?string $reason = null): void
    {
        $department = (new \App\Models\Department())->find((int) $row['department_id']);
        $departmentName = $department['name'] ?? 'Unknown department';
        $actor = Auth::user()['name'] ?? 'System';
        $when = date('Y-m-d H:i:s');
        $creator = $row['created_by'] !== null ? [(int) $row['created_by']] : [];
        NotificationService::notifyUsers(
            $creator,
            'sanction_' . $to,
            'Sanction ' . ucfirst($to),
            "Sanction {$row['sanction_no']} has been $to." . ($reason ? " Reason: $reason" : ''),
            '/sanctions'
        );
        NotificationService::notifyDepartmentHeads(
            (int) $row['department_id'],
            'sanction_' . $to,
            'Sanction ' . ucfirst($to),
            "Sanction {$row['sanction_no']} has been $to.",
            '/sanctions'
        );
        if ($to === 'approved') {
            NotificationService::notifyRoles(
                ['accounts'],
                'accounts_forwarded',
                'Sanction approved for Accounts',
                "Sanction {$row['sanction_no']} | Department: {$departmentName} | Amount: " . number_format((float) $row['amount'], 2) . " | Approved by: {$actor} | {$when}",
                '/sanctions/' . (int) $row['id'] . '/view'
            );
        }
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
        if (!Auth::canAccessDepartment((int) $sanction['department_id']) || (Auth::role() === 'accounts' && $sanction['status'] !== 'approved')) {
            http_response_code(403);
            exit('Forbidden.');
        }

        $att = (new SanctionAttachment())->find($attachmentId);
        if ($att === null || (int) $att['sanction_id'] !== $sanctionId) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        $root = realpath(rtrim(config('uploads.path'), '/\\'));
        $fullPath = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $att['file_path']));
        if ($root === false || $fullPath === false || !str_starts_with($fullPath, $root . DIRECTORY_SEPARATOR) || !is_file($fullPath)) {
            http_response_code(404);
            exit('File not found on disk.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($fullPath) ?: (string) $att['mime_type'];
        $extension = strtolower((string) ($att['file_extension'] ?: pathinfo($att['original_filename'], PATHINFO_EXTENSION)));
        $mime = self::normaliseAttachmentMime($mime, $extension);
        $previewable = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true);
        $disposition = (!$forceDownload && $previewable) ? 'inline' : 'attachment';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: ' . $disposition . '; filename="' . self::downloadFilename($att['original_filename']) . '"; filename*=UTF-8\'\'' . rawurlencode($att['original_filename']));
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
        exit;
    }

    private static function downloadFilename(string $name): string
    {
        return str_replace(['"', "\r", "\n"], '_', basename($name));
    }

    private static function normaliseAttachmentMime(string $mime, string $extension): string
    {
        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return 'image/jpeg';
        }
        if ($extension === 'png') {
            return 'image/png';
        }
        $fallbacks = [
            'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        return ($mime === 'application/octet-stream' || $mime === '') && isset($fallbacks[$extension])
            ? $fallbacks[$extension] : $mime;
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
        if (empty($_FILES['attachments']['name']) || !array_filter($_FILES['attachments']['name'])) {
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
        if (empty($_FILES['attachments']['name']) || !array_filter($_FILES['attachments']['name'])) {
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
        if (Auth::role() === 'accounts' && $row['status'] !== 'approved') { http_response_code(404); exit('Sanction not found.'); }
        $this->view('sanctions.print', ['sanction' => $row], '');
    }

    /**
     * GET /api/sanctions/export?format=csv|excel
     */
    public function export(): void
    {
        $fy   = Request::query('financial_year_id') ? (int) Request::query('financial_year_id') : null;
        $dept = Auth::role() === 'department_head' ? Auth::departmentId() : null;
        $model = new Sanction();
        $single = Request::query('id') ? $model->findWithRelations((int) Request::query('id')) : null;
        $rows = $single ? [$single] : $model->paginate(0, 10000, '', $fy, $dept, Auth::role() === 'accounts' ? 'approved' : '')['items'];

        $headers = ['Sanction No', 'Department', 'Maintenance Category', 'Work Location / Service Area', 'Other Work Location', 'Financial Year', 'Amount', 'Purpose', 'Status', 'Created By', 'Created At'];
        $data    = array_map(static fn ($r) => [
            $r['sanction_no'], $r['department_name'], $r['maintenance_category'] ?? '', $r['work_location_name'] ?? '', $r['other_work_location'] ?? '', $r['financial_year'],
            $r['amount'], $r['purpose'], $r['status'], $r['created_by_name'], $r['created_at'],
        ], $rows);

        if (Request::query('format') === 'excel') {
            Response::excel('sanctions.xls', $headers, $data, 'Sanctions');
        }
        Response::csv('sanctions.csv', $headers, $data);
    }
}
