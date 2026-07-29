<?php $u = auth_user(); ?>
<div class="page-head">
    <div>
        <h1 class="page-title" id="greeting">Good Morning,</h1>
        <span class="page-sub"><?= e($u['name'] ?? '') ?> · <?= e(role_label($u['role'] ?? '')) ?>
            <?php if (!empty($financialYear)): ?> · FY <?= e($financialYear['label']) ?><?php endif; ?>
        </span>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4" id="statCards">
    <?php
    $stats = [
        ['key' => 'total_budget',      'label' => 'Total Budget',              'icon' => 'fa-wallet',            'tint' => 'tint-blue',   'money' => true],
        ['key' => 'allocated_budget',  'label' => 'Allocated Budget',          'icon' => 'fa-sack-dollar',       'tint' => 'tint-purple', 'money' => true],
        ['key' => 'committed_budget',  'label' => 'Committed Amount',          'icon' => 'fa-lock',              'tint' => 'tint-orange', 'money' => true],
        ['key' => 'remaining_budget',  'label' => 'Remaining Budget',          'icon' => 'fa-piggy-bank',        'tint' => 'tint-green',  'money' => true],
        ['key' => 'total_sanctions',   'label' => 'Total Sanction Amount',     'icon' => 'fa-stamp',             'tint' => 'tint-teal',   'money' => true],
        ['key' => 'pending_requests',  'label' => 'Pending Requisitions',      'icon' => 'fa-hourglass-half',    'tint' => 'tint-orange', 'money' => false],
        ['key' => 'approved_requests', 'label' => 'Approved Requisitions',     'icon' => 'fa-circle-check',      'tint' => 'tint-green',  'money' => false],
        ['key' => 'purchase_orders',   'label' => 'Purchase Orders',           'icon' => 'fa-file-invoice',      'tint' => 'tint-blue',   'money' => false],
        ['key' => 'monthly_expense',   'label' => 'Monthly Expense',           'icon' => 'fa-money-bill-wave',   'tint' => 'tint-red',    'money' => true],
        ['key' => 'utilization',       'label' => 'Budget Utilization %',      'icon' => 'fa-gauge-high',        'tint' => 'tint-purple', 'money' => false, 'suffix' => '%'],
    ];
    foreach ($stats as $s): ?>
    <div class="col-6 col-md-4 col-xl">
        <div class="stat-card">
            <div class="stat-icon <?= $s['tint'] ?>"><i class="fa-solid <?= $s['icon'] ?>"></i></div>
            <div class="stat-value" data-stat="<?= $s['key'] ?>" data-money="<?= $s['money'] ? 1 : 0 ?>"
                 data-suffix="<?= e($s['suffix'] ?? '') ?>">
                <span class="skeleton d-inline-block" style="width:70px">&nbsp;</span>
            </div>
            <div class="stat-label"><?= e($s['label']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Department Orbit Showcase -->
<section class="orbit-section">
    <div class="orbit-stage" id="orbitStage">
        <div class="orbit-center">
            <img src="<?= asset('img/logo.svg') ?>" alt="Logo">
            <h3>Departments</h3>
            <p>Hover a card for details · click to open its budget</p>
        </div>
    </div>
</section>

<!-- Department Budget Health -->
<section class="glass-card table-card mb-4">
    <h6 class="fw-bold mb-3"><i class="fa-solid fa-heart-pulse me-2 text-primary"></i>Department Budget Health</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0" id="tblHealth">
            <thead>
                <tr>
                    <th>Department</th><th>Allocated</th><th>Used / Committed</th>
                    <th>Remaining</th><th style="min-width:170px">Usage</th><th>Health</th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="6"><div class="skeleton" style="height:38px"></div></td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Charts -->
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-chart-column me-2 text-primary"></i>Budget vs Actual Spending</h6>
            <div class="chart-wrap" style="height:320px"><canvas id="chBudgetVsActual"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Department-wise Budget</h6>
            <div class="chart-wrap" style="height:320px"><canvas id="chDeptBudget"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-chart-line me-2 text-primary"></i>Monthly Expenses</h6>
            <div class="chart-wrap"><canvas id="chMonthly"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-chart-simple me-2 text-primary"></i>Quarterly Spending</h6>
            <div class="chart-wrap"><canvas id="chQuarterly"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-calendar me-2 text-primary"></i>Yearly Spending</h6>
            <div class="chart-wrap"><canvas id="chYearly"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-6">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-arrow-trend-up me-2 text-primary"></i>Expense Trend</h6>
            <div class="chart-wrap"><canvas id="chExpenseTrend"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-6">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-signature me-2 text-primary"></i>Sanction Trend</h6>
            <div class="chart-wrap"><canvas id="chSanctionTrend"></canvas></div>
        </div>
    </div>
</div>

<!-- Widgets -->
<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-triangle-exclamation me-2 text-warning"></i>Budget Alerts — Low Budget Departments</h6>
            <div id="lowBudgetList"><div class="skeleton mb-2" style="height:40px"></div><div class="skeleton" style="height:40px"></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent Activities</h6>
            <div id="recentActivities"><div class="skeleton mb-2" style="height:40px"></div><div class="skeleton" style="height:40px"></div></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    // Greeting by local time
    const h = new Date().getHours();
    document.getElementById('greeting').textContent =
        (h < 12 ? 'Good Morning,' : h < 17 ? 'Good Afternoon,' : 'Good Evening,');

    let payload;
    try {
        payload = (await App.api('/api/dashboard')).data;
    } catch (e) {
        App.toast('error', e.message);
        return;
    }

    // ---- Stat counters
    document.querySelectorAll('[data-stat]').forEach(el => {
        const key = el.dataset.stat;
        const value = parseFloat(payload.stats[key] ?? 0);
        const isMoney = el.dataset.money === '1';
        const suffix = el.dataset.suffix || '';
        el.innerHTML = '';
        App.animateCounter(el, value, v =>
            (isMoney ? App.moneyShort(v) : Math.round(v).toLocaleString('en-IN')) + suffix);
    });

    // ---- Department orbit showcase
    App.initOrbit(document.getElementById('orbitStage'), payload.departments || []);

    // ---- Charts
    const opts = App.baseChartOptions;
    const c = App.chartColors();
    const bva = payload.charts.budget_vs_actual || [];

    new Chart(document.getElementById('chBudgetVsActual'), {
        type: 'bar',
        data: {
            labels: bva.map(r => r.department),
            datasets: [
                { label: 'Allocated', data: bva.map(r => r.allocated), backgroundColor: c.blue + 'cc', borderRadius: 8 },
                { label: 'Actual', data: bva.map(r => r.used), backgroundColor: c.purple + 'cc', borderRadius: 8 },
            ],
        },
        options: opts(),
    });

    const palette = [c.blue, c.purple, c.teal, c.green, c.orange, c.red,
        '#af52de', '#ff2d55', '#64d2ff', '#ffd60a', '#32ade6', '#a2845e'];
    const deptWithBudget = bva.filter(r => r.allocated > 0);
    new Chart(document.getElementById('chDeptBudget'), {
        type: 'doughnut',
        data: {
            labels: deptWithBudget.map(r => r.department),
            datasets: [{
                data: deptWithBudget.map(r => r.allocated),
                backgroundColor: deptWithBudget.map((_, i) => palette[i % palette.length]),
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '62%',
            animation: { duration: 900, easing: 'easeOutQuart' },
            plugins: {
                legend: { position: 'right', labels: { color: App.chartColors().text, font: { family: 'Inter', size: 10 }, boxWidth: 10, usePointStyle: true } },
                tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + App.moneyShort(ctx.parsed) } },
            },
        },
    });

    const monthLabel = ym => new Date(ym + '-01').toLocaleDateString('en-IN', { month: 'short', year: '2-digit' });

    const monthly = payload.charts.monthly_expenses || [];
    new Chart(document.getElementById('chMonthly'), {
        type: 'bar',
        data: {
            labels: monthly.map(r => monthLabel(r.ym)),
            datasets: [{ label: 'Expenses', data: monthly.map(r => r.total), backgroundColor: c.teal + 'cc', borderRadius: 8 }],
        },
        options: opts(),
    });

    const quarterly = payload.charts.quarterly || [];
    new Chart(document.getElementById('chQuarterly'), {
        type: 'bar',
        data: {
            labels: quarterly.map(r => r.quarter),
            datasets: [{ label: 'Spending', data: quarterly.map(r => r.total), backgroundColor: c.orange + 'cc', borderRadius: 8 }],
        },
        options: opts(),
    });

    const yearly = payload.charts.yearly || [];
    new Chart(document.getElementById('chYearly'), {
        type: 'bar',
        data: {
            labels: yearly.map(r => r.label),
            datasets: [{ label: 'Spending', data: yearly.map(r => r.total), backgroundColor: c.green + 'cc', borderRadius: 8 }],
        },
        options: opts(),
    });

    function lineChart(id, rows, color, label) {
        new Chart(document.getElementById(id), {
            type: 'line',
            data: {
                labels: rows.map(r => monthLabel(r.ym)),
                datasets: [{
                    label, data: rows.map(r => r.total),
                    borderColor: color, borderWidth: 2.5,
                    pointRadius: 3, pointBackgroundColor: color,
                    tension: 0.4, fill: true,
                    backgroundColor: color + '18',
                }],
            },
            options: opts(),
        });
    }
    lineChart('chExpenseTrend', payload.charts.expense_trend || [], c.red, 'Expenses');
    lineChart('chSanctionTrend', payload.charts.sanction_trend || [], c.blue, 'Sanctions');

    // ---- Department Budget Health
    const health = payload.widgets.budget_health || [];
    const healthText = { healthy: 'Healthy', warning: 'Warning', critical: 'Critical', exceeded: 'Budget Exceeded' };
    document.querySelector('#tblHealth tbody').innerHTML = health.length
        ? health.map(h => {
            const pct = Math.min(100, parseFloat(h.usage_percent));
            const usedCommitted = parseFloat(h.used_amount) + parseFloat(h.committed_amount);
            return `
            <tr>
                <td><strong>${App.esc(h.department)}</strong>${h.workflow_type === 'full' ? ' <span class="badge-soft verified ms-1">Full</span>' : ''}</td>
                <td>${App.money(h.allocated_amount)}</td>
                <td>${App.money(usedCommitted)}</td>
                <td>${App.money(h.remaining_amount)}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="health-bar flex-grow-1"><span class="${h.health}" style="width:${pct}%"></span></div>
                        <small class="fw-semibold">${h.usage_percent}%</small>
                    </div>
                </td>
                <td><span class="badge-soft ${h.health}">${healthText[h.health] || h.health}</span></td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="6"><div class="empty-state py-4"><i class="fa-solid fa-inbox"></i>No budgets allocated yet</div></td></tr>';

    // ---- Widgets
    const low = payload.widgets.low_budget || [];
    document.getElementById('lowBudgetList').innerHTML = low.length
        ? low.map(d => `
            <div class="d-flex align-items-center justify-content-between py-2 border-bottom" style="border-color:var(--border)!important">
                <div>
                    <div class="fw-semibold small">${App.esc(d.name)}</div>
                    <div class="text-secondary" style="font-size:.72rem">Used ${App.moneyShort(d.used_amount)} of ${App.moneyShort(d.allocated_amount)}</div>
                </div>
                <span class="badge-soft rejected">${d.utilization}%</span>
            </div>`).join('')
        : '<div class="empty-state py-4"><i class="fa-solid fa-circle-check"></i>All departments are within budget</div>';

    const acts = payload.widgets.recent_activities || [];
    document.getElementById('recentActivities').innerHTML = acts.length
        ? acts.map(a => `
            <div class="d-flex gap-3 py-2 border-bottom" style="border-color:var(--border)!important">
                <span class="stat-icon tint-blue" style="width:34px;height:34px;font-size:.8rem;margin:0">
                    <i class="fa-solid fa-bolt"></i>
                </span>
                <div>
                    <div class="small">${App.esc(a.description || a.action + ' ' + a.table_name)}</div>
                    <div class="text-secondary" style="font-size:.72rem">${App.esc(a.user_name || 'System')} · ${App.esc(a.created_at)}</div>
                </div>
            </div>`).join('')
        : '<div class="empty-state py-4"><i class="fa-solid fa-inbox"></i>No recent activity</div>';
});
</script>
