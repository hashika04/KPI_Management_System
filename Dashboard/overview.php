<?php
/*
 * overview.php  — KPI Monitor
 * Styles: ../asset/universal.css (shared) + ../asset/overview.css (page-specific)
 */
require_once __DIR__ . '/../vendor/autoload.php';
include("../includes/auth.php");
include("../Dashboard/data.php");
include("../config/db.php");

$chartSql = "SELECT 
                SUBSTRING_INDEX(Date, '/', -1) as kpi_year, 
                ROUND((AVG(Score)/5)*100, 1) as yearly_avg 
             FROM kpi_data 
             WHERE SUBSTRING_INDEX(Date, '/', -1) BETWEEN '2022' AND '2025'
             GROUP BY kpi_year 
             ORDER BY kpi_year ASC";

$chartRes = $conn->query($chartSql);
$yearlyScores = [];

while($row = $chartRes->fetch_assoc()) {
    $yearlyScores[] = $row['yearly_avg'];
}

$jsDataString = implode(', ', $yearlyScores);

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

    <div class="stat-card">
      <div class="stat-icon blue"><i class="ph ph-users-three"></i></div>
      <div class="stat-value"><?= $totalStaff ?></div>
      <div class="stat-label">Total Staff</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon teal"><i class="ph ph-trend-up"></i></div>
      <div class="stat-value"><?= $avgKPI ?></div>
      <div class="stat-label">Average KPI 2025</div>
      <div id="sparkline-kpi" class="sparkline-container"></div>
    </div>

    <div class="stat-card">
      <div class="stat-icon green"><i class="ph ph-medal"></i></div>
      <div class="stat-value"><?= $topCount ?></div>
      <div class="stat-label">Top Performers</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon orange"><i class="ph ph-warning"></i></div>
      <div class="stat-value"><?= $atRiskCount ?></div>
      <div class="stat-label">At Risk</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon rose"><i class="ph ph-warning-circle"></i></div>
      <div class="stat-value truncate"><?= htmlspecialchars($criticalCat) ?></div>
      <div class="stat-label">Critical Category</div>
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
            src="qr.php?id=<?= $p['id'] ?>"
            alt="Scan to view <?= htmlspecialchars($p['name']) ?>'s profile"
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
            src="qr.php?id=<?= $p['id'] ?>"
            alt="Scan to view <?= htmlspecialchars($p['name']) ?>'s profile"
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
            src="qr.php?id=<?= $p['id'] ?>"
            alt="Scan to view <?= htmlspecialchars($p['name']) ?>'s profile"
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
    // The data now represents [2022_avg, 2023_avg, 2024_avg, 2025_avg]
    const multiYearData = [<?php echo $jsDataString; ?>];
    
    renderSparkline('#sparkline-kpi', multiYearData, '#e8308c');
</script>

</body>
</html>