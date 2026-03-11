document.addEventListener("DOMContentLoaded", function () {
    const navPills = document.querySelectorAll(".nav-pill");
    const filterPills = document.querySelectorAll(".filter-pill");
    const segmentedButtons = document.querySelectorAll(".segmented button");

    navPills.forEach((btn) => {
        btn.addEventListener("click", function () {
            navPills.forEach((b) => b.classList.remove("active"));
            this.classList.add("active");
        });
    });

    filterPills.forEach((btn) => {
        btn.addEventListener("click", function () {
            filterPills.forEach((b) => b.classList.remove("active"));
            this.classList.add("active");
        });
    });

    segmentedButtons.forEach((btn) => {
        btn.addEventListener("click", function () {
            const parent = this.parentElement.querySelectorAll("button");
            parent.forEach((b) => b.classList.remove("active"));
            this.classList.add("active");
        });
    });
});