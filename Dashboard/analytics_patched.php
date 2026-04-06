<?php
$activePage = 'analytics';
require_once __DIR__ . '/../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
    <link rel="stylesheet" href="../asset/universal.css?v=2">
    <link rel="stylesheet" href="../asset/analytics.css?v=2">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="analytics-layout">
    <section class="page-header">
        <div>
            <a href="./dashboard.php" class="back-link">← Back to Dashboard</a>
            <h1>Analytics</h1>
            <p>Interactive KPI analytics with database-driven filtering, drill-down, and staff comparison.</p>
        </div>
        <div class="filter-toolbar">
            <select id="yearFilter">
                <option value="">All Years</option>
            </select>
            <select id="departmentFilter">
                <option value="All Departments">All Departments</option>
            </select>
            <select id="kpiCategoryFilter">
                <option value="All Categories">All Categories</option>
            </select>
            <select id="periodFilter">
                <option value="Monthly">Monthly</option>
                <option value="Yearly">Yearly</option>
            </select>
            <button class="ghost-btn" id="resetTopFilters" type="button">Reset Filters</button>
        </div>
    </section>

    <section class="cards-grid">
        <article class="alert-card high-risk">
            <h3>High Risk Alert</h3>
            <div id="highRiskAlert">Loading...</div>
        </article>
        <article class="alert-card moderate-risk">
            <h3>Moderate Risk Alert</h3>
            <div id="moderateRiskAlert">Loading...</div>
        </article>
        <article class="stat-card current-overview">
            <h3>Current Overview</h3>
            <div class="three-stats">
                <div><strong id="totalStaff">0</strong><span>Total Staff</span></div>
                <div><strong id="avgKpi">0</strong><span>Average KPI %</span></div>
                <div><strong id="improvingCount">0</strong><span>Improving</span></div>
            </div>
        </article>
        <article class="stat-card workforce-overview">
            <h3>Workforce Overview</h3>
            <ul class="workforce-list">
                <li><span id="departmentsCount">0</span> Departments Monitored</li>
                <li><span id="topPerformersCount">0</span> Top Performers ≥ 85%</li>
                <li><span id="atRiskCount">0</span> Critical / At-Risk Staff</li>
            </ul>
        </article>
    </section>

   <section class="comparison-tool">
    <div class="comparison-title-wrap">
        <h2>Staff Comparison</h2>
        <p>Select any two staff members to compare performance trends, stability, and risk level.</p>
    </div>

    <div class="comparison-toolbar">
        <select id="compareDepartmentFilter">
            <option value="All Departments">Select Department</option>
        </select>

        <select id="performanceFilter">
            <option value="All Performance">Select Performance</option>
            <option value="top">Top</option>
            <option value="good">Good</option>
            <option value="average">Average</option>
            <option value="critical">Critical</option>
            <option value="at-risk">At-Risk</option>
        </select>

        <button class="ghost-btn" id="resetCompareFilters" type="button">✕ Reset Filters</button>
    </div>

    <div class="comparison-search-row">
        <div class="search-block">
            <input type="text" id="staff1Search" placeholder="Search by name...">
            <div class="suggestions" id="staff1Suggestions"></div>
        </div>

        <div class="search-block">
            <input type="text" id="staff2Search" placeholder="Search by name...">
            <div class="suggestions" id="staff2Suggestions"></div>
        </div>
    </div>

    <div class="comparison-selected-row">
        <div id="selectedStaff1" class="selected-chip empty">No staff selected</div>
        <div class="vs-pill">vs</div>
        <div id="selectedStaff2" class="selected-chip empty">No staff selected</div>
    </div>

    <div class="suggested-wrap">
        <div class="suggestion-group">
            <h4>Suggestions:</h4>
            <div id="topPairSuggestions"></div>
        </div>
        <div class="suggestion-group">
            <h4>&nbsp;</h4>
            <div id="averagePairSuggestions"></div>
        </div>
    </div>

    <div class="compare-actions">
        <button class="compare-main-btn" id="compareBtn" type="button" disabled>Compare Staff</button>
    </div>
    </section>

    <section class="chart-grid">
        <article class="card chart-card chart-span-2">
            <div class="chart-head"><h2>Performance Trends & Risk Prediction</h2></div>
            <div id="performanceTrendChart" class="chart"></div>
            <p class="interpretation" id="performanceTrendInsight"></p>
        </article>

        <article class="card chart-card chart-span-2">
            <div class="chart-head">
            <h2>Performance Distribution & Trends</h2>
            </div>

            <div class="dual-chart-wrap">
            <div>
            <div id="performanceDistributionChart" class="chart small"></div>
            </div>
            <div>
            <div id="trendDistributionChart" class="chart small"></div>
        </div>
    </div>

    <p class="interpretation" id="distributionInsight"></p>
</article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Workforce Overview</h2></div>
            <div id="departmentComparisonChart" class="chart"></div>
            <p class="interpretation" id="departmentInsight"></p>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>KPI vs Target by Category</h2></div>
            <div id="kpiVsTargetChart" class="chart"></div>
            <p class="interpretation" id="kpiGapInsight"></p>
        </article>

        <article class="card chart-card chart-span-2">
            <div class="chart-head"><h2>Score Movement Heatmap</h2></div>
            <div id="heatmapChart" class="chart heatmap"></div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Staff Performance Risk</h2></div>
            <div id="riskHistogramChart" class="chart"></div>
        </article>

        <article class="card table-card">
            <div class="chart-head"><h2>At-Risk Staff</h2></div>
            <div class="table-wrap">
                <table id="atRiskTable">
                    <thead>
                        <tr><th>Name</th><th>Department</th><th>Score</th><th>Trend</th><th>Action</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </article>

        <article class="card table-card chart-span-2">
            <div class="chart-head"><h2>Department Statistics</h2></div>
            <div class="table-wrap">
                <table id="departmentStatsTable">
                    <thead>
                        <tr><th>Department</th><th>Average Score</th><th>Top Performers</th><th>At Risk</th><th>Trend</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </article>
    </section>
</main>

<script>
const state = {
    year: '',
    department: 'All Departments',
    kpi_category: 'All Categories',
    period: 'Monthly',
    compareDepartment: 'All Departments',
    performance: 'All Performance',
    staff1: '',
    staff2: '',
    staff1Name: '',
    staff2Name: ''
};

async function fetchJson(params) {
    const url = './analytics_data_patched.php?' + new URLSearchParams(params).toString();
    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!response.ok) {
        const text = await response.text();
        throw new Error(text || 'Failed to load analytics data.');
    }
    return await response.json();
}

function setDepartmentOptions(departments) {
    const selects = [document.getElementById('departmentFilter'), document.getElementById('compareDepartmentFilter')];
    selects.forEach(select => {
        const currentValue = select.value || 'All Departments';
        select.innerHTML = '<option value="All Departments">All Departments</option>' +
            departments.map(dept => `<option value="${escapeHtml(dept)}">${escapeHtml(dept)}</option>`).join('');
        if ([...select.options].some(option => option.value === currentValue)) {
            select.value = currentValue;
        }
    });
}


function setCategoryOptions(categories) {
    const select = document.getElementById('kpiCategoryFilter');
    const currentValue = select.value || 'All Categories';
    select.innerHTML = '<option value="All Categories">All Categories</option>' +
        categories.map(cat => `<option value="${escapeHtml(cat)}">${escapeHtml(cat)}</option>`).join('');
    if ([...select.options].some(option => option.value === currentValue)) {
        select.value = currentValue;
    } else {
        select.value = 'All Categories';
        state.kpi_category = 'All Categories';
    }
}

function setYearOptions(trendRows) {
    const years = [...new Set(trendRows.map(row => String(row.period).slice(0, 4)).filter(Boolean))].sort();
    const yearFilter = document.getElementById('yearFilter');
    const current = yearFilter.value;
    yearFilter.innerHTML = '<option value="">All Years</option>' + years.map(year => `<option value="${year}">${year}</option>`).join('');
    if ([...yearFilter.options].some(option => option.value === current)) {
        yearFilter.value = current;
    }
}

function renderSummary(data) {
    document.getElementById('totalStaff').textContent = data.summary.total_staff;
    document.getElementById('avgKpi').textContent = data.summary.avg_kpi.toFixed(2) + '%';
    document.getElementById('improvingCount').textContent = data.summary.improving;
    document.getElementById('departmentsCount').textContent = data.summary.departments;
    document.getElementById('topPerformersCount').textContent = data.summary.top_performers;
    document.getElementById('atRiskCount').textContent = data.summary.at_risk;

    const high = data.high_risk_departments[0];
    document.getElementById('highRiskAlert').innerHTML = high
        ? `<strong>${escapeHtml(high.department)}</strong><p>${high.at_risk} staff need attention</p><p>Average score: ${high.score}%</p>`
        : '<p>No high-risk department under the current filters.</p>';

    const moderate = data.moderate_risk_departments[0];
    document.getElementById('moderateRiskAlert').innerHTML = moderate
        ? `<strong>${escapeHtml(moderate.department)}</strong><p>Average score: ${moderate.score}%</p><p>Below target but still recoverable.</p>`
        : '<p>No moderate-risk department under the current filters.</p>';
}

function renderCharts(data) {
    Plotly.react('performanceTrendChart', [
        {
            x: data.performance_trend.map(item => item.period),
            y: data.performance_trend.map(item => item.score),
            type: 'bar',
            name: 'Actual KPI %',
            marker: { color: data.performance_trend.map(item => item.atRisk ? '#ef4444' : '#4f46e5') },
            hovertemplate: '%{x}<br>Actual: %{y:.2f}%<extra></extra>'
        },
        {
            x: data.performance_trend.map(item => item.period),
            y: data.performance_trend.map(item => item.target),
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Target %',
            line: { color: '#14b8a6', width: 3 },
            hovertemplate: '%{x}<br>Target: %{y:.2f}%<extra></extra>'
        }
    ], {
        margin: { t: 10, r: 10, b: 40, l: 50 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: { range: [0, 100], title: 'KPI %' },
        legend: { orientation: 'h' }
    }, { responsive: true, displayModeBar: true });
    document.getElementById('performanceTrendInsight').textContent = data.insight;

    Plotly.react('performanceDistributionChart', [{
        labels: ['Top', 'Good', 'Average', 'Critical', 'At-Risk'],
        values: [
            data.performance_distribution.top,
            data.performance_distribution.good,
            data.performance_distribution.average,
            data.performance_distribution.critical,
            data.performance_distribution['at-risk']
        ],
        type: 'pie',
        hole: 0.48,
        marker: { colors: ['#10b981', '#3b82f6', '#f59e0b', '#fb7185', '#ef4444'] },
        textinfo: 'label+percent',
        hovertemplate: '%{label}: %{value}<extra></extra>'
    }], {
        margin: { t: 10, r: 10, b: 10, l: 10 },
        paper_bgcolor: 'transparent'
    }, { responsive: true, displayModeBar: false });

    Plotly.react('trendDistributionChart', [{
        labels: ['Improving', 'Stable', 'Declining'],
        values: [data.trend_distribution.up, data.trend_distribution.stable, data.trend_distribution.down],
        type: 'pie',
        hole: 0.58,
        marker: { colors: ['#10b981', '#3b82f6', '#ef4444'] },
        textinfo: 'label+percent',
        hovertemplate: '%{label}: %{value}<extra></extra>'
    }], {
        margin: { t: 10, r: 10, b: 10, l: 10 },
        paper_bgcolor: 'transparent'
    }, { responsive: true, displayModeBar: false });

    Plotly.react('departmentComparisonChart', [{
        x: data.department_comparison.map(item => item.score),
        y: data.department_comparison.map(item => item.department),
        type: 'bar',
        orientation: 'h',
        marker: { color: data.department_comparison.map(item => item.score < 60 ? '#ef4444' : '#6366f1') },
        hovertemplate: '%{y}<br>%{x:.2f}%<extra></extra>'
    }], {
        margin: { t: 10, r: 10, b: 30, l: 140 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        xaxis: { range: [0, 100], title: 'Average KPI %' },
        yaxis: { automargin: true }
    }, { responsive: true, displayModeBar: true });

    if (data.department_comparison.length > 0) {
        const best = data.department_comparison[0];
        const worst = data.department_comparison[data.department_comparison.length - 1];
        document.getElementById('departmentInsight').textContent = `${best.department} is leading at ${best.score}%, while ${worst.department} needs the most attention at ${worst.score}%.`;
    } else {
        document.getElementById('departmentInsight').textContent = 'No department data matched the current filters.';
    }

    Plotly.react('kpiVsTargetChart', [
        {
            x: data.kpi_vs_target.map(item => item.category),
            y: data.kpi_vs_target.map(item => item.actual),
            type: 'bar',
            name: 'Actual %',
            marker: { color: '#8b5cf6' },
            hovertemplate: '%{x}<br>Actual: %{y:.2f}%<extra></extra>'
        },
        {
            x: data.kpi_vs_target.map(item => item.category),
            y: data.kpi_vs_target.map(item => item.target),
            type: 'scatter',
            mode: 'lines+markers',
            name: 'Target %',
            marker: { color: '#14b8a6' },
            line: { color: '#14b8a6', width: 2 },
            hovertemplate: '%{x}<br>Target: %{y:.2f}%<extra></extra>'
        }
    ], {
        margin: { t: 10, r: 10, b: 110, l: 50 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: { range: [0, 100], title: 'Score %' },
        xaxis: { tickangle: -20 }
    }, { responsive: true, displayModeBar: true });

    if (data.kpi_vs_target.length > 0) {
        const worstGap = [...data.kpi_vs_target].sort((a, b) => a.gap - b.gap)[0];
        document.getElementById('kpiGapInsight').textContent = `${worstGap.category} has the largest shortfall at ${Math.abs(worstGap.gap).toFixed(2)} points from target.`;
    } else {
        document.getElementById('kpiGapInsight').textContent = 'No KPI category data matched the current filters.';
    }

    const heatmapRows = data.heatmap || [];
    const heatmapColumns = [...new Set(heatmapRows.flatMap(row => Object.keys(row)).filter(key => key !== 'period'))];
    const heatmapValues = heatmapRows.map(row => heatmapColumns.map(col => row[col] ?? null));
    Plotly.react('heatmapChart', [{
        x: heatmapColumns,
        y: heatmapRows.map(row => row.period),
        z: heatmapValues,
        type: 'heatmap',
        colorscale: [
            [0, '#fecaca'],
            [0.45, '#fde68a'],
            [0.75, '#93c5fd'],
            [1, '#10b981']
        ],
        hovertemplate: 'Period: %{y}<br>Department: %{x}<br>Score: %{z:.2f}%<extra></extra>'
    }], {
        margin: { t: 10, r: 10, b: 30, l: 90 },
        paper_bgcolor: 'transparent'
    }, { responsive: true, displayModeBar: true });

    Plotly.react('riskHistogramChart', [{
        x: data.risk_histogram.map(item => item.range),
        y: data.risk_histogram.map(item => item.count),
        type: 'bar',
        marker: { color: ['#ef4444', '#fb7185', '#f59e0b', '#3b82f6', '#10b981'] },
        hovertemplate: '%{x}<br>Staff: %{y}<extra></extra>'
    }], {
        margin: { t: 10, r: 10, b: 40, l: 40 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: { title: 'Count' }
    }, { responsive: true, displayModeBar: true });
}

function renderTables(data) {
    document.querySelector('#atRiskTable tbody').innerHTML = data.at_risk_staff.map(item => `
        <tr>
            <td>${escapeHtml(item.name)}</td>
            <td>${escapeHtml(item.department)}</td>
            <td>${item.score}%</td>
            <td>${escapeHtml(item.trend)}</td>
            <td>${escapeHtml(item.action)}</td>
        </tr>
    `).join('') || '<tr><td colspan="5">No at-risk staff under the current filters.</td></tr>';

    document.querySelector('#departmentStatsTable tbody').innerHTML = data.department_stats.map(item => `
        <tr>
            <td>${escapeHtml(item.department)}</td>
            <td>${item.score}%</td>
            <td>${item.top_performers}</td>
            <td>${item.at_risk}</td>
            <td>${escapeHtml(item.trend)}</td>
        </tr>
    `).join('') || '<tr><td colspan="5">No department statistics available.</td></tr>';
}

function getBadgeClass(level) {
    const value = String(level || '').toLowerCase();
    if (value === 'top') return 'top';
    if (value === 'good') return 'good';
    if (value === 'average') return 'average';
    if (value === 'critical') return 'critical';
    return 'at-risk';
}

function buildSuggestionCard(left, right) {
    if (!left || !right) {
        return '<div class="empty-note">Not enough staff for this suggestion.</div>';
    }

    const leftBadge = getBadgeClass(left.performance_level);
    const rightBadge = getBadgeClass(right.performance_level);

    return `
        <button class="suggestion-card"
            type="button"
            data-staff1="${left.staff.id}"
            data-staff1-name="${escapeHtml(left.staff.name)}"
            data-staff2="${right.staff.id}"
            data-staff2-name="${escapeHtml(right.staff.name)}">

            <div class="suggestion-person">
                <img class="suggestion-avatar" src="${escapeHtml(left.staff.avatar || '../asset/images/supervisor_profile.jpg')}" alt="${escapeHtml(left.staff.name)}">
                <div>
                    <strong>${escapeHtml(left.staff.name)}</strong>
                    <small>${escapeHtml(left.staff.department)} • Score: ${left.currentScore}%</small>
                </div>
            </div>

            <div class="vs-pill">vs</div>

            <div class="suggestion-person" style="justify-content:flex-end;">
                <div style="text-align:right;">
                    <strong>${escapeHtml(right.staff.name)}</strong>
                    <small>${escapeHtml(right.staff.department)} • Score: ${right.currentScore}%</small>
                </div>
                <img class="suggestion-avatar" src="${escapeHtml(right.staff.avatar || '../asset/images/supervisor_profile.jpg')}" alt="${escapeHtml(right.staff.name)}">
            </div>
        </button>
        <div style="display:flex; justify-content:space-between; margin-top:8px;">
            <span class="badge ${leftBadge}">${escapeHtml(left.performance_level)}</span>
            <span class="badge ${rightBadge}">${escapeHtml(right.performance_level)}</span>
        </div>
    `;
}

function renderSuggestionPills(data) {
    const top = data.suggestions.top_pair || [];
    const avg = data.suggestions.average_pair || [];

    document.getElementById('topPairSuggestions').innerHTML =
        top.length === 2 ? buildSuggestionCard(top[0], top[1]) : '<div class="empty-note">Not enough top performers</div>';

    document.getElementById('averagePairSuggestions').innerHTML =
        avg.length === 2 ? buildSuggestionCard(avg[0], avg[1]) : '<div class="empty-note">Not enough average performers</div>';

    document.querySelectorAll('.suggestion-card').forEach(button => {
        button.addEventListener('click', () => {
            state.staff1 = button.dataset.staff1 || '';
            state.staff2 = button.dataset.staff2 || '';
            state.staff1Name = button.dataset.staff1Name || '';
            state.staff2Name = button.dataset.staff2Name || '';
            document.getElementById('staff1Search').value = state.staff1Name;
            document.getElementById('staff2Search').value = state.staff2Name;
            updateSelectedChips();
        });
    });
}

function updateSelectedChips() {
    const chip1 = document.getElementById('selectedStaff1');
    const chip2 = document.getElementById('selectedStaff2');
    chip1.textContent = state.staff1Name || 'No staff selected';
    chip2.textContent = state.staff2Name || 'No staff selected';
    chip1.classList.toggle('empty', !state.staff1);
    chip2.classList.toggle('empty', !state.staff2);
    document.getElementById('compareBtn').disabled = !(state.staff1 && state.staff2);
}

async function populateSearch(inputId, suggestionsId, key, excludeKey) {
    const query = document.getElementById(inputId).value;
    const data = await fetchJson({
        action: 'search_staff',
        query,
        department: state.compareDepartment,
        performance: state.performance,
        exclude_id: state[excludeKey]
    });

    const container = document.getElementById(suggestionsId);
    container.innerHTML = data.items.map(item => `
        <button class="suggestion-item" type="button"
            data-id="${item.id}"
            data-name="${escapeHtml(item.name)}">
            <span>
                <strong>${escapeHtml(item.name)}</strong>
                <small>${escapeHtml(item.department)} • ${item.score}%</small>
            </span>
            <em>${escapeHtml(item.performance_level)}</em>
        </button>
    `).join('');

    container.querySelectorAll('.suggestion-item').forEach(button => {
        button.addEventListener('click', () => {
            state[key] = button.dataset.id || '';
            state[key + 'Name'] = button.dataset.name || '';
            document.getElementById(inputId).value = state[key + 'Name'];
            container.innerHTML = '';
            updateSelectedChips();
        });
    });
}

async function loadDashboard() {
    const data = await fetchJson({
        action: 'dashboard',
        year: state.year,
        department: state.department,
        kpi_category: state.kpi_category,
        period: state.period
    });

    setDepartmentOptions(data.filters.available_departments || []);
    setCategoryOptions(data.filters.available_categories || []);
    setYearOptions(data.performance_trend || []);
    renderSummary(data);
    renderCharts(data);
    renderTables(data);
    renderSuggestionPills(data);
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function attachEvents() {
    document.getElementById('yearFilter').addEventListener('change', event => {
        state.year = event.target.value;
        loadDashboard();
    });

    document.getElementById('departmentFilter').addEventListener('change', event => {
        state.department = event.target.value;
        loadDashboard();
    });

    document.getElementById('kpiCategoryFilter').addEventListener('change', event => {
        state.kpi_category = event.target.value;
        loadDashboard();
    });

    document.getElementById('periodFilter').addEventListener('change', event => {
        state.period = event.target.value;
        loadDashboard();
    });

    document.getElementById('resetTopFilters').addEventListener('click', () => {
        state.year = '';
        state.department = 'All Departments';
        state.kpi_category = 'All Categories';
        state.period = 'Monthly';

        document.getElementById('yearFilter').value = '';
        document.getElementById('departmentFilter').value = 'All Departments';
        document.getElementById('kpiCategoryFilter').value = 'All Categories';
        document.getElementById('periodFilter').value = 'Monthly';
        loadDashboard();
    });

    document.getElementById('compareDepartmentFilter').addEventListener('change', event => {
        state.compareDepartment = event.target.value;
    });

    document.getElementById('performanceFilter').addEventListener('change', event => {
        state.performance = event.target.value;
    });

    document.getElementById('resetCompareFilters').addEventListener('click', () => {
        state.compareDepartment = 'All Departments';
        state.performance = 'All Performance';
        state.staff1 = '';
        state.staff2 = '';
        state.staff1Name = '';
        state.staff2Name = '';
        document.getElementById('compareDepartmentFilter').value = 'All Departments';
        document.getElementById('performanceFilter').value = 'All Performance';
        document.getElementById('staff1Search').value = '';
        document.getElementById('staff2Search').value = '';
        document.getElementById('staff1Suggestions').innerHTML = '';
        document.getElementById('staff2Suggestions').innerHTML = '';
        updateSelectedChips();
    });

    document.getElementById('staff1Search').addEventListener('input', () => populateSearch('staff1Search', 'staff1Suggestions', 'staff1', 'staff2'));
    document.getElementById('staff2Search').addEventListener('input', () => populateSearch('staff2Search', 'staff2Suggestions', 'staff2', 'staff1'));

    document.getElementById('compareBtn').addEventListener('click', () => {
        if (!state.staff1 || !state.staff2) return;
        const params = new URLSearchParams({ staff1: state.staff1, staff2: state.staff2 });
        window.location.href = './staff_comparison.php?' + params.toString();
    });
}

attachEvents();
loadDashboard().catch(error => {
    console.error(error);
    document.getElementById('performanceTrendInsight').textContent = 'Unable to load analytics data. Check analytics_data.php and your database connection.';
});

document.getElementById('distributionInsight').textContent =
    `${data.performance_distribution.top + data.performance_distribution.good}% of staff are performing well, while ${data.performance_distribution.critical + data.performance_distribution['at-risk']}% require attention.`;
    
</script>
</body>
</html>
