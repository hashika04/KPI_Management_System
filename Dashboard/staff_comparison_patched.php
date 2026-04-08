<?php
$activePage = 'analytics';
require_once __DIR__ . '/../includes/auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual Staff Comparison</title>
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
    <link rel="stylesheet" href="../asset/universal.css?v=2">
    <link rel="stylesheet" href="../asset/analytics.css?v=20">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="analytics-layout comparison-page">
    <section class="page-header">
        <div>
            <a href="./analytics_patched.php" class="back-link">← Back to Analytics</a>
            <h1>Individual Staff Comparison</h1>
            <p>Compare two staff members using the latest KPI records, category performance, and trend behaviour.</p>
        </div>

        <div class="filter-toolbar">
            <select id="comparePeriodFilter">
                <option value="Yearly">Yearly</option>
                <option value="Monthly">Monthly</option>
            </select>
            <button class="ghost-btn" id="backToAnalytics" type="button">Choose Different Staff</button>
        </div>
    </section>

    <section class="comparison-cards-grid compact-comparison-grid">
        <article class="card compare-summary-card compare-summary-strong" id="compareCard1">
            <div id="staffCard1">Loading first staff...</div>
        </article>

        <article class="card compare-summary-card compare-summary-weak" id="compareCard2">
            <div id="staffCard2">Loading second staff...</div>
        </article>
    </section>

    <section class="chart-grid comparison-clean-grid">
        <article class="card chart-card chart-span-2">
            <div class="chart-head"><h2>KPI Trend Comparison</h2></div>
            <div id="compareTrendChart" class="chart"></div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Category Performance Comparison</h2></div>
            <div id="compareRadarChart" class="chart"></div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Category Performance Gap</h2></div>
            <div id="categoryGapChart" class="chart"></div>
        </article>

        <article class="card insight-card chart-span-2">
            <div class="chart-head"><h2>Supervisor Insight</h2></div>
            <div id="supervisorInsightBox" class="comparison-insight-box">
                <p id="supervisorInsight" class="interpretation">Loading narrative insight...</p>
            </div>
        </article>

        <article class="card chart-span-2 comparison-notes-card">
            <div class="comparison-notes-grid">
                <div class="comparison-note-block">
                    <h3 id="leftNoteTitle">Supervisor Comment</h3>
                    <p id="leftCommentText">Loading...</p>
                </div>

                <div class="comparison-note-block">
                    <h3 id="rightNoteTitle">Training Recommendation</h3>
                    <p id="rightTrainingText">Loading...</p>
                </div>
            </div>
        </article>
    </section>
</main>

<script>
const params = new URLSearchParams(window.location.search);
const selectedStaff1 = params.get('staff1') || '';
const selectedStaff2 = params.get('staff2') || '';

const compareState = {
    period: 'Yearly'
};

async function fetchComparison() {
    const query = new URLSearchParams({
        action: 'compare_staff',
        staff1: selectedStaff1,
        staff2: selectedStaff2,
        period: compareState.period
    });

    const response = await fetch('./analytics_data_patched.php?' + query.toString(), {
        headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
        throw new Error(await response.text());
    }

    return await response.json();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function badgeClass(level) {
    switch (String(level || '').toLowerCase()) {
        case 'top': return 'badge-top';
        case 'good': return 'badge-good';
        case 'average': return 'badge-average';
        case 'critical': return 'badge-critical';
        default: return 'badge-risk';
    }
}

function trendLabel(trend) {
    return trend === 'up' ? 'Improving' : trend === 'down' ? 'Declining' : 'Stable';
}

function safePhoto(path) {
    return path && String(path).trim() !== ''
        ? path
        : '../asset/images/staff/default-profile.jpg';
}

function buildComparisonInsight(leftStaff, rightStaff, payload) {
    const gap = Math.abs(Number(leftStaff.current_percentage) - Number(rightStaff.current_percentage)).toFixed(2);

    let strongerStability = '';
    if (Number(leftStaff.stability_score) > Number(rightStaff.stability_score)) {
        strongerStability = `${leftStaff.staff.name} also shows stronger stability over time.`;
    } else if (Number(leftStaff.stability_score) < Number(rightStaff.stability_score)) {
        strongerStability = `${rightStaff.staff.name} is more stable across recent periods despite the lower KPI.`;
    } else {
        strongerStability = `Both staff members show a similar level of stability.`;
    }

    return `${leftStaff.staff.name} currently leads with ${Number(leftStaff.current_percentage).toFixed(2)}%, while ${rightStaff.staff.name} records ${Number(rightStaff.current_percentage).toFixed(2)}%. The KPI gap between them is ${gap} percentage points. ${strongerStability} ${payload.insight || ''}`;
}

function renderStaffCard(targetId, data, theme) {
    const card = document.getElementById(targetId);
    const photo = safePhoto(data.staff.profile_photo);
    const trendText = trendLabel(data.trend);
    const performanceLabel = String(data.performance_level || '').replace('-', ' ');
    const scoreText = Number(data.current_percentage).toFixed(2) + '%';

    card.innerHTML = `
        <div class="compare-mini-head ${theme}">
            <div class="compare-mini-profile">
                <img src="${escapeHtml(photo)}" alt="${escapeHtml(data.staff.name)}" class="compare-mini-avatar">
                <div>
                    <h2>${escapeHtml(data.staff.name)}</h2>
                    <p>${escapeHtml(data.staff.department)}</p>
                    <small>${escapeHtml(data.staff.position || 'Staff')}</small>
                </div>
            </div>

            <div class="compare-mini-score">${scoreText}</div>
        </div>

        <div class="compare-mini-metrics">
            <div class="compare-metric-pill">
                <span>Trend</span>
                <strong>${escapeHtml(trendText)}</strong>
            </div>
            <div class="compare-metric-pill">
                <span>Stability</span>
                <strong>${Number(data.stability_score)}/100</strong>
            </div>
            <div class="compare-metric-pill">
                <span>Risk</span>
                <strong>${escapeHtml(data.risk_level)}</strong>
            </div>
        </div>

        <div class="compare-mini-footer">
            <span class="level-badge ${badgeClass(data.performance_level)}">${escapeHtml(performanceLabel)}</span>
            <span class="compare-note-inline">Latest KPI performance from database</span>
        </div>
    `;
}

function renderCharts(payload) {
    const trendRows = payload.trend_series || [];

    Plotly.react('compareTrendChart', [
        {
            x: trendRows.map(row => row.period),
            y: trendRows.map(row => row.staff1),
            type: 'scatter',
            mode: 'lines+markers',
            name: payload.staff1.staff.name,
            line: { color: '#16a34a', width: 3, shape: 'spline' },
            marker: { size: 8, color: '#16a34a' },
            hovertemplate: '%{x}<br>' + escapeHtml(payload.staff1.staff.name) + ': %{y:.2f}%<extra></extra>'
        },
        {
            x: trendRows.map(row => row.period),
            y: trendRows.map(row => row.staff2),
            type: 'scatter',
            mode: 'lines+markers',
            name: payload.staff2.staff.name,
            line: { color: '#ec4899', width: 3, shape: 'spline' },
            marker: { size: 8, color: '#ec4899' },
            hovertemplate: '%{x}<br>' + escapeHtml(payload.staff2.staff.name) + ': %{y:.2f}%<extra></extra>'
        },
        {
            x: trendRows.map(row => row.period),
            y: trendRows.map(row => row.target),
            type: 'scatter',
            mode: 'lines',
            name: 'Target %',
            line: { color: '#14b8a6', dash: 'dash', width: 2 },
            hovertemplate: '%{x}<br>Target: %{y:.2f}%<extra></extra>'
        }
    ], {
        margin: { t: 20, r: 20, b: 50, l: 55 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: {
            range: [0, 100],
            title: 'KPI %',
            gridcolor: 'rgba(148, 163, 184, 0.18)'
        },
        xaxis: {
            showgrid: false
        },
        legend: {
            orientation: 'h',
            x: 0,
            y: 1.12
        }
    }, { responsive: true, displayModeBar: true });

    const radar = payload.radar_categories || [];

    Plotly.react('compareRadarChart', [
        {
            type: 'scatterpolar',
            r: radar.map(row => row.staff1),
            theta: radar.map(row => row.category),
            fill: 'toself',
            name: payload.staff1.staff.name,
            line: { color: '#16a34a', width: 2 },
            fillcolor: 'rgba(22, 163, 74, 0.28)'
        },
        {
            type: 'scatterpolar',
            r: radar.map(row => row.staff2),
            theta: radar.map(row => row.category),
            fill: 'toself',
            name: payload.staff2.staff.name,
            line: { color: '#ec4899', width: 2 },
            fillcolor: 'rgba(236, 72, 153, 0.22)'
        },
        {
            type: 'scatterpolar',
            r: radar.map(row => row.target),
            theta: radar.map(row => row.category),
            fill: 'none',
            name: 'Target %',
            line: { color: '#94a3b8', dash: 'dot', width: 2 }
        }
    ], {
        margin: { t: 20, r: 30, b: 20, l: 30 },
        paper_bgcolor: 'transparent',
        polar: {
            radialaxis: {
                visible: true,
                range: [0, 100]
            }
        },
        legend: {
            orientation: 'h',
            x: 0,
            y: 1.12
        }
    }, { responsive: true, displayModeBar: true });

    const gapRows = payload.category_gap || [];

    Plotly.react('categoryGapChart', [{
        x: gapRows.map(row => row.category),
        y: gapRows.map(row => row.gap),
        type: 'bar',
        marker: {
            color: gapRows.map(row => row.gap >= 0 ? '#16a34a' : '#ec4899')
        },
        hovertemplate: '%{x}<br>Difference: %{y:.2f} points<extra></extra>'
    }], {
        margin: { t: 20, r: 20, b: 100, l: 55 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: {
            title: 'Gap',
            zeroline: true,
            gridcolor: 'rgba(148, 163, 184, 0.18)'
        },
        xaxis: {
            tickangle: -90
        }
    }, { responsive: true, displayModeBar: true });
}

async function loadComparison() {
    if (!selectedStaff1 || !selectedStaff2) {
        document.getElementById('supervisorInsight').textContent = 'Please return to analytics and choose two staff members first.';
        return;
    }

    const payload = await fetchComparison();

    if (payload.error) {
        document.getElementById('supervisorInsight').textContent = payload.error;
        return;
    }

    const orderedStaff = [payload.staff1, payload.staff2].sort(
        (a, b) => Number(b.current_percentage) - Number(a.current_percentage)
    );

    const leftStaff = orderedStaff[0];
    const rightStaff = orderedStaff[1];

    renderStaffCard('staffCard1', leftStaff, 'theme-green');
    renderStaffCard('staffCard2', rightStaff, 'theme-pink');

    renderCharts({
        ...payload,
        staff1: leftStaff,
        staff2: rightStaff
    });

    document.getElementById('supervisorInsight').textContent =
        buildComparisonInsight(leftStaff, rightStaff, payload);

    document.getElementById('leftNoteTitle').textContent = `${leftStaff.staff.name} — Supervisor Comment`;
    document.getElementById('leftCommentText').textContent =
        leftStaff.comments || 'No supervisor comment available.';

    document.getElementById('rightNoteTitle').textContent = `${rightStaff.staff.name} — Training Recommendation`;
    document.getElementById('rightTrainingText').textContent =
        rightStaff.training || 'No training recommendation available.';
}

document.getElementById('comparePeriodFilter').addEventListener('change', event => {
    compareState.period = event.target.value;
    loadComparison().catch(console.error);
});

document.getElementById('backToAnalytics').addEventListener('click', () => {
    window.location.href = './analytics_patched.php';
});

loadComparison().catch(error => {
    console.error(error);
    document.getElementById('supervisorInsight').textContent =
        'Unable to load comparison data. Check analytics_data_patched.php and the selected staff IDs.';
});
</script>
</body>
</html>