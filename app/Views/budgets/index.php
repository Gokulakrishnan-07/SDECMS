<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Budget Allocation</h1>
        <span class="page-sub">Department budgets for each financial year</span>
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
        <?php if (Auth::can('budgets.manage')): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#budgetModal" id="btnNew">
            <i class="fa-solid fa-plus me-1"></i> Allocate Budget
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="glass-card table-card">
    <div class="row g-2 mb-3 align-items-center">
        <div class="col-6 col-md-3">
            <span class="fy-chip"><i class="fa-solid fa-calendar-check"></i> Active FY: <span id="activeFyLabel">…</span></span>
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="fDept"><option value="">All Departments</option></select>
        </div>
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="fStatus">
                <option value="">All Approval States</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="tblBudgets" style="width:100%">
            <thead>
                <tr>
                    <th>Department</th><th>Allocated</th><th>Committed</th><th>Used</th>
                    <th>Remaining</th><th>Utilization</th><th>Status</th><th>Approval</th><th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Create/Edit modal -->
<div class="modal fade" id="budgetModal" tabindex="-1">
    <div class="modal-dialog modal-form">
        <form class="modal-content" id="budgetForm">
            <div class="modal-header">
                <h5 class="modal-title" id="budgetModalTitle">Allocate Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="bId">
                <div class="row g-3">
                    <div class="col-md-6" id="grpDept">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="bDept" required></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Financial Year</label>
                        <input type="text" class="form-control" id="bFyLabel" readonly>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Allocated Amount (₹)</label>
                        <input type="number" class="form-control" id="bAmount" min="0" step="0.01" required>
                        <div class="words-preview" id="bAmountWords"></div>
                    </div>
                    <div class="col-12" id="grpPeriods" style="display:none">
                        <label class="form-label">Allocation by Period <small class="text-secondary">(optional — must not exceed the allocated amount)</small></label>
                        <div id="periodInputs" class="row g-2"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" id="bRemarks" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const canManage  = <?= Auth::can('budgets.manage') ? 'true' : 'false' ?>;
    const canApprove = <?= Auth::can('budgets.approve') ? 'true' : 'false' ?>;
    const canDelete  = <?= Auth::can('budgets.delete') ? 'true' : 'false' ?>;

    const modal = new bootstrap.Modal(document.getElementById('budgetModal'));
    let table, editing = null;

    App.bindAmountWords('#bAmount', '#bAmountWords');

    // Global active financial year — no manual FY selection anywhere.
    const [fys, depts] = await Promise.all([
        App.api('/api/financial-years').then(r => r.data),
        App.api('/api/departments').then(r => r.data),
    ]);
    const activeFy = fys.find(fy => fy.is_active == 1) || fys[0];
    document.getElementById('activeFyLabel').textContent = activeFy ? activeFy.label : '—';
    document.getElementById('bFyLabel').value = activeFy ? 'FY ' + activeFy.label : '';

    const fDept = document.getElementById('fDept');
    depts.forEach(d => fDept.add(new Option(d.name, d.id)));
    const params = new URLSearchParams(location.search);
    if (params.get('department_id')) fDept.value = params.get('department_id');

    const bDept = document.getElementById('bDept');
    depts.forEach(d => bDept.add(new Option(d.name, d.id)));

    // Load any periods defined for the active FY (for optional per-period allocation).
    let periods = [];
    if (activeFy) {
        try { periods = (await App.api('/api/financial-years/' + activeFy.id + '/periods')).data || []; } catch (e) {}
    }
    function renderPeriodInputs(values = {}) {
        const grp = document.getElementById('grpPeriods');
        const wrap = document.getElementById('periodInputs');
        if (!periods.length) { grp.style.display = 'none'; wrap.innerHTML = ''; return; }
        grp.style.display = '';
        wrap.innerHTML = periods.map(p => `
            <div class="col-md-6 col-lg-3">
                <label class="form-label small">${App.esc(p.name)}</label>
                <input type="number" class="form-control period-input" data-period="${p.id}" min="0" step="0.01" value="${values[p.id] || ''}">
            </div>`).join('');
    }

    async function load() {
        const q = new URLSearchParams({ per_page: 1000 });
        if (activeFy) q.set('financial_year_id', activeFy.id);
        if (fDept.value) q.set('department_id', fDept.value);
        if (document.getElementById('fStatus').value) q.set('approval_status', document.getElementById('fStatus').value);

        const rows = (await App.api('/api/budget?' + q)).data;

        if (table) table.destroy();
        const tbody = document.querySelector('#tblBudgets tbody');
        tbody.innerHTML = rows.map(r => `
            <tr>
                <td><i class="fa-solid ${App.esc(r.department_icon)} me-2 text-secondary"></i><strong>${App.esc(r.department_name)}</strong>
                    ${r.workflow_type === 'full' ? '<span class="badge-soft verified ms-1" title="Full procurement workflow">Full</span>' : ''}</td>
                <td>${App.money(r.allocated_amount)}</td>
                <td>${App.money(r.committed_amount)}</td>
                <td>${App.money(r.used_amount)}</td>
                <td>${App.money(r.remaining_amount)}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="orbit-progress flex-grow-1" style="width:70px"><span style="width:${Math.min(100, r.utilization)}%"></span></div>
                        <small>${r.utilization}%</small>
                    </div>
                </td>
                <td>${App.badge(r.status)}</td>
                <td>${App.badge(r.approval_status)}</td>
                <td class="text-end text-nowrap">
                    ${canManage ? `<button class="btn btn-sm btn-outline-secondary act-edit" data-id="${r.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>` : ''}
                    ${canApprove && r.approval_status === 'pending' ? `
                        <button class="btn btn-sm btn-outline-success act-approve" data-id="${r.id}" title="Approve"><i class="fa-solid fa-check"></i></button>
                        <button class="btn btn-sm btn-outline-danger act-reject" data-id="${r.id}" title="Reject"><i class="fa-solid fa-xmark"></i></button>` : ''}
                    ${canDelete && parseFloat(r.used_amount) === 0 ? `<button class="btn btn-sm btn-outline-danger act-del" data-id="${r.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : ''}
                </td>
            </tr>`).join('');

        table = App.dataTable('#tblBudgets', { columnDefs: [{ orderable: false, targets: -1 }] });
        window._budgetRows = Object.fromEntries(rows.map(r => [r.id, r]));
    }

    // Row actions (event delegation)
    document.querySelector('#tblBudgets tbody').addEventListener('click', async e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const id = btn.dataset.id;
        const row = window._budgetRows[id];

        if (btn.classList.contains('act-edit')) {
            editing = id;
            document.getElementById('budgetModalTitle').textContent = 'Edit Budget — ' + row.department_name;
            document.getElementById('grpDept').style.display = 'none';
            document.getElementById('bAmount').value = row.allocated_amount;
            document.getElementById('bAmount').dispatchEvent(new Event('input'));
            document.getElementById('bRemarks').value = row.remarks || '';
            renderPeriodInputs();
            modal.show();
        } else if (btn.classList.contains('act-approve') || btn.classList.contains('act-reject')) {
            const decision = btn.classList.contains('act-approve') ? 'approved' : 'rejected';
            if (!await App.confirmAction('Confirm ' + decision, row.department_name + ' — ' + App.money(row.allocated_amount))) return;
            try {
                await App.api('/api/budget/' + id + '/approve', { method: 'POST', body: { decision } });
                App.toast('success', 'Budget ' + decision);
                load();
            } catch (err) { App.toast('error', err.message); }
        } else if (btn.classList.contains('act-del')) {
            if (!await App.confirmAction('Delete budget?', 'This cannot be undone.', 'Yes, delete')) return;
            try {
                await App.api('/api/budget/' + id, { method: 'DELETE' });
                App.toast('success', 'Budget deleted');
                load();
            } catch (err) { App.toast('error', err.message); }
        }
    });

    document.getElementById('btnNew')?.addEventListener('click', () => {
        editing = null;
        document.getElementById('budgetModalTitle').textContent = 'Allocate Budget';
        document.getElementById('grpDept').style.display = '';
        document.getElementById('budgetForm').reset();
        document.getElementById('bFyLabel').value = activeFy ? 'FY ' + activeFy.label : '';
        document.getElementById('bAmountWords').textContent = '';
        renderPeriodInputs();
    });

    document.getElementById('budgetForm').addEventListener('submit', async e => {
        e.preventDefault();
        const body = {
            allocated_amount: document.getElementById('bAmount').value,
            remarks: document.getElementById('bRemarks').value,
        };
        // Optional per-period breakdown.
        const periodVals = [...document.querySelectorAll('.period-input')]
            .map(i => ({ period_id: parseInt(i.dataset.period), allocated_amount: parseFloat(i.value || 0) }))
            .filter(p => p.allocated_amount > 0);
        if (periodVals.length) {
            const sum = periodVals.reduce((s, p) => s + p.allocated_amount, 0);
            if (sum > parseFloat(body.allocated_amount || 0) + 0.001) {
                App.toast('error', 'Period allocations (' + App.money(sum) + ') exceed the allocated amount');
                return;
            }
            body.periods = periodVals;
        }
        try {
            if (editing) {
                await App.api('/api/budget/' + editing, { method: 'PUT', body });
            } else {
                body.department_id = document.getElementById('bDept').value;
                body.financial_year_id = activeFy.id;
                await App.api('/api/budget', { method: 'POST', body });
            }
            modal.hide();
            App.toast('success', 'Budget saved');
            load();
        } catch (err) { App.toast('error', err.message); }
    });

    ['fDept', 'fStatus'].forEach(id => document.getElementById(id).addEventListener('change', load));

    // Exports
    function exportUrl(format) {
        const q = new URLSearchParams({ format });
        if (activeFy) q.set('financial_year_id', activeFy.id);
        return App.base + '/api/budget/export?' + q;
    }
    document.getElementById('expCsv').addEventListener('click', e => { e.preventDefault(); location.href = exportUrl('csv'); });
    document.getElementById('expExcel').addEventListener('click', e => { e.preventDefault(); location.href = exportUrl('excel'); });

    load();
});
</script>
