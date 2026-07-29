/* ============================================================================
   SECMS shared front-end helpers: API wrapper (CSRF), theme, clock, sidebar,
   notifications bell, animated counters, DataTables defaults, orbit showcase.
   ========================================================================== */

window.App = (function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const base = (document.querySelector('meta[name="base-url"]')?.content || '/').replace(/\/$/, '');

    // ------------------------------------------------------------- API layer
    async function api(path, options = {}) {
        const opts = {
            method: options.method || 'GET',
            headers: {
                'X-CSRF-Token': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        };
        if (options.body instanceof FormData) {
            opts.body = options.body;                     // browser sets content-type
        } else if (options.body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(options.body);
        }

        const res = await fetch(base + path, opts);
        let json = null;
        try { json = await res.json(); } catch (e) { /* non-JSON (e.g. redirect) */ }

        if (res.status === 401) {
            window.location.href = base + '/login';
            throw new Error('Unauthenticated');
        }
        if (!res.ok) {
            let message = json?.message || 'Request failed (' + res.status + ')';
            // Surface the first field-level validation error so the user
            // sees WHAT is wrong, not just "Validation failed."
            const fields = json?.errors ? Object.entries(json.errors) : [];
            if (fields.length) {
                const [field, error] = fields[0];
                message = field.replace(/_/g, ' ') + ': ' + error;
            }
            const err = new Error(message);
            err.errors = json?.errors || {};
            err.status = res.status;
            throw err;
        }
        return json;
    }

    // ---------------------------------------------------------------- UI kit
    function toast(icon, title) {
        Swal.fire({
            toast: true, position: 'top-end', icon, title,
            showConfirmButton: false, timer: 2600, timerProgressBar: true,
        });
    }

    function confirmAction(title, text, confirmText = 'Yes, proceed') {
        return Swal.fire({
            title, text, icon: 'warning',
            showCancelButton: true,
            confirmButtonText: confirmText,
            confirmButtonColor: '#0071e3',
            cancelButtonColor: '#6e6e73',
            reverseButtons: true,
        }).then(r => r.isConfirmed);
    }

    function money(n) {
        n = parseFloat(n || 0);
        return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function moneyShort(n) {
        n = parseFloat(n || 0);
        if (Math.abs(n) >= 1e7) return '₹' + (n / 1e7).toFixed(2) + ' Cr';
        if (Math.abs(n) >= 1e5) return '₹' + (n / 1e5).toFixed(2) + ' L';
        return money(n);
    }

    function badge(status) {
        return '<span class="badge-soft ' + String(status).toLowerCase() + '">' +
            String(status).charAt(0).toUpperCase() + String(status).slice(1) + '</span>';
    }

    // --------------------------------------------------- Amount in words (INR)
    // Indian numbering system (Thousand/Lakh/Crore), no "Only" suffix.
    const _ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen'];
    const _tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    function _two(n) {
        return n < 20 ? _ones[n] : (_tens[Math.floor(n / 10)] + ' ' + _ones[n % 10]).trim();
    }
    function _three(n) {
        const h = Math.floor(n / 100), rest = n % 100, parts = [];
        if (h) parts.push(_ones[h] + ' Hundred');
        if (rest) parts.push(_two(rest));
        return parts.join(' ');
    }
    function _intWords(n) {
        if (n === 0) return 'Zero';
        const parts = [];
        const cr = Math.floor(n / 10000000); n %= 10000000;
        const la = Math.floor(n / 100000);   n %= 100000;
        const th = Math.floor(n / 1000);      n %= 1000;
        if (cr) parts.push(_intWords(cr) + ' Crore');
        if (la) parts.push(_two(la) + ' Lakh');
        if (th) parts.push(_two(th) + ' Thousand');
        if (n)  parts.push(_three(n));
        return parts.join(' ');
    }

    function amountInWords(amount) {
        amount = parseFloat(amount || 0);
        if (!amount || amount <= 0) return '';
        const rupees = Math.floor(amount);
        const paise = Math.round((amount - rupees) * 100);
        let out = rupees > 0 ? _intWords(rupees) + ' Rupees' : '';
        if (paise > 0) out = (out ? out + ' and ' : '') + _two(paise) + ' Paise';
        return out;
    }

    /**
     * Wire a live "amount in words" preview: as the user types in `input`,
     * write the words into `preview`. Both are elements or selectors.
     */
    function bindAmountWords(input, preview) {
        input = typeof input === 'string' ? document.querySelector(input) : input;
        preview = typeof preview === 'string' ? document.querySelector(preview) : preview;
        if (!input || !preview) return;
        const update = () => {
            const w = amountInWords(input.value);
            preview.textContent = w;
            preview.style.display = w ? '' : 'none';
        };
        input.addEventListener('input', update);
        update();
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    // ------------------------------------------------------ Animated counter
    function animateCounter(el, target, formatter = v => Math.round(v).toLocaleString('en-IN')) {
        const duration = 1100;
        const start = performance.now();
        function tick(now) {
            const t = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - t, 3);          // ease-out cubic
            el.textContent = formatter(target * eased);
            if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    // ----------------------------------------------------------------- Theme
    function applyTheme(mode) {
        const resolved = mode === 'auto'
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : mode;
        document.documentElement.setAttribute('data-bs-theme', resolved);
        const icon = document.querySelector('#themeToggle i');
        if (icon) icon.className = resolved === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }

    function initTheme() {
        applyTheme(localStorage.getItem('secms-theme') || 'auto');
        document.getElementById('themeToggle')?.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('secms-theme', next);
            applyTheme(next);
            api('/api/settings/preferences', { method: 'POST', body: { theme: next, notify_email: 1, notify_inapp: 1 } }).catch(() => {});
        });
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if ((localStorage.getItem('secms-theme') || 'auto') === 'auto') applyTheme('auto');
        });
    }

    // ----------------------------------------------------------------- Clock
    function initClock() {
        const dateEl = document.getElementById('clockDate');
        const timeEl = document.getElementById('clockTime');
        if (!dateEl || !timeEl) return;
        function update() {
            const now = new Date();
            dateEl.textContent = now.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
            timeEl.textContent = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        update();
        setInterval(update, 1000);
    }

    // --------------------------------------------------------------- Sidebar
    function initSidebar() {
        document.getElementById('sidebarToggle')?.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-open');
        });
        document.getElementById('sidebarBackdrop')?.addEventListener('click', () => {
            document.body.classList.remove('sidebar-open');
        });
        document.getElementById('sidebarCollapse')?.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('secms-sidebar', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
        });
        if (localStorage.getItem('secms-sidebar') === '1') document.body.classList.add('sidebar-collapsed');
    }

    // --------------------------------------------------------- Notifications
    function renderNotifications(items) {
        const list = document.getElementById('notifList');
        if (!list) return;
        if (!items.length) {
            list.innerHTML = '<div class="notif-empty">No notifications yet</div>';
            return;
        }
        list.innerHTML = items.map(n => `
            <a class="notif-item ${n.is_read == 0 ? 'unread' : ''}" href="${base + (n.link || '/notifications')}"
               data-id="${n.id}">
                <div>
                    <div class="n-title">${esc(n.title)}</div>
                    <div class="n-msg">${esc(n.message)}</div>
                    <div class="n-time">${esc(timeAgo(n.created_at))}</div>
                </div>
            </a>`).join('');
        list.querySelectorAll('.notif-item.unread').forEach(el => {
            el.addEventListener('click', () => {
                api('/api/notifications/' + el.dataset.id + '/read', { method: 'POST' }).catch(() => {});
            });
        });
    }

    function timeAgo(dateStr) {
        const s = Math.floor((Date.now() - new Date(dateStr.replace(' ', 'T'))) / 1000);
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + 'm ago';
        if (s < 86400) return Math.floor(s / 3600) + 'h ago';
        return Math.floor(s / 86400) + 'd ago';
    }

    function refreshNotifications() {
        api('/api/notifications?limit=10').then(res => {
            const unread = res.data.unread || 0;
            const badgeEl = document.getElementById('notifBadge');
            if (badgeEl) {
                badgeEl.textContent = unread > 99 ? '99+' : unread;
                badgeEl.classList.toggle('d-none', unread === 0);
            }
            renderNotifications(res.data.items || []);
        }).catch(() => {});
    }

    function initNotifications() {
        if (!document.getElementById('notifList')) return;
        refreshNotifications();
        setInterval(refreshNotifications, 30000);
        document.getElementById('notifReadAll')?.addEventListener('click', e => {
            e.stopPropagation();
            api('/api/notifications/read-all', { method: 'POST' }).then(refreshNotifications).catch(() => {});
        });
    }

    // -------------------------------------------------- Global page search
    function initGlobalSearch() {
        const input = document.getElementById('globalSearch');
        if (!input) return;
        input.addEventListener('input', () => {
            // Drives any DataTable on the page.
            if (window.jQuery && $.fn.dataTable) {
                $('.dataTable').each(function () {
                    $(this).DataTable().search(input.value).draw();
                });
            }
        });
    }

    // ---------------------------------------------------- DataTable defaults
    function dataTable(selector, extra = {}) {
        return $(selector).DataTable(Object.assign({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            language: {
                emptyTable: '<div class="empty-state"><i class="fa-solid fa-inbox"></i>No records found</div>',
                zeroRecords: '<div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i>No matching records</div>',
            },
        }, extra));
    }

    // ======================================================================
    // Department Orbit Showcase (Apple-style)
    // Cards float in a circular orbit around the logo; hover pauses + expands;
    // click navigates to the department budget page. GPU-friendly transforms.
    // ======================================================================
    function initOrbit(container, departments) {
        const stage = container;
        const cards = [];
        const N = departments.length;
        if (!N) return;

        const isMobile = () => window.innerWidth < 768;

        departments.forEach((d, i) => {
            const remaining = parseFloat(d.remaining_amount || 0);
            const allocated = parseFloat(d.allocated_amount || 0);
            const used = parseFloat(d.used_amount || 0);
            const pct = allocated > 0 ? Math.min(100, used / allocated * 100) : 0;

            const el = document.createElement('div');
            el.className = 'orbit-card';
            el.innerHTML = `
                <div class="oc-icon"><i class="fa-solid ${esc(d.icon || 'fa-building')}"></i></div>
                <div class="oc-name">${esc(d.name)}</div>
                <div class="oc-budget">${moneyShort(allocated)}</div>
                <div class="oc-status ${allocated > 0 ? 'text-success' : 'text-secondary'}">${allocated > 0 ? esc(d.approval_status) : 'no budget'}</div>
                <div class="orbit-progress"><span style="width:${pct.toFixed(1)}%"></span></div>
                <div class="oc-details">
                    Used: <strong>${moneyShort(used)}</strong><br>
                    Remaining: <strong>${moneyShort(remaining)}</strong><br>
                    Utilization: <strong>${pct.toFixed(1)}%</strong>
                </div>`;
            el.addEventListener('click', () => {
                window.location.href = base + '/budgets?department_id=' + d.id;
            });
            stage.appendChild(el);
            cards.push({ el, angle: (i / N) * Math.PI * 2, phase: Math.random() * Math.PI * 2 });
        });

        if (isMobile()) {                 // static responsive grid fallback
            cards.forEach(c => { c.el.style.opacity = 1; });
            return;
        }

        let rotation = 0;
        let speed = 0.00022;              // radians per ms — slow, premium
        let targetSpeed = speed;
        let paused = false;
        let last = performance.now();
        let entranceStart = null;
        const ENTRANCE_MS = 900;

        cards.forEach(c => {
            c.el.addEventListener('mouseenter', () => { paused = true; c.el.classList.add('expanded'); });
            c.el.addEventListener('mouseleave', () => { paused = false; c.el.classList.remove('expanded'); });
        });

        function frame(now) {
            if (!stage.isConnected) return;              // page navigated away
            const dt = Math.min(now - last, 64);
            last = now;
            if (entranceStart === null) entranceStart = now;
            const entrance = Math.min((now - entranceStart) / ENTRANCE_MS, 1);
            const entranceEase = 1 - Math.pow(1 - entrance, 3);

            targetSpeed = paused ? 0 : 0.00022;
            speed += (targetSpeed - speed) * 0.06;       // smooth ease in/out
            rotation += speed * dt;

            const w = stage.clientWidth;
            const h = stage.clientHeight;
            const rx = Math.max(w / 2 - 130, 180);       // ellipse radii
            const ry = Math.max(h / 2 - 100, 130);

            cards.forEach((c, i) => {
                const a = c.angle + rotation;
                const float = Math.sin(now / 1400 + c.phase) * 7;   // gentle bob
                const x = Math.cos(a) * rx * entranceEase;
                const y = (Math.sin(a) * ry + float) * entranceEase;
                const depth = (Math.sin(a) + 1) / 2;                 // 0 back – 1 front
                const scale = (0.86 + depth * 0.18) * (c.el.matches(':hover') ? 1.08 : 1);
                c.el.style.opacity = (0.55 + depth * 0.45) * entranceEase;
                c.el.style.zIndex = c.el.matches(':hover') ? 50 : Math.round(4 + depth * 20);
                c.el.style.transform = `translate3d(${x}px, ${y}px, 0) scale(${scale})`;
            });

            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    // ------------------------------------------------------------- Chart kit
    function chartColors() {
        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return {
            text: dark ? '#98989d' : '#6e6e73',
            grid: dark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)',
            blue: '#0071e3', green: '#34c759', orange: '#ff9f0a',
            red: '#ff375f', purple: '#5e5ce6', teal: '#30b0c7',
        };
    }

    function baseChartOptions() {
        const c = chartColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 900, easing: 'easeOutQuart' },
            plugins: {
                legend: { labels: { color: c.text, font: { family: 'Inter', size: 11 }, boxWidth: 12, usePointStyle: true } },
                tooltip: {
                    backgroundColor: 'rgba(28,28,30,.92)', cornerRadius: 12, padding: 12,
                    titleFont: { family: 'Inter' }, bodyFont: { family: 'Inter' },
                    callbacks: { label: ctx => ' ' + (ctx.dataset.label || '') + ': ' + moneyShort(ctx.parsed.y ?? ctx.parsed) },
                },
            },
            scales: {
                x: { ticks: { color: c.text, font: { family: 'Inter', size: 10 } }, grid: { color: 'transparent' } },
                y: { ticks: { color: c.text, font: { family: 'Inter', size: 10 }, callback: v => moneyShort(v) }, grid: { color: c.grid } },
            },
        };
    }

    // ---------------------------------------------------------------- Boot
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initClock();
        initSidebar();
        initNotifications();
        initGlobalSearch();
    });

    return {
        api, toast, confirmAction, money, moneyShort, badge, esc,
        animateCounter, dataTable, initOrbit, baseChartOptions, chartColors, base,
        amountInWords, bindAmountWords,
    };
})();
