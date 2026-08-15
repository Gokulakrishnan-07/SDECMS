<?php /** @var array $sanction */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sanction <?= e($sanction['sanction_no']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1d1d1f; max-width: 760px; margin: 2rem auto; padding: 0 1.5rem; }
        .head { text-align: center; border-bottom: 2px solid #1d1d1f; padding-bottom: 1rem; margin-bottom: 1.6rem; }
        .head h1 { font-size: 1.25rem; margin: 0.4rem 0 0; }
        .head p { margin: 0.15rem 0; color: #555; font-size: 0.85rem; }
        .no { font-size: 1.05rem; font-weight: 700; letter-spacing: 0.03em; }
        table { width: 100%; border-collapse: collapse; margin: 1.2rem 0; }
        th, td { text-align: left; padding: 0.55rem 0.7rem; border: 1px solid #ccc; font-size: 0.9rem; }
        th { background: #f2f2f4; width: 220px; }
        .amount { font-size: 1.15rem; font-weight: 700; }
        .sign { display: flex; justify-content: space-between; margin-top: 4.5rem; }
        .sign div { text-align: center; width: 220px; border-top: 1px solid #888; padding-top: 0.4rem; font-size: 0.85rem; }
        .print-btn { position: fixed; top: 14px; right: 14px; padding: 0.55rem 1.2rem; border: none; background: #0071e3; color: #fff; border-radius: 10px; font-weight: 600; cursor: pointer; }
        @media print { .print-btn { display: none; } body { margin: 0.5rem auto; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print / Save PDF</button>

    <div class="head">
        <p><strong>Swami Dayanandha Educational Institutions</strong></p>
        <p>Manjakkudi, Thiruvarur District, Tamil Nadu</p>
        <h1>SANCTION ORDER</h1>
        <p class="no">No: <?= e($sanction['sanction_no']) ?></p>
    </div>

    <table>
        <tr><th>Sanction Number</th><td><?= e($sanction['sanction_no']) ?></td></tr>
        <tr><th>Department</th><td><?= e($sanction['department_name']) ?> (<?= e($sanction['department_code']) ?>)</td></tr>
        <?php if (!empty($sanction['work_location_type'])): ?><?php if (!empty($sanction['maintenance_category'])): ?><tr><th>Maintenance Category</th><td><?= e($sanction['maintenance_category']) ?></td></tr><?php endif; ?><tr><th>Work Location / Service Area</th><td><?= e($sanction['work_location_name'] ?? 'No records available.') ?></td></tr><?php endif; ?>
        <tr><th>Financial Year</th><td><?= e($sanction['financial_year']) ?></td></tr>
        <tr><th>Sanctioned Amount</th><td class="amount"><?= e(money((float) $sanction['amount'])) ?></td></tr>
        <tr><th>Purpose</th><td><?= e($sanction['purpose']) ?></td></tr>
        <tr><th>Status</th><td style="text-transform:capitalize"><?= e($sanction['status']) ?></td></tr>
        <tr><th>Created By</th><td><?= e($sanction['created_by_name'] ?? '—') ?></td></tr>
        <tr><th>Created On</th><td><?= e($sanction['created_at']) ?></td></tr>
        <?php if (!empty($sanction['approved_by_name'])): ?>
        <tr><th>Approved By</th><td><?= e($sanction['approved_by_name']) ?> on <?= e($sanction['approved_at']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($sanction['remarks'])): ?>
        <tr><th>Remarks</th><td><?= e($sanction['remarks']) ?></td></tr>
        <?php endif; ?>
    </table>

    <div class="sign">
        <div>Prepared By</div>
        <div>Verified By</div>
        <div>Approved By</div>
    </div>
</body>
</html>
