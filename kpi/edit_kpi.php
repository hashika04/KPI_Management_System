<?php
include("../includes/auth.php");
include("../config/db.php");

/* ---------- Helpers ---------- */
function safeId(string $str): string {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $str);
}

/* ---------- GET STAFF & TEMPLATE ---------- */
$staffId = intval($_GET['staff_id'] ?? 0);
if (!$staffId) die("Invalid staff");

$staffRes = $conn->prepare("SELECT full_name, staff_code FROM staff WHERE id=?");
$staffRes->bind_param("i", $staffId);
$staffRes->execute();
$staff = $staffRes->get_result()->fetch_assoc();

$template = $conn->query("
    SELECT * FROM kpi_templates
    WHERE status='active'
    ORDER BY year DESC
    LIMIT 1
")->fetch_assoc();

$evalYear   = $template['year'];
$templateId = $template['id'];

/* ---------- FETCH KPI ITEMS ---------- */
$items = $conn->query("
    SELECT * FROM kpi_template_items
    WHERE template_id={$templateId} AND is_active=1
    ORDER BY display_order
")->fetch_all(MYSQLI_ASSOC);

$sec1Items = array_values(array_filter($items, fn($i)=>$i['section']=="Section 1"));
$sec2Items = array_values(array_filter($items, fn($i)=>$i['section']=="Section 2"));

$sec1Weights = [5,10,10];
$sec2GroupWeights = [15,15,15,10,5,15];

/* ---------- GROUP SECTION 2 ---------- */
$sec2Groups = [];
foreach($sec2Items as $i){
    $sec2Groups[$i['kpi_group']][] = $i;
}

/* ---------- EXISTING SCORES ---------- */
$scores = [];
$res = $conn->prepare("
    SELECT KPI_Code, Score FROM kpi_data
    WHERE Name=? AND YEAR(Date)=?
");
$res->bind_param("si", $staff['full_name'], $evalYear);
$res->execute();
foreach($res->get_result() as $r){
    $scores[$r['KPI_Code']] = $r['Score'];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="assets/edit_kpi.css">
</head>
<body>

<h2><?=htmlspecialchars($staff['full_name'])?> (<?=$staff['staff_code']?>)</h2>
<p>Evaluation Year: <?=$evalYear?></p>

<!-- ================= SECTION 1 ================= -->
<div class="section-header">SECTION 1 – Core Competencies (25%)</div>
<table class="kpi-table">
<thead>
<tr>
    <th>Competency</th>
    <th>Weight</th>
    <th>Score</th>
    <th>Weighted Score</th>
</tr>
</thead>
<tbody>
<?php foreach($sec1Items as $i=>$item):
    $code = $item['kpi_code'];
    $w    = $sec1Weights[$i];
    $cur  = $scores[$code] ?? 3;
?>
<tr>
<td><?=htmlspecialchars($item['kpi_description'])?></td>
<td><?=$w?>%</td>
<td>
<select class="score-select sec1-score"
        name="score[<?=$code?>]"
        data-weight="<?=$w?>">
    <?php for($s=1;$s<=5;$s++): ?>
        <option value="<?=$s?>" <?=$s==$cur?'selected':''?>><?=$s?></option>
    <?php endfor; ?>
</select>
</td>
<td id="ws_<?=$code?>">0.00</td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
    <td colspan="3"><b>Section 1 Total</b></td>
    <td id="sec1Total" class="cell-primary">0.00</td>
</tr>
</tfoot>
</table>

<!-- ================= SECTION 2 ================= -->
<div class="section-header">SECTION 2 – KPI Achievement</div>
<table class="kpi-table">
<thead>
<tr>
    <th>KPI Group</th>
    <th>Measurable Target</th>
    <th>Rating</th>
    <th>Avg KPI Score</th>
    <th>Weight</th>
    <th>Weighted Score</th>
</tr>
</thead>
<tbody>
<?php
$gIndex = 0;
foreach($sec2Groups as $gName=>$items):
    $count   = count($items);
    $gWeight = $sec2GroupWeights[$gIndex++] ?? 0;
    $first   = true;
    foreach($items as $it):
        $code = $it['kpi_code'];
        $cur  = $scores[$code] ?? 3;
?>
<tr>
<?php if($first): ?>
<td rowspan="<?=$count?>" class="kpi-group-title"><b><?=$gName?></b></td>
<?php endif; ?>
<td><?=htmlspecialchars($it['kpi_description'])?></td>
<td>
<select class="score-select sec2-score"
        name="score[<?=$code?>]"
        data-group="<?=safeId($gName)?>">
    <?php for($s=1;$s<=5;$s++): ?>
        <option value="<?=$s?>" <?=$s==$cur?'selected':''?>><?=$s?></option>
    <?php endfor; ?>
</select>
</td>
<?php if($first): ?>
<td id="avg_<?=safeId($gName)?>" rowspan="<?=$count?>">0.00</td>
<td rowspan="<?=$count?>"><?=$gWeight?>%</td>
<td id="gws_<?=safeId($gName)?>" rowspan="<?=$count?>">0.00</td>
<?php endif; ?>
</tr>
<?php $first=false; endforeach; endforeach; ?>
</tbody>
<tfoot>
<tr>
    <td colspan="5"><b>Section 2 Total</b></td>
    <td id="sec2Total"><b>0.00</b></td>
</tr>
<tr>
    <td colspan="5"><b>FINAL SCORE</b></td>
    <td id="finalScore"><b>0.00</b></td>
</tr>
</tfoot>
</table>

<script src="../kpi/edit_kpi.js"></script>
</body>
</html>
