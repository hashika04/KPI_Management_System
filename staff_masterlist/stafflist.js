document.addEventListener("DOMContentLoaded", function() {
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
            return sort.value === "score-high"
                ? b.dataset.score - a.dataset.score
                : a.dataset.score - b.dataset.score;
        });


        visible.forEach(c => grid.appendChild(c));
    }
    const statusFilter = document.getElementById("staffFilter");
    statusFilter.addEventListener("change", update);


    search.addEventListener("input", update);
    dept.addEventListener("change", update);
    sort.addEventListener("change", update);
});


