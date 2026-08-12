<?php use App\Core\Auth; ?>
 <?php
$isDepartmentHead = Auth::role() === 'department_head';

$sanctionLabel = $isDepartmentHead
    ? 'Sanction Raising'
    : 'Sanction Requisition';

$newSanctionLabel = $isDepartmentHead
    ? 'New Sanction Raising'
    : 'New Sanction Requisition';
    ?>
<div class="page-head">
    <div>
        <h1 class="page-title"><?= $sanctionLabel ?></h1>
        <span class="page-sub">Auto-numbered sanctions per department (e.g. COL-2026-001)</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fa-solid fa-download me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" id="expCsv" href="#">CSV</a></li>
                <li><a class="dropdown-item" id="expExcel" href="#">Excel</a></li>
                <li><a class="dropdown-item" href="#" onclick="window.print();return false">PDF (Print)</a></li>
            </ul>
        </div>
        <?php if (Auth::can('sanctions.create')): ?>
        <button class="btn btn-primary btn-sm" id="btnNew">
            <i class="fa-solid fa-plus me-1"></i> <?= $newSanctionLabel ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card table-card">
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><select class="form-select form-select-sm" id="fDept"><option value="">All Departments</option></select></div>
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="fStatus">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="tblSanctions" style="width:100%">
            <thead>
                <tr>
                    <th>Sanction No</th><th>Department</th><th>Amount</th><th>Balance</th>
                    <th>Purpose</th><th>Status</th><th>Created By</th><th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="sanctionModal" tabindex="-1">
    <div class="modal-dialog modal-form">
        <form class="modal-content" id="sanctionForm">
            <div class="modal-header">
                <h5 class="modal-title" id="sanctionModalTitle"><?= $newSanctionLabel ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small rounded-4" id="autoNoHint">
                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>
                    The sanction number is generated automatically (e.g. COL-2026-001) and cannot be changed.
                </div>
                <div class="row g-3">
                    <div class="col-md-6" id="grpDept">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="sDept" required></select>
                    </div>
                    <div class="col-12">
                        <div class="budget-panel is-empty" id="budgetPanel">
                            <div class="bp-item"><div class="bp-label">Select a department to see the live budget</div></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Requested Amount (₹)</label>
                        <input type="number" class="form-control" id="sAmount" min="1" step="0.01" required>
                        <div class="words-preview" id="sAmountWords"></div>
                        <div class="form-warning" id="sAmountWarn"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="sPurpose" maxlength="255" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" id="sRemarks" rows="2" maxlength="500"></textarea>
                    </div>
                    <div class="col-12" id="grpAttachments">
                        <label class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="sAttachments" multiple
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <div class="form-text">PDF, DOC, DOCX, JPG, PNG — max 5 MB each</div>
                        <div id="existingAttachments" class="mt-2"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="sSave">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const canCreate  = <?= Auth::can('sanctions.create') ? 'true' : 'false' ?>;
    const canApprove = <?= Auth::can('sanctions.approve') ? 'true' : 'false' ?>;
    const canVerify  = <?= Auth::can('sanctions.verify') ? 'true' : 'false' ?>;
    const canDelete  = <?= Auth::can('sanctions.delete') ? 'true' : 'false' ?>;

    const modal = new bootstrap.Modal(document.getElementById('sanctionModal'));
    let table, editing = null;
    let ctx = { available: 0, workflow: 'simple' };

    App.bindAmountWords('#sAmount', '#sAmountWords');

    const depts = (await App.api('/api/departments')).data;
    const fDept = document.getElementById('fDept');
    depts.forEach(d => fDept.add(new Option(d.name, d.id)));
    const sDept = document.getElementById('sDept');
    depts.forEach(d => sDept.add(new Option(d.name + ' (' + d.code + ')', d.id)));

    async function onDeptChange() {
        const id = sDept.value;
        const panel = document.getElementById('budgetPanel');
        if (!id) {
            panel.className = 'budget-panel is-empty';
            panel.innerHTML = '<div class="bp-item"><div class="bp-label">Select a department to see the live budget</div></div>';
            ctx = { available: 0, workflow: 'simple' };
            validateAmount();
            return;
        }
        const b = (await App.api('/api/departments/' + id + '/budget-summary')).data;
        ctx.available = b.available;
        ctx.workflow = b.workflow_type;
        if (!b.has_budget) {
            panel.className = 'budget-panel is-empty';
            panel.innerHTML = '<div class="bp-item"><div class="bp-label">No budget allocated for this department in the active year</div></div>';
        } else {
            panel.className = 'budget-panel';
            panel.innerHTML = `
                <div class="bp-item"><div class="bp-label">Allocated</div><div class="bp-value">${App.moneyShort(b.allocated)}</div></div>
                ${b.workflow_type === 'full' ? `<div class="bp-item"><div class="bp-label">Committed</div><div class="bp-value">${App.moneyShort(b.committed)}</div></div>` : ''}
                <div class="bp-item"><div class="bp-label">Used</div><div class="bp-value">${App.moneyShort(b.used)}</div></div>
                <div class="bp-item"><div class="bp-label">Available Budget</div><div class="bp-value ${b.available > 0 ? 'good' : 'danger'}">${App.moneyShort(b.available)}</div></div>`;
        }
        validateAmount();
    }

    function validateAmount() {
        const amt = parseFloat(document.getElementById('sAmount').value || 0);
        const warn = document.getElementById('sAmountWarn');
        const save = document.getElementById('sSave');
        let msg = '';
        // Full-workflow sanctions commit the budget, so they cannot exceed it.
        if (ctx.workflow === 'full' && amt > ctx.available + 0.001) {
            msg = 'Insufficient budget balance. The sanction amount exceeds the available budget of ' + App.money(ctx.available) + '.';
        }
        warn.textContent = msg;
        warn.style.display = msg ? 'block' : 'none';
        save.disabled = !!msg;
    }
    document.getElementById('sAmount').addEventListener('input', validateAmount);
    sDept.addEventListener('change', onDeptChange);

    /* ── Attachment helpers ───────────────────────────────────── */
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function fileIcon(name) {
        const ext = (name || '').split('.').pop().toLowerCase();
        const map = {
            pdf: 'fa-file-pdf text-danger',
            doc: 'fa-file-word text-primary',
            docx: 'fa-file-word text-primary',
            jpg: 'fa-file-image text-warning',
            jpeg: 'fa-file-image text-warning',
            png: 'fa-file-image text-info',
        };
        return map[ext] || 'fa-file';
    }

    async function loadExistingAttachments(sanctionId) {
        const box = document.getElementById('existingAttachments');
        box.innerHTML = '';
        if (!sanctionId) return;
        try {
            const atts = (await App.api('/api/sanctions/' + sanctionId + '/attachments')).data;
            if (atts.length === 0) return;
            const list = atts.map(a => `
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="fa-solid ${fileIcon(a.original_filename)}"></i>
                    <a href="${App.base}/sanctions/${sanctionId}/attachments/${a.id}/view" target="_blank"
                       class="text-decoration-none small">${App.esc(a.original_filename)}</a>
                    <a href="${App.base}/sanctions/${sanctionId}/attachments/${a.id}/download" class="small text-muted" title="Download">
                        <i class="fa-solid fa-download"></i>
                    </a>
                    <span class="text-muted small">(${formatFileSize(a.file_size)})</span>
                </div>`).join('');
            box.innerHTML = '<div class="small text-muted mb-1">Existing files:</div>' + list;
        } catch (e) { /* ignore */ }
    }

    function renderAttachmentBadge(sanctionId, count) {
        if (!count || count === 0) return '';
        return `<a href="#" class="badge bg-secondary text-decoration-none act-atts" data-id="${sanctionId}" title="${count} attachment(s)">
                    <i class="fa-solid fa-paperclip"></i> ${count}
                </a>`;
    }

    /* ── Table load ───────────────────────────────────────────── */
    async function load() {
        const q = new URLSearchParams({ per_page: 1000 });
        if (fDept.value) q.set('department_id', fDept.value);
        if (document.getElementById('fStatus').value) q.set('status', document.getElementById('fStatus').value);

        const rows = (await App.api('/api/sanctions?' + q)).data;

        /* Fetch attachment counts in parallel */
        const attCounts = {};
        await Promise.allSettled(rows.map(async r => {
            try {
                const atts = (await App.api('/api/sanctions/' + r.id + '/attachments')).data;
                attCounts[r.id] = atts.length;
            } catch (e) { attCounts[r.id] = 0; }
        }));

        if (table) table.destroy();
        document.querySelector('#tblSanctions tbody').innerHTML = rows.map(r => `
            <tr>
                <td>
                    <strong class="text-primary">${App.esc(r.sanction_no)}</strong>
                    ${renderAttachmentBadge(r.id, attCounts[r.id])}
                </td>
                <td>${App.esc(r.department_name)}</td>
                <td>${App.money(r.amount)}</td>
                <td>${r.status === 'approved' ? App.money(r.balance_amount) : '—'}</td>
                <td>${App.esc(r.purpose)}</td>
                <td>${App.badge(r.status)}</td>
                <td>${App.esc(r.created_by_name || '—')}</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="${App.base}/sanctions/${r.id}/view" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a class="btn btn-sm btn-outline-secondary" href="${App.base}/sanctions/${r.id}/print" target="_blank" title="Print"><i class="fa-solid fa-print"></i></a>
                    ${canCreate && r.status === 'pending' ? `<button class="btn btn-sm btn-outline-secondary act-edit" data-id="${r.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>` : ''}
                    ${canVerify && r.status === 'pending' ? `<button class="btn btn-sm btn-outline-primary act-verify" data-id="${r.id}" title="Verify"><i class="fa-solid fa-clipboard-check"></i></button>` : ''}
                    ${canApprove && ['pending','verified'].includes(r.status) ? `
                        <button class="btn btn-sm btn-outline-success act-approve" data-id="${r.id}" title="Approve"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-sm btn-outline-danger act-reject" data-id="${r.id}" title="Reject"><i class="fa-solid fa-xmark"></i></button>` : ''}
                    ${canDelete ? `<button class="btn btn-sm btn-outline-danger act-del" data-id="${r.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : ''}
                </td>
            </tr>`).join('');
        table = App.dataTable('#tblSanctions', { columnDefs: [{ orderable: false, targets: -1 }] });
        window._sRows = Object.fromEntries(rows.map(r => [r.id, r]));
    }

    document.querySelector('#tblSanctions tbody').addEventListener('click', async e => {
        const btn = e.target.closest('button') || e.target.closest('a.act-atts');
        if (!btn) return;
        const id = btn.dataset.id;
        const row = window._sRows[id];

        /* ── Show attachments popover ─────────────────────────── */
        if (btn.classList.contains('act-atts')) {
            e.preventDefault();
            try {
                const atts = (await App.api('/api/sanctions/' + id + '/attachments')).data;
                if (atts.length === 0) return;
                const html = atts.map(a => `
                    <div class="mb-1">
                        <i class="fa-solid ${fileIcon(a.original_filename)} me-1"></i>
                        <a href="${App.base}/sanctions/${id}/attachments/${a.id}/view" target="_blank">${App.esc(a.original_filename)}</a>
                        <a href="${App.base}/sanctions/${id}/attachments/${a.id}/download" class="ms-1" title="Download"><i class="fa-solid fa-download"></i></a>
                        <span class="text-muted">(${formatFileSize(a.file_size)})</span>
                    </div>`).join('');
                Swal.fire({
                    title: 'Attachments — ' + (row ? row.sanction_no : ''),
                    html: html,
                    showConfirmButton: true,
                    confirmButtonColor: '#0071e3',
                    confirmButtonText: 'Close',
                });
            } catch (err) { App.toast('error', err.message); }
            return;
        }

        async function action(path, label) {
            if (!await App.confirmAction(label + '?', row.sanction_no + ' — ' + App.money(row.amount))) return;
            try {
                const res = await App.api('/api/sanctions/' + id + path, { method: 'POST' });
                App.toast('success', res.message || 'Done');
                load();
            } catch (err) { Swal.fire({ icon: 'warning', title: 'Action blocked', text: err.message, confirmButtonColor: '#0071e3' }); }
        }

        if (btn.classList.contains('act-edit')) {
            editing = id;
            document.getElementById('sanctionModalTitle').textContent = 'Edit ' + row.sanction_no;
            document.getElementById('grpDept').style.display = 'none';
            document.getElementById('budgetPanel').style.display = 'none';
            document.getElementById('sAmount').value = row.amount;
            document.getElementById('sPurpose').value = row.purpose;
            document.getElementById('sRemarks').value = row.remarks || '';
            document.getElementById('sAttachments').value = '';
            document.getElementById('sSave').disabled = false;
            App.bindAmountWords('#sAmount', '#sAmountWords');
            loadExistingAttachments(id);
            modal.show();
        }
        else if (btn.classList.contains('act-verify'))  action('/verify', 'Verify sanction');
        else if (btn.classList.contains('act-approve')) action('/approve', 'Approve sanction');
        else if (btn.classList.contains('act-reject'))  action('/reject', 'Reject sanction');
        else if (btn.classList.contains('act-del')) {
            if (!await App.confirmAction('Delete sanction?', row.sanction_no + ' will be permanently removed.', 'Yes, delete')) return;
            try {
                await App.api('/api/sanctions/' + id, { method: 'DELETE' });
                App.toast('success', 'Sanction deleted');
                load();
            } catch (err) { App.toast('error', err.message); }
        }
    });

    document.getElementById('btnNew')?.addEventListener('click', () => {
        editing = null;
        document.getElementById('sanctionModalTitle').textContent = <?= json_encode($newSanctionLabel) ?>;
        document.getElementById('grpDept').style.display = '';
        document.getElementById('budgetPanel').style.display = '';
        document.getElementById('sanctionForm').reset();
        document.getElementById('existingAttachments').innerHTML = '';
        onDeptChange();
        modal.show();
    });

    document.getElementById('sanctionForm').addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('amount', document.getElementById('sAmount').value);
        fd.append('purpose', document.getElementById('sPurpose').value);
        fd.append('remarks', document.getElementById('sRemarks').value);

        // Append attachment files
        const fileInput = document.getElementById('sAttachments');
        for (let i = 0; i < fileInput.files.length; i++) {
            fd.append('attachments[]', fileInput.files[i]);
        }

        try {
            if (editing) {
                await App.api('/api/sanctions/' + editing, { method: 'PUT', body: fd });
                App.toast('success', 'Sanction updated');
            } else {
                fd.append('department_id', sDept.value);
                const res = await App.api('/api/sanctions', { method: 'POST', body: fd });
                Swal.fire({
                    icon: 'success', title: 'Sanction created',
                    html: 'Sanction number: <strong>' + App.esc(res.data.sanction_no) + '</strong>',
                    confirmButtonColor: '#0071e3',
                });
            }
            modal.hide();
            load();
        } catch (err) { App.toast('error', err.message); }
    });

    ['fDept', 'fStatus'].forEach(id => document.getElementById(id).addEventListener('change', load));
    document.getElementById('expCsv').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/sanctions/export?format=csv'; });
    document.getElementById('expExcel').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/sanctions/export?format=excel'; });

    load();
});
</script>
