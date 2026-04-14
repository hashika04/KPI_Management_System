<?php
//overview.php
// Set default year to 2025 (not null)
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : 2025;

include("../config/db.php");
include("../includes/auth.php");

// Compute yearly averages for the sparkline and main KPI stat
$chartSql = "
    SELECT 
        YEAR(Date) as kpi_year,
        ROUND((AVG(Score)/5)*100, 1) as yearly_avg 
    FROM kpi_data 
    WHERE YEAR(Date) BETWEEN 2022 AND 2025
    GROUP BY kpi_year 
    ORDER BY kpi_year ASC
";
$chartRes = $conn->query($chartSql);
$yearlyData = ['2022' => 0, '2023' => 0, '2024' => 0, '2025' => 0];
while ($row = $chartRes->fetch_assoc()) {
    $yearlyData[$row['kpi_year']] = $row['yearly_avg'];
}
$avgKPI = $yearlyData[$statYear] ?? 0;
$jsDataString = implode(', ', array_values($yearlyData));

include("../Dashboard/data.php");

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$lanIp = gethostbyname(gethostname());
$currentHost = (filter_var($lanIp, FILTER_VALIDATE_IP) && $lanIp !== '127.0.0.1')
    ? $lanIp
    : '192.168.0.233';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$dynamicBaseUrl = 'https://kpimonitor.infinityfreeapp.com/KPI_Management_System/staff_masterlist/staffprofile.php?id=';

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Performance Overview – KPI Monitor</title>

  <!-- Shared design system -->
  <link rel="stylesheet" href="../asset/universal.css">
  <!-- Page-specific styles -->
  <link rel="stylesheet" href="../asset/overview.css">
  <!-- Icons -->
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <!-- ApexCharts for KPI trend visualization -->
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script src="https://cdn.jsdelivr.net/npm/d3@7"></script>
</head>
<body>

<?php include("../includes/sidebar.php"); ?>

<div class="overview-page">

  <!-- ── Page Header ── -->
  <div class="ov-header">
    <h1 class="ov-title">Performance Overview</h1>
    <p class="ov-sub">Monitor and analyze team performance metrics</p>
  </div>

  <!-- ══════════════════════
       STAT CARDS
  ══════════════════════ -->
  <!-- Year filter for stat cards (Top Performers, Average KPI, Critical KPI Group) -->
    <div class="stat-year-filter" style="margin-bottom: 20px;">
        <form method="GET" id="statYearForm">
            <label for="stat_year">Stat Cards Year: </label>
            <select name="stat_year" id="stat_year" onchange="this.form.submit()" class="year-filter-form">
                <?php
                $statYearSelected = isset($_GET['stat_year']) ? intval($_GET['stat_year']) : 2025;
                $yearRes = $conn->query("SELECT DISTINCT YEAR(Date) as yr FROM kpi_data ORDER BY yr DESC");
                while($yr = $yearRes->fetch_assoc()):
                    $sel = ($yr['yr'] == $statYearSelected) ? 'selected' : '';
                ?>
                    <option value="<?= $yr['yr'] ?>" <?= $sel ?>><?= $yr['yr'] ?></option>
                <?php endwhile; ?>
            </select>
        </form>
    </div>
  <div class="stat-cards">

    <div class="stat-card stat-card--dept">
      <div class="stat-icon blue"><i class="ph ph-users-three"></i></div>
      <div class="stat-value"><?= $totalStaff ?></div>
      <div class="stat-label">Total Staff</div>
      <div id="dept-donut" style="width: 100%; height: 120px; margin-top: auto; margin-bottom: -15px;"></div>
    </div>

    <div class="stat-card stat-card--top">
      <div class="stat-icon green"><i class="ph ph-medal"></i></div>
      <div class="stat-value"><?= $topCount ?></div>
        <div class="stat-label">Top Performers</div>

        <div class="top-perf-bars">
          <?php if ($topCount === 0): ?>
              <div class="perf-bar-row">
                  <div class="perf-bar-header">
                      <span class="perf-bar-label">No top performers</span>
                      <span class="perf-bar-pct">0%</span>
                  </div>
                  <div class="perf-bar-track">
                      <div class="perf-bar-fill" style="width:0%"></div>
                  </div>
              </div>
          <?php else: ?>
              <?php 
              foreach (array_slice($topPerformers, 0, 3) as $p): 
                  $pct = $p['score']; 
                  $firstName = explode(' ', $p['name'])[0];
              ?>
              <div class="perf-bar-row">
                  <div class="perf-bar-header">
                      <span class="perf-bar-label"><?= htmlspecialchars($firstName) ?></span>
                      <span class="perf-bar-pct"><?= $pct ?>%</span>
                  </div>
                  <div class="perf-bar-track">
                      <div class="perf-bar-fill" style="width:<?= $pct ?>%"></div>
                  </div>
              </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>
    </div>

    <div class="stat-card stat-card--kpi">
      <div class="stat-icon teal"><i class="ph ph-trend-up"></i></div>
      <div class="stat-value"><?= $avgKPI ?>%</div>
      <div class="stat-label">Average KPI </div>
      <div id="sparkline-kpi" class="sparkline-container"></div>
    </div>

    <div class="stat-card stat-card--critical" style="overflow: visible;">
      <div class="stat-icon red-icon"><i class="ph-bold ph-warning-circle"></i></div>
      <div class="stat-value truncate" title="<?= htmlspecialchars($groupLabels[0]) ?>">
          <?= htmlspecialchars($groupLabels[0]) ?>
      </div>
      <div class="stat-label">Critical KPI Group (<?= $statYear ?>)</div>
      <div id="group-bar-chart" class="group-bar-container"></div>
  </div>

  </div>

  <!-- ══════════════════════
      HEATMAP ROW
  ══════════════════════ -->
  <div class="heatmap-row">
    <div class="heatmap-card">
      <div class="ov-section-head" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
          <div>
              <h2>Department × KPI Group Performance</h2>
              <p>Bubble size and color represent average KPI score — 2025</p>
          </div>
      </div>
      <div id="kpiHeatmap"></div>
      <div class="heatmap-insight" style="margin-top: 20px; padding: 12px 16px; background: #f8f9fc; border-radius: 12px; border-left: 4px solid #7e22ce;">
          <i class="ph ph-lightbulb" style="margin-right: 8px; color: #7e22ce;"></i>
          <strong>Insight:</strong> <?= $heatmapInsight ?>
      </div>
    </div>
    
    <!-- SPEEDOMETER -->
    <div class="heatmap-card speedometer-card">
      <div class="ov-section-head">
          <div>
              <h2>Target Achievement</h2>
              <p>Progress against set KPI goal</p>
          </div>
          <form method="GET" id="speedoForm" style="display: flex; gap: 8px; align-items: center;">
              <?php if (isset($_GET['year'])): ?>
                  <input type="hidden" name="year" value="<?= htmlspecialchars($_GET['year']) ?>">
              <?php endif; ?>
              
              <select name="speedo_year" id="speedoYear" class="year-filter-form" onchange="this.form.submit()">
                  <?php
                  $speedoYearSelected = isset($_GET['speedo_year']) ? intval($_GET['speedo_year']) : 2025;
                  $speedoYearRes = $conn->query("SELECT DISTINCT YEAR(Date) as yr FROM kpi_data ORDER BY yr DESC");
                  while($syr = $speedoYearRes->fetch_assoc()):
                      $sel = ($syr['yr'] == $speedoYearSelected) ? 'selected' : '';
                  ?>
                      <option value="<?= $syr['yr'] ?>" <?= $sel ?>><?= $syr['yr'] ?></option>
                  <?php endwhile; ?>
              </select>
              
              <input type="number" name="speedo_target" id="speedoTarget" value="<?= isset($_GET['speedo_target']) ? intval($_GET['speedo_target']) : 80 ?>" min="1" max="100" style="width: 60px; padding: 4px; border-radius: 6px; border: 1.5px solid #efd8e5;">
              <span style="font-size: 12px; font-weight: 700;">%</span>
              <button type="submit" style="display: none;">Apply</button>
          </form>
      </div>

      <div id="targetSpeedometer"></div>

      <div class="speedo-footer">
          Actual Avg: <span id="actualVal">63%</span>
      </div>

      <div class="target-insight" style="margin: 16px 0 12px 0; padding: 12px 16px; background: #fefce8; border-radius: 12px; border-left: 4px solid #eab308;">
          <i class="ph ph-chart-line" style="margin-right: 8px; color: #eab308;"></i>
          <strong>Insight:</strong> <?= $targetInsight ?>
      </div>
    </div>
  </div>

  <!-- ══════════════════════
       TOP PERFORMERS PODIUM
  ══════════════════════ -->
  <div class="performers-card">
    <div style="display:flex; justify-content:center; align-items:center; margin-bottom:20px; position:relative;">
      <h2 class="performers-title" style="margin:0;">Highest KPI Staff</h2>
      <div style="position:absolute; right:0;">
        <form method="GET">
          <input type="hidden" name="speedo_year" value="<?= isset($_GET['speedo_year']) ? intval($_GET['speedo_year']) : 2025 ?>">
          <select name="year" onchange="this.form.submit()" class="year-filter-form">
              <option value="">Overall (All Years)</option>
              <?php
              $yearRes = $conn->query("SELECT DISTINCT YEAR(Date) as yr FROM kpi_data ORDER BY yr DESC");
              while($yr = $yearRes->fetch_assoc()):
                  $selected = (isset($_GET['year']) && $_GET['year'] == $yr['yr']) ? 'selected' : '';
              ?>
                  <option value="<?= $yr['yr'] ?>" <?= $selected ?>><?= $yr['yr'] ?></option>
              <?php endwhile; ?>
          </select>
      </form>
      </div>
    </div>

    <div class="podium-grid">

      <!-- Bronze — 3rd (left) -->
      <?php if ($podium['bronze'] && $podium['bronze']['podium_score'] > 0): $p = $podium['bronze']; ?>
        <a href="../staff_masterlist/staffprofile.php?id=<?= $p['id'] ?>" class="performer-card bronze">
            <span class="medal-badge bronze">Bronze</span>
            <div class="performer-avatar">
                <?php if (!empty($p['avatar']) && file_exists($p['avatar'])): ?>
                    <img src="<?= htmlspecialchars($p['avatar']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                    <div class="avatar-initials"><?= strtoupper(substr($p['name'],0,2)) ?></div>
                <?php endif; ?>
            </div>
            <div class="performer-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="performer-id">Staff ID: <?= htmlspecialchars($p['staffId']) ?></div>
            <div class="performer-kpi">
                <div class="performer-kpi-row">
                    <span class="performer-kpi-label">Overall KPI:</span>
                    <span class="performer-kpi-value"><?= $p['podium_score'] ?>%</span>
                </div>
                <div class="kpi-bar-bg">
                    <div class="kpi-bar-fill" style="width:<?= min($p['podium_score'],100) ?>%"></div>
                </div>
            </div>
            <div class="qr-wrap">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($dynamicBaseUrl . $p['id']) ?>" 
                    alt="Scan to view profile" class="qr-image" width="120" height="120">
            </div>
            <span class="view-btn bronze">View Details <i class="ph ph-arrow-right"></i></span>
        </a>
      <?php else: ?>
          <div class="performer-card bronze" style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:300px; opacity:0.5;">
              <span class="medal-badge bronze">Bronze</span>
              <div style="margin-top:20px; text-align:center;">
                  <div style="font-size:40px;">🏅</div>
                  <p style="margin-top:10px; font-weight:600; color:#888;">No Staff</p>
                  <p style="font-size:12px; color:#aaa;">No KPI records for this period</p>
              </div>
          </div>
      <?php endif; ?>

      <!-- Gold — 1st (centre) -->
      <?php if ($podium['gold'] && $podium['gold']['podium_score'] > 0): $p = $podium['gold']; ?>
        <a href="../staff_masterlist/staffprofile.php?id=<?= $p['id'] ?>" class="performer-card gold">
            <span class="medal-badge gold">Gold</span>
            <div class="performer-avatar">
                <?php if (!empty($p['avatar']) && file_exists($p['avatar'])): ?>
                    <img src="<?= htmlspecialchars($p['avatar']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                    <div class="avatar-initials"><?= strtoupper(substr($p['name'],0,2)) ?></div>
                <?php endif; ?>
            </div>
            <div class="performer-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="performer-id">Staff ID: <?= htmlspecialchars($p['staffId']) ?></div>
            <div class="performer-kpi">
                <div class="performer-kpi-row">
                    <span class="performer-kpi-label">Overall KPI:</span>
                    <span class="performer-kpi-value"><?= $p['podium_score'] ?>%</span>
                </div>
                <div class="kpi-bar-bg">
                    <div class="kpi-bar-fill" style="width:<?= min($p['score'],100) ?>%"></div>
                </div>
            </div>
            <div class="qr-wrap">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($dynamicBaseUrl . $p['id']) ?>" 
                    alt="Scan to view profile" class="qr-image" width="120" height="120">
            </div>
            <span class="view-btn gold">View Details <i class="ph ph-arrow-right"></i></span>
        </a>
      <?php else: ?>
          <div class="performer-card gold" style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:300px; opacity:0.5;">
              <span class="medal-badge gold">Gold</span>
              <div style="margin-top:20px; text-align:center;">
                  <div style="font-size:40px;">🥇</div>
                  <p style="margin-top:10px; font-weight:600; color:#888;">No Staff Data</p>
                  <p style="font-size:12px; color:#aaa;">No KPI records for this period</p>
              </div>
          </div>
      <?php endif; ?>

      <!-- Silver — 2nd (right) -->
      <?php if ($podium['silver'] && $podium['silver']['podium_score'] > 0): $p = $podium['silver']; ?>
        <a href="../staff_masterlist/staffprofile.php?id=<?= $p['id'] ?>" class="performer-card silver">
            <span class="medal-badge silver">Silver</span>
            <div class="performer-avatar">
                <?php if (!empty($p['avatar']) && file_exists($p['avatar'])): ?>
                    <img src="<?= htmlspecialchars($p['avatar']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                    <div class="avatar-initials"><?= strtoupper(substr($p['name'],0,2)) ?></div>
                <?php endif; ?>
            </div>
            <div class="performer-name"><?= htmlspecialchars($p['name']) ?></div>
            <div class="performer-id">Staff ID: <?= htmlspecialchars($p['staffId']) ?></div>
            <div class="performer-kpi">
                <div class="performer-kpi-row">
                    <span class="performer-kpi-label">Overall KPI:</span>
                    <span class="performer-kpi-value"><?= $p['podium_score'] ?>%</span>
                </div>
                <div class="kpi-bar-bg">
                    <div class="kpi-bar-fill" style="width:<?= min($p['podium_score'],100) ?>%"></div>
                </div>
            </div>
            <div class="qr-wrap">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($dynamicBaseUrl . $p['id']) ?>" 
                    alt="Scan to view profile" class="qr-image" width="120" height="120">
            </div>
            <span class="view-btn silver">View Details <i class="ph ph-arrow-right"></i></span>
        </a>
      <?php else: ?>
          <div class="performer-card silver" style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:300px; opacity:0.5;">
              <span class="medal-badge silver">Silver</span>
              <div style="margin-top:20px; text-align:center;">
                  <div style="font-size:40px;">🥈</div>
                  <p style="margin-top:10px; font-weight:600; color:#888;">No Staff Data</p>
                  <p style="font-size:12px; color:#aaa;">No KPI records for this period</p>
              </div>
          </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════════════════
       ATTENTION REQUIRED
  ══════════════════════ -->
  <div class="attention-card">
    <div class="attention-head">
        <div>
            <h2>Attention Required</h2>
            <p>Analyzing drops and support needs for <strong><?= $selectedYear ?></strong></p>
        </div>
        <form method="GET" class="attention-year-filter">
            <select name="year" onchange="this.form.submit()" class="year-filter-form">
                <?php for($y=2025; $y>=2022; $y--): ?>
                    <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>>Focus: <?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="at-risk-list">
        <?php foreach($atRisk as $i => $s): ?>
        <div class="ar-item-container">
            <div class="at-risk-item clickable" onclick="toggleExpand('ar-<?= $i ?>')">
                <div class="at-risk-avatar">
                    <img src="<?= $s['avatar'] ?: '../asset/images/staff/default-profile.jpg' ?>">
                </div>
                <div class="at-risk-info">
                    <div class="at-risk-name-row">
                        <a href="../staff_masterlist/staffprofile.php?id=<?= $s['id'] ?>" class="at-risk-name" onclick="event.stopPropagation()"><?= $s['name'] ?></a>
                        <span class="level-badge <?= $s['level'] ?>"><?= strtoupper($s['level']) ?></span>
                    </div>
                    <div class="at-risk-dept"><?= $s['dept'] ?></div>
                </div>
                
                <div class="ar-stats-summary">
                    <div class="ar-stat-box">
                        <small><?= $prevYear ?> Avg</small>
                        <span><?= $s['prev_score'] ?>%</span>
                    </div>
                    <div class="ar-stat-box highlight">
                        <small><?= $selectedYear ?> Avg</small>
                        <span><?= $s['podium_score'] ?>%</span> 
                    </div>
                    <div class="ar-trend-box <?= $s['diff'] < 0 ? 'drop' : 'gain' ?>">
                        <i class="ph ph-trend-<?= $s['diff'] < 0 ? 'down' : 'up' ?>"></i>
                        <?= abs($s['diff']) ?>%
                    </div>
                    <i class="ph ph-caret-down expand-icon" id="icon-ar-<?= $i ?>"></i>
                </div>
            </div>

            <div class="ar-expandable" id="ar-<?= $i ?>" style="display: none;">
                <div class="ar-detail-grid">
                    <div class="ar-chart-wrap">
                        <h4>KPI Group Breakdown (Weakest First)</h4>
                        <div id="chart-ar-<?= $i ?>"></div>
                    </div>
                    <div class="ar-action-plan">
                        <h4>System Insight</h4>
                        <p><?= $s['level'] === 'critical' 
                          ? "No evaluation data for $selectedYear. Showing breakdown from most recent available year." 
                          : ($s['diff'] < 0 
                              ? "Performance dropped by ".abs($s['diff'])."% since $prevYear. Focus on the lowest bars in the chart."
                              : "Performance improved by ".$s['diff']."% since $prevYear.") ?>
                              </p>
                        <a href="../staff_masterlist/staffprofile.php?id=<?= $s['id'] ?>" class="btn-profile">Go to Profile</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleExpand(id) {
    const el = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    const isOpen = el.style.display === 'block';
    
    // Close others
    document.querySelectorAll('.ar-expandable').forEach(d => d.style.display = 'none');
    document.querySelectorAll('.expand-icon').forEach(i => i.style.transform = 'rotate(0deg)');

    if (!isOpen) {
        el.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
        renderDetailChart(id);
    }
}

function renderDetailChart(id) {
    const dataIndex = id.split('-')[1];
    const staff = <?= json_encode($atRisk) ?>[dataIndex];
    const containerId = "#chart-" + id;

    if (document.querySelector(containerId).hasChildNodes()) return;

    const options = {
        series: [{ name: 'Score', data: staff.group_details.map(g => g.avg_pct) }],
        chart: { type: 'bar', height: 180, toolbar: {show: false} },
        plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4 } },
        colors: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
        xaxis: { categories: staff.group_details.map(g => g.kpi_group.substring(0,15) + '...') },
        legend: { show: false }
    };

    new ApexCharts(document.querySelector(containerId), options).render();
}
</script>
</div>

<script>
function filterTable() {
  const q     = document.getElementById('searchInput').value.toLowerCase();
  const dept  = document.getElementById('deptFilter').value;
  const level = document.getElementById('levelFilter').value;

  document.querySelectorAll('.at-risk-item').forEach(row => {
    const name = row.dataset.name  || '';
    const d    = row.dataset.dept  || '';
    const l    = row.dataset.level || '';
    const show =
      (!q     || name.includes(q)) &&
      (!dept  || d === dept)       &&
      (!level || l === level);
    row.style.display = show ? '' : 'none';
  });
}
</script>

<script src="../Dashboard/script.js"></script>

<script>
//DONUT CHART   
document.addEventListener('DOMContentLoaded', function () {
    const kpiData = [<?= htmlspecialchars($jsDataString ?? '0,0,0,0') ?>];
    const kpiLabels  = ['2022', '2023', '2024', '2025'];

    renderSparkline('#sparkline-kpi', kpiData, '#097067');
});

document.addEventListener('DOMContentLoaded', function () {
    //DATA PREPARATION
    const rawLabels = <?= json_encode($deptLabels) ?>;
    
    // Logic to force line breaks for long names
    const deptLabels = rawLabels.map(label => {
        if (typeof label === 'string' && label.includes(' ')) {
            return label.split(' '); 
        }
        return label;
    });

    const deptCounts = <?= json_encode($deptCounts) ?>;

    //RENDER DEPT DONUT
    new ApexCharts(document.querySelector('#dept-donut'), {
        series: deptCounts,
        labels: deptLabels, 
        chart: {
            type: 'donut',
            sparkline: { enabled: true },
            padding: { top: 0, right: 0, bottom: 0, left: 0 }
        },
        stroke: { width: 0 },
        colors: ['#03045e', '#0077b6', '#00b4d8', '#90e0ef', '#9ceafc'], // Burgundy palette
        dataLabels: { enabled: false }, 
        tooltip: {
          enabled: true,
          theme: 'dark',
          style: { fontSize: '10px', fontFamily: 'Sora' },
          y: {
              title: { 
                  formatter: (seriesName, opts) => {
                      // Get the label 
                      const rawLabel = opts.w.globals.labels[opts.seriesIndex];
                      
                      return (Array.isArray(rawLabel) ? rawLabel.join(' ') : rawLabel) + ":";
                  }
              },
              formatter: (val) => val + " Staff"
          },
          marker: { show: true }
      },
        states: {
            hover: { filter: { type: 'none' } },
            active: { filter: { type: 'none' } }
        },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: '70%', 
                    labels: {
                        show: true,
                        name: { 
                            show: true, 
                            fontSize: '9px', 
                            fontWeight: 600, 
                            offsetY: 5, 
                            color: '#333',
                            formatter: () => "Depts"
                        },
                        value: { show: false }, 
                        total: {
                            show: true,
                            label: 'Depts',
                            formatter: () => "Depts" 
                        }
                    }
                }
            }
        }
    }).render();
});

//critical kpi group bar chart
document.addEventListener('DOMContentLoaded', function () {
 
    const groupLabels = <?= json_encode($groupLabels) ?>;
    const groupScores = <?= json_encode($groupScores) ?>;
 
    const barColors = groupScores.map((score, index) => {
        if (index === 0) return '#dc2626'; 
        if (score < 50)  return '#ef4444'; 
        if (score < 75)  return '#f59e0b'; 
        return '#fcd34d';                  
    });
 
    new ApexCharts(document.querySelector('#group-bar-chart'), {
        series: [{ name: 'Performance', data: groupScores }],
        chart: {
            type: 'bar',
            height: 110,
            toolbar: { show: false },
            sparkline: { enabled: true },
            animations: { enabled: true, speed: 600},
            padding: {
                top: 0,
                bottom: 2
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                borderRadius: 5,
                borderRadiusApplication: 'end',
                distributed: true,
                columnWidth: '60%'
            }
        },
        colors: barColors,
        dataLabels: { enabled: false },
        xaxis: {
            categories: groupLabels,
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            min: 0, max: 100,
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        legend: { show: false },
        grid:   { show: false },
        tooltip: {
            theme: 'light',
            style: { fontSize: '12px', fontFamily: 'Sora, sans-serif' },
            x: {
                formatter: function(val, { dataPointIndex }) {
                    return groupLabels[dataPointIndex];
                }
            },
            y: {
                formatter: function(val) { return val + '%'; },
                title: { formatter: function() { return 'Performance: '; } }
            }
        }
    }).render();
 
});

// heatmap
document.addEventListener('DOMContentLoaded', function () {
    const heatmapSeries = <?= !empty($heatmapSeries) ? json_encode($heatmapSeries) : '[]' ?>;
    if (!heatmapSeries.length) return;

    // Build flat data
    const depts = heatmapSeries.map(s => s.name);
    const groups = heatmapSeries[0].data.map(d => d.x);
    const data = [];

    heatmapSeries.forEach(series => {
        series.data.forEach(point => {
            data.push({
                dept: series.name,
                group: point.x,
                value: point.y
            });
        });
    });

    // Dimensions
    const margin = { top: 10, right: 85, bottom: 70, left: 130 };
    const cellW = 80;
    const cellH = 45;
    const width = groups.length * cellW;
    const height = depts.length * cellH;

    const container = document.getElementById('kpiHeatmap');
    container.innerHTML = '';

    const svg = d3.select('#kpiHeatmap')
        .append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom)
        .append('g')
        .attr('transform', `translate(${margin.left},${margin.top})`);

    const x = d3.scaleBand().domain(groups).range([0, width]).padding(0.05);
    const y = d3.scaleBand().domain(depts).range([0, height]).padding(0.05);

    const color = d3.scaleSequential()
        .domain([0, 100])
        .interpolator(d3.interpolate('#fce7f3', '#7e22ce'));

    // Draw cells
    svg.selectAll('rect')
        .data(data).enter().append('rect')
        .attr('x', d => x(d.group))
        .attr('y', d => y(d.dept))
        .attr('width', x.bandwidth())
        .attr('height', y.bandwidth())
        .attr('rx', 6)
        .attr('fill', d => d.value > 0 ? color(d.value) : '#f9f0f5');

    // Cell percentages
    svg.selectAll('.cell-label')
        .data(data).enter().append('text')
        .attr('x', d => x(d.group) + x.bandwidth() / 2)
        .attr('y', d => y(d.dept) + y.bandwidth() / 2 + 4)
        .attr('text-anchor', 'middle').attr('font-size', '11px')
        .attr('fill', d => d.value > 60 ? '#fff' : '#4a1d6e')
        .attr('font-weight', '700')
        .text(d => d.value > 0 ? d.value + '%' : '');

    // X axis
    svg.append('g')
        .attr('transform', `translate(0,${height})`)
        .call(d3.axisBottom(x).tickSize(0))
        .selectAll('text')
        .attr('transform', 'rotate(-30)')
        .attr('text-anchor', 'end')
        .attr('dx', '-0.5em')
        .attr('dy', '0.5em')
        .style('font-size', '10px')
        .text(function(d) {
            return d.length > 12 ? d.substring(0, 12) + "..." : d;
        });

    // Y axis
    svg.append('g').call(d3.axisLeft(y).tickSize(0))
        .selectAll('text').attr('dx', '-8px').style('font-size', '11px');

    svg.selectAll('.domain').remove();

    // Create tooltip div
    const tooltip = d3.select('body').append('div')
        .style('position', 'fixed')
        .style('background', '#1e1b2e')
        .style('color', '#fff')
        .style('padding', '8px 14px')
        .style('border-radius', '10px')
        .style('font-size', '12px')
        .style('font-family', 'Sora, sans-serif')
        .style('font-weight', '600')
        .style('pointer-events', 'none')
        .style('opacity', 0)
        .style('z-index', 9999)
        .style('box-shadow', '0 4px 16px rgba(0,0,0,0.18)')
        .style('transition', 'opacity 0.15s ease')
        .style('white-space', 'nowrap');

    svg.selectAll('rect')
        .on('mousemove', function(event, d) {
            tooltip
                .style('opacity', 1)
                .style('left', (event.clientX + 14) + 'px')
                .style('top',  (event.clientY - 36) + 'px')
                .html(`
                    <div style="margin-bottom:3px; font-size:11px; color:#c4b5fd;">${d.dept}</div>
                    <div>${d.group}</div>
                    <div style="font-size:18px; color:#f0abfc; margin-top:2px;">${d.value > 0 ? d.value + '%' : 'No data'}</div>
                `);
        })
        .on('mouseleave', function() {
            tooltip.style('opacity', 0);
        });
    // Color legend bar
    const legendWidth = 10;
    const legendHeight = height;
    const legendX = width + 30; 

    const defs = svg.append('defs');
    const linearGradient = defs.append('linearGradient')
        .attr('id', 'legend-gradient').attr('x1', '0%').attr('x2', '0%')
        .attr('y1', '100%').attr('y2', '0%');

    linearGradient.append('stop').attr('offset', '0%').attr('stop-color', '#fce7f3');
    linearGradient.append('stop').attr('offset', '100%').attr('stop-color', '#7e22ce');

    svg.append('rect')
        .attr('x', legendX).attr('y', 0)
        .attr('width', legendWidth).attr('height', legendHeight)
        .attr('rx', 4).style('fill', 'url(#legend-gradient)');

    // Legend labels
    [0, 50, 100].forEach(val => {
        svg.append('text')
            .attr('x', legendX + legendWidth + 6)
            .attr('y', legendHeight - (val / 100) * legendHeight + 4)
            .attr('font-size', '10px')
            .attr('fill', '#6b7280')
            .text(val + '%');
    });
});


function initARChart(index, groups, scores, name) {
    const options = {
        series: [{ name: 'Group Score', data: scores }],
        chart: { type: 'bar', height: 160, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, distributed: true, borderRadius: 4 } },
        colors: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
        xaxis: { categories: groups.map(g => g.substring(0, 15) + '...') },
        legend: { show: false },
        tooltip: { y: { formatter: val => val + "%" } }
    };
    new ApexCharts(document.querySelector("#chart-ar-" + index), options).render();
}

//speedometer
document.addEventListener('DOMContentLoaded', function () {
    const yearSelect = document.getElementById('speedoYear');
    const targetInput = document.getElementById('speedoTarget');
    const actualSpan = document.getElementById('actualVal');

    let speedometerChart = null; // global variable to hold chart instance

    function renderSpeedometer() {
        const year = yearSelect.value;
        const target = parseFloat(targetInput.value) || 80;
        const yearlyActuals = <?= json_encode((object)($yearlyData ?? [])) ?>;
        const actual = parseFloat(yearlyActuals[year]) || 0;
        const percentage = Math.min(Math.round((actual / target) * 100), 100);
        
        if (actualSpan) actualSpan.textContent = actual.toFixed(1) + '%';
        
        if (speedometerChart) {
            speedometerChart.destroy();
            speedometerChart = null;
        }

        const options = {
            series: [percentage],
            chart: { height: 260, type: 'radialBar', offsetY: 20 },
            plotOptions: {
                radialBar: {
                    startAngle: -135, endAngle: 135,
                    track: { background: '#f3e8ff', strokeWidth: '97%', margin: 5 },
                    hollow: { size: '65%' },
                    dataLabels: {
                        name: { fontSize: '11px', color: '#6b7280', offsetY: 70, fontFamily: 'Sora' },
                        value: { offsetY: -10, fontSize: '28px', fontWeight: 800, fontFamily: 'Sora', formatter: v => v + '%' }
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: { shade: 'dark', shadeIntensity: 0.15, inverseColors: false, opacityFrom: 1, opacityTo: 1, stops: [0,50,65,91], gradientToColors: ['#7e22ce'] }
            },
            stroke: { dashArray: 4 },
            labels: ['Target Achievement'],
            colors: ['#e8308c']
        };
        
        speedometerChart = new ApexCharts(document.querySelector("#targetSpeedometer"), options);
        speedometerChart.render();
    }

    renderSpeedometer();
});

</script>

</body>
</html>