const searchInput = document.getElementById("staffSearch");
const filterSelect = document.getElementById("staffFilter");
const sortSelect = document.getElementById("staffSort");
const staffGrid = document.getElementById("staffGrid");

function updateStaffCards() {
    const cards = Array.from(document.querySelectorAll(".staff-card"));
    const searchValue = searchInput.value.toLowerCase().trim();
    const filterValue = filterSelect.value;
    const sortValue = sortSelect.value;

    cards.forEach(card => {
        const name = card.dataset.name;
        const status = card.dataset.status;

        const matchesSearch = name.includes(searchValue);
        const matchesFilter = filterValue === "all" || status === filterValue;

        card.style.display = (matchesSearch && matchesFilter) ? "" : "none";
    });

    const visibleCards = cards.filter(card => card.style.display !== "none");

    visibleCards.sort((a, b) => {
        if (sortValue === "name-asc") {
            return a.dataset.name.localeCompare(b.dataset.name);
        }
        if (sortValue === "name-desc") {
            return b.dataset.name.localeCompare(a.dataset.name);
        }
        if (sortValue === "score-high") {
            return parseFloat(b.dataset.score) - parseFloat(a.dataset.score);
        }
        if (sortValue === "score-low") {
            return parseFloat(a.dataset.score) - parseFloat(b.dataset.score);
        }
        return 0;
    });

    visibleCards.forEach(card => staffGrid.appendChild(card));
}

searchInput.addEventListener("input", updateStaffCards);
filterSelect.addEventListener("change", updateStaffCards);
sortSelect.addEventListener("change", updateStaffCards);