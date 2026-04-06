<?php
$activePage = 'analytics';
require_once __DIR__ . '/../includes/auth.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Comparison</title>
    <script src="https://cdn.plot.ly/plotly-2.35.2.min.js"></script>
    <link rel="stylesheet" href="../asset/universal.css?v=2">
    <link rel="stylesheet" href="../asset/analytics.css?v=2">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="analytics-layout comparison-page">
    <section class="page-header">
        <div>
            <a href="./analytics.php" class="back-link">← Back to Analytics</a>
            <h1>Individual Staff Comparison</h1>
            <p>Weighted KPI comparison using the latest records from the database.</p>
        </div>
        <div class="filter-toolbar">
            <select id="comparePeriodFilter">
                <option value="Yearly">Yearly</option>
                <option value="Monthly">Monthly</option>
            </select>
            <button class="ghost-btn" id="backToAnalytics" type="button">Choose Different Staff</button>
        </div>
    </section>

    <section class="comparison-cards-grid">
        <article class="card compare-card compare-card-a">
            <div id="staffCard1">Loading first staff...</div>
        </article>
        <article class="card compare-card compare-card-b">
            <div id="staffCard2">Loading second staff...</div>
        </article>
    </section>

    <section class="chart-grid comparison-grid-extended">
        <article class="card chart-card chart-span-2">
            <div class="chart-head"><h2>KPI Trend Comparison</h2></div>
            <div id="compareTrendChart" class="chart"></div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Category Radar Comparison</h2></div>
            <div id="compareRadarChart" class="chart"></div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Category Gap</h2></div>
            <div id="categoryGapChart" class="chart"></div>
        </article>

        <article class="card chart-card chart-span-2">
            <div class="chart-head"><h2>Stability Gauge</h2></div>
            <div id="stabilityGaugeChart" class="chart"></div>
        </article>

        <article class="card insight-card chart-span-2">
            <div class="chart-head"><h2>Supervisor Insight</h2></div>
            <p id="supervisorInsight" class="interpretation">Loading narrative insight...</p>
        </article>
    </section>
</main>

<script>
const params = new URLSearchParams(window.location.search);
const selectedStaff1 = params.get('staff1') || '<?php echo $staff1; ?>';
const selectedStaff2 = params.get('staff2') || '<?php echo $staff2; ?>';
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

    const response = await fetch('./analytics_data.php?' + query.toString(), {
        headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
        throw new Error(await response.text());
    }

    return await response.json();
}

function badgeClass(level) {
    switch (level) {
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

function renderStaffCard(targetId, data, accentClass) {
    const card = document.getElementById(targetId);
    const photo = data.staff.profile_photo || './asset/images/staff/default-profile.jpg';
    card.innerHTML = `
        <div class="compare-head ${accentClass}">
            <div class="compare-profile">
                <img src="${photo}" alt="${escapeHtml(data.staff.name)}" class="compare-avatar">
                <div>
                    <h2>${escapeHtml(data.staff.name)}</h2>
                    <p>${escapeHtml(data.staff.staff_code || 'No Code')} • ${escapeHtml(data.staff.department)}</p>
                    <p>${escapeHtml(data.staff.position || 'Staff')}</p>
                </div>
            </div>
            <div class="compare-score-pill">${Number(data.current_percentage).toFixed(2)}%</div>
        </div>

        <div class="mini-metrics-grid">
            <div class="mini-metric">
                <span>Current KPI</span>
                <strong>${Number(data.current_score_5).toFixed(2)} / 5</strong>
            </div>
            <div class="mini-metric">
                <span>Trend</span>
                <strong>${trendLabel(data.trend)} (${Number(data.trend_delta).toFixed(2)})</strong>
            </div>
            <div class="mini-metric">
                <span>Risk</span>
                <strong>${escapeHtml(data.risk_level)}</strong>
            </div>
            <div class="mini-metric">
                <span>Stability</span>
                <strong>${data.stability_score}/100</strong>
            </div>
        </div>

        <div class="status-row">
            <span class="level-badge ${badgeClass(data.performance_level)}">${escapeHtml(data.performance_level)}</span>
            <span class="level-note">Thresholds: Top ≥ 85, Good 80–84, Average 70–79, Critical 60–69, At-Risk &lt; 60</span>
        </div>

        <div class="comment-box">
            <h4>Supervisor Comment</h4>
            <p>${escapeHtml(data.comments)}</p>
        </div>
        <div class="comment-box">
            <h4>Training Recommendation</h4>
            <p>${escapeHtml(data.training)}</p>
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
            line: { color: '#ec4899', width: 3 },
            hovertemplate: '%{x}<br>' + escapeHtml(payload.staff1.staff.name) + ': %{y:.2f}%<extra></extra>'
        },
        {
            x: trendRows.map(row => row.period),
            y: trendRows.map(row => row.staff2),
            type: 'scatter',
            mode: 'lines+markers',
            name: payload.staff2.staff.name,
            line: { color: '#10b981', width: 3 },
            hovertemplate: '%{x}<br>' + escapeHtml(payload.staff2.staff.name) + ': %{y:.2f}%<extra></extra>'
        },
        {
            x: trendRows.map(row => row.period),
            y: trendRows.map(row => row.target),
            type: 'scatter',
            mode: 'lines',
            name: 'Target %',
            line: { color: '#14b8a6', dash: 'dash' },
            hovertemplate: '%{x}<br>Target: %{y:.2f}%<extra></extra>'
        }
    ], {
        margin: { t: 10, r: 10, b: 40, l: 50 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: { range: [0, 100], title: 'KPI %' }
    }, { responsive: true, displayModeBar: true });

    const radar = payload.radar_categories || [];
    Plotly.react('compareRadarChart', [
        {
            type: 'scatterpolar',
            r: radar.map(row => row.staff1),
            theta: radar.map(row => row.category),
            fill: 'toself',
            name: payload.staff1.staff.name,
            line: { color: '#ec4899' }
        },
        {
            type: 'scatterpolar',
            r: radar.map(row => row.staff2),
            theta: radar.map(row => row.category),
            fill: 'toself',
            name: payload.staff2.staff.name,
            line: { color: '#10b981' }
        },
        {
            type: 'scatterpolar',
            r: radar.map(row => row.target),
            theta: radar.map(row => row.category),
            fill: 'none',
            name: 'Target %',
            line: { color: '#14b8a6', dash: 'dash' }
        }
    ], {
        margin: { t: 10, r: 20, b: 20, l: 20 },
        paper_bgcolor: 'transparent',
        polar: {
            radialaxis: { visible: true, range: [0, 100] }
        }
    }, { responsive: true, displayModeBar: true });

    const gapRows = payload.category_gap || [];
    Plotly.react('categoryGapChart', [{
        x: gapRows.map(row => row.category),
        y: gapRows.map(row => row.gap),
        type: 'bar',
        marker: { color: gapRows.map(row => row.gap >= 0 ? '#ec4899' : '#10b981') },
        hovertemplate: '%{x}<br>Gap: %{y:.2f} points<extra></extra>'
    }], {
        margin: { t: 10, r: 10, b: 90, l: 50 },
        paper_bgcolor: 'transparent',
        plot_bgcolor: 'transparent',
        yaxis: { title: payload.staff1.staff.name + ' - ' + payload.staff2.staff.name }
    }, { responsive: true, displayModeBar: true });

    Plotly.react('stabilityGaugeChart', [
        {
            type: 'indicator',
            mode: 'gauge+number',
            value: payload.staff1.stability_score,
            title: { text: payload.staff1.staff.name },
            domain: { x: [0, 0.45], y: [0, 1] },
            gauge: {
                axis: { range: [0, 100] },
                bar: { color: '#ec4899' },
                steps: [
                    { range: [0, 50], color: '#fee2e2' },
                    { range: [50, 75], color: '#fef3c7' },
                    { range: [75, 100], color: '#dcfce7' }
                ]
            }
        },
        {
            type: 'indicator',
            mode: 'gauge+number',
            value: payload.staff2.stability_score,
            title: { text: payload.staff2.staff.name },
            domain: { x: [0.55, 1], y: [0, 1] },
            gauge: {
                axis: { range: [0, 100] },
                bar: { color: '#10b981' },
                steps: [
                    { range: [0, 50], color: '#fee2e2' },
                    { range: [50, 75], color: '#fef3c7' },
                    { range: [75, 100], color: '#dcfce7' }
                ]
            }
        }
    ], {
        margin: { t: 20, r: 20, b: 20, l: 20 },
        paper_bgcolor: 'transparent'
    }, { responsive: true, displayModeBar: false });
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
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

    renderStaffCard('staffCard1', payload.staff1, 'accent-a');
    renderStaffCard('staffCard2', payload.staff2, 'accent-b');
    renderCharts(payload);
    document.getElementById('supervisorInsight').textContent = payload.insight;
}

document.getElementById('comparePeriodFilter').addEventListener('change', event => {
    compareState.period = event.target.value;
    loadComparison().catch(console.error);
});

document.getElementById('backToAnalytics').addEventListener('click', () => {
    window.location.href = './analytics.php';
});

loadComparison().catch(error => {
    console.error(error);
    document.getElementById('supervisorInsight').textContent = 'Unable to load comparison data. Check analytics_data.php and your selected staff IDs.';
});
</script>
</body>
</html>
