<div class="page-head">
    <div>
        <h1 class="page-title">Financial Year Management</h1>
        <span class="page-sub">Indian financial years (April – March); exactly one is active</span>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fyModal" id="btnNew">
        <i class="fa-solid fa-plus me-1"></i> New Financial Year
    </button>
</div>

<div class="glass-card table-card">
    <div class="table-responsive">
        <table class="table" id="tblFy" style="width:100%">
            <thead>
                <tr><th>Label</th><th>Year Code</th><th>Start</th><th>End</th><th>Status</th><th></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="fyModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="fyForm">
            <div class="modal-header">
                <h5 class="modal-title" id="fyModalTitle">New Financial Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Label (e.g. 2026-27)</label>
                    <input type="text" class="form-control" id="yLabel" maxlength="20" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Year Code <small class="text-secondary">(used in sanction numbers, e.g. 2027)</small></label>
                    <input type="number" class="form-control" id="yCode" min="2000" max="2999" required>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="yStart" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" id="yEnd" required>
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

<!-- Periods modal -->
<div class="modal fade" id="periodModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="periodForm">
            <div class="modal-header">
                <h5 class="modal-title" id="periodTitle">Periods</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary">Divide this financial year into periods so budgets can be allocated per period.</p>
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="quarters">Quarters (Q1–Q4)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="halves">Half Years (H1–H2)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="none">Clear</button>
                </div>
                <label class="form-label">Period names <small class="text-secondary">(one per line — edit freely for custom periods)</small></label>
                <textarea class="form-control" id="periodNames" rows="6" placeholder="Quarter 1&#10;Quarter 2&#10;…"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Periods</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = new bootstrap.Modal(document.getElementById('fyModal'));
    const periodModal = new bootstrap.Modal(document.getElementById('periodModal'));
    let table, editing = null, rowsById = {}, periodFyId = null;

    async function load() {
        const rows = (await App.api('/api/financial-years')).data;
        rowsById = Object.fromEntries(rows.map(r => [r.id, r]));
        if (table) table.destroy();
        document.querySelector('#tblFy tbody').innerHTML = rows.map(r => `
            <tr>
                <td><strong>FY ${App.esc(r.label)}</strong></td>
                <td>${App.esc(r.year_code)}</td>
                <td>${App.esc(r.start_date)}</td>
                <td>${App.esc(r.end_date)}</td>
                <td>${r.is_active == 1 ? '<span class="badge-soft approved">Active</span>' : '<span class="badge-soft draft">Inactive</span>'}</td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-secondary act-periods" data-id="${r.id}" title="Manage periods"><i class="fa-solid fa-table-cells-large"></i></button>
                    <button class="btn btn-sm btn-outline-secondary act-edit" data-id="${r.id}"><i class="fa-solid fa-pen"></i></button>
                    ${r.is_active != 1 ? `<button class="btn btn-sm btn-outline-success act-activate" data-id="${r.id}" title="Set active"><i class="fa-solid fa-toggle-on"></i></button>` : ''}
                </td>
            </tr>`).join('');
        table = App.dataTable('#tblFy', { columnDefs: [{ orderable: false, targets: -1 }] });
    }

    document.querySelector('#tblFy tbody').addEventListener('click', async e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const id = btn.dataset.id;
        const row = rowsById[id];

        if (btn.classList.contains('act-edit')) {
            editing = id;
            document.getElementById('fyModalTitle').textContent = 'Edit FY ' + row.label;
            document.getElementById('yLabel').value = row.label;
            document.getElementById('yCode').value = row.year_code;
            document.getElementById('yStart').value = row.start_date;
            document.getElementById('yEnd').value = row.end_date;
            modal.show();
        } else if (btn.classList.contains('act-activate')) {
            if (!await App.confirmAction('Activate FY ' + row.label + '?', 'This deactivates every other financial year.')) return;
            try {
                await App.api('/api/financial-years/' + id + '/activate', { method: 'POST' });
                App.toast('success', 'FY ' + row.label + ' activated');
                load();
            } catch (err) { App.toast('error', err.message); }
        } else if (btn.classList.contains('act-periods')) {
            periodFyId = id;
            document.getElementById('periodTitle').textContent = 'Periods — FY ' + row.label;
            const periods = (await App.api('/api/financial-years/' + id + '/periods')).data || [];
            document.getElementById('periodNames').value = periods.map(p => p.name).join('\n');
            periodModal.show();
        }
    });

    document.querySelectorAll('#periodForm [data-preset]').forEach(b => b.addEventListener('click', () => {
        const preset = b.dataset.preset;
        const map = {
            quarters: 'Quarter 1\nQuarter 2\nQuarter 3\nQuarter 4',
            halves: 'Half Year 1\nHalf Year 2',
            none: '',
        };
        document.getElementById('periodNames').value = map[preset];
    }));

    document.getElementById('periodForm').addEventListener('submit', async e => {
        e.preventDefault();
        const names = document.getElementById('periodNames').value.split('\n').map(s => s.trim()).filter(Boolean);
        try {
            await App.api('/api/financial-years/' + periodFyId + '/periods', { method: 'POST', body: { names } });
            App.toast('success', 'Periods saved');
            periodModal.hide();
        } catch (err) { App.toast('error', err.message); }
    });

    document.getElementById('btnNew').addEventListener('click', () => {
        editing = null;
        document.getElementById('fyModalTitle').textContent = 'New Financial Year';
        document.getElementById('fyForm').reset();
    });

    document.getElementById('fyForm').addEventListener('submit', async e => {
        e.preventDefault();
        const body = {
            label: document.getElementById('yLabel').value,
            year_code: document.getElementById('yCode').value,
            start_date: document.getElementById('yStart').value,
            end_date: document.getElementById('yEnd').value,
        };
        try {
            if (editing) await App.api('/api/financial-years/' + editing, { method: 'PUT', body });
            else await App.api('/api/financial-years', { method: 'POST', body });
            modal.hide();
            App.toast('success', 'Financial year saved');
            load();
        } catch (err) { App.toast('error', err.message); }
    });

    load();
});
</script>
