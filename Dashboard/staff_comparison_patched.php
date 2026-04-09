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
    <link rel="stylesheet" href="../asset/analytics.css?v=2">
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
            <div id="trendInterpretation" class="interpretation">
                Loading trend interpretation...
            </div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Category Performance Comparison</h2></div>
            <div id="compareRadarChart" class="chart"></div>
            <div id="radarInterpretation" class="interpretation">
                Loading category interpretation...
            </div>
        </article>

        <article class="card chart-card">
            <div class="chart-head"><h2>Category Performance Gap</h2></div>
            <div id="categoryGapChart" class="chart"></div>
            <div id="gapInterpretation" class="interpretation">
                Loading gap interpretation...
            </div>
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

async function fetchComparison() {
    const query = new URLSearchParams({
        action: 'compare_staff',
        staff1: selectedStaff1,
        staff2: selectedStaff2
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

function wrapCategoryLabel(label) {
    const map = {
        'Customer Service Quality': 'Customer<br>Service<br>Quality',
        'Daily Sales Operations': 'Daily<br>Sales<br>Operations',
        'Inventory & Cost Control': 'Inventory &<br>Cost Control',
        'People, Training, Learning & Team Contribution': 'People, <br>Training, <br>Learning & Team<br>Contribution',
        'Sales Target Contribution': 'Sales Target<br>Contribution',
        'Store Operations Support': 'Store Operations<br>Support'
    };

    return map[label] || label;
}

function formatNumber(value) {
    return Number(value).toFixed(2);
}

function joinLabelsNaturally(items) {
    if (!items || items.length === 0) return '';
    if (items.length === 1) return items[0];
    if (items.length === 2) return items[0] + ' and ' + items[1];
    return items.slice(0, -1).join(', ') + ', and ' + items[items.length - 1];
}

function buildTrendInterpretation(payload) {
    const left = payload.staff1;
    const right = payload.staff2;

    const leftScore = Number(left.current_percentage);
    const rightScore = Number(right.current_percentage);
    const gap = Math.abs(leftScore - rightScore);

    let leaderText = '';
    if (leftScore > rightScore) {
        leaderText = `${left.staff.name} is currently leading with ${formatNumber(leftScore)}%, compared to ${right.staff.name} at ${formatNumber(rightScore)}%.`;
    } else if (rightScore > leftScore) {
        leaderText = `${right.staff.name} is currently leading with ${formatNumber(rightScore)}%, compared to ${left.staff.name} at ${formatNumber(leftScore)}%.`;
    } else {
        leaderText = `Both staff members currently have the same overall KPI performance at ${formatNumber(leftScore)}%.`;
    }

    let stabilityText = '';
    if (Number(left.stability_score) > Number(right.stability_score)) {
        stabilityText = `${left.staff.name} also shows more stable recent performance.`;
    } else if (Number(right.stability_score) > Number(left.stability_score)) {
        stabilityText = `${right.staff.name} also shows more stable recent performance.`;
    } else {
        stabilityText = `Both staff members show a similar level of stability.`;
    }

    if (gap > 0) {
        return `${leaderText} The current KPI gap between them is ${formatNumber(gap)} percentage points. ${stabilityText}`;
    }

    return `${leaderText} ${stabilityText}`;
}

function buildRadarInterpretation(payload) {
    const radar = payload.radar_categories || [];
    if (!radar.length) return 'No KPI category comparison data is available.';

    const positiveRows = radar.filter(row => Number(row.staff1) > Number(row.staff2));
    const negativeRows = radar.filter(row => Number(row.staff2) > Number(row.staff1));
    const equalRows = radar.filter(row => Number(row.staff1) === Number(row.staff2));

    const topPositive = positiveRows.sort((a, b) => (Number(b.staff1) - Number(b.staff2)) - (Number(a.staff1) - Number(a.staff2)))[0];
    const topNegative = negativeRows.sort((a, b) => (Number(b.staff2) - Number(b.staff1)) - (Number(a.staff2) - Number(a.staff1)))[0];

    let text = 'This chart compares both staff members across the main KPI categories. ';

    if (topPositive) {
        text += `${payload.staff1.staff.name} performs better in ${topPositive.category}, where the score advantage is ${formatNumber(Number(topPositive.staff1) - Number(topPositive.staff2))} points. `;
    }

    if (topNegative) {
        text += `${payload.staff2.staff.name} performs better in ${topNegative.category}, with a ${formatNumber(Number(topNegative.staff2) - Number(topNegative.staff1))} point advantage. `;
    }

    if (equalRows.length > 0) {
        text += `Both staff members are equal in ${joinLabelsNaturally(equalRows.map(row => row.category))}.`;
    }

    return text.trim();
}

function buildGapInterpretation(payload) {
    const gapRows = payload.category_gap || [];
    if (!gapRows.length) return 'No category gap data is available.';

    const positiveRows = gapRows.filter(row => Number(row.gap) > 0);
    const negativeRows = gapRows.filter(row => Number(row.gap) < 0);
    const neutralRows = gapRows.filter(row => Number(row.gap) === 0);

    const strongestPositive = [...positiveRows].sort((a, b) => Number(b.gap) - Number(a.gap))[0];
    const strongestNegative = [...negativeRows].sort((a, b) => Number(a.gap) - Number(b.gap))[0];

    let text = '';

    if (strongestPositive) {
        text += `${payload.staff1.staff.name} has the biggest positive advantage in ${strongestPositive.category} at ${formatNumber(Number(strongestPositive.gap))} points. `;
    }

    if (strongestNegative) {
        text += `${payload.staff2.staff.name} has the biggest positive advantage in ${strongestNegative.category} at ${formatNumber(Math.abs(Number(strongestNegative.gap)))} points. `;
    }

    if (neutralRows.length > 0) {
        text += `Both staff members perform equally in ${joinLabelsNaturally(neutralRows.map(row => row.category))}.`;
    }

    return text.trim();
}

function renderChartInterpretations(payload) {
    document.getElementById('trendInterpretation').innerHTML =
        `<strong>Interpretation:</strong> ${escapeHtml(buildTrendInterpretation(payload))}`;

    document.getElementById('radarInterpretation').innerHTML =
        `<strong>Interpretation:</strong> ${escapeHtml(buildRadarInterpretation(payload))}`;

    document.getElementById('gapInterpretation').innerHTML =
        `<strong>Interpretation:</strong> ${escapeHtml(buildGapInterpretation(payload))}`;
}

function normalisePayloadOrder(payload) {
    const originalStaff1 = payload.staff1;
    const originalStaff2 = payload.staff2;

    const orderedStaff = [originalStaff1, originalStaff2].sort(
        (a, b) => Number(b.current_percentage) - Number(a.current_percentage)
    );

    const leftStaff = orderedStaff[0];
    const rightStaff = orderedStaff[1];
    const leftIsOriginalStaff1 = leftStaff.staff.id == originalStaff1.staff.id;

    const trendRows = (payload.trend_series || []).map(row => ({
        period: row.period,
        staff1: leftIsOriginalStaff1 ? row.staff1 : row.staff2,
        staff2: leftIsOriginalStaff1 ? row.staff2 : row.staff1,
        target: row.target
    }));

    const radarCategories = (payload.radar_categories || []).map(row => ({
        category: row.category,
        staff1: leftIsOriginalStaff1 ? row.staff1 : row.staff2,
        staff2: leftIsOriginalStaff1 ? row.staff2 : row.staff1,
        target: row.target
    }));

    const categoryGap = (payload.category_gap || []).map(row => ({
        category: row.category,
        gap: leftIsOriginalStaff1 ? row.gap : (Number(row.gap) * -1)
    }));

    return {
        ...payload,
        staff1: leftStaff,
        staff2: rightStaff,
        trend_series: trendRows,
        radar_categories: radarCategories,
        category_gap: categoryGap
    };
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
    const wrappedGapLabels = gapRows.map(row => wrapCategoryLabel(row.category));

    Plotly.react('categoryGapChart', [{
        x: wrappedGapLabels,
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
            tickangle: 0,
            automargin: true
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

    const normalisedPayload = normalisePayloadOrder(payload);
    const leftStaff = normalisedPayload.staff1;
    const rightStaff = normalisedPayload.staff2;

    renderStaffCard('staffCard1', leftStaff, 'theme-green');
    renderStaffCard('staffCard2', rightStaff, 'theme-pink');
    renderCharts(normalisedPayload);
    renderChartInterpretations(normalisedPayload);

    document.getElementById('supervisorInsight').textContent =
        buildComparisonInsight(leftStaff, rightStaff, normalisedPayload);

    document.getElementById('leftNoteTitle').textContent = `${leftStaff.staff.name} — Supervisor Comment`;
    document.getElementById('leftCommentText').textContent =
        leftStaff.comments || 'No supervisor comment available.';

    document.getElementById('rightNoteTitle').textContent = `${rightStaff.staff.name} — Training Recommendation`;
    document.getElementById('rightTrainingText').textContent =
        rightStaff.training || 'No training recommendation available.';
}

loadComparison().catch(error => {
    console.error(error);
    document.getElementById('supervisorInsight').textContent =
        'Unable to load comparison data. Check analytics_data_patched.php and the selected staff IDs.';
});
</script>
</body>
</html>
