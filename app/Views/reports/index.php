<div class="page-head">
    <div>
        <h1 class="page-title">Reports</h1>
        <span class="page-sub">Financial reports with charts and exports</span>
    </div>
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
</div>

<div class="glass-card table-card mb-3">
    <div class="row g-2">
        <div class="col-12 col-md-4">
            <label class="form-label">Report Type</label>
            <select class="form-select form-select-sm" id="rType">
                <option value="monthly">Monthly Report</option>
                <option value="quarterly">Quarterly Report</option>
                <option value="half_yearly">Half-Yearly Report</option>
                <option value="annual">Annual Report</option>
                <option value="budget_vs_actual">Budget vs Actual</option>
                <option value="department">Department Report</option>
                <option value="sanction">Sanction Report</option>
                <option value="purchase">Purchase Report</option>
                <option value="expense_analysis">Expense Analysis</option>
            </select>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">Financial Year</label>
            <select class="form-select form-select-sm" id="rFy"></select>
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label">Department</label>
            <select class="form-select form-select-sm" id="rDept"><option value="">All Departments</option></select>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="glass-card table-card">
            <h6 id="reportTitle" class="fw-bold mb-3">Report</h6>
            <div class="table-responsive" id="reportTableWrap">
                <div class="skeleton mb-2" style="height:36px"></div>
                <div class="skeleton mb-2" style="height:36px"></div>
                <div class="skeleton" style="height:36px"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="glass-card chart-card">
            <h6><i class="fa-solid fa-chart-column me-2 text-primary"></i>Visualisation</h6>
            <div class="chart-wrap" style="height:380px"><canvas id="reportChart"></canvas></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    let chart;

    const [fys, depts] = await Promise.all([
        App.api('/api/financial-years').then(r => r.data),
        App.api('/api/departments').then(r => r.data),
    ]);
    const rFy = document.getElementById('rFy');
    fys.forEach(fy => rFy.add(new Option('FY ' + fy.label + (fy.is_active == 1 ? ' (Active)' : ''), fy.id, false, fy.is_active == 1)));
    const rDept = document.getElementById('rDept');
    depts.forEach(d => rDept.add(new Option(d.name, d.id)));

    function currentQuery(extra = {}) {
        const q = new URLSearchParams({
            type: document.getElementById('rType').value,
            financial_year_id: rFy.value,
            ...extra,
        });
        if (rDept.value) q.set('department_id', rDept.value);
        return q;
    }

    async function load() {
        const wrap = document.getElementById('reportTableWrap');
        wrap.innerHTML = '<div class="skeleton mb-2" style="height:36px"></div><div class="skeleton" style="height:36px"></div>';

        let report;
        try {
            report = (await App.api('/api/reports?' + currentQuery())).data;
        } catch (e) {
            wrap.innerHTML = '<div class="empty-state"><i class="fa-solid fa-circle-exclamation"></i>' + App.esc(e.message) + '</div>';
            return;
        }

        document.getElementById('reportTitle').textContent = report.title + ' — FY ' + report.financial_year;
        const rows = report.rows || [];
        if (!rows.length) {
            wrap.innerHTML = '<div class="empty-state"><i class="fa-solid fa-inbox"></i>No data for this selection</div>';
            if (chart) { chart.destroy(); chart = null; }
            return;
        }

        // Table
        const keys = Object.keys(rows[0]);
        const isNum = k => rows.every(r => r[k] === null || r[k] === '' || !isNaN(parseFloat(r[k])));
        const moneyKeys = ['total', 'allocated', 'used', 'amount', 'allocated_amount', 'used_amount', 'remaining_amount', 'subtotal', 'gst_amount', 'total_amount'];
        wrap.innerHTML = '<table class="table"><thead><tr>' +
            keys.map(k => '<th>' + App.esc(k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())) + '</th>').join('') +
            '</tr></thead><tbody>' +
            rows.map(r => '<tr>' + keys.map(k => {
                let v = r[k];
                if (moneyKeys.includes(k) && v !== null && v !== '') v = App.money(v);
                return '<td>' + App.esc(v ?? '—') + '</td>';
            }).join('') + '</tr>').join('') +
            '</tbody></table>';

        // Chart: first label-ish column + numeric columns
        const labelKey = keys.find(k => !isNum(k)) || keys[0];
        const numKeys = keys.filter(k => k !== labelKey && isNum(k) && moneyKeys.concat(['utilization']).includes(k));
        const c = App.chartColors();
        const palette = [c.blue, c.purple, c.teal, c.green, c.orange, c.red];

        if (chart) chart.destroy();
        if (numKeys.length) {
            chart = new Chart(document.getElementById('reportChart'), {
                type: 'bar',
                data: {
                    labels: rows.map(r => r[labelKey]),
                    datasets: numKeys.map((k, i) => ({
                        label: k.replace(/_/g, ' '),
                        data: rows.map(r => parseFloat(r[k]) || 0),
                        backgroundColor: palette[i % palette.length] + 'cc',
                        borderRadius: 8,
                    })),
                },
                options: App.baseChartOptions(),
            });
        }
    }

    ['rType', 'rFy', 'rDept'].forEach(id => document.getElementById(id).addEventListener('change', load));
    document.getElementById('expCsv').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/reports/export?' + currentQuery({ format: 'csv' }); });
    document.getElementById('expExcel').addEventListener('click', e => { e.preventDefault(); location.href = App.base + '/api/reports/export?' + currentQuery({ format: 'excel' }); });

    load();
});
</script>
