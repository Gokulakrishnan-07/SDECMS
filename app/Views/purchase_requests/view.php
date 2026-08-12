<?php
/** @var array $request */ /** @var array $budget */
?>
<div class="page-head"><div><h1 class="page-title">Purchase Request</h1><span class="page-sub">Read-only details for <?= e($request['pr_no']) ?></span></div><a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('purchase-requests')) ?>"><i class="fa-solid fa-arrow-left me-1"></i> Back to Purchase Requests</a></div>

<div class="glass-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div><div class="text-secondary small">Request Number</div><h4 class="mb-0 text-primary"><?= e($request['pr_no']) ?></h4></div><span class="badge text-bg-secondary text-capitalize"><?= e($request['status']) ?></span></div>
    <div class="row g-3 small">
        <div class="col-md-4"><span class="text-secondary d-block">Department</span><?= e($request['department_name']) ?></div><div class="col-md-4"><span class="text-secondary d-block">Financial Year</span><?= e($request['financial_year']) ?></div><div class="col-md-4"><span class="text-secondary d-block">Requested Amount</span><strong><?= money((float) $request['amount']) ?></strong></div>
        <div class="col-md-4"><span class="text-secondary d-block">Parent Sanction</span><?= e($request['parent_sanction_no'] ?? 'No records available.') ?></div><div class="col-md-4"><span class="text-secondary d-block">Sanction Balance</span><?= $request['sanction_amount'] !== null ? money((float) $request['sanction_amount'] - (float) $request['requisitioned_amount']) : 'No records available.' ?></div><div class="col-md-4"><span class="text-secondary d-block">Unit</span><?= e($request['unit_name'] ?? 'No records available.') ?></div>
        <div class="col-md-6"><span class="text-secondary d-block">Title</span><?= e($request['title']) ?></div><div class="col-md-6"><span class="text-secondary d-block">Created By</span><?= e($request['created_by_name'] ?? 'System') ?></div>
        <div class="col-12"><span class="text-secondary d-block">Description</span><?= $request['description'] ? nl2br(e($request['description'])) : '<span class="text-secondary">No records available.</span>' ?></div>
        <div class="col-12"><span class="text-secondary d-block">Previous Remarks</span><?= $request['remarks'] ? nl2br(e($request['remarks'])) : '<span class="text-secondary">No records available.</span>' ?><?= $request['reject_reason'] ? '<div class="text-danger mt-1">Rejection reason: ' . e($request['reject_reason']) . '</div>' : '' ?></div>
    </div>
</div>

<div class="glass-card p-4 mb-3"><h5 class="mb-3">Budget Details</h5><?php if ($budget['has_budget']): ?><div class="row g-3 small"><div class="col-6 col-md-3"><span class="text-secondary d-block">Allocated</span><?= money($budget['allocated']) ?></div><div class="col-6 col-md-3"><span class="text-secondary d-block">Committed</span><?= money($budget['committed']) ?></div><div class="col-6 col-md-3"><span class="text-secondary d-block">Used</span><?= money($budget['used']) ?></div><div class="col-6 col-md-3"><span class="text-secondary d-block">Remaining Budget</span><strong><?= money($budget['available']) ?></strong></div></div><?php else: ?><span class="text-secondary small">No records available.</span><?php endif; ?></div>

<div class="glass-card p-4 mb-3"><h5 class="mb-3">Attachments</h5><?php if ($request['attachment_name']): ?><div class="small"><i class="fa-solid fa-paperclip me-2"></i><?= e($request['attachment_name']) ?></div><?php else: ?><span class="text-secondary small">No attachments.</span><?php endif; ?></div>

<div class="glass-card p-4"><h5 class="mb-3">Approval History</h5><div class="small"><div class="mb-2"><strong>Created</strong><span class="text-secondary ms-2"><?= e($request['created_by_name'] ?? 'System') ?> · <?= e($request['created_at']) ?></span></div><?php if ($request['approved_at']): ?><div><strong><?= $request['status'] === 'rejected' ? 'Rejected' : 'Approved' ?></strong><span class="text-secondary ms-2"><?= e($request['approved_by_name'] ?? 'System') ?> · <?= e($request['approved_at']) ?></span></div><?php else: ?><span class="text-secondary">No further approval records available.</span><?php endif; ?></div></div>
