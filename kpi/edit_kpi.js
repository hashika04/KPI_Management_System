function recalculate() {
    let sec1Total = 0;
    let sec2Total = 0;

    /* ---------- SECTION 1 ---------- */
    document.querySelectorAll(".sec1-score").forEach(sel => {
        let score = parseInt(sel.value);
        let weight = parseFloat(sel.dataset.weight) / 100;
        let weighted = score * weight;

        let code = sel.name.match(/\[(.*?)\]/)[1];
        document.getElementById("ws_" + code).innerText = weighted.toFixed(2);
        sec1Total += weighted;
    });
    document.getElementById("sec1Total").innerText = sec1Total.toFixed(2);

    /* ---------- SECTION 2 ---------- */
    let groups = {};
    document.querySelectorAll(".sec2-score").forEach(sel => {
        let gid = sel.dataset.group;
        groups[gid] = groups[gid] || { sum: 0, count: 0 };
        groups[gid].sum += parseInt(sel.value);
        groups[gid].count++;
    });

    for (let gid in groups) {
        let avg = groups[gid].sum / groups[gid].count;
        let weightText = document.querySelector(`#gws_${gid}`).previousElementSibling.innerText;
        let weight = parseFloat(weightText) / 100;
        let weighted = avg * weight;

        document.getElementById("avg_" + gid).innerText = avg.toFixed(2);
        document.getElementById("gws_" + gid).innerText = weighted.toFixed(2);
        sec2Total += weighted;
    }

    document.getElementById("sec2Total").innerText = sec2Total.toFixed(2);
    document.getElementById("finalScore").innerText = (sec1Total + sec2Total).toFixed(2);
}

/* ---------- INIT ---------- */
document.addEventListener("DOMContentLoaded", recalculate);
document.addEventListener("change", e => {
    if (e.target.classList.contains("score-select")) recalculate();
});
