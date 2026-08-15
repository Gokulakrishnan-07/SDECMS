<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
       <h1 class="page-title">
     <?= Auth::role() === 'department_head'
    ? 'Purchase Raising'
    : 'Purchase Requisitions' ?></h1>
        <span class="page-sub">Raised under an approved sanction · Auto sequential numbering (01, 02, 03...)</span>
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
        <?php if (Auth::can('purchase_requests.create')): ?>
      <button class="btn btn-primary btn-sm" id="btnNew">
         <i class="fa-solid fa-plus me-1"></i>
         <?= Auth::role() === 'department_head'
        ? 'New Purchase Raising'
        : 'New Requisition' ?>
</button>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card table-card">
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><select class="form-select form-select-sm" id="fDept"><option value="">All Departments</option></select></div>
        <div class="col-6 col-md-3"><select class="form-select form-select-sm" id="fMaintenanceCategory"><option value="">All Maintenance Categories</option><option>Civil</option><option>Electrical</option><option>Plumbing</option></select></div>
        <div class="col-6 col-md-3"><select class="form-select form-select-sm" id="fWorkLocation"><option value="">All Work Locations</option></select></div>
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="fStatus">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="tblPRs" style="width:100%">
            <thead>
                <tr>
                    <th>Requisition No</th><th>Sanction</th><th>Department</th><th>Unit</th>
                    <th>Title</th><th>Amount</th><th>Status</th><th>Created By</th><th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="prModal" tabindex="-1">
    <div class="modal-dialog modal-form">
        <form class="modal-content" id="prForm" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title" id="prModalTitle">
                    <?= Auth::role() === 'department_head'
                      ? 'New Purchase Raising'
                      : 'New Purchase Requisition' ?></h5>  
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6" id="grpSanction">
                        <label class="form-label">Approved Sanction</label>
                        <select class="form-select" id="pSanction" required></select>
                        <div class="form-text">Requisitions can only be raised under an approved sanction.</div>
                    </div>
                    <div class="col-md-6" id="grpUnit" style="display:none">
                        <label class="form-label" id="unitLabel">Unit</label>
                        <select class="form-select" id="pUnit"></select>
                    </div>
                    <div class="col-md-6" id="grpMaintenanceCategory" style="display:none"><label class="form-label">Maintenance Category</label><input class="form-control" id="pMaintenanceCategory" readonly></div>
                    <div class="col-md-6" id="grpWorkLocation" style="display:none"><label class="form-label">Work Location</label><input class="form-control" id="pWorkLocation" readonly></div>

                    <div class="col-12">
                        <div class="budget-panel is-empty" id="budgetPanel">
                            <div class="bp-item"><div class="bp-label">Select a sanction to see the live budget</div></div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Amount (₹)</label>
                        <input type="number" class="form-control" id="pAmount" min="1" step="0.01" required>
                        <div class="words-preview" id="pAmountWords"></div>
                        <div class="form-warning" id="pAmountWarn"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" id="pTitle" maxlength="200" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description / Purpose</label>
                        <textarea class="form-control" id="pDesc" rows="3" maxlength="5000"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attachment <small class="text-secondary">(pdf, image, office — max 5 MB)</small></label>
                        <input type="file" class="form-control" id="pFile" accept=".pdf,.png,.jpg,.jpeg,.xlsx,.xls,.csv,.doc,.docx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Remarks</label>
                        <input type="text" class="form-control" id="pRemarks" maxlength="500">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="prSave">Save Draft</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const canCreate  = <?= Auth::can('purchase_requests.create') ? 'true' : 'false' ?>;
    const canApprove = <?= Auth::can('purchase_requests.approve') ? 'true' : 'false' ?>;
    const canDelete  = <?= Auth::can('purchase_requests.delete') ? 'true' : 'false' ?>;
    const isDeptHead = <?= Auth::role() === 'department_head' ? 'true' : 'false' ?>;
    const myId       = <?= (int) Auth::id() ?>;

    const modal = new bootstrap.Modal(document.getElementById('prModal'));
    let table, editing = null;
    let sanctions = {};   // id -> sanction row
    let selCtx = { balance: 0, available: 0, workflow: 'simple', maxAllowed: 0 };

    App.bindAmountWords('#pAmount', '#pAmountWords');

    const depts = (await App.api('/api/departments')).data;
    const locations = (await App.api('/api/work-locations')).data;
    locations.forEach(l => document.getElementById('fWorkLocation').add(new Option(l.label, l.value)));
    document.getElementById('fWorkLocation').add(new Option('Others', 'other'));
    const fDept = document.getElementById('fDept');
    depts.forEach(d => fDept.add(new Option(d.name, d.id)));

    // ---- Load approved sanctions into the create dropdown
    async function loadSanctions() {
        const rows = (await App.api('/api/sanctions?status=approved&per_page=1000')).data;
        sanctions = Object.fromEntries(rows.map(r => [r.id, r]));
        const sel = document.getElementById('pSanction');
        sel.length = 0;
        sel.add(new Option('— Select an approved sanction —', ''));
        rows.forEach(r => {
            const bal = parseFloat(r.balance_amount);
            sel.add(new Option(`${r.sanction_no} · ${r.department_name} · balance ${App.moneyShort(bal)}`, r.id));
        });
    }

    async function onSanctionChange() {
        const id = document.getElementById('pSanction').value;
        const grpUnit = document.getElementById('grpUnit');
        const panel = document.getElementById('budgetPanel');
        if (!id || !sanctions[id]) {
            panel.className = 'budget-panel is-empty';
            panel.innerHTML = '<div class="bp-item"><div class="bp-label">Select a sanction to see the live budget</div></div>';
            grpUnit.style.display = 'none';
            selCtx = { balance: 0, available: 0, workflow: 'simple', maxAllowed: 0 };
            validateAmount();
            return;
        }
        const s = sanctions[id];
        const deptId = s.department_id;
        const maintenance = !!(s.department_code === 'MNT');
        document.getElementById('grpMaintenanceCategory').style.display = maintenance ? '' : 'none';
        document.getElementById('grpWorkLocation').style.display = maintenance ? '' : 'none';
        document.getElementById('pMaintenanceCategory').value = s.maintenance_category || 'Housekeeping';
        document.getElementById('pWorkLocation').value = s.work_location_name || '';

        // Units (mandatory when the department has any)
        const units = (await App.api('/api/departments/' + deptId + '/units')).data;
        const uSel = document.getElementById('pUnit');
        if (units.length && !maintenance) {
            uSel.length = 0;
            uSel.add(new Option('— Select unit —', ''));
            units.forEach(u => uSel.add(new Option(u.unit_name, u.id)));
            document.getElementById('unitLabel').textContent = s.department_name + ' Unit';
            grpUnit.style.display = '';
        } else {
            grpUnit.style.display = 'none';
            uSel.length = 0;
        }

        // Live budget panel
        const b = (await App.api('/api/departments/' + deptId + '/budget-summary')).data;
        const balance = parseFloat(s.balance_amount);
        selCtx.balance = balance;
        selCtx.available = b.available;
        selCtx.workflow = b.workflow_type;
        selCtx.maxAllowed = b.workflow_type === 'full' ? balance : Math.min(balance, b.available);

        panel.className = 'budget-panel';
        panel.innerHTML = `
            <div class="bp-item"><div class="bp-label">Allocated</div><div class="bp-value">${App.moneyShort(b.allocated)}</div></div>
            ${b.workflow_type === 'full' ? `<div class="bp-item"><div class="bp-label">Committed</div><div class="bp-value">${App.moneyShort(b.committed)}</div></div>` : ''}
            <div class="bp-item"><div class="bp-label">Used</div><div class="bp-value">${App.moneyShort(b.used)}</div></div>
            <div class="bp-item"><div class="bp-label">Available Budget</div><div class="bp-value ${b.available > 0 ? 'good' : 'danger'}">${App.moneyShort(b.available)}</div></div>
            <div class="bp-item"><div class="bp-label">Sanction Balance</div><div class="bp-value ${balance > 0 ? 'good' : 'danger'}">${App.moneyShort(balance)}</div></div>`;
        validateAmount();
    }

    function validateAmount() {
        const amt = parseFloat(document.getElementById('pAmount').value || 0);
        const warn = document.getElementById('pAmountWarn');
        const save = document.getElementById('prSave');
        let msg = '';
        if (amt > 0 && selCtx.maxAllowed >= 0) {
            if (amt > selCtx.balance + 0.001) {
                msg = 'Exceeds the remaining sanction balance of ' + App.money(selCtx.balance) + '.';
            } else if (selCtx.workflow !== 'full' && amt > selCtx.available + 0.001) {
                msg = 'Insufficient budget balance. Requested amount exceeds the available budget of ' + App.money(selCtx.available) + '.';
            }
        }
        warn.textContent = msg;
        warn.style.display = msg ? 'block' : 'none';
        save.disabled = !!msg;
    }
    document.getElementById('pAmount').addEventListener('input', validateAmount);
    document.getElementById('pSanction').addEventListener('change', onSanctionChange);

    async function load() {
        const q = new URLSearchParams({ per_page: 1000 });
        if (fDept.value) q.set('department_id', fDept.value);
        if (document.getElementById('fMaintenanceCategory').value) q.set('maintenance_category', document.getElementById('fMaintenanceCategory').value);
        if (document.getElementById('fWorkLocation').value) q.set('work_location', document.getElementById('fWorkLocation').value);
        if (document.getElementById('fStatus').value) q.set('status', document.getElementById('fStatus').value);

        const rows = (await App.api('/api/purchase-requests?' + q)).data;
        if (table) table.destroy();
        document.querySelector('#tblPRs tbody').innerHTML = rows.map(r => {
            const own = !isDeptHead || parseInt(r.created_by) === myId;
            return `
            <tr>
                <td><strong class="text-primary">${App.esc(r.pr_no)}</strong></td>
                <td>${App.esc(r.parent_sanction_no || '—')}</td>
                <td>${App.esc(r.department_name)}</td>
                <td>${r.department_code === 'MNT' ? (r.work_location_name ? `<div class="small text-secondary">${App.esc(r.maintenance_category || '')} · ${App.esc(r.work_location_name)}</div>` : '—') : `${App.esc(r.unit_name || '—')}${r.work_location_name ? `<div class="small text-secondary">${App.esc(r.maintenance_category || 'Housekeeping')} · ${App.esc(r.work_location_name)}</div>` : ''}`}</td>
                <td>
                    ${App.esc(r.title)}
                    ${r.attachment_path ? '<i class="fa-solid fa-paperclip text-secondary ms-1" title="Has attachment"></i>' : ''}
                    ${r.reject_reason ? `<div class="text-danger" style="font-size:.72rem">Reason: ${App.esc(r.reject_reason)}</div>` : ''}
                </td>
                <td>${App.money(r.amount)}</td>
                <td>${App.badge(r.status)}</td>
                <td>${App.esc(r.created_by_name || '—')}</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="${App.base}/purchase-requests/${r.id}/view" title="View"><i class="fa-solid fa-eye"></i></a>
                    ${canCreate && own && ['draft','rejected'].includes(r.status) ? `
                        <button class="btn btn-sm btn-outline-secondary act-edit" data-id="${r.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>` : ''}
                    ${canCreate && own && r.status === 'draft' ? `
                        <button class="btn btn-sm btn-outline-primary act-submit" data-id="${r.id}" title="Submit for approval"><i class="fa-solid fa-paper-plane"></i></button>` : ''}
                    ${canApprove && r.status === 'submitted' ? `
                        <button class="btn btn-sm btn-outline-success act-approve" data-id="${r.id}" title="Approve"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-sm btn-outline-danger act-reject" data-id="${r.id}" title="Reject"><i class="fa-solid fa-xmark"></i></button>` : ''}
                    ${canDelete && ['draft','rejected'].includes(r.status) ? `<button class="btn btn-sm btn-outline-danger act-del" data-id="${r.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : ''}
                </td>
            </tr>`;
        }).join('');
        table = App.dataTable('#tblPRs', { columnDefs: [{ orderable: false, targets: -1 }] });
        window._prRows = Object.fromEntries(rows.map(r => [r.id, r]));
    }

    document.querySelector('#tblPRs tbody').addEventListener('click', async e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const id = btn.dataset.id;
        const row = window._prRows[id];

        if (btn.classList.contains('act-edit')) {
            editing = id;
            await openForEdit(row);
        } else if (btn.classList.contains('act-submit')) {
            if (!await App.confirmAction('Submit for approval?', row.pr_no + ' — ' + App.money(row.amount))) return;
            try {
                await App.api('/api/purchase-requests/' + id + '/submit', { method: 'POST' });
                App.toast('success', 'Submitted for approval');
                load();
            } catch (err) {
                Swal.fire({ icon: 'warning', title: 'Submission blocked', text: err.message, confirmButtonColor: '#0071e3' });
            }
        } else if (btn.classList.contains('act-approve')) {
            if (!await App.confirmAction('Approve requisition?', row.pr_no + ' — ' + App.money(row.amount))) return;
            try {
                const res = await App.api('/api/purchase-requests/' + id + '/approve', { method: 'POST' });
                App.toast('success', res.message || 'Approved');
                load();
            } catch (err) { Swal.fire({ icon: 'warning', title: 'Approval blocked', text: err.message, confirmButtonColor: '#0071e3' }); }
        } else if (btn.classList.contains('act-reject')) {
            const { value: reason, isConfirmed } = await Swal.fire({
                title: 'Reject ' + row.pr_no + '?',
                input: 'textarea', inputLabel: 'Rejection Reason',
                showCancelButton: true, confirmButtonText: 'Reject', confirmButtonColor: '#ff375f',
                inputValidator: value => !value || !value.trim() ? 'Rejection reason is required.' : undefined,
            });
            if (!isConfirmed) return;
            try {
                await App.api('/api/purchase-requests/' + id + '/reject', { method: 'POST', body: { reason } });
                App.toast('success', 'Requisition rejected');
                load();
            } catch (err) { App.toast('error', err.message); }
        } else if (btn.classList.contains('act-del')) {
            if (!await App.confirmAction('Delete requisition?', row.pr_no + ' will be permanently removed.', 'Yes, delete')) return;
            try {
                await App.api('/api/purchase-requests/' + id, { method: 'DELETE' });
                App.toast('success', 'Requisition deleted');
                load();
            } catch (err) { App.toast('error', err.message); }
        }
    });

    async function openForEdit(row) {
        document.getElementById('prModalTitle').textContent = 'Edit ' + row.pr_no;
        document.getElementById('grpSanction').style.display = 'none';
        await loadSanctions();
        // Preselect the parent sanction so the budget panel + units populate.
        document.getElementById('pSanction').value = row.sanction_id;
        await onSanctionChange();
        if (row.unit_id) document.getElementById('pUnit').value = row.unit_id;
        document.getElementById('pAmount').value = row.amount;
        document.getElementById('pTitle').value = row.title;
        document.getElementById('pDesc').value = row.description || '';
        document.getElementById('pRemarks').value = row.remarks || '';
        App.bindAmountWords('#pAmount', '#pAmountWords');
        validateAmount();
        modal.show();
    }

    document.getElementById('btnNew')?.addEventListener('click', async () => {
        editing = null;
        document.getElementById('prModalTitle').textContent =
         <?= json_encode(
             Auth::role() === 'department_head'
               ? 'New Purchase Raising'
               : 'New Purchase Requisition') ?>;
        document.getElementById('grpSanction').style.display = '';
        document.getElementById('prForm').reset();
        document.getElementById('grpUnit').style.display = 'none';
        await loadSanctions();
        await onSanctionChange();
        modal.show();
    });

    document.getElementById('prForm').addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData();
        fd.set('amount', document.getElementById('pAmount').value);
        fd.set('title', document.getElementById('pTitle').value);
        fd.set('description', document.getElementById('pDesc').value);
        fd.set('remarks', document.getElementById('pRemarks').value);
        const unitVisible = document.getElementById('grpUnit').style.display !== 'none';
        if (unitVisible) fd.set('unit_id', document.getElementById('pUnit').value);
        const file = document.getElementById('pFile').files[0];
        if (file) fd.set('attachment', file);

        try {
            if (editing) {
                fd.set('_method', 'PUT');
                await App.api('/api/purchase-requests/' + editing, { method: 'POST', body: fd });
            } else {
                fd.set('sanction_id', document.getElementById('pSanction').value);
                await App.api('/api/purchase-requests', { method: 'POST', body: fd });
            }
            modal.hide();
            App.toast('success', 'Purchase requisition saved');
            load();
        } catch (err) { App.toast('error', err.message); }
    });

    ['fDept', 'fStatus', 'fMaintenanceCategory', 'fWorkLocation'].forEach(id => document.getElementById(id).addEventListener('change', load));
    document.getElementById('expCsv').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/purchase-requests/export?format=csv'; });
    document.getElementById('expExcel').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/purchase-requests/export?format=excel'; });

    load();
});
</script>
