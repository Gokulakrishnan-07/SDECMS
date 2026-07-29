<div class="page-head">
    <div>
        <h1 class="page-title">Notifications</h1>
        <span class="page-sub">Everything that needs your attention</span>
    </div>
    <button class="btn btn-outline-secondary btn-sm" id="btnReadAll">
        <i class="fa-solid fa-check-double me-1"></i> Mark all read
    </button>
</div>

<div class="glass-card table-card" id="notifPage">
    <div class="skeleton mb-2" style="height:56px"></div>
    <div class="skeleton mb-2" style="height:56px"></div>
    <div class="skeleton" style="height:56px"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const icons = {
        budget_alert: 'fa-triangle-exclamation text-warning',
        budget_updated: 'fa-sack-dollar text-primary',
        budget_approved: 'fa-circle-check text-success',
        budget_rejected: 'fa-circle-xmark text-danger',
        pr_new: 'fa-file-signature text-primary',
        pr_approved: 'fa-circle-check text-success',
        pr_rejected: 'fa-circle-xmark text-danger',
        sanction_new: 'fa-stamp text-primary',
        sanction_approved: 'fa-circle-check text-success',
        sanction_rejected: 'fa-circle-xmark text-danger',
        sanction_verified: 'fa-clipboard-check text-primary',
    };

    async function load() {
        const res = (await App.api('/api/notifications?limit=100')).data;
        const wrap = document.getElementById('notifPage');
        if (!res.items.length) {
            wrap.innerHTML = '<div class="empty-state"><i class="fa-regular fa-bell-slash"></i>You have no notifications</div>';
            return;
        }
        wrap.innerHTML = res.items.map(n => `
            <a class="notif-item rounded-3 ${n.is_read == 0 ? 'unread' : ''}" data-id="${n.id}"
               href="${App.base + (n.link || '#')}">
                <span class="stat-icon tint-blue" style="width:40px;height:40px;font-size:.95rem;margin:0">
                    <i class="fa-solid ${icons[n.type] || 'fa-bell text-primary'}"></i>
                </span>
                <div class="flex-grow-1">
                    <div class="n-title">${App.esc(n.title)} ${n.is_read == 0 ? '<span class="badge-soft submitted ms-1">New</span>' : ''}</div>
                    <div class="n-msg">${App.esc(n.message)}</div>
                    <div class="n-time">${App.esc(n.created_at)}</div>
                </div>
            </a>`).join('');

        wrap.querySelectorAll('.notif-item.unread').forEach(el => {
            el.addEventListener('click', () => {
                App.api('/api/notifications/' + el.dataset.id + '/read', { method: 'POST' }).catch(() => {});
            });
        });
    }

    document.getElementById('btnReadAll').addEventListener('click', async () => {
        await App.api('/api/notifications/read-all', { method: 'POST' });
        App.toast('success', 'All notifications marked read');
        load();
    });

    load();
});
</script>
