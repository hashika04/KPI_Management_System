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
    <link rel="stylesheet" href="../asset/analytics.css?v=7">
<style>
/* FINAL OVERRIDES */
.cards-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:24px}.alert-card,.stat-card{min-height:250px;border-radius:24px;padding:18px;box-shadow:0 10px 24px rgba(200,80,140,.08)}
.risk-card-new{display:flex;flex-direction:column;gap:14px;margin-top:10px}.risk-main-box,.risk-sub-box{background:rgba(255,255,255,.88);border:1px solid rgba(255,255,255,.7);border-radius:18px;text-align:center;box-shadow:inset 0 1px 0 rgba(255,255,255,.55)}
.risk-main-box{padding:18px;flex:1}.risk-sub-box{padding:14px}.risk-main-box span,.risk-sub-box span{display:block;font-size:.8rem;color:#8a7284;font-weight:700;margin-bottom:4px}.risk-main-box small{display:block;margin-top:2px;font-size:.78rem;color:#9a8795;font-weight:700}
.risk-main-box h2{margin:4px 0;font-size:2rem;font-weight:900}.risk-main-box h3{margin:6px 0 0;font-size:1.8rem;font-weight:900}.risk-sub-box h1{margin:6px 0 0;font-size:2.4rem;font-weight:900}
.high-risk-card .risk-main-box h2,.high-risk-card .risk-main-box h3,.high-risk-card .risk-sub-box h1{color:#dc2626}.moderate-risk-card .risk-main-box h2,.moderate-risk-card .risk-main-box h3,.moderate-risk-card .risk-sub-box h1{color:#ea580c}
.chart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px;margin-top:22px}.chart-span-2{grid-column:span 2}.chart-card,.table-card,.comparison-tool{background:rgba(255,255,255,.94);border:1px solid #f0d8e4;border-radius:24px;padding:22px;box-shadow:0 10px 28px rgba(200,80,140,.08)}
#performanceTrendChart{height:360px}#performanceDistributionChart,#trendDistributionChart,#departmentComparisonChart,#kpiVsTargetChart{height:300px}#heatmapChart{height:420px}#riskHistogramChart{height:300px}
.kpi-breakdown-list{display:flex;flex-direction:column;gap:12px}.kpi-item{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-radius:14px;background:#f9fafb;border:1px solid #efe4eb}.kpi-item strong{color:#231942;font-size:.96rem}.kpi-item span{font-weight:800;color:#4f46e5}
.comparison-tool{display:flex;flex-direction:column;justify-content:flex-start}.comparison-toolbar,.comparison-select{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}.comparison-toolbar .ghost-btn{grid-column:span 2}
.comparison-select select,.comparison-toolbar select{border:1.5px solid #eed4e3;border-radius:14px;background:#fff;padding:10px 14px;min-height:46px;color:#3a2948;font-size:.95rem;box-shadow:0 4px 10px rgba(232,48,140,.04)}
.comparison-action-row{margin-top:6px}.compare-main-btn{width:100%;min-height:50px}
@media (max-width:1200px){.cards-grid,.chart-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.chart-span-2{grid-column:span 2}}
@media (max-width:768px){.cards-grid,.chart-grid,.comparison-toolbar,.comparison-select{grid-template-columns:1fr}.chart-span-2{grid-column:span 1}}

/* analytics_patched final clean overrides */
.comparison-toolbar{display:flex!important;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px;}
.comparison-toolbar select{flex:1 1 220px;min-width:220px;}
.comparison-toolbar .ghost-btn{flex:0 0 auto!important;grid-column:auto!important;width:auto!important;min-width:130px;}
.comparison-select-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.comparison-select-box label{display:block;margin:0 0 8px;font-size:.9rem;font-weight:700;color:#6f6376;}
.comparison-select-box select{width:100%;border:1.5px solid #eed4e3;border-radius:14px;background:#fff;padding:10px 14px;min-height:46px;color:#3a2948;font-size:.95rem;box-shadow:0 4px 10px rgba(232,48,140,.04);}
.compare-main-btn{max-width:none;}
@media (max-width: 900px){.comparison-select-grid{grid-template-columns:1fr;}}
/* MINIMAL MODAL - NO DARK BACKDROP */
.risk-modal {
    position: fixed !important;
    inset: 0 !important;
    display: none !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 999999 !important;
    margin: 0 !important;
    padding: 20px !important;
    background: transparent !important;  /* NO dark background */
    backdrop-filter: none !important;
}

.risk-modal.show {
    display: flex !important;
}

.risk-modal-box {
    width: min(500px, 90vw) !important;  /* Smaller width */
    max-height: 70vh !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    background: #ffffff !important;
    border-radius: 20px !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
    border: 1px solid #e8d5e2 !important;
    position: relative !important;
    margin: 0 auto !important;
}

.risk-modal-head {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 16px 20px !important;
    border-bottom: 1px solid #f0e3ea !important;
    background: #ffffff !important;
    border-radius: 20px 20px 0 0 !important;
}

.risk-modal-head h3 {
    margin: 0 !important;
    font-size: 1.2rem !important;
    font-weight: 700 !important;
    color: #231942 !important;
}

.risk-modal-close {
    width: 28px !important;
    height: 28px !important;
    border: none !important;
    border-radius: 50% !important;
    background: #f0e6ec !important;
    color: #b42361 !important;
    font-size: 1.1rem !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: all 0.2s ease !important;
}

.risk-modal-close:hover {
    background: #e0d4dc !important;
}

.risk-modal-body {
    padding: 18px 20px 22px !important;
    color: #4b3a4c !important;
    line-height: 1.5 !important;
    font-size: 0.9rem !important;
}

.modal-table {
    width: 100% !important;
    border-collapse: collapse !important;
    margin-top: 12px !important;
    font-size: 0.85rem !important;
}

.modal-table th,
.modal-table td {
    padding: 8px 10px !important;
    border-bottom: 1px solid #f0e3ea !important;
    text-align: left !important;
}

.modal-table th {
    background: #fcf6fa !important;
    color: #6b4d60 !important;
    font-weight: 700 !important;
    font-size: 0.8rem !important;
}

.modal-note {
    margin-top: 12px !important;
    font-size: 0.8rem !important;
    color: #9a8795 !important;
}
</style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="analytics-layout">
        <section class="page-header">
            <div class="header-top">
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

                <select id="periodFilter">
                    <option value="Monthly">Monthly</option>
                    <option value="Yearly">Yearly</option>
                </select>

                <button class="ghost-btn" id="resetTopFilters" type="button">
                    Reset Filters
                </button>
            </div>
        </section>

    <section class="cards-grid">
        <article class="alert-card high-risk-card" id="highRiskCard">
            <div class="alert-card-head">
                <h3><span class="alert-icon">⚠</span> High Risk Alert</h3>
                <button type="button" class="details-link" id="highRiskDetailsBtn">View Details</button>
            </div>
            <div class="risk-card-new">
                <div class="risk-main-box">
                    <span>Department</span>
                    <h2 id="highRiskDepartmentName">-</h2>
                    <small>Average KPI</small>
                    <h3 id="highRiskDepartmentKpi">-</h3>
                </div>
                <div class="risk-sub-box">
                    <span>At-Risk Staff</span>
                    <h1 id="highRiskStaffCount">0</h1>
                </div>
            </div>
        </article>

        <article class="alert-card moderate-risk-card" id="moderateRiskCard">
            <div class="alert-card-head">
                <h3><span class="alert-icon">⚠</span> Moderate Risk Alert</h3>
                <button type="button" class="details-link" id="moderateRiskDetailsBtn">View Details</button>
            </div>
            <div class="risk-card-new">
                <div class="risk-main-box">
                    <span>Department</span>
                    <h2 id="moderateRiskDepartmentName">-</h2>
                    <small>Average KPI</small>
                    <h3 id="moderateRiskDepartmentKpi">-</h3>
                </div>
                <div class="risk-sub-box">
                    <span>At-Risk Staff</span>
                    <h1 id="moderateRiskStaffCount">0</h1>
                </div>
            </div>
        </article>

        <article class="stat-card summary-overview">
            <h3>Overall KPI Summary</h3>
            <div class="summary-kpi-layout">
                <button type="button" class="summary-kpi-main" id="avgKpiBtn">
                    <span class="stat-icon-circle kpi-icon">📈</span>
                    <strong id="avgKpi">0.00%</strong>
                    <span class="stat-label-text">Average KPI %</span>
                </button>
                <div class="summary-kpi-bottom">
                    <button type="button" class="summary-kpi-small" id="totalStaffBtn">
                        <span class="stat-icon-circle staff-icon">👥</span>
                        <strong id="totalStaff">0</strong>
                        <span class="stat-label-text">Total Staff</span>
                    </button>
                    <button type="button" class="summary-kpi-small" id="improvingBtn">
                        <span class="stat-icon-circle improving-icon">↗</span>
                        <strong id="improvingCount">0</strong>
                        <span class="stat-label-text">Improving</span>
                    </button>
                </div>
            </div>
        </article>

        <article class="stat-card workforce-overview">
            <h3>Workforce Overview</h3>
            <ul class="workforce-list clickable-workforce-list">
                <li id="departmentsCountBtn" tabindex="0">
                    <span class="workforce-icon">🏢</span>
                    <strong id="departmentsCount">0</strong>
                    <span class="workforce-text">Departments Monitored</span>
                </li>
                <li id="topPerformersCountBtn" tabindex="0">
                    <span class="workforce-icon">🏆</span>
                    <strong id="topPerformersCount">0</strong>
                    <span class="workforce-text">Top Performers ≥ 85%</span>
                </li>
                <li id="atRiskCountBtn" tabindex="0">
                    <span class="workforce-icon">⚠</span>
                    <strong id="atRiskCount">0</strong>
                    <span class="workforce-text">At-Risk Staff</span>
                </li>
            </ul>
        </article>
        </section>

        <section class="chart-grid">
        <article class="card chart-card chart-span-2">
            <div class="chart-head"><h2>Performance Trends & Risk Prediction</h2></div>
            <div id="performanceTrendChart" class="chart"></div>
            <p class="interpretation" id="performanceTrendInsight">Waiting for analytics data...</p>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Performance Distribution</h2></div>
            <div id="performanceDistributionChart" class="chart small"></div>
            <p class="interpretation" id="distributionInsight"></p>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Trend Distribution</h2></div>
            <div id="trendDistributionChart" class="chart small"></div>
            <p class="interpretation" id="trendDistributionInsight"></p>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Department KPI Performance vs Target KPI</h2></div>
            <div id="departmentComparisonChart" class="chart"></div>
            <p class="interpretation" id="departmentInsight"></p>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>KPI Category Performance vs Target</h2></div>
            <div id="kpiVsTargetChart" class="chart"></div>
            <p class="interpretation" id="kpiGapInsight"></p>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Score Movement Heatmap</h2></div>
            <div id="heatmapChart" class="chart heatmap"></div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Staff Performance Risk</h2></div>
            <div id="riskHistogramChart" class="chart"></div>
        </article>

        <article class="card table-card chart-span-2">
            <div class="chart-head"><h2>At-Risk Staff</h2></div>
            <div class="table-wrap">
                <table id="atRiskTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Score</th>
                            <th>Trend</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </article>

        <article class="comparison-tool">
            <div class="comparison-title-wrap">
                <h2>Staff Comparison</h2>
                <p>Select two staff from the current filtered dataset.</p>
            </div>
            <div class="comparison-toolbar">
                <select id="compareDepartmentFilter">
                    <option value="All Departments">All Departments</option>
                </select>
                <select id="performanceFilter">
                    <option value="All Performance">All Performance</option>
                    <option value="top">Top</option>
                    <option value="good">Good</option>
                    <option value="average">Average</option>
                    <option value="at-risk">At Risk</option>
                </select>
                <button type="button" class="ghost-btn" id="resetCompareFilters">Reset</button>
            </div>
            <div class="comparison-select-grid">
            <div class="comparison-select-box">
                <label for="staff1Select">Staff 1</label>
                <select id="staff1Select">
                    <option value="">Select Staff 1</option>
                </select>
            </div>

            <div class="comparison-select-box">
                <label for="staff2Select">Staff 2</label>
                <select id="staff2Select">
                    <option value="">Select Staff 2</option>
                </select>
            </div>
        </div>

        <div class="comparison-action-row">
            <button type="button" class="primary-btn compare-main-btn" id="compareBtn" disabled>
                Compare Selected Staff
            </button>
        </div>
        </article>

        <article class="card table-card chart-span-2">
            <div class="chart-head"><h2>Department Statistics</h2></div>
            <div class="table-wrap">
                <table id="departmentStatsTable">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Average Score</th>
                            <th>Top Performers</th>
                            <th>At Risk</th>
                            <th>Trend</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </article>
        </section>
    </main>

            <div class="risk-modal" id="detailsModal">
        <div class="risk-modal-box">
            <div class="risk-modal-head">
                <h3 id="detailsModalTitle"></h3>
                <button type="button" class="risk-modal-close" id="detailsModalClose">×</button>
            </div>
            <div class="risk-modal-body" id="detailsModalBody"></div>
        </div>
        </div>
    <script>
    const state = {
        year: '',
        department: 'All Departments',
        period: 'Monthly',
        compareDepartment: 'All Departments',
        performance: 'All Performance',
        staff1: '',
        staff2: '',
        staff1Name: '',
        staff2Name: ''
    };

    let latestDashboardData = null;

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
        const safeDepartments = Array.isArray(departments) ? departments : [];
        const selects = [
            document.getElementById('departmentFilter'),
            document.getElementById('compareDepartmentFilter')
        ];

        selects.forEach(select => {
            if (!select) return;

            const currentValue = select.value || 'All Departments';
            select.innerHTML = '<option value="All Departments">All Departments</option>';

            safeDepartments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept;
                option.textContent = dept;
                if (dept === currentValue) option.selected = true;
                select.appendChild(option);
            });

            if (![...select.options].some(opt => opt.value === currentValue)) {
                select.value = 'All Departments';
            }
        });
    }


    function setYearOptions(years) {
        const safeYears = Array.isArray(years) ? years : [];
        const yearFilter = document.getElementById('yearFilter');
        if (!yearFilter) return;

        const current = state.year || '';
        yearFilter.innerHTML = '<option value="">All Years</option>';

        safeYears.forEach(year => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (String(year) === String(current)) option.selected = true;
            yearFilter.appendChild(option);
        });
    }

    function renderSummary(data) {
        document.getElementById('totalStaff').textContent = Number(data.summary?.total_staff || 0);
        document.getElementById('avgKpi').textContent = Number(data.summary?.avg_kpi || 0).toFixed(2) + '%';
        document.getElementById('improvingCount').textContent = Number(data.summary?.improving || 0);
        document.getElementById('departmentsCount').textContent = Number(data.summary?.departments || 0);
        document.getElementById('topPerformersCount').textContent = Number(data.summary?.top_performers || 0);
        document.getElementById('atRiskCount').textContent = Number(data.summary?.at_risk || 0);

        const high = (data.high_risk_departments || [])[0];
        document.getElementById('highRiskDepartmentName').textContent =
            high ? high.department : 'No High-Risk Department';
        document.getElementById('highRiskDepartmentKpi').textContent =
            high ? Number(high.score).toFixed(2) + '%' : '–';
        document.getElementById('highRiskStaffCount').textContent =
            high ? Number(high.at_risk || 0) : 0;

        const moderate = (data.moderate_risk_departments || [])[0];
            document.getElementById('moderateRiskDepartmentName').textContent =
                moderate ? moderate.department : 'No Moderate-Risk Department';
            document.getElementById('moderateRiskDepartmentKpi').textContent =
                moderate ? Number(moderate.score).toFixed(2) + '%' : '–';
            document.getElementById('moderateRiskStaffCount').textContent =
                moderate ? Number(moderate.at_risk || 0) : 0;
            }

    function renderCharts(data) {
        const trendRows = data.performance_trend || [];
        const periods = trendRows.map(item => item.period);

        const actualSeries = trendRows.map(item => item.actual);
        const targetSeries = trendRows.map(item => item.target);
        const forecastSeries = trendRows.map(item => item.forecast);

        const lastActualIndex = actualSeries
            .map((value, index) => value !== null ? index : -1)
            .filter(index => index !== -1)
            .pop();

        const forecastStartIndex = trendRows.findIndex(item => item.is_forecast === true);

        const forecastConnectorX = [];
        const forecastConnectorY = [];

        if (lastActualIndex !== undefined && forecastStartIndex > -1) {
            forecastConnectorX.push(periods[lastActualIndex], periods[forecastStartIndex]);
            forecastConnectorY.push(actualSeries[lastActualIndex], forecastSeries[forecastStartIndex]);
        }

        Plotly.react('performanceTrendChart', [
            {
                x: periods,
                y: actualSeries,
                type: 'scatter',
                mode: 'lines+markers',
                name: 'Actual KPI %',
                line: {
                    color: '#5b4ce6',
                    width: 4,
                    shape: 'spline'
                },
                marker: {
                    size: 8,
                    color: actualSeries.map(value => value !== null && value < 80 ? '#ef4444' : '#5b4ce6')
                },
                hovertemplate: 'Period: %{x}<br>Actual KPI: %{y:.2f}%<extra></extra>'
            },
            {
                x: periods,
                y: targetSeries,
                type: 'scatter',
                mode: 'lines',
                name: 'Target %',
                line: {
                    color: '#14b8a6',
                    width: 2
                },
                hovertemplate: 'Period: %{x}<br>Target KPI: %{y:.2f}%<extra></extra>'
            },
            {
                x: forecastConnectorX,
                y: forecastConnectorY,
                type: 'scatter',
                mode: 'lines',
                name: 'Forecast Transition',
                line: {
                    color: '#ec4899',
                    width: 2,
                    dash: 'dot'
                },
                hoverinfo: 'skip',
                showlegend: false
            },
            {
                x: periods,
                y: forecastSeries,
                type: 'scatter',
                mode: 'lines+markers',
                name: 'Forecast KPI %',
                line: {
                    color: '#ec4899',
                    width: 3,
                    dash: 'dash',
                    shape: 'spline'
                },
                marker: {
                    size: 8,
                    color: forecastSeries.map(value => value !== null && value < 80 ? '#f97316' : '#ec4899')
                },
                hovertemplate: 'Period: %{x}<br>Forecast KPI: %{y:.2f}%<extra></extra>'
            }
        ], {
            margin: { t: 20, r: 20, b: 55, l: 60 },
            paper_bgcolor: 'transparent',
            plot_bgcolor: 'transparent',
            xaxis: {
                title: 'Time Period',
                tickfont: { size: 12, color: '#6b7280' },
                showgrid: false
            },
            yaxis: {
                title: 'KPI %',
                range: [0, 100],
                tickfont: { size: 12, color: '#6b7280' },
                gridcolor: 'rgba(148, 163, 184, 0.18)',
                zeroline: false
            },
            legend: {
                orientation: 'h',
                x: 0,
                y: 1.14,
                font: { size: 12 }
            },
            hoverlabel: {
                bgcolor: '#ffffff',
                bordercolor: '#e5e7eb',
                font: { color: '#111827' }
            }
        }, { responsive: true, displayModeBar: true });

        const latestActual = [...trendRows].reverse().find(item => item.actual !== null);
        const latestForecast = trendRows.find(item => item.is_forecast === true);

        if (latestActual && latestForecast) {
            const riskText = latestForecast.forecast < 80
                ? `The next forecasted KPI is ${latestForecast.forecast.toFixed(2)}%, which remains below the 80% target and indicates continued performance risk.`
                : `The next forecasted KPI is ${latestForecast.forecast.toFixed(2)}%, which is expected to meet the 80% target.`;

            document.getElementById('performanceTrendInsight').textContent =
                `The latest recorded KPI is ${latestActual.actual.toFixed(2)}%. Trend across recent periods is ${data.insight.toLowerCase().includes('improving') ? 'improving' : data.insight.toLowerCase().includes('declining') ? 'declining' : 'stable'}. ${riskText}`;
        } else {
            document.getElementById('performanceTrendInsight').textContent = data.insight;
        }

        Plotly.react('performanceDistributionChart', [{
            labels: ['Excellence', 'Good', 'Moderate', 'Critical', 'At Risk'],
            values: [
                data.performance_distribution.top,
                data.performance_distribution.good,
                data.performance_distribution.average,
                data.performance_distribution.critical,
                data.performance_distribution['at-risk']
            ],
            type: 'pie',
            hole: 0.62,
            sort: false,
            direction: 'clockwise',
            textinfo: 'percent',
            textposition: 'inside',
            insidetextorientation: 'auto',
            textfont: {
                size: 12
            },
            marker: {
                colors: ['#10b981', '#3b82f6', '#f59e0b', '#fb7185', '#ef4444']
            },
            hovertemplate: '%{label}: %{value} staff (%{percent})<extra></extra>',
            domain: {
                x: [0.08, 0.58],
                y: [0.12, 0.92]
            }
        }], {
            margin: { t: 10, r: 120, b: 10, l: 40 },
            paper_bgcolor: 'transparent',
            plot_bgcolor: 'transparent',
            showlegend: true,
            legend: {
                orientation: 'v',
                x: 0.72,
                y: 0.5,
                xanchor: 'left',
                yanchor: 'middle',
                font: {
                    size: 12,
                    color: '#3a2948'
                }
            }
        }, {
            responsive: true,
            displayModeBar: false
        });

            Plotly.react('trendDistributionChart', [{
            labels: ['Improving', 'Stable', 'Declining'],
            values: [
                data.trend_distribution.up,
                data.trend_distribution.stable,
                data.trend_distribution.down
            ],
            type: 'pie',
            hole: 0.62,
            sort: false,
            direction: 'clockwise',
            textinfo: 'percent',
            textposition: 'inside',
            insidetextorientation: 'auto',
            textfont: {
                size: 12
            },
            marker: {
                colors: ['#10b981', '#3b82f6', '#ef4444']
            },
            hovertemplate: '%{label}: %{value} staff (%{percent})<extra></extra>',
            domain: {
                x: [0.08, 0.58],
                y: [0.12, 0.92]
            }
        }], {
            margin: { t: 10, r: 120, b: 10, l: 40 },
            paper_bgcolor: 'transparent',
            plot_bgcolor: 'transparent',
            showlegend: true,
            legend: {
                orientation: 'v',
                x: 0.72,
                y: 0.5,
                xanchor: 'left',
                yanchor: 'middle',
                font: {
                    size: 12,
                    color: '#3a2948'
                }
            }
        }, {
            responsive: true,
            displayModeBar: false
        });

        const performanceChart = document.getElementById('performanceDistributionChart');
            if (performanceChart) {
                performanceChart.on('plotly_click', function(eventData) {
                    const label = eventData.points?.[0]?.label;
                    if (!label) return;
                    openDetailsModal(`${label} Performance Details`, buildPerformanceSliceHtml(label));
                });
            }

            const trendChart = document.getElementById('trendDistributionChart');
            if (trendChart) {
                trendChart.on('plotly_click', function(eventData) {
                    const label = eventData.points?.[0]?.label;
                    if (!label) return;
                    openDetailsModal(`${label} Trend Details`, buildTrendSliceHtml(label));
                });
            }
        document.getElementById('distributionInsight').textContent =
        `${data.performance_distribution.top + data.performance_distribution.good} staff are in the stronger performance bands, while ${data.performance_distribution.critical + data.performance_distribution['at-risk']} staff are below the desired level and need closer support.`;

        const trendInsightEl = document.getElementById('trendDistributionInsight');
        if (trendInsightEl) {
            const up = Number(data.trend_distribution?.up || 0);
            const stable = Number(data.trend_distribution?.stable || 0);
            const down = Number(data.trend_distribution?.down || 0);
            if (up > stable && up > down) {
                trendInsightEl.textContent = `${up} staff are currently improving, making improvement the most common movement pattern under the selected filters.`;
            } else if (down > up && down > stable) {
                trendInsightEl.textContent = `${down} staff are declining, which suggests closer monitoring is needed for recent KPI movement.`;
            } else {
                trendInsightEl.textContent = `${stable} staff are stable, indicating performance is relatively steady under the current filters.`;
            }
        }

        const deptRows = [...(data.department_comparison || [])].sort((a, b) => a.score - b.score);

            const deptNames = deptRows.map(item => item.department);
            const deptScores = deptRows.map(item => item.score);
            const deptColors = deptRows.map(item => {
                if (item.at_risk > 0) return '#ef4444';
                if (item.score < 80) return '#f59e0b';
                return '#10b981';
            });

            const lollipopShapes = deptRows.map((item) => ({
                type: 'line',
                x0: 0,
                x1: item.score,
                y0: item.department,
                y1: item.department,
                xref: 'x',
                yref: 'y',
                line: {
                    color: '#2341ec',
                    width: 3
                }
            }));

            lollipopShapes.push({
                type: 'line',
                x0: 80,
                x1: 80,
                y0: -0.5,
                y1: deptNames.length - 0.5,
                xref: 'x',
                yref: 'y',
                line: {
                    color: '#14b8a6',
                    width: 2,
                    dash: 'dash'
                }
            });

            Plotly.react('departmentComparisonChart', [
                {
                    x: deptScores,
                    y: deptNames,
                    type: 'scatter',
                    mode: 'markers+text',
                    name: 'Average KPI %',
                    marker: {
                        color: deptColors,
                        size: 18,
                        line: {
                            color: '#ffffff',
                            width: 2
                        }
                    },
                    text: deptScores.map(score => score.toFixed(2) + '%'),
                    textposition: 'middle right',
                    textfont: {
                        size: 12,
                        color: '#374151'
                    },
                    customdata: deptRows.map(item => [
                        item.at_risk,
                        (80 - item.score).toFixed(2)
                    ]),
                    hovertemplate:
                        '<b>%{y}</b><br>' +
                        'Average KPI: %{x:.2f}%<br>' +
                        'Gap to Target: %{customdata[1]} points<br>' +
                        'At-Risk Staff: %{customdata[0]}<extra></extra>'
                },
                {
                    x: [null],
                    y: [null],
                    type: 'scatter',
                    mode: 'lines',
                    name: 'Target 80%',
                    line: {
                        color: '#14b8a6',
                        width: 2,
                        dash: 'dash'
                    },
                    hoverinfo: 'skip'
                }
            ], {
                margin: { t: 20, r: 50, b: 45, l: 170 },
                paper_bgcolor: 'transparent',
                plot_bgcolor: 'transparent',
                shapes: lollipopShapes,
                xaxis: {
                    range: [0, 100],
                    title: 'Average KPI %',
                    gridcolor: 'rgba(23, 99, 205, 0.15)',
                    zeroline: false
                },
                yaxis: {
                    automargin: true,
                    title: 'Department',
                    fontweight: 'bold',
                    categoryorder: 'array',
                    categoryarray: deptNames
                },
                legend: {
                    orientation: 'h',
                    x: 0,
                    y: 1.12
                }
            }, {
                responsive: true,
                displayModeBar: true
            });
            const deptChart = document.getElementById('departmentComparisonChart');

            deptChart.on('plotly_hover', function(data) {
                const pointIndex = data.points[0].pointIndex;

                Plotly.restyle('departmentComparisonChart', {
                    'marker.size': [deptScores.map((_, i) => i === pointIndex ? 24 : 18)],
                    'marker.line.width': [deptScores.map((_, i) => i === pointIndex ? 3 : 2)],
                    'marker.line.color': [deptScores.map((_, i) => i === pointIndex ? '#111827' : '#ffffff')]
                }, [0]);
            });

            deptChart.on('plotly_unhover', function() {
                Plotly.restyle('departmentComparisonChart', {
                    'marker.size': [deptScores.map(() => 18)],
                    'marker.line.width': [deptScores.map(() => 2)],
                    'marker.line.color': [deptScores.map(() => '#ffffff')]
                }, [0]);
            });

        if (data.department_comparison.length > 0) {
                const sortedByScore = [...data.department_comparison].sort((a, b) => b.score - a.score);
                const sortedByRisk = [...data.department_comparison].sort((a, b) => {
                    if (a.at_risk === b.at_risk) return a.score - b.score;
                    return b.at_risk - a.at_risk;
                    });

                const best = sortedByScore[0];
                const weakest = [...data.department_comparison].sort((a, b) => a.score - b.score)[0];
                const mostCritical = sortedByRisk[0];

                document.getElementById('departmentInsight').textContent =
            `${best.department} currently leads with an average KPI of ${best.score.toFixed(2)}%, while ${weakest.department} records the lowest department KPI at ${weakest.score.toFixed(2)}%. ${mostCritical.department} should be prioritised for closer supervision due to ${mostCritical.at_risk} at-risk staff.`;
            } else {
                document.getElementById('departmentInsight').textContent = 'No department data matched the current filters.';
            }

        const kpiRows = data.kpi_vs_target || [];

    const shortCategoryLabels = kpiRows.map(item => {
        const category = item.category || '';

        if (category === 'Customer Service Quality') return 'Customer Service';
        if (category === 'Sales Target Contribution') return 'Sales Target';
        if (category === 'Daily Sales Operations') return 'Daily Sales';
        if (category === 'Store Operations Support') return 'Store Operations';
        if (category === 'Inventory & Cost Control') return 'Inventory & Cost';
        if (category === 'Training, Learning & Team Contribution') return 'Training & Team';

        return category;
    });

    const categoryColors = kpiRows.map(item => {
        const category = item.category || '';

        if (category === 'Customer Service Quality') return '#ef4444';
        if (category === 'Sales Target Contribution') return '#f97316';
        if (category === 'Daily Sales Operations') return '#f59e0b';
        if (category === 'Store Operations Support') return '#3b82f6';
        if (category === 'Inventory & Cost Control') return '#8b5cf6';
        if (category === 'Training, Learning & Team Contribution') return '#10b981';

        return '#6366f1';
    });

        Plotly.react('kpiVsTargetChart', [
            {
                x: shortCategoryLabels,
                y: kpiRows.map(item => item.actual),
                type: 'bar',
                name: 'Actual %',
                marker: {
                    color: categoryColors
                },
                text: kpiRows.map(item => item.actual.toFixed(1) + '%'),
                textposition: 'outside',
                customdata: kpiRows.map(item => [
                    item.category,
                    item.target,
                    item.gap
                ]),
                hovertemplate:
                    '<b>%{customdata[0]}</b><br>' +
                    'Actual KPI: %{y:.2f}%<br>' +
                    'Target KPI: %{customdata[1]:.2f}%<br>' +
                    'Gap: %{customdata[2]:.2f} points<extra></extra>'
            },
            {
                x: shortCategoryLabels,
                y: kpiRows.map(item => item.target),
                type: 'scatter',
                mode: 'lines+markers',
                name: 'Target %',
                marker: {
                    color: '#14b8a6',
                    size: 7
                },
                line: {
                    color: '#14b8a6',
                    width: 3,
                    dash: 'dash'
                },
                hovertemplate: 'Target: %{y:.2f}%<extra></extra>'
            }
        ], {
            margin: { t: 20, r: 20, b: 80, l: 55 },
            paper_bgcolor: 'transparent',
            plot_bgcolor: 'transparent',
            yaxis: {
                range: [0, 100],
                title: 'Score %',
                gridcolor: 'rgba(148, 163, 184, 0.18)',
                zeroline: false
            },
            xaxis: {
                tickangle: 0,
                automargin: true,
                title: 'KPI Categories',
                tickfont: {
                    size: 11
                }
            },
            legend: {
                orientation: 'h',
                x: 0,
                y: 1.14
            },
            hoverlabel: {
                bgcolor: '#ffffff',
                bordercolor: '#e5e7eb',
                font: { color: '#111827' }
            }
        }, {
            responsive: true,
            displayModeBar: true
        });

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
            yaxis: { title: 'StaffCount' },
            xaxis: { title: 'KPI Score Range' }
        }, { responsive: true, displayModeBar: true });

        const riskChart = document.getElementById('riskHistogramChart');

        riskChart.removeAllListeners?.('plotly_click');

        riskChart.on('plotly_click', function(eventData) {
            if (!eventData || !eventData.points || !eventData.points.length) return;

            const clickedBand = eventData.points[0].x;
            openDetailsModal(`${clickedBand} Details`, buildRiskBandDetailsHtml(clickedBand));
        });

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
                    <img class="suggestion-avatar" src="${escapeHtml(left.staff.profile_photo || '../asset/images/supervisor_profile.jpg')}" alt="${escapeHtml(left.staff.name)}">
                    <div>
                        <strong>${escapeHtml(left.staff.name)}</strong>
                        <small>${escapeHtml(left.staff.department)} • Score: ${left.current_percentage}%</small>
                    </div>
                </div>

                <div class="vs-pill">vs</div>

                <div class="suggestion-person" style="justify-content:flex-end;">
                    <div style="text-align:right;">
                        <strong>${escapeHtml(right.staff.name)}</strong>
                        <small>${escapeHtml(right.staff.department)} • Score: ${right.current_percentage}%</small>
                    </div>
                    <img class="suggestion-avatar" src="${escapeHtml(right.staff.profile_photo || '../asset/images/supervisor_profile.jpg')}" alt="${escapeHtml(right.staff.name)}">
                </div>
            </button>
            <div style="display:flex; justify-content:space-between; margin-top:8px;">
                <span class="badge ${leftBadge}">${escapeHtml(left.performance_level)}</span>
                <span class="badge ${rightBadge}">${escapeHtml(right.performance_level)}</span>
            </div>
        `;
    }
    function renderSuggestionPills(data) {
        /* suggestions hidden in this finalized version */
    }

    function updateSelectedChips() {
        document.getElementById('compareBtn').disabled = !(state.staff1 && state.staff2 && state.staff1 !== state.staff2);
    }

    function populateStaffDropdown(data) {
        const select1 = document.getElementById('staff1Select');
        const select2 = document.getElementById('staff2Select');

        if (!select1 || !select2) return;

        const compareDepartment = state.compareDepartment || 'All Departments';
        const performance = (state.performance || 'All Performance').toLowerCase();

        let staff = Array.isArray(data.staff_snapshot_list) ? [...data.staff_snapshot_list] : [];

        if (compareDepartment !== 'All Departments') {
            staff = staff.filter(item => item.department === compareDepartment);
        }

        if (performance !== 'all performance') {
            staff = staff.filter(item => String(item.performance_level).toLowerCase() === performance);
        }
        staff.sort((a, b) => a.name.localeCompare(b.name));

        select1.innerHTML = '<option value="">Select Staff 1</option>';
        select2.innerHTML = '<option value="">Select Staff 2</option>';

        staff.forEach(item => {
            const label = `${item.name} (${item.department}) - ${Number(item.score).toFixed(2)}%`;

            const option1 = document.createElement('option');
            option1.value = item.id;
            option1.textContent = label;
            option1.dataset.name = item.name;
            select1.appendChild(option1);

            const option2 = document.createElement('option');
            option2.value = item.id;
            option2.textContent = label;
            option2.dataset.name = item.name;
            select2.appendChild(option2);
        });

        select1.value = '';
        select2.value = '';
        state.staff1 = '';
        state.staff2 = '';
        state.staff1Name = '';
        state.staff2Name = '';
        document.getElementById('compareBtn').disabled = true;
    }

    async function loadDashboard() {
        try {
            const data = await fetchJson({
                action: 'dashboard',
                year: state.year,
                department: state.department,
                period: state.period
            });

            latestDashboardData = data;
            setDepartmentOptions(data.filters?.available_departments || []);
            setYearOptions(data.filters?.available_years || []);
            renderSummary(data);
            renderCharts(data);
            renderTables(data);
            populateStaffDropdown(data);
            updateSelectedChips();
        } catch (error) {
            console.error('loadDashboard failed completely:', error);
            document.getElementById('performanceTrendInsight').textContent =
                'Unable to load analytics data. Check analytics_data_patched.php and your database connection.';
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
function openDetailsModal(title, html, triggerElement) {
    const modal = document.getElementById('detailsModal');
    const titleEl = document.getElementById('detailsModalTitle');
    const bodyEl = document.getElementById('detailsModalBody');

    if (!modal || !titleEl || !bodyEl) return;

    titleEl.textContent = title;
    bodyEl.innerHTML = html;
    
    // Position modal near the clicked button
    if (triggerElement) {
        const rect = triggerElement.getBoundingClientRect();
        const modalWidth = 500; // Max width of modal
        const modalHeight = 400; // Approximate height
        
        let top = rect.bottom + window.scrollY + 8;
        let left = rect.left + window.scrollX;
        
        // Adjust if modal would go off-screen
        if (left + modalWidth > window.innerWidth) {
            left = window.innerWidth - modalWidth - 20;
        }
        if (left < 20) {
            left = 20;
        }
        
        // If not enough space below, show above
        if (top + modalHeight > window.innerHeight + window.scrollY) {
            top = rect.top + window.scrollY - modalHeight - 8;
        }
        
        modal.style.position = 'absolute';
        modal.style.top = top + 'px';
        modal.style.left = left + 'px';
        modal.style.right = 'auto';
        modal.style.bottom = 'auto';
    } else {
        // Fallback to center
        modal.style.position = 'fixed';
        modal.style.top = '50%';
        modal.style.left = '50%';
        modal.style.transform = 'translate(-50%, -50%)';
    }
    
    modal.classList.add('show');
}

function closeDetailsModal() {
    const modal = document.getElementById('detailsModal');
    if (!modal) return;

    modal.classList.remove('show');
    modal.style.position = '';
    modal.style.top = '';
    modal.style.left = '';
    modal.style.transform = '';
}

// Update event listeners to pass the trigger element
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('detailsModal');
    const closeBtn = document.getElementById('detailsModalClose');
    
    if (modal) {
        modal.classList.remove('show');
    }
    
    if (closeBtn) {
        // Remove existing listeners to avoid duplicates
        const newCloseBtn = closeBtn.cloneNode(true);
        closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
        newCloseBtn.addEventListener('click', closeDetailsModal);
    }
    
    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeDetailsModal();
        });
    }
    
    // Update all detail buttons to pass the trigger element
    const highRiskBtn = document.getElementById('highRiskDetailsBtn');
    const moderateRiskBtn = document.getElementById('moderateRiskDetailsBtn');
    const totalStaffBtn = document.getElementById('totalStaffBtn');
    const avgKpiBtn = document.getElementById('avgKpiBtn');
    const improvingBtn = document.getElementById('improvingBtn');
    const departmentsCountBtn = document.getElementById('departmentsCountBtn');
    const topPerformersCountBtn = document.getElementById('topPerformersCountBtn');
    const atRiskCountBtn = document.getElementById('atRiskCountBtn');
    
    if (highRiskBtn) {
        const newBtn = highRiskBtn.cloneNode(true);
        highRiskBtn.parentNode.replaceChild(newBtn, highRiskBtn);
        newBtn.addEventListener('click', (event) => {
            if (!latestDashboardData || !latestDashboardData.high_risk_departments || latestDashboardData.high_risk_departments.length === 0) {
                openDetailsModal('High Risk Alert Details', '<p>No high-risk department under the current filters.</p>', newBtn);
                return;
            }
            const dept = latestDashboardData.high_risk_departments[0];
            openDetailsModal('High Risk Alert Details', buildDepartmentDetailsHtml(dept.department), newBtn);
        });
    }
    
    if (moderateRiskBtn) {
        const newBtn = moderateRiskBtn.cloneNode(true);
        moderateRiskBtn.parentNode.replaceChild(newBtn, moderateRiskBtn);
        newBtn.addEventListener('click', (event) => {
            if (!latestDashboardData || !latestDashboardData.moderate_risk_departments || latestDashboardData.moderate_risk_departments.length === 0) {
                openDetailsModal('Moderate Risk Alert Details', '<p>No moderate-risk department under the current filters.</p>', newBtn);
                return;
            }
            const dept = latestDashboardData.moderate_risk_departments[0];
            openDetailsModal('Moderate Risk Alert Details', buildDepartmentDetailsHtml(dept.department), newBtn);
        });
    }
    
    if (totalStaffBtn) {
        const newBtn = totalStaffBtn.cloneNode(true);
        totalStaffBtn.parentNode.replaceChild(newBtn, totalStaffBtn);
        newBtn.addEventListener('click', () => openDetailsModal('Total Staff Details', buildTotalStaffHtml(), newBtn));
    }
    
    if (avgKpiBtn) {
        const newBtn = avgKpiBtn.cloneNode(true);
        avgKpiBtn.parentNode.replaceChild(newBtn, avgKpiBtn);
        newBtn.addEventListener('click', () => openDetailsModal('Average KPI Breakdown', buildAverageKpiHtml(), newBtn));
    }
    
    if (improvingBtn) {
        const newBtn = improvingBtn.cloneNode(true);
        improvingBtn.parentNode.replaceChild(newBtn, improvingBtn);
        newBtn.addEventListener('click', () => openDetailsModal('Improving Performance Details', buildImprovingStaffHtml(), newBtn));
    }
    
    if (departmentsCountBtn) {
        const newBtn = departmentsCountBtn.cloneNode(true);
        departmentsCountBtn.parentNode.replaceChild(newBtn, departmentsCountBtn);
        newBtn.addEventListener('click', () => openDetailsModal('Departments Monitored', buildDepartmentsMonitoredHtml(), newBtn));
    }
    
    if (topPerformersCountBtn) {
        const newBtn = topPerformersCountBtn.cloneNode(true);
        topPerformersCountBtn.parentNode.replaceChild(newBtn, topPerformersCountBtn);
        newBtn.addEventListener('click', () => openDetailsModal('Top Performers Details', buildTopPerformersHtml(), newBtn));
    }
    
    if (atRiskCountBtn) {
        const newBtn = atRiskCountBtn.cloneNode(true);
        atRiskCountBtn.parentNode.replaceChild(newBtn, atRiskCountBtn);
        newBtn.addEventListener('click', () => openDetailsModal('At-Risk Staff Details', buildAtRiskStaffHtml(), newBtn));
    }
    
    // Also handle chart click events
    const riskChart = document.getElementById('riskHistogramChart');
    if (riskChart) {
        riskChart.removeAllListeners?.('plotly_click');
        riskChart.on('plotly_click', function(eventData) {
            if (!eventData || !eventData.points || !eventData.points.length) return;
            const clickedBand = eventData.points[0].x;
            openDetailsModal(`${clickedBand} Details`, buildRiskBandDetailsHtml(clickedBand), riskChart);
        });
    }
    
    const performanceChart = document.getElementById('performanceDistributionChart');
    if (performanceChart) {
        performanceChart.removeAllListeners?.('plotly_click');
        performanceChart.on('plotly_click', function(eventData) {
            const label = eventData.points?.[0]?.label;
            if (!label) return;
            openDetailsModal(`${label} Performance Details`, buildPerformanceSliceHtml(label), performanceChart);
        });
    }
    
    const trendChart = document.getElementById('trendDistributionChart');
    if (trendChart) {
        trendChart.removeAllListeners?.('plotly_click');
        trendChart.on('plotly_click', function(eventData) {
            const label = eventData.points?.[0]?.label;
            if (!label) return;
            openDetailsModal(`${label} Trend Details`, buildTrendSliceHtml(label), trendChart);
        });
    }
});
    function buildDepartmentDetailsHtml(departmentName) {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const rows = (latestDashboardData.department_stats || []).filter(
            item => item.department === departmentName
        );

        const staffRows = (latestDashboardData.at_risk_staff || []).filter(
            item => item.department === departmentName
        );

        let html = '';

        if (rows.length > 0) {
            const dept = rows[0];
            html += `
                <p><strong>Department:</strong> ${dept.department}</p>
                <p><strong>Average KPI:</strong> ${dept.score}%</p>
                <p><strong>At-Risk Staff:</strong> ${dept.at_risk}</p>
                <p><strong>Trend:</strong> ${dept.trend}</p>
            `;
        }

        if (staffRows.length > 0) {
            html += `
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Score</th>
                            <th>Trend</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${staffRows.map(staff => `
                            <tr>
                                <td>${escapeHtml(staff.name)}</td>
                                <td>${staff.score}%</td>
                                <td>${escapeHtml(staff.trend)}</td>
                                <td>${escapeHtml(staff.action)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } else {
            html += `<p class="modal-note">No staff in this department are currently in the at-risk list.</p>`;
        }

        return html;
    }

    function buildTotalStaffHtml() {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const rows = latestDashboardData.staff_snapshot_list || [];

        return `
            <p><strong>Total Staff:</strong> ${latestDashboardData.summary.total_staff}</p>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(item => `
                        <tr>
                            <td>${escapeHtml(item.staff_code || item.id || '-')}</td>
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.department)}</td>
                            <td>${escapeHtml(item.position || '-')}</td>
                            <td>${Number(item.score).toFixed(2)}%</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function buildAverageKpiHtml() {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const trendRows = latestDashboardData.performance_trend || [];

        return `
            <p><strong>Average KPI:</strong> ${latestDashboardData.summary.avg_kpi}%</p>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Actual KPI</th>
                        <th>Forecast KPI</th>
                        <th>Target</th>
                    </tr>
                </thead>
                <tbody>
                    ${trendRows.map(row => `
                        <tr>
                            <td>${escapeHtml(row.period)}</td>
                            <td>${row.actual !== null && row.actual !== undefined ? row.actual + '%' : '-'}</td>
                            <td>${row.forecast !== null && row.forecast !== undefined ? row.forecast + '%' : '-'}</td>
                            <td>${row.target}%</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <p class="modal-note">This summary shows the KPI movement across available years and periods based on the current filters.</p>
        `;
    }

    function buildImprovingStaffHtml() {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const deptStats = latestDashboardData.department_stats || [];
        const improvingDepartments = deptStats.filter(item => item.trend === 'Improving');

        if (improvingDepartments.length === 0) {
            return '<p>No improving departments or staff under current filters.</p>';
        }

        return `
            <p><strong>Improving Count:</strong> ${latestDashboardData.summary.improving}</p>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Average KPI</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    ${improvingDepartments.map(item => `
                        <tr>
                            <td>${escapeHtml(item.department)}</td>
                            <td>${item.score}%</td>
                            <td>${escapeHtml(item.trend)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <p class="modal-note">If you want, next we can upgrade this popup to include a mini line chart.</p>
        `;
    }

    function buildDepartmentsMonitoredHtml() {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const departments = latestDashboardData.department_stats || [];

        return `
            <p><strong>Departments Monitored:</strong> ${latestDashboardData.summary.departments}</p>
            <ul class="modal-list">
                ${departments.map(item => `
                    <li><strong>${escapeHtml(item.department)}</strong> — ${item.score}% average KPI</li>
                `).join('')}
            </ul>
        `;
    }

    function buildTopPerformersHtml() {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const deptStats = latestDashboardData.department_stats || [];
        const topCount = latestDashboardData.summary.top_performers || 0;

        return `
            <p><strong>Top Performers ≥ 85%:</strong> ${topCount}</p>
            <p class="modal-note">Current dashboard summary shows how many staff meet the top-performer threshold under the selected filters.</p>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Average KPI</th>
                        <th>Top Performers</th>
                    </tr>
                </thead>
                <tbody>
                    ${deptStats.map(item => `
                        <tr>
                            <td>${escapeHtml(item.department)}</td>
                            <td>${item.score}%</td>
                            <td>${item.top_performers}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function buildAtRiskStaffHtml() {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const rows = latestDashboardData.at_risk_staff || [];

        if (rows.length === 0) {
            return '<p>No at-risk staff under current filters.</p>';
        }

        return `
            <p><strong>At-Risk Staff:</strong> ${latestDashboardData.summary.at_risk}</p>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Score</th>
                        <th>Trend</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(item => `
                        <tr>
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.department)}</td>
                            <td>${item.score}%</td>
                            <td>${escapeHtml(item.trend)}</td>
                            <td>${escapeHtml(item.action)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function getRiskBandLabel(score) {
        const value = Number(score || 0);

        if (value >= 0 && value < 40) return '0-39';
        if (value >= 40 && value < 50) return '40-49';
        if (value >= 50 && value < 70) return '50-69';
        if (value >= 70 && value < 85) return '70-89';
        return '90-100';
    }

    function buildRiskBandDetailsHtml(bandLabel) {
        if (!latestDashboardData) return '<p>No data available.</p>';

        const allStaff = latestDashboardData.staff_snapshot_list || [];
        const atRiskStaff = latestDashboardData.at_risk_staff || [];

        let sourceRows = [];

        if (Array.isArray(allStaff) && allStaff.length > 0) {
            sourceRows = allStaff;
        } else {
            sourceRows = atRiskStaff;
        }

        const matchedRows = sourceRows.filter(item => {
            const score = Number(item.score ?? item.current_percentage ?? item.kpi_percentage ?? 0);
            return getRiskBandLabel(score) === bandLabel;
        });

        const displayBand = {
            '0-39': 'High Risk (0-39)',
            '40-49': 'Critical (40-49)',
            '50-69': 'Moderate (50-69)',
            '70-84': 'Good (70-84)',
            '85-100': 'Top (85-100)'
        }[bandLabel] || bandLabel;

        if (matchedRows.length === 0) {
            return `<p>No staff found in the <strong>${displayBand}</strong> band under the current filters.</p>`;
        }

        return `
            <p><strong>Risk Band:</strong> ${displayBand}</p>
            <p><strong>Total Staff in Band:</strong> ${matchedRows.length}</p>

            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Score</th>
                        <th>Trend</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${matchedRows.map(item => {
                        const score = Number(item.score ?? item.current_percentage ?? item.kpi_percentage ?? 0).toFixed(2);
                        const trend = item.trend === 'up'
                            ? 'Improving'
                            : item.trend === 'down'
                            ? 'Declining'
                            : item.trend === 'stable'
                            ? 'Stable'
                            : '-';
                        const action = (() => {
                        const score = Number(item.score ?? item.current_percentage ?? 0);
                        const trend = item.trend;

                        if (score < 40) return 'Immediate coaching plan';
                        if (score < 50) return trend === 'down' ? 'Targeted intervention' : 'Close monitoring';
                        if (score < 70) return trend === 'down' ? 'Performance improvement plan' : 'Regular coaching';
                        if (score < 85) return 'Maintain & monitor';
                        return 'Recognize & reward';
                        })();
                        const department = item.department ?? '-';
                        const name = item.name ?? '-';

                        return `
                            <tr>
                                <td>${escapeHtml(name)}</td>
                                <td>${escapeHtml(department)}</td>
                                <td>${score}%</td>
                                <td>${escapeHtml(trend)}</td>
                                <td>${escapeHtml(action)}</td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
    }

    function formatPerformanceLabel(label) {
        const map = {
            'Excellence': 'top',
            'Top': 'top',
            'Good': 'good',
            'Moderate': 'average',
            'Average': 'average',
            'Critical': 'critical',
            'At Risk': 'at-risk'
        };
        return map[label] || '';
    }

    function formatTrendLabel(label) {
        const map = {
            'Improving': 'up',
            'Stable': 'stable',
            'Declining': 'down'
        };
        return map[label] || '';
    }

    function buildPerformanceSliceHtml(sliceLabel) {
        if (!latestDashboardData || !latestDashboardData.staff_snapshot_list) {
            return '<p>No data available.</p>';
        }

        const performanceKey = formatPerformanceLabel(sliceLabel);
        const rows = latestDashboardData.staff_snapshot_list.filter(item => item.performance_level === performanceKey);

        if (rows.length === 0) {
            return `<p>No staff found in the <strong>${escapeHtml(sliceLabel)}</strong> band under the current filters.</p>`;
        }

        return `
            <p><strong>${escapeHtml(sliceLabel)} Staff:</strong> ${rows.length}</p>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Score</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(item => `
                        <tr>
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.department)}</td>
                            <td>${escapeHtml(item.position || '-')}</td>
                            <td>${item.score}%</td>
                            <td>${escapeHtml(item.trend === 'up' ? 'Improving' : item.trend === 'down' ? 'Declining' : 'Stable')}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function buildTrendSliceHtml(sliceLabel) {
        if (!latestDashboardData || !latestDashboardData.staff_snapshot_list) {
            return '<p>No data available.</p>';
        }

        const trendKey = formatTrendLabel(sliceLabel);
        const rows = latestDashboardData.staff_snapshot_list.filter(item => item.trend === trendKey);

        if (rows.length === 0) {
            return `<p>No staff found in the <strong>${escapeHtml(sliceLabel)}</strong> trend group under the current filters.</p>`;
        }

        return `
            <p><strong>${escapeHtml(sliceLabel)} Staff:</strong> ${rows.length}</p>
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Score</th>
                        <th>Performance Band</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(item => `
                        <tr>
                            <td>${escapeHtml(item.name)}</td>
                            <td>${escapeHtml(item.department)}</td>
                            <td>${escapeHtml(item.position || '-')}</td>
                            <td>${item.score}%</td>
                            <td>${escapeHtml(
                                item.performance_level === 'top' ? 'Top' :
                                item.performance_level === 'good' ? 'Good' :
                                item.performance_level === 'average' ? 'Average' :
                                item.performance_level === 'critical' ? 'Critical' : 'At Risk'
                            )}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function attachEvents() {
        document.getElementById('compareDepartmentFilter').addEventListener('change', event => {
        state.compareDepartment = event.target.value;
        if (latestDashboardData) {
            populateStaffDropdown(latestDashboardData);
        }
    });

    document.getElementById('performanceFilter').addEventListener('change', event => {
        state.performance = event.target.value;
        if (latestDashboardData) {
            populateStaffDropdown(latestDashboardData);
        }
    });

    document.getElementById('staff1Select').addEventListener('change', event => {
        const selected = event.target.selectedOptions[0];
        state.staff1 = selected ? selected.value : '';
        state.staff1Name = selected ? selected.dataset.name || '' : '';
        document.getElementById('compareBtn').disabled = !(state.staff1 && state.staff2 && state.staff1 !== state.staff2);
    });

    document.getElementById('staff2Select').addEventListener('change', event => {
        const selected = event.target.selectedOptions[0];
        state.staff2 = selected ? selected.value : '';
        state.staff2Name = selected ? selected.dataset.name || '' : '';
        document.getElementById('compareBtn').disabled = !(state.staff1 && state.staff2 && state.staff1 !== state.staff2);
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

        if (latestDashboardData) {
            populateStaffDropdown(latestDashboardData);
        }
    });

    document.getElementById('compareBtn').addEventListener('click', () => {
        if (!state.staff1 || !state.staff2 || state.staff1 === state.staff2) return;

        const params = new URLSearchParams({
            staff1: state.staff1,
            staff2: state.staff2
        });

        window.location.href = './staff_comparison_patched.php?' + params.toString();
    });
        document.getElementById('detailsModalClose').addEventListener('click', closeDetailsModal);
        document.getElementById('detailsModal').addEventListener('click', (event) => {
            if (event.target.id === 'detailsModal') closeDetailsModal();
        });
        document.getElementById('highRiskDetailsBtn').addEventListener('click', () => {
            if (!latestDashboardData || !latestDashboardData.high_risk_departments || latestDashboardData.high_risk_departments.length === 0) {
                openDetailsModal('High Risk Alert Details', '<p>No high-risk department under the current filters.</p>');
                return;
            }
            const dept = latestDashboardData.high_risk_departments[0];
            openDetailsModal('High Risk Alert Details', buildDepartmentDetailsHtml(dept.department));
        });
        document.getElementById('moderateRiskDetailsBtn').addEventListener('click', () => {
            if (!latestDashboardData || !latestDashboardData.moderate_risk_departments || latestDashboardData.moderate_risk_departments.length === 0) {
                openDetailsModal('Moderate Risk Alert Details', '<p>No moderate-risk department under the current filters.</p>');
                return;
            }
            const dept = latestDashboardData.moderate_risk_departments[0];
            openDetailsModal('Moderate Risk Alert Details', buildDepartmentDetailsHtml(dept.department));
        });
        document.getElementById('totalStaffBtn').addEventListener('click', () => openDetailsModal('Total Staff Details', buildTotalStaffHtml()));
        document.getElementById('avgKpiBtn').addEventListener('click', () => openDetailsModal('Average KPI Breakdown', buildAverageKpiHtml()));
        document.getElementById('improvingBtn').addEventListener('click', () => openDetailsModal('Improving Performance Details', buildImprovingStaffHtml()));
        document.getElementById('departmentsCountBtn').addEventListener('click', () => openDetailsModal('Departments Monitored', buildDepartmentsMonitoredHtml()));
        document.getElementById('topPerformersCountBtn').addEventListener('click', () => openDetailsModal('Top Performers Details', buildTopPerformersHtml()));
        document.getElementById('atRiskCountBtn').addEventListener('click', () => openDetailsModal('At-Risk Staff Details', buildAtRiskStaffHtml()));
    }

    attachEvents();
    loadDashboard().catch(error => {
        console.error(error);
        document.getElementById('performanceTrendInsight').textContent = 'Unable to load analytics data. Check analytics_data_patched.php and your database connection.';
    });

    </script>

    
</body>
</html>
