<?php
/*
 * edit_kpi.php — Professional KPI Evaluation Form
 * Layout matches screenshots: Purple-Pink gradient header & Blue section bars.
 */
include("../includes/auth.php");
include("../config/db.php");


// Utility to create safe HTML IDs for JavaScript (replaces spaces with underscores)
function safeId(string $str): string {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $str);
}


$staffId = intval($_GET['staff_id'] ?? 0);
if (!$staffId) { echo "Invalid staff."; exit; }


/* ── 1. FETCH DATA ── */
$staffRes = $conn->prepare("SELECT full_name, staff_code FROM staff WHERE id = ? LIMIT 1");
$staffRes->bind_param("i", $staffId);
$staffRes->execute();
$staff = $staffRes->get_result()->fetch_assoc();
if (!$staff) { echo "Staff not found."; exit; }


$staffName = $staff['full_name'];
$staffCode = $staff['staff_code'];


$tmplRes = $conn->query("SELECT * FROM kpi_templates WHERE status='active' ORDER BY year DESC LIMIT 1");
$template = $tmplRes->fetch_assoc();
if (!$template) { echo "No active KPI template found."; exit; }


$templateId = $template['id'];
$evalYear   = $template['year'];


// Fixed Requirements: Section 1 (5, 10, 10) and Section 2 (5 per group)
$sec1Weights = [5.0, 10.0, 10.0];
$sec1WeightTotal = array_sum($sec1Weights); // 25.0%


$itemsRes = $conn->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? AND is_active = 1 ORDER BY display_order ASC");
$itemsRes->bind_param("i", $templateId);
$itemsRes->execute();
$allItems = $itemsRes->get_result()->fetch_all(MYSQLI_ASSOC);


$sec1Items = array_values(array_filter($allItems, fn($i) => $i['section'] === 'Section 1'));
$sec2Items = array_values(array_filter($allItems, fn($i) => $i['section'] === 'Section 2'));


$sec2Groups = [];
foreach ($sec2Items as $item) {
    $sec2Groups[$item['kpi_group']][] = $item;
}


// Calculate Total Sec 2 weight (5.0% * number of groups)
$sec2WeightTotal = count($sec2Groups) * 5.0;


// Existing Data
$existingScores = [];
$scoreRes = $conn->prepare("SELECT KPI_Code, Score FROM kpi_data WHERE Name = ? AND YEAR(Date) = ?");
$scoreRes->bind_param("si", $staffName, $evalYear);
$scoreRes->execute();
$scoreRows = $scoreRes->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($scoreRows as $r) { $existingScores[$r['KPI_Code']] = (int)$r['Score']; }


$commentRes = $conn->prepare("SELECT `Supervisor Comments`, `Training/Development Recommendations` FROM kpi_comment WHERE Name = ? AND Year = ? LIMIT 1");
$commentRes->bind_param("si", $staffName, $evalYear);
$commentRes->execute();
$existingComment = $commentRes->get_result()->fetch_assoc();
?>


<style>
    .kpi-modal { width: 100%; max-width: 1000px; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.1); display: flex; flex-direction: column; max-height: 90vh; font-family: 'Sora', sans-serif; }
    .modal-header { background: linear-gradient(135deg, #7b3fa0 0%, #e8308c 100%); padding: 20px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
    .modal-body { overflow-y: auto; padding: 25px 30px; flex: 1; background: #fcfcfd; }
    .section-header { background: #2563eb; color: #fff; padding: 12px 18px; border-radius: 8px; font-weight: 700; margin: 25px 0 15px; text-transform: uppercase; }
    .kpi-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
    .kpi-table th { background: #f8fafc; padding: 12px; font-size: 11px; color: #64748b; border-bottom: 2px solid #edf2f7; text-align: left; }
    .kpi-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
    .score-select { width: 70px; padding: 6px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; background: #f8fafc; cursor: pointer; }
    .final-score-row td { background: #f0fdf4; border-top: 2px solid #bbf7d0; text-align: center; padding: 20px; }
    .final-score-value { font-size: 28px; font-weight: 800; color: #16a34a; }
    .comments-card { background: #fffdf0; border: 1.5px solid #fde68a; border-radius: 14px; padding: 20px; margin-top: 25px; }
    .btn-save { background: linear-gradient(135deg, #7b3fa0, #e8308c); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(232, 48, 140, 0.3); }
</style>


<div class="kpi-modal">
    <div class="modal-header">
        <div>
            <h2 style="margin:0;">Update KPI Scores</h2>
            <p style="margin:5px 0 0; opacity:0.8;"><?= htmlspecialchars($staffName) ?> &middot; <?= htmlspecialchars($staffCode) ?></p>
        </div>
        <button type="button" onclick="closeModal()" style="background:rgba(255,255,255,0.2); border:none; border-radius:50%; width:32px; height:32px; color:white; cursor:pointer;"><i class="ph ph-x"></i></button>
    </div>


    <div class="modal-body">
        <form id="kpiForm">
            <input type="hidden" name="staff_name" value="<?= htmlspecialchars($staffName) ?>">
            <input type="hidden" name="year" value="<?= $evalYear ?>">


            <div class="eval-period">
                <label style="font-weight:700; color:#64748b; font-size:11px;">EVALUATION PERIOD</label><br>
                <span style="background:#f1f5f9; padding:8px 15px; border-radius:6px; display:inline-block; margin-top:5px; font-weight:600;"><?= $evalYear ?></span>
            </div>


            <div class="section-header">SECTION 1: Core Competencies (<?= $sec1WeightTotal ?>%)</div>
            <table class="kpi-table">
                <thead><tr><th>Competency</th><th>Weight</th><th>Score (1-5)</th><th>Weighted Score</th></tr></thead>
                <tbody>
                    <?php foreach ($sec1Items as $idx => $item):
                        $code = $item['kpi_code']; $weight = $sec1Weights[$idx] ?? 0.0; $current = $existingScores[$code] ?? 3; ?>
                        <tr>
                            <td><?= htmlspecialchars($item['kpi_description']) ?></td>
                            <td><?= number_format($weight, 1) ?>%</td>
                            <td>
                                <select class="score-select sec1-score" name="score[<?= $code ?>]" data-weight="<?= $weight ?>" onchange="recalculate()">
                                    <?php for($s=1;$s<=5;$s++) echo "<option value='$s' ".($s==$current?'selected':'').">$s</option>"; ?>
                                </select>
                            </td>
                            <td id="ws_<?= $code ?>" style="font-weight:700; text-align:center;">0.00</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr style="background:#f8fafc; font-weight:700;"><td>Section 1 Total</td><td><?= $sec1WeightTotal ?>%</td><td></td><td id="sec1Total" style="color:#2563eb; text-align:center;">0.00</td></tr></tfoot>
            </table>


            <div class="section-header">SECTION 2: KPI Achievement (<?= $sec2WeightTotal ?>%)</div>
            <table class="kpi-table">
                <thead><tr><th>KPI Group</th><th>Measurable Target</th><th>Rating (1-5)</th><th>Weighted Score</th><th>Avg Score</th><th>Weight</th><th>Group Weighted</th></tr></thead>
                <tbody>
                    <?php foreach ($sec2Groups as $groupName => $groupItems):
                        $count = count($groupItems); $first = true; $grpWeight = 5.0; $itemWeight = $grpWeight / $count; ?>
                        <?php foreach ($groupItems as $item):
                            $code = $item['kpi_code']; $current = $existingScores[$code] ?? 3; ?>
                            <tr data-group="<?= safeId($groupName) ?>">
                                <?php if($first): ?><td rowspan="<?= $count ?>" style="font-weight:700; background:#fbfcfe; border-right:1px solid #f1f5f9;"><?= htmlspecialchars($groupName) ?></td><?php endif; ?>
                                <td><?= htmlspecialchars($item['kpi_description']) ?></td>
                                <td>
                                    <select class="score-select sec2-score" name="score[<?= $code ?>]" data-group="<?= safeId($groupName) ?>" data-weight="<?= $itemWeight ?>" onchange="recalculate()">
                                        <?php for($s=1;$s<=5;$s++) echo "<option value='$s' ".($s==$current?'selected':'').">$s</option>"; ?>
                                    </select>
                                </td>
                                <td id="ws_<?= $code ?>" style="text-align:center; font-weight:700; color:#64748b;">0.00</td>
                                <?php if($first): ?>
                                    <td id="avg_<?= safeId($groupName) ?>" rowspan="<?= $count ?>" style="text-align:center; font-weight:700; border-left:1px solid #f1f5f9;">0.00</td>
                                    <td rowspan="<?= $count ?>" style="text-align:center;"><?= number_format($grpWeight, 1) ?>%</td>
                                    <td id="gws_<?= safeId($groupName) ?>" rowspan="<?= $count ?>" style="text-align:center; font-weight:700; color:#2563eb;">0.00</td>
                                <?php endif; ?>
                            </tr>
                        <?php $first = false; endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:#f8fafc; font-weight:700;"><td>Section 2 Total</td><td colspan="4"></td><td><?= number_format($sec2WeightTotal, 1) ?>%</td><td id="sec2Total" style="color:#2563eb; text-align:center;">0.00</td></tr>
                    <tr class="final-score-row"><td colspan="6"><strong>FINAL PERFORMANCE SCORE (1-5 SCALE)</strong></td><td><span class="final-score-value" id="finalScore">0.00</span><br><span id="finalLabel" style="color:#64748b; font-size:11px;">Excellent</span></td></tr>
                </tfoot>
            </table>


            <div class="comments-card">
                <h3>Comments & Development</h3>
                <label style="font-size:12px; color:#92400e; font-weight:600;">Supervisor Comments:</label>
                <textarea name="supervisor_comments" style="width:100%; min-height:80px; border-radius:8px; border:1px solid #fde68a; padding:10px; margin:8px 0 15px; font-family:inherit;"><?= htmlspecialchars($existingComment['Supervisor Comments'] ?? '') ?></textarea>
                <label style="font-size:12px; color:#92400e; font-weight:600;">Training Recommendations:</label>
                <textarea name="training_recommendations" style="width:100%; min-height:80px; border-radius:8px; border:1px solid #fde68a; padding:10px; margin:8px 0 0; font-family:inherit;"><?= htmlspecialchars($existingComment['Training/Development Recommendations'] ?? '') ?></textarea>
            </div>
        </form>
    </div>


    <div class="modal-footer" style="padding:15px 30px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
        <button type="button" onclick="closeModal()" style="border:none; background:none; color:#64748b; font-weight:700; cursor:pointer;">Cancel</button>
        <button type="button" class="btn-save" onclick="saveKPI()">Save KPI Scores</button>
    </div>
</div>


<script>
// Logic to sanitize IDs to match PHP safeId outputs
function safeId(str) { return str.replace(/[^a-zA-Z0-9_-]/g, '_'); }


function recalculate() {
    let s1Total = 0, s2Total = 0;
   
    // SECTION 1: Update each row (Weight% * Score)
    document.querySelectorAll('.sec1-score').forEach(sel => {
        let score = parseInt(sel.value);
        let weightFactor = parseFloat(sel.dataset.weight) / 100; // e.g., 0.05
        let weightedScore = score * weightFactor; // e.g., 3 * 0.05 = 0.15
       
        let code = sel.name.match(/\[(.*?)\]/)[1];
        let rowDisplay = document.getElementById('ws_' + code);
        if (rowDisplay) rowDisplay.textContent = weightedScore.toFixed(2);
       
        s1Total += weightedScore;
    });
    document.getElementById('sec1Total').textContent = s1Total.toFixed(2);


    // SECTION 2: Update each row and calculate group averages
    let groupMap = {};
    document.querySelectorAll('.sec2-score').forEach(sel => {
        let score = parseInt(sel.value);
        let weightFactor = parseFloat(sel.dataset.weight) / 100;
        let weightedScore = score * weightFactor;
       
        let code = sel.name.match(/\[(.*?)\]/)[1];
        let rowDisplay = document.getElementById('ws_' + code);
        if (rowDisplay) rowDisplay.textContent = weightedScore.toFixed(2);


        let gid = sel.dataset.group;
        if(!groupMap[gid]) groupMap[gid] = {sum:0, count:0, grpWeight: 5.0};
        groupMap[gid].sum += score;
        groupMap[gid].count++;
    });


    for (let gid in groupMap) {
        let avg = groupMap[gid].sum / groupMap[gid].count;
        let grpWeighted = avg * (groupMap[gid].grpWeight / 100); // avg * 0.05
       
        let avgCell = document.getElementById('avg_' + safeId(gid));
        let gwsCell = document.getElementById('gws_' + safeId(gid));
        if (avgCell) avgCell.textContent = avg.toFixed(2);
        if (gwsCell) gwsCell.textContent = grpWeighted.toFixed(2);
       
        s2Total += grpWeighted;
    }
    document.getElementById('sec2Total').textContent = s2Total.toFixed(2);


    // FINAL SCORE: Sum of weighted scores (already on 1-5 scale)
    let final = s1Total + s2Total;
    document.getElementById('finalScore').textContent = final.toFixed(2);
   
    // Label Mapping
    let label = "Unsatisfactory";
    if (final >= 4.5) label = "Excellent";
    else if (final >= 3.5) label = "Good";
    else if (final >= 2.5) label = "Satisfactory";
    else if (final >= 1.5) label = "Needs Improvement";
    document.getElementById('finalLabel').textContent = label;
}


// Initial calculation on load
recalculate();
</script>
