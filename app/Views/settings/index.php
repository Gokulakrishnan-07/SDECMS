<?php $u = auth_user(); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Settings</h1>
        <span class="page-sub">Profile, security and preferences</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="glass-card table-card mb-3">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-user me-2 text-primary"></i>Profile</h6>
            <form id="profileForm">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="sfName" value="<?= e($u['name']) ?>" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="<?= e($u['email']) ?>" disabled>
                    <small class="text-secondary">Ask an administrator to change your email.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" id="sfPhone" value="<?= e($u['phone'] ?? '') ?>" maxlength="20">
                </div>
                <button class="btn btn-primary btn-sm">Save Profile</button>
            </form>
        </div>

        <div class="glass-card table-card">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-lock me-2 text-primary"></i>Change Password</h6>
            <form id="passwordForm">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="sfCurrent" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password <small class="text-secondary">(min 8 characters)</small></label>
                    <input type="password" class="form-control" id="sfNew" minlength="8" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="sfConfirm" minlength="8" required autocomplete="new-password">
                </div>
                <button class="btn btn-primary btn-sm">Change Password</button>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="glass-card table-card">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i>Preferences</h6>
            <form id="prefForm">
                <div class="mb-4">
                    <label class="form-label d-block">Theme</label>
                    <div class="btn-group" role="group">
                        <?php foreach (['light' => 'fa-sun', 'dark' => 'fa-moon', 'auto' => 'fa-circle-half-stroke'] as $mode => $icon): ?>
                        <input type="radio" class="btn-check" name="theme" id="theme_<?= $mode ?>" value="<?= $mode ?>"
                               <?= ($u['theme'] ?? 'auto') === $mode ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary btn-sm" for="theme_<?= $mode ?>">
                            <i class="fa-solid <?= $icon ?> me-1"></i><?= ucfirst($mode) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="sfNotifInapp" <?= ($u['notify_inapp'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="sfNotifInapp">In-app notifications</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="sfNotifEmail" <?= ($u['notify_email'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="sfNotifEmail">Email notifications <small class="text-secondary">(language-ready; requires SMTP configuration)</small></label>
                </div>
                <button class="btn btn-primary btn-sm">Save Preferences</button>
            </form>
        </div>

        <div class="glass-card table-card mt-3">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-circle-info me-2 text-primary"></i>System</h6>
            <div class="small text-secondary">
                <div class="d-flex justify-content-between py-1"><span>Application</span><strong class="text-body"><?= e(config('app.name')) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Institution</span><strong class="text-body"><?= e(config('app.org')) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Your Role</span><strong class="text-body"><?= e(role_label($u['role'])) ?></strong></div>
                <div class="d-flex justify-content-between py-1"><span>Session Timeout</span><strong class="text-body"><?= (int) config('app.session_timeout') / 60 ?> minutes</strong></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('profileForm').addEventListener('submit', async e => {
        e.preventDefault();
        try {
            await App.api('/api/settings/profile', { method: 'POST', body: {
                name: document.getElementById('sfName').value,
                phone: document.getElementById('sfPhone').value,
            }});
            App.toast('success', 'Profile updated');
        } catch (err) { App.toast('error', err.message); }
    });

    document.getElementById('passwordForm').addEventListener('submit', async e => {
        e.preventDefault();
        const np = document.getElementById('sfNew').value;
        if (np !== document.getElementById('sfConfirm').value) {
            App.toast('error', 'Passwords do not match');
            return;
        }
        try {
            await App.api('/api/settings/password', { method: 'POST', body: {
                current_password: document.getElementById('sfCurrent').value,
                new_password: np,
            }});
            App.toast('success', 'Password changed');
            e.target.reset();
        } catch (err) { App.toast('error', err.message); }
    });

    document.getElementById('prefForm').addEventListener('submit', async e => {
        e.preventDefault();
        const theme = document.querySelector('input[name="theme"]:checked').value;
        try {
            await App.api('/api/settings/preferences', { method: 'POST', body: {
                theme,
                notify_inapp: document.getElementById('sfNotifInapp').checked ? 1 : 0,
                notify_email: document.getElementById('sfNotifEmail').checked ? 1 : 0,
            }});
            localStorage.setItem('secms-theme', theme);
            document.documentElement.setAttribute('data-bs-theme',
                theme === 'auto'
                    ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : theme);
            App.toast('success', 'Preferences saved');
        } catch (err) { App.toast('error', err.message); }
    });
});
</script>
