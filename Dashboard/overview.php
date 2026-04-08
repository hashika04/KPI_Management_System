<?php
/*
 * overview.php  — KPI Monitor
 * Styles: ../asset/universal.css (shared) + ../asset/overview.css (page-specific)
 */
require_once __DIR__ . '/../vendor/autoload.php';
include("../includes/auth.php");
include("../Dashboard/data.php");
include("../config/db.php");


$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$lanIp = gethostbyname(gethostname());
$currentHost = (filter_var($lanIp, FILTER_VALIDATE_IP) && $lanIp !== '127.0.0.1')
    ? $lanIp
    : '192.168.0.233';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST']; // THIS is the key
$dynamicBaseUrl = 'https://clawless-dorinda-victoryless.ngrok-free.dev/KPI_Management_System/staff_masterlist/staffprofile.php?id=';

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
$yearlyScores = [];

// Initialize with zeros to ensure exactly 4 points even if data is missing for a year
$yearlyData = ['2022' => 0, '2023' => 0, '2024' => 0, '2025' => 0];

while($row = $chartRes->fetch_assoc()) {
    $yearlyData[$row['kpi_year']] = $row['yearly_avg'];
}

// This creates a string like "75.5, 80.2, 65.0, 61.2"
$jsDataString = implode(', ', array_values($yearlyData));
$avgKPI = $yearlyData['2025'];

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
      <div class="stat-label">Average KPI 2025</div>
      <div id="sparkline-kpi" class="sparkline-container"></div>
    </div>

    <div class="stat-card stat-card--critical" style="overflow: visible;">
      <div class="stat-icon red-icon"><i class="ph-bold ph-warning-circle"></i></div>
      <div class="stat-value truncate" title="<?= htmlspecialchars($groupLabels[0]) ?>">
          <?= htmlspecialchars($groupLabels[0]) ?>
      </div>
      <div class="stat-label">Critical KPI Group</div>
      <div id="group-bar-chart" class="group-bar-container"></div>
  </div>

  </div><!-- /.stat-cards -->

  <!-- ══════════════════════
       FILTERS
  ══════════════════════ -->
  <div class="filters-card">
    <div class="filters-head">
      <i class="ph ph-funnel"></i>
      <h2>Filters</h2>
    </div>
    <div class="filters-row">

      <div class="filter-input-wrap">
        <i class="ph ph-magnifying-glass"></i>
        <input
          class="filter-input"
          type="text"
          id="searchInput"
          placeholder="Search staff..."
          oninput="filterTable()"
        >
      </div>

      <select class="filter-select" id="deptFilter" onchange="filterTable()">
        <option value="">All Departments</option>
        <?php foreach($departments as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
      </select>

      <select class="filter-select" id="levelFilter" onchange="filterTable()">
        <option value="">All Levels</option>
        <option value="top">Top Performers</option>
        <option value="good">Good</option>
        <option value="average">Average</option>
        <option value="at-risk">At Risk</option>
        <option value="critical">Critical</option>
      </select>

    </div>
  </div><!-- /.filters-card -->

  <!-- ══════════════════════
       TOP PERFORMERS PODIUM
  ══════════════════════ -->
  <?php if (!empty($tops)): ?>
  <div class="performers-card">
    <h2 class="performers-title">Top Performers</h2>

    <div class="podium-grid">

      <!-- Bronze — 3rd (left) -->
      <?php if ($podium['bronze']): $p = $podium['bronze']; ?>
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
            <span class="performer-kpi-value"><?= $p['score'] ?>%</span>
          </div>
          <div class="kpi-bar-bg">
            <div class="kpi-bar-fill" style="width:<?= min($p['score'],100) ?>%"></div>
          </div>
        </div>
        <div class="qr-wrap">
          <img 
            src="qr.php?url=<?= urlencode($dynamicBaseUrl . $p['id']) ?>" 
            alt="Scan to view profile" 
            class="qr-image" 
            width="120" 
            height="120"
          >
        </div>
        <span class="view-btn bronze">View Details <i class="ph ph-arrow-right"></i></span>
      </a>
      <?php endif; ?>

      <!-- Gold — 1st (centre) -->
      <?php if ($podium['gold']): $p = $podium['gold']; ?>
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
            <span class="performer-kpi-value"><?= $p['score'] ?>%</span>
          </div>
          <div class="kpi-bar-bg">
            <div class="kpi-bar-fill" style="width:<?= min($p['score'],100) ?>%"></div>
          </div>
        </div>
        <div class="qr-wrap">
          <img 
            src="qr.php?url=<?= urlencode($dynamicBaseUrl . $p['id']) ?>" 
            alt="Scan to view profile" 
            class="qr-image" 
            width="120" 
            height="120"
          >
        </div>
        <span class="view-btn gold">View Details <i class="ph ph-arrow-right"></i></span>
      </a>
      <?php endif; ?>

      <!-- Silver — 2nd (right) -->
      <?php if ($podium['silver']): $p = $podium['silver']; ?>
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
            <span class="performer-kpi-value"><?= $p['score'] ?>%</span>
          </div>
          <div class="kpi-bar-bg">
            <div class="kpi-bar-fill" style="width:<?= min($p['score'],100) ?>%"></div>
          </div>
        </div>
        <div class="qr-wrap">
          <img 
            src="qr.php?url=<?= urlencode($dynamicBaseUrl . $p['id']) ?>" 
            alt="Scan to view profile" 
            class="qr-image" 
            width="120" 
            height="120"
          >
        </div>
        <span class="view-btn silver">View Details <i class="ph ph-arrow-right"></i></span>
      </a>
      <?php endif; ?>

    </div><!-- /.podium-grid -->
  </div><!-- /.performers-card -->
  <?php endif; ?>

  <!-- ══════════════════════
       ATTENTION REQUIRED
  ══════════════════════ -->
  <?php if (!empty($atRisk)): ?>
  <div class="attention-card">
    <div class="attention-head">
      <div>
        <h2>Attention Required</h2>
        <p>Staff members needing support</p>
      </div>
      <a href="../Reports/reports.php" class="view-report-link">
        View Report <i class="ph ph-arrow-right"></i>
      </a>
    </div>

    <div class="at-risk-list" id="atRiskList">
      <?php foreach($atRisk as $s): ?>
      <a href="../staff_masterlist/staff_profile.php?id=<?= $s['id'] ?>"
         class="at-risk-item"
         data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>"
         data-dept="<?= htmlspecialchars($s['dept']) ?>"
         data-level="<?= htmlspecialchars($s['level']) ?>">

        <div class="at-risk-avatar">
          <?php if (!empty($s['avatar']) && file_exists($s['avatar'])): ?>
            <img src="<?= htmlspecialchars($s['avatar']) ?>" alt="">
          <?php else: ?>
            <div class="avatar-initials"><?= strtoupper(substr($s['name'],0,2)) ?></div>
          <?php endif; ?>
        </div>

        <div class="at-risk-info">
          <div class="at-risk-name-row">
            <span class="at-risk-name"><?= htmlspecialchars($s['name']) ?></span>
            <span class="level-badge <?= htmlspecialchars($s['level']) ?>"><?= htmlspecialchars($s['level']) ?></span>
          </div>
          <div class="at-risk-dept"><?= htmlspecialchars($s['dept']) ?></div>
        </div>

        <div class="at-risk-score-wrap">
          <div class="at-risk-score"><?= $s['score'] ?></div>
          <div class="at-risk-trend">
            <i class="ph ph-trend-<?= $s['trend']==='up' ? 'up' : 'down' ?>"></i>
            KPI
          </div>
        </div>

      </a>
      <?php endforeach; ?>
    </div><!-- /.at-risk-list -->
  </div><!-- /.attention-card -->
  <?php endif; ?>

</div><!-- /.overview-page -->

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
document.addEventListener('DOMContentLoaded', function () {
    const kpiData    = [<?= $jsDataString ?>];
    const kpiLabels  = ['2022', '2023', '2024', '2025'];

    renderSparkline('#sparkline-kpi', kpiData, '#097067');
});

document.addEventListener('DOMContentLoaded', function () {
    // 1. DATA PREPARATION
    const rawLabels = <?= json_encode($deptLabels) ?>;
    
    // Logic to force line breaks for long names
    const deptLabels = rawLabels.map(label => {
        if (typeof label === 'string' && label.includes(' ')) {
            return label.split(' '); // Returns array for multiline support
        }
        return label;
    });

    const deptCounts = <?= json_encode($deptCounts) ?>;

    // 2. RENDER DEPT DONUT
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
                      // Get the label (which might be an array like ['Home', '&', 'Living'])
                      const rawLabel = opts.w.globals.labels[opts.seriesIndex];
                      
                      // If it's an array, join it with a space. If not, just return it.
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
                    size: '70%', // Inner radius
                    labels: {
                        show: true,
                        name: { 
                            show: true, 
                            fontSize: '9px', 
                            fontWeight: 600, 
                            offsetY: 5, // Adjusted up to center stacked lines
                            color: '#66021F',
                            formatter: () => "Depts"
                        },
                        value: { show: false }, // Hides the large number in center
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
</script>

</body>
</html>