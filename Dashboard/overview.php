<?php
/*
 * overview.php  — KPI Monitor
 * Styles: ../asset/universal.css (shared) + ../asset/overview.css (page-specific)
 */
require_once __DIR__ . '/../vendor/autoload.php';
include("../includes/auth.php");
$activePage = 'dashboard';

/* ══════════════════════════════════════════
   STAFF + COMPUTED KPI SCORES
   Joins staff table with kpi_data to calculate
   each staff member's average KPI score (0–100)
   by averaging their Score values (each 1–5)
   then normalising to a percentage: (avg/5)*100
══════════════════════════════════════════ */
 
$sql = "
    SELECT
        s.id,
        s.full_name   AS name,
        s.staff_code  AS staffId,
        s.department  AS dept,
        s.profile_photo AS avatar,
        ROUND(COALESCE((AVG(k.Score) / 5) * 100, 0), 1) AS score
    FROM staff s
    LEFT JOIN kpi_data k ON k.Name = s.full_name
    GROUP BY s.id, s.full_name, s.staff_code, s.department, s.profile_photo
    ORDER BY score DESC
";
 
$result    = $conn->query($sql);
$staffData = [];
 
while ($row = $result->fetch_assoc()) {
    $score = (float)$row['score'];
 
    /* Classify level based on score */
    if ($score >= 85) {
        $level = 'top';
    } elseif ($score >= 70) {
        $level = 'good';
    } elseif ($score >= 50) {
        $level = 'average';
    } elseif ($score > 0) {
        $level = 'at-risk';
    } else {
        $level = 'critical';   /* score = 0 means no KPI data at all */
    }
 
    /* Simple trend: compare last month's avg vs overall avg */
    $sqlTrend = "
        SELECT ROUND(COALESCE((AVG(Score)/5)*100, 0), 1) AS recent_score
        FROM kpi_data
        WHERE Name = ?
          AND STR_TO_DATE(Date,'%m/%d/%Y') >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $stmt = $conn->prepare($sqlTrend);
    $stmt->bind_param('s', $row['name']);
    $stmt->execute();
    $trendRow    = $stmt->get_result()->fetch_assoc();
    $recentScore = (float)($trendRow['recent_score'] ?? 0);
    $trend       = ($recentScore >= $score) ? 'up' : 'down';
    $stmt->close();
 
    $staffData[] = [
        'id'      => (int)$row['id'],
        'name'    => $row['name'],
        'staffId' => $row['staffId'],
        'dept'    => $row['dept'],
        'avatar'  => $row['avatar'] ?? '',
        'score'   => $score,
        'level'   => $level,
        'trend'   => $trend,
    ];
}
 
/* ── Computed summary stats ── */
$totalStaff  = count($staffData);
$avgKPI      = $totalStaff
    ? round(array_sum(array_column($staffData, 'score')) / $totalStaff, 1)
    : 0;
$topCount    = count(array_filter($staffData, fn($s) => $s['level'] === 'top'));
$atRiskCount = count(array_filter($staffData, fn($s) => in_array($s['level'], ['at-risk', 'critical'])));
 
/* ── Critical Category: department with lowest avg KPI ── */
$sqlCrit = "
    SELECT
        s.department,
        ROUND((AVG(k.Score)/5)*100, 1) AS dept_avg
    FROM staff s
    LEFT JOIN kpi_data k ON k.Name = s.full_name
    GROUP BY s.department
    ORDER BY dept_avg ASC
    LIMIT 1
";
$critResult  = $conn->query($sqlCrit);
$critRow     = $critResult->fetch_assoc();
$criticalCat = $critRow ? $critRow['department'] : '—';
 
/* ── Top 3 performers (podium) ── */
$tops = array_slice($staffData, 0, 3);

$podium = [
    'bronze' => $tops[2] ?? null,
    'gold'   => $tops[0] ?? null,
    'silver' => $tops[1] ?? null,
];
 
/* ── At-risk staff (lowest scores first) ── */
$atRisk = array_values(
    array_filter($staffData, fn($s) => in_array($s['level'], ['at-risk', 'critical']))
);
usort($atRisk, fn($a, $b) => $a['score'] <=> $b['score']);
 
/* ── Departments for filter dropdown ── */
$departments = array_unique(array_column($staffData, 'dept'));
sort($departments);
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
      <div class="stat-label">Average KPI</div>
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

</body>
</html>