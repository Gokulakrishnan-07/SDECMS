<?php use App\Core\Auth; ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Purchase Orders</h1>
        <span class="page-sub">Vendors, line items, GST, invoices and status history</span>
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
        <?php if (Auth::can('purchase_orders.create')): ?>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#poModal" id="btnNew">
            <i class="fa-solid fa-plus me-1"></i> New Purchase Order
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
                <option value="issued">Issued</option>
                <option value="received">Received</option>
                <option value="paid">Paid</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="tblPOs" style="width:100%">
            <thead>
                <tr>
                    <th>PO No</th><th>Vendor</th><th>Department</th><th>Linked PR</th>
                    <th>Total</th><th>Invoice</th><th>Status</th><th>Date</th><th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Create/Edit modal -->
<div class="modal fade" id="poModal" tabindex="-1">
    <div class="modal-dialog modal-form">
        <form class="modal-content" id="poForm">
            <div class="modal-header">
                <h5 class="modal-title" id="poModalTitle">New Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-4" id="grpDept">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="oDept" required></select>
                    </div>
                    <div class="col-md-4" id="grpHousekeepingWorkLocation" style="display:none"><label class="form-label">Work Location / Service Area</label><select class="form-select" id="oHousekeepingWorkLocation"><option value="">— Select location —</option></select></div>
                    <div class="col-12" id="grpHousekeepingOther" style="display:none"><label class="form-label">Other Work Location / Service Area</label><input type="text" class="form-control" id="oOtherWorkLocation" maxlength="255"></div>
                    <div class="col-md-4" id="grpMaintenanceCategory" style="display:none"><label class="form-label">Maintenance Category</label><select class="form-select" id="oMaintenanceCategory"><option value="">— Select category —</option><option>Civil</option><option>Electrical</option><option>Plumbing</option></select></div>
                    <div class="col-md-4" id="grpWorkLocation" style="display:none"><label class="form-label">Work Location</label><select class="form-select" id="oWorkLocation"><option value="">— Select work location —</option></select></div>
                    <div class="col-md-4" id="grpPr">
                        <label class="form-label">Linked Purchase Request <small class="text-secondary">(optional, approved only)</small></label>
                        <select class="form-select" id="oPr"><option value="">— None —</option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vendor Name</label>
                        <input type="text" class="form-control" id="oVendor" maxlength="200" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vendor GSTIN</label>
                        <input type="text" class="form-control" id="oGstin" maxlength="20">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Invoice No</label>
                        <input type="text" class="form-control" id="oInvoice" maxlength="50">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">GST %</label>
                        <input type="number" class="form-control" id="oGst" min="0" max="100" step="0.01" value="18">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Vendor Address</label>
                        <input type="text" class="form-control" id="oAddress" maxlength="500">
                    </div>
                </div>

                <label class="form-label mt-2">Line Items</label>
                <div class="table-responsive">
                    <table class="table" id="tblItems">
                        <thead>
                            <tr><th style="width:28%">Item</th><th>Description</th><th style="width:10%">Qty</th><th style="width:15%">Unit Price</th><th style="width:14%">Amount</th><th style="width:44px"></th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddItem">
                    <i class="fa-solid fa-plus me-1"></i> Add Item
                </button>

                <div class="d-flex justify-content-end mt-3">
                    <div style="min-width:260px" class="small">
                        <div class="d-flex justify-content-between py-1"><span>Subtotal</span><strong id="sumSub">₹0.00</strong></div>
                        <div class="d-flex justify-content-between py-1"><span>GST</span><strong id="sumGst">₹0.00</strong></div>
                        <div class="d-flex justify-content-between py-1 border-top"><span>Total</span><strong id="sumTotal" class="text-primary">₹0.00</strong></div>
                        <div class="words-preview text-end" id="oTotalWords"></div>
                    </div>
                </div>

                <div class="mt-2">
                    <label class="form-label">Remarks</label>
                    <input type="text" class="form-control" id="oRemarks" maxlength="500">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Detail modal -->
<div class="modal fade" id="poDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="poDetailTitle">Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="poDetailBody"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const canManage = <?= Auth::can('purchase_orders.manage') ? 'true' : 'false' ?>;
    const canDelete = <?= Auth::can('purchase_orders.delete') ? 'true' : 'false' ?>;

    const modal = new bootstrap.Modal(document.getElementById('poModal'));
    const detail = new bootstrap.Modal(document.getElementById('poDetail'));
    let table, editing = null;

    const depts = (await App.api('/api/departments')).data;
    const locations = (await App.api('/api/work-locations')).data;
    locations.forEach(l => document.getElementById('fWorkLocation').add(new Option(l.label, l.value)));
    document.getElementById('fWorkLocation').add(new Option('Others', 'other'));
    const fDept = document.getElementById('fDept');
    depts.forEach(d => fDept.add(new Option(d.name, d.id)));
    const oDept = document.getElementById('oDept');
    depts.forEach(d => oDept.add(new Option(d.name, d.id)));
    locations.forEach(l => document.getElementById('oWorkLocation').add(new Option(l.label, l.value)));
    const oHousekeepingLocation = document.getElementById('oHousekeepingWorkLocation');
    locations.forEach(l => oHousekeepingLocation.add(new Option(l.label, l.value)));
    oHousekeepingLocation.add(new Option('Others', 'other'));
    function maintenanceFields() {
        const on = (depts.find(d => String(d.id) === String(oDept.value)) || {}).code === 'MNT';
        document.getElementById('grpMaintenanceCategory').style.display = on ? '' : 'none';
        document.getElementById('grpWorkLocation').style.display = on ? '' : 'none';
        document.getElementById('oMaintenanceCategory').required = on;
        document.getElementById('oWorkLocation').required = on;
    }
    oDept.addEventListener('change', maintenanceFields);
    function housekeepingFields() {
        const on = (depts.find(d => String(d.id) === String(oDept.value)) || {}).code === 'HKP';
        document.getElementById('grpHousekeepingWorkLocation').style.display = on ? '' : 'none';
        document.getElementById('grpHousekeepingOther').style.display = on && oHousekeepingLocation.value === 'other' ? '' : 'none';
        oHousekeepingLocation.required = on;
        document.getElementById('oOtherWorkLocation').required = on && oHousekeepingLocation.value === 'other';
        if (!on) { oHousekeepingLocation.value = ''; document.getElementById('oOtherWorkLocation').value = ''; }
    }
    oHousekeepingLocation.addEventListener('change', housekeepingFields);
    document.getElementById('oPr').addEventListener('change', () => {
        const linked = !!document.getElementById('oPr').value;
        document.getElementById('oMaintenanceCategory').required = !linked && document.getElementById('grpMaintenanceCategory').style.display !== 'none';
        document.getElementById('oWorkLocation').required = !linked && document.getElementById('grpWorkLocation').style.display !== 'none';
    });

    async function loadApprovedPRs() {
        const rows = (await App.api('/api/purchase-requests?status=approved&per_page=500')).data;
        const oPr = document.getElementById('oPr');
        oPr.length = 1;
        rows.forEach(r => oPr.add(new Option(r.pr_no + ' — ' + r.title, r.id)));
    }

    // ---- Items editor
    const itemsBody = document.querySelector('#tblItems tbody');
    function addItemRow(item = {}) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input class="form-control form-control-sm it-name" value="${App.esc(item.item_name || '')}" required></td>
            <td><input class="form-control form-control-sm it-desc" value="${App.esc(item.description || '')}"></td>
            <td><input type="number" class="form-control form-control-sm it-qty" min="0.01" step="0.01" value="${item.quantity || 1}"></td>
            <td><input type="number" class="form-control form-control-sm it-price" min="0" step="0.01" value="${item.unit_price || 0}"></td>
            <td class="it-amount align-middle small fw-semibold">₹0.00</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger it-del"><i class="fa-solid fa-trash"></i></button></td>`;
        itemsBody.appendChild(tr);
        recalc();
    }
    itemsBody.addEventListener('input', recalc);
    itemsBody.addEventListener('click', e => {
        if (e.target.closest('.it-del')) { e.target.closest('tr').remove(); recalc(); }
    });
    document.getElementById('btnAddItem').addEventListener('click', () => addItemRow());
    document.getElementById('oGst').addEventListener('input', recalc);

    function collectItems() {
        return [...itemsBody.querySelectorAll('tr')].map(tr => ({
            item_name: tr.querySelector('.it-name').value.trim(),
            description: tr.querySelector('.it-desc').value.trim(),
            quantity: parseFloat(tr.querySelector('.it-qty').value) || 0,
            unit_price: parseFloat(tr.querySelector('.it-price').value) || 0,
        })).filter(i => i.item_name);
    }

    function recalc() {
        let sub = 0;
        itemsBody.querySelectorAll('tr').forEach(tr => {
            const amount = (parseFloat(tr.querySelector('.it-qty').value) || 0) *
                           (parseFloat(tr.querySelector('.it-price').value) || 0);
            tr.querySelector('.it-amount').textContent = App.money(amount);
            sub += amount;
        });
        const gst = sub * ((parseFloat(document.getElementById('oGst').value) || 0) / 100);
        document.getElementById('sumSub').textContent = App.money(sub);
        document.getElementById('sumGst').textContent = App.money(gst);
        document.getElementById('sumTotal').textContent = App.money(sub + gst);
        document.getElementById('oTotalWords').textContent = App.amountInWords(sub + gst);
    }

    // ---- Listing
    async function load() {
        const q = new URLSearchParams({ per_page: 1000 });
        if (fDept.value) q.set('department_id', fDept.value);
        if (document.getElementById('fMaintenanceCategory').value) q.set('maintenance_category', document.getElementById('fMaintenanceCategory').value);
        if (document.getElementById('fWorkLocation').value) q.set('work_location', document.getElementById('fWorkLocation').value);
        if (document.getElementById('fStatus').value) q.set('status', document.getElementById('fStatus').value);

        const rows = (await App.api('/api/purchase-orders?' + q)).data;
        if (table) table.destroy();
        document.querySelector('#tblPOs tbody').innerHTML = rows.map(r => `
            <tr>
                <td><strong class="text-primary">${App.esc(r.po_no)}</strong></td>
                <td>${App.esc(r.vendor_name)}</td>
                <td>${App.esc(r.department_name)}${r.maintenance_category ? `<div class="small text-secondary">${App.esc(r.maintenance_category)} · ${App.esc(r.work_location_name || '—')}</div>` : ''}</td>
                <td>${App.esc(r.pr_no || '—')}</td>
                <td>${App.money(r.total_amount)}</td>
                <td>${App.esc(r.invoice_no || '—')}</td>
                <td>${App.badge(r.status)}</td>
                <td class="text-nowrap">${App.esc((r.created_at || '').slice(0, 16))}</td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="${App.base}/purchase-orders/${r.id}/view" title="View"><i class="fa-solid fa-eye"></i></a>
                    <a class="btn btn-sm btn-outline-secondary" href="${App.base}/purchase-orders/${r.id}/print" target="_blank" title="Print"><i class="fa-solid fa-print"></i></a>
                    ${canManage && r.status === 'draft' ? `<button class="btn btn-sm btn-outline-secondary act-edit" data-id="${r.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>` : ''}
                    ${canManage && !['paid','cancelled'].includes(r.status) ? `<button class="btn btn-sm btn-outline-primary act-status" data-id="${r.id}" title="Change status"><i class="fa-solid fa-arrow-right-arrow-left"></i></button>` : ''}
                    ${canDelete && r.status === 'draft' ? `<button class="btn btn-sm btn-outline-danger act-del" data-id="${r.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>` : ''}
                </td>
            </tr>`).join('');
        table = App.dataTable('#tblPOs', { columnDefs: [{ orderable: false, targets: -1 }] });
        window._poRows = Object.fromEntries(rows.map(r => [r.id, r]));
    }

    const NEXT_STATUS = { draft: ['issued', 'cancelled'], issued: ['received', 'cancelled'], received: ['paid', 'cancelled'] };

    document.querySelector('#tblPOs tbody').addEventListener('click', async e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const id = btn.dataset.id;
        const row = window._poRows[id];

        if (btn.classList.contains('act-view')) {
            const po = (await App.api('/api/purchase-orders/' + id)).data;
            document.getElementById('poDetailTitle').textContent = po.po_no + ' — ' + po.vendor_name;
            document.getElementById('poDetailBody').innerHTML = `
                <div class="row g-3 small mb-3">
                    <div class="col-md-4"><span class="text-secondary d-block">Department</span><strong>${App.esc(po.department_name)}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Linked PR</span><strong>${App.esc(po.pr_no || '—')}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Status</span>${App.badge(po.status)}</div>
                    <div class="col-md-4"><span class="text-secondary d-block">GSTIN</span><strong>${App.esc(po.vendor_gstin || '—')}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Invoice</span><strong>${App.esc(po.invoice_no || '—')}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Created by</span><strong>${App.esc(po.created_by_name || '—')}</strong></div>
                </div>
                <h6 class="small fw-bold">Items</h6>
                <table class="table table-sm small">
                    <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>${po.items.map(i => `
                        <tr><td>${App.esc(i.item_name)}${i.description ? '<div class="text-secondary">' + App.esc(i.description) + '</div>' : ''}</td>
                        <td>${i.quantity}</td><td>${App.money(i.unit_price)}</td><td class="text-end">${App.money(i.amount)}</td></tr>`).join('')}
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end">${App.money(po.subtotal)}</td></tr>
                        <tr><td colspan="3" class="text-end">GST (${po.gst_percent}%)</td><td class="text-end">${App.money(po.gst_amount)}</td></tr>
                        <tr><td colspan="3" class="text-end fw-bold">Total</td><td class="text-end fw-bold">${App.money(po.total_amount)}</td></tr>
                    </tfoot>
                </table>
                <h6 class="small fw-bold mt-3">Status History</h6>
                ${po.history.map(h => `
                    <div class="d-flex justify-content-between border-bottom py-2 small" style="border-color:var(--border)!important">
                        <div>${App.badge(h.status)} ${h.remarks ? '<span class="text-secondary ms-2">' + App.esc(h.remarks) + '</span>' : ''}</div>
                        <div class="text-secondary">${App.esc(h.changed_by_name || 'System')} · ${App.esc(h.created_at)}</div>
                    </div>`).join('')}`;
            detail.show();
        } else if (btn.classList.contains('act-edit')) {
            editing = id;
            const po = (await App.api('/api/purchase-orders/' + id)).data;
            document.getElementById('poModalTitle').textContent = 'Edit ' + po.po_no;
            document.getElementById('grpDept').style.display = 'none';
            document.getElementById('grpPr').style.display = 'none';
            document.getElementById('oVendor').value = po.vendor_name;
            document.getElementById('oGstin').value = po.vendor_gstin || '';
            document.getElementById('oAddress').value = po.vendor_address || '';
            document.getElementById('oInvoice').value = po.invoice_no || '';
            document.getElementById('oGst').value = po.gst_percent;
            document.getElementById('oRemarks').value = po.remarks || '';
            document.getElementById('oDept').value = po.department_id;
            oHousekeepingLocation.value = po.work_location_type === 'other' ? 'other' : (po.work_location_type ? po.work_location_type + ':' + po.work_location_id : '');
            document.getElementById('oOtherWorkLocation').value = po.other_work_location || '';
            housekeepingFields();
            itemsBody.innerHTML = '';
            po.items.forEach(addItemRow);
            modal.show();
        } else if (btn.classList.contains('act-status')) {
            const options = Object.fromEntries((NEXT_STATUS[row.status] || []).map(s => [s, s.charAt(0).toUpperCase() + s.slice(1)]));
            const { value: status, isConfirmed } = await Swal.fire({
                title: 'Update status of ' + row.po_no,
                input: 'select', inputOptions: options, inputPlaceholder: 'Select new status',
                text: 'Marking as Paid records the expense and consumes the department budget.',
                showCancelButton: true, confirmButtonColor: '#0071e3',
            });
            if (!isConfirmed || !status) return;
            try {
                await App.api('/api/purchase-orders/' + id + '/status', { method: 'POST', body: { status } });
                App.toast('success', 'Status updated to ' + status);
                load();
            } catch (err) { App.toast('error', err.message); }
        } else if (btn.classList.contains('act-del')) {
            if (!await App.confirmAction('Delete purchase order?', row.po_no + ' will be permanently removed.', 'Yes, delete')) return;
            try {
                await App.api('/api/purchase-orders/' + id, { method: 'DELETE' });
                App.toast('success', 'Purchase order deleted');
                load();
            } catch (err) { App.toast('error', err.message); }
        }
    });

    document.getElementById('btnNew')?.addEventListener('click', async () => {
        editing = null;
        document.getElementById('poModalTitle').textContent = 'New Purchase Order';
        document.getElementById('grpDept').style.display = '';
        document.getElementById('grpPr').style.display = '';
        document.getElementById('poForm').reset();
        document.getElementById('oGst').value = 18;
        maintenanceFields();
        document.getElementById('oOtherWorkLocation').value = '';
        housekeepingFields();
        itemsBody.innerHTML = '';
        addItemRow();
        loadApprovedPRs();
    });

    document.getElementById('poForm').addEventListener('submit', async e => {
        e.preventDefault();
        const items = collectItems();
        if (!items.length) { App.toast('error', 'Add at least one line item'); return; }

        const body = {
            vendor_name: document.getElementById('oVendor').value,
            vendor_gstin: document.getElementById('oGstin').value,
            vendor_address: document.getElementById('oAddress').value,
            invoice_no: document.getElementById('oInvoice').value,
            gst_percent: document.getElementById('oGst').value,
            maintenance_category: document.getElementById('oMaintenanceCategory').value,
            work_location: document.getElementById('oWorkLocation').value,
            other_work_location: document.getElementById('oOtherWorkLocation').value,
            remarks: document.getElementById('oRemarks').value,
            items,
        };
        try {
            if (editing) {
                await App.api('/api/purchase-orders/' + editing, { method: 'PUT', body });
                App.toast('success', 'Purchase order updated');
            } else {
                body.department_id = oDept.value;
                body.purchase_request_id = document.getElementById('oPr').value || null;
                if ((depts.find(d => String(d.id) === String(oDept.value)) || {}).code === 'HKP') body.work_location = oHousekeepingLocation.value;
                const res = await App.api('/api/purchase-orders', { method: 'POST', body });
                App.toast('success', res.data.po_no + ' created');
            }
            modal.hide();
            load();
        } catch (err) { App.toast('error', err.message); }
    });

    ['fDept', 'fStatus', 'fMaintenanceCategory', 'fWorkLocation'].forEach(id => document.getElementById(id).addEventListener('change', load));
    document.getElementById('expCsv').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/purchase-orders/export?format=csv'; });
    document.getElementById('expExcel').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/purchase-orders/export?format=excel'; });

    load();
});
</script>
