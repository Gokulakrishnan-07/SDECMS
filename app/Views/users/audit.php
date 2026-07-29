<div class="page-head">
    <div>
        <h1 class="page-title">Audit Logs</h1>
        <span class="page-sub">Every create, update, delete, approval and login</span>
    </div>
</div>

<div class="glass-card table-card">
    <div class="table-responsive">
        <table class="table" id="tblAudit" style="width:100%">
            <thead>
                <tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th><th>IP</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const rows = (await App.api('/api/audit-logs?per_page=100')).data;
    document.querySelector('#tblAudit tbody').innerHTML = rows.map(r => `
        <tr>
            <td class="text-nowrap">${App.esc(r.created_at)}</td>
            <td>${App.esc(r.user_name || 'System')}</td>
            <td>${App.badge(r.action)}</td>
            <td>${App.esc(r.table_name)}</td>
            <td>${App.esc(r.record_id || '—')}</td>
            <td>${App.esc(r.description || '')}</td>
            <td>${App.esc(r.ip_address || '')}</td>
        </tr>`).join('');
    App.dataTable('#tblAudit', { order: [[0, 'desc']] });
});
</script>
