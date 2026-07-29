<div class="page-head">
    <div>
        <h1 class="page-title">User Management</h1>
        <span class="page-sub">Accounts, roles, departments and login history</span>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" id="btnNew">
        <i class="fa-solid fa-user-plus me-1"></i> New User
    </button>
</div>

<div class="glass-card table-card">
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <select class="form-select form-select-sm" id="fRole">
                <option value="">All Roles</option>
                <option value="administrator">Administrator</option>
                <option value="principal">Principal</option>
                <option value="accounts">Accounts Department</option>
                <option value="department_head">Department Head</option>
                <option value="viewer">Viewer</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table" id="tblUsers" style="width:100%">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Last Login</th><th></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-form">
        <form class="modal-content" id="userForm">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="uName" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="uEmail" maxlength="150" required>
                </div>
                <div class="mb-3" id="grpPassword">
                    <label class="form-label">Password <small class="text-secondary">(min 8 characters)</small></label>
                    <input type="password" class="form-control" id="uPassword" minlength="8">
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Role</label>
                        <select class="form-select" id="uRole" required>
                            <option value="administrator">Administrator</option>
                            <option value="principal">Principal</option>
                            <option value="accounts">Accounts Department</option>
                            <option value="department_head">Department Head</option>
                            <option value="viewer" selected>Viewer</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Department</label>
                        <select class="form-select" id="uDept"><option value="">— None —</option></select>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" id="uPhone" maxlength="20">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Login history modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyTitle">Login History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="historyBody"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
    let table, editing = null, rowsById = {};

    const depts = (await App.api('/api/departments')).data;
    const uDept = document.getElementById('uDept');
    depts.forEach(d => uDept.add(new Option(d.name, d.id)));

    const roleLabels = {
        administrator: 'Administrator', principal: 'Principal', accounts: 'Accounts Department',
        department_head: 'Department Head', viewer: 'Viewer',
    };

    async function load() {
        const q = new URLSearchParams({ per_page: 1000 });
        if (document.getElementById('fRole').value) q.set('role', document.getElementById('fRole').value);
        const rows = (await App.api('/api/users?' + q)).data;
        rowsById = Object.fromEntries(rows.map(r => [r.id, r]));

        if (table) table.destroy();
        document.querySelector('#tblUsers tbody').innerHTML = rows.map(r => `
            <tr class="${r.is_active == 0 ? 'opacity-50' : ''}">
                <td><strong>${App.esc(r.name)}</strong></td>
                <td>${App.esc(r.email)}</td>
                <td>${App.esc(roleLabels[r.role] || r.role)}</td>
                <td>${App.esc(r.department_name || '—')}</td>
                <td>${r.is_active == 1 ? '<span class="badge-soft approved">Active</span>' : '<span class="badge-soft rejected">Disabled</span>'}</td>
                <td class="text-nowrap">${App.esc(r.last_login_at || 'Never')}</td>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-secondary act-edit" data-id="${r.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-sm btn-outline-secondary act-history" data-id="${r.id}" title="Login history"><i class="fa-solid fa-clock-rotate-left"></i></button>
                    <button class="btn btn-sm btn-outline-warning act-reset" data-id="${r.id}" title="Reset password"><i class="fa-solid fa-key"></i></button>
                    <button class="btn btn-sm btn-outline-${r.is_active == 1 ? 'danger' : 'success'} act-toggle" data-id="${r.id}" title="${r.is_active == 1 ? 'Disable' : 'Enable'}">
                        <i class="fa-solid fa-power-off"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger act-del" data-id="${r.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>`).join('');
        table = App.dataTable('#tblUsers', { columnDefs: [{ orderable: false, targets: -1 }] });
    }

    document.querySelector('#tblUsers tbody').addEventListener('click', async e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const id = btn.dataset.id;
        const row = rowsById[id];

        if (btn.classList.contains('act-edit')) {
            editing = id;
            document.getElementById('userModalTitle').textContent = 'Edit ' + row.name;
            document.getElementById('grpPassword').style.display = 'none';
            document.getElementById('uName').value = row.name;
            document.getElementById('uEmail').value = row.email;
            document.getElementById('uRole').value = row.role;
            document.getElementById('uDept').value = row.department_id || '';
            document.getElementById('uPhone').value = row.phone || '';
            modal.show();
        } else if (btn.classList.contains('act-history')) {
            const rows = (await App.api('/api/users/' + id + '/login-history')).data;
            document.getElementById('historyTitle').textContent = 'Login History — ' + row.name;
            document.getElementById('historyBody').innerHTML = rows.length
                ? '<table class="table table-sm small"><thead><tr><th>When</th><th>IP Address</th><th>Browser</th></tr></thead><tbody>' +
                  rows.map(h => `<tr><td class="text-nowrap">${App.esc(h.logged_in_at)}</td><td>${App.esc(h.ip_address)}</td><td class="text-truncate" style="max-width:380px">${App.esc(h.user_agent)}</td></tr>`).join('') +
                  '</tbody></table>'
                : '<div class="empty-state"><i class="fa-solid fa-clock"></i>No logins recorded</div>';
            historyModal.show();
        } else if (btn.classList.contains('act-reset')) {
            const { value: password, isConfirmed } = await Swal.fire({
                title: 'Reset password for ' + row.name,
                input: 'password', inputLabel: 'New password (min 8 characters)',
                inputAttributes: { minlength: 8, autocomplete: 'new-password' },
                showCancelButton: true, confirmButtonColor: '#0071e3',
            });
            if (!isConfirmed || !password) return;
            try {
                await App.api('/api/users/' + id + '/reset-password', { method: 'POST', body: { password } });
                App.toast('success', 'Password reset');
            } catch (err) { App.toast('error', err.message); }
        } else if (btn.classList.contains('act-toggle')) {
            try {
                const res = await App.api('/api/users/' + id + '/toggle', { method: 'POST' });
                App.toast('success', res.message);
                load();
            } catch (err) { App.toast('error', err.message); }
        } else if (btn.classList.contains('act-del')) {
            if (!await App.confirmAction('Delete user?', row.email + ' will be permanently removed.', 'Yes, delete')) return;
            try {
                await App.api('/api/users/' + id, { method: 'DELETE' });
                App.toast('success', 'User deleted');
                load();
            } catch (err) { App.toast('error', err.message); }
        }
    });

    document.getElementById('btnNew').addEventListener('click', () => {
        editing = null;
        document.getElementById('userModalTitle').textContent = 'New User';
        document.getElementById('grpPassword').style.display = '';
        document.getElementById('userForm').reset();
    });

    document.getElementById('userForm').addEventListener('submit', async e => {
        e.preventDefault();
        const body = {
            name: document.getElementById('uName').value,
            email: document.getElementById('uEmail').value,
            role: document.getElementById('uRole').value,
            department_id: document.getElementById('uDept').value || null,
            phone: document.getElementById('uPhone').value,
        };
        try {
            if (editing) {
                await App.api('/api/users/' + editing, { method: 'PUT', body });
            } else {
                body.password = document.getElementById('uPassword').value;
                if (!body.password || body.password.length < 8) {
                    App.toast('error', 'Password must be at least 8 characters');
                    return;
                }
                await App.api('/api/users', { method: 'POST', body });
            }
            modal.hide();
            App.toast('success', 'User saved');
            load();
        } catch (err) { App.toast('error', err.message); }
    });

    document.getElementById('fRole').addEventListener('change', load);
    load();
});
</script>
