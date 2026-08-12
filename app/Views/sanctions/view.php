<?php
/** @var array $sanction */ /** @var array $budget */ /** @var array $attachments */
$back = base_url('sanctions');
?>
<div class="page-head">
    <div>
        <h1 class="page-title">Sanction Request</h1>
        <span class="page-sub">Read-only details for <?= e($sanction['sanction_no']) ?></span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($back) ?>"><i class="fa-solid fa-arrow-left me-1"></i> Back to Sanctions</a>
</div>

<div class="glass-card p-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div><div class="text-secondary small">Sanction Number</div><h4 class="mb-0 text-primary"><?= e($sanction['sanction_no']) ?></h4></div>
        <span class="badge text-bg-secondary text-capitalize"><?= e($sanction['status']) ?></span>
    </div>
    <div class="row g-3 small">
        <div class="col-md-4"><span class="text-secondary d-block">Department</span><?= e($sanction['department_name']) ?></div>
        <div class="col-md-4"><span class="text-secondary d-block">Financial Year</span><?= e($sanction['financial_year']) ?></div>
        <div class="col-md-4"><span class="text-secondary d-block">Requested Amount</span><strong><?= money((float) $sanction['amount']) ?></strong></div>
        <div class="col-md-4"><span class="text-secondary d-block">Remaining Sanction Balance</span><?= money((float) $sanction['balance_amount']) ?></div>
        <div class="col-md-8"><span class="text-secondary d-block">Purpose</span><?= e($sanction['purpose']) ?></div>
        <div class="col-12"><span class="text-secondary d-block">Previous Remarks</span><?= $sanction['remarks'] ? nl2br(e($sanction['remarks'])) : '<span class="text-secondary">No records available.</span>' ?></div>
    </div>
</div>

<div class="glass-card p-4 mb-3">
    <h5 class="mb-3">Budget Details</h5>
    <?php if ($budget['has_budget']): ?>
    <div class="row g-3 small">
        <div class="col-6 col-md-3"><span class="text-secondary d-block">Allocated</span><?= money($budget['allocated']) ?></div>
        <div class="col-6 col-md-3"><span class="text-secondary d-block">Committed</span><?= money($budget['committed']) ?></div>
        <div class="col-6 col-md-3"><span class="text-secondary d-block">Used</span><?= money($budget['used']) ?></div>
        <div class="col-6 col-md-3"><span class="text-secondary d-block">Remaining Budget</span><strong><?= money($budget['available']) ?></strong></div>
    </div>
    <?php else: ?><span class="text-secondary small">No records available.</span><?php endif; ?>
</div>

<div class="glass-card p-4 mb-3">
    <h5 class="mb-3">Attachments</h5>
    <?php if ($attachments === []): ?><span class="text-secondary small">No attachments.</span>
    <?php else: ?><div class="list-group list-group-flush">
        <?php foreach ($attachments as $attachment): ?>
        <div class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center gap-2">
            <div class="small"><i class="fa-solid fa-paperclip me-2"></i><?= e($attachment['original_filename']) ?> <span class="text-secondary">(<?= e(strtoupper($attachment['file_extension'])) ?>, <?= number_format((int) $attachment['file_size']) ?> bytes)</span></div>
            <div class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(base_url('sanctions/' . $sanction['id'] . '/attachments/' . $attachment['id'] . '/view')) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('sanctions/' . $sanction['id'] . '/attachments/' . $attachment['id'] . '/download')) ?>">Download</a></div>
        </div>
        <?php endforeach; ?>
    </div><?php endif; ?>
</div>

<div class="glass-card p-4">
    <h5 class="mb-3">Approval History</h5>
    <div class="small">
        <div class="mb-2"><strong>Created</strong><span class="text-secondary ms-2"><?= e($sanction['created_by_name'] ?? 'System') ?> · <?= e($sanction['created_at']) ?></span></div>
        <?php if ($sanction['verified_at']): ?><div class="mb-2"><strong>Verified</strong><span class="text-secondary ms-2"><?= e($sanction['verified_by_name'] ?? 'System') ?> · <?= e($sanction['verified_at']) ?></span></div><?php endif; ?>
        <?php if ($sanction['approved_at']): ?><div><strong><?= $sanction['status'] === 'rejected' ? 'Rejected' : 'Approved' ?></strong><span class="text-secondary ms-2"><?= e($sanction['approved_by_name'] ?? 'System') ?> · <?= e($sanction['approved_at']) ?></span></div><?php endif; ?>
        <?php if (!$sanction['verified_at'] && !$sanction['approved_at']): ?><span class="text-secondary">No further approval records available.</span><?php endif; ?>
    </div>
</div>
