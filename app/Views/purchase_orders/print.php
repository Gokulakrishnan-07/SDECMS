<?php /** @var array $po */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order <?= e($po['po_no']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1d1d1f; max-width: 800px; margin: 2rem auto; padding: 0 1.5rem; }
        .head { text-align: center; border-bottom: 2px solid #1d1d1f; padding-bottom: 1rem; margin-bottom: 1.4rem; }
        .head h1 { font-size: 1.25rem; margin: 0.4rem 0 0; }
        .head p { margin: 0.15rem 0; color: #555; font-size: 0.85rem; }
        .meta { display: flex; justify-content: space-between; gap: 2rem; font-size: 0.88rem; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: 0.5rem 0.65rem; border: 1px solid #ccc; font-size: 0.88rem; }
        th { background: #f2f2f4; }
        .num { text-align: right; }
        tfoot td { font-weight: 600; }
        .sign { display: flex; justify-content: space-between; margin-top: 4rem; }
        .sign div { text-align: center; width: 200px; border-top: 1px solid #888; padding-top: 0.4rem; font-size: 0.85rem; }
        .print-btn { position: fixed; top: 14px; right: 14px; padding: 0.55rem 1.2rem; border: none; background: #0071e3; color: #fff; border-radius: 10px; font-weight: 600; cursor: pointer; }
        @media print { .print-btn { display: none; } body { margin: 0.5rem auto; } }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print / Save PDF</button>

    <div class="head">
        <p><strong>Swami Dayanandha Educational Institutions</strong></p>
        <p>Manjakkudi, Thiruvarur District, Tamil Nadu</p>
        <h1>PURCHASE ORDER</h1>
        <p><strong><?= e($po['po_no']) ?></strong> · <?= e($po['created_at']) ?></p>
    </div>

    <div class="meta">
        <div>
            <strong>Vendor</strong><br>
            <?= e($po['vendor_name']) ?><br>
            <?php if (!empty($po['vendor_address'])): ?><?= e($po['vendor_address']) ?><br><?php endif; ?>
            <?php if (!empty($po['vendor_gstin'])): ?>GSTIN: <?= e($po['vendor_gstin']) ?><?php endif; ?>
        </div>
        <div style="text-align:right">
            <strong>Department:</strong> <?= e($po['department_name']) ?><br>
            <?php if (!empty($po['work_location_type'])): ?><?php if (!empty($po['maintenance_category'])): ?><strong>Maintenance Category:</strong> <?= e($po['maintenance_category']) ?><br><?php endif; ?><strong>Work Location / Service Area:</strong> <?= e($po['work_location_name'] ?? 'No records available.') ?><br><?php endif; ?>
            <strong>Financial Year:</strong> <?= e($po['financial_year']) ?><br>
            <?php if (!empty($po['pr_no'])): ?><strong>Ref PR:</strong> <?= e($po['pr_no']) ?><br><?php endif; ?>
            <?php if (!empty($po['invoice_no'])): ?><strong>Invoice:</strong> <?= e($po['invoice_no']) ?><br><?php endif; ?>
            <strong>Status:</strong> <span style="text-transform:capitalize"><?= e($po['status']) ?></span>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Item</th><th>Description</th><th class="num">Qty</th><th class="num">Unit Price</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            <?php foreach ($po['items'] as $i => $item): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($item['item_name']) ?></td>
                <td><?= e($item['description'] ?? '') ?></td>
                <td class="num"><?= e($item['quantity']) ?></td>
                <td class="num"><?= e(money((float) $item['unit_price'])) ?></td>
                <td class="num"><?= e(money((float) $item['amount'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="5" class="num">Subtotal</td><td class="num"><?= e(money((float) $po['subtotal'])) ?></td></tr>
            <tr><td colspan="5" class="num">GST (<?= e($po['gst_percent']) ?>%)</td><td class="num"><?= e(money((float) $po['gst_amount'])) ?></td></tr>
            <tr><td colspan="5" class="num">Grand Total</td><td class="num"><?= e(money((float) $po['total_amount'])) ?></td></tr>
        </tfoot>
    </table>

    <?php if (!empty($po['remarks'])): ?>
        <p style="font-size:.85rem"><strong>Remarks:</strong> <?= e($po['remarks']) ?></p>
    <?php endif; ?>

    <div class="sign">
        <div>Prepared By</div>
        <div>Accounts</div>
        <div>Authorised Signatory</div>
    </div>
</body>
</html>
