const search = document.getElementById("staffSearch");
const dept = document.getElementById("departmentFilter");
const sort = document.getElementById("staffSort");
const grid = document.getElementById("staffGrid");

function update() {
    let cards = Array.from(document.querySelectorAll(".staff-card"));

    let s = search.value.toLowerCase();
    let d = dept.value;

    cards.forEach(c => {
        let name = c.dataset.name;
        let dep = c.dataset.department;

        let show =
            (name.includes(s)) &&
            (d === "all" || dep === d);

        c.style.display = show ? "" : "none";
    });

    let visible = cards.filter(c => c.style.display !== "none");

    visible.sort((a, b) => {
        return sort.value === "high"
            ? b.dataset.score - a.dataset.score
            : a.dataset.score - b.dataset.score;
    });

    visible.forEach(c => grid.appendChild(c));
}

search.addEventListener("input", update);
dept.addEventListener("change", update);
sort.addEventListener("change", update);

function openKPIModal(staffId, staffName) {
    const modal = document.getElementById('addKPIModal');
    document.getElementById('modalStaffId').value = staffId;
    document.getElementById('modalStaffName').value = staffName;
    document.getElementById('modalStaffNameDisplay').value = staffName;
    modal.style.display = 'flex';
}

function closeModal() {
    const modal = document.getElementById('addKPIModal');
    modal.style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('addKPIModal');
    if (event.target === modal) {
        closeModal();
    }
}