function openAddKPIModal(staffId, staffName) {
    const modal = document.getElementById('addKPIModal');
    const target = document.getElementById('modalContentTarget');
   
    modal.style.display = 'flex';


    fetch(`edit_kpi.php?staff_id=${staffId}`)
        .then(response => response.text())
        .then(html => {
            target.innerHTML = html;
            if (typeof recalculate === "function") {
                recalculate();
            }
        })
        .catch(() => {
            target.innerHTML = `<div style="padding:20px;">Error loading form.</div>`;
        });
    }


function closeModal() {
    document.getElementById('addKPIModal').style.display = 'none';
   
}


window.onclick = function(event) {
    const modal = document.getElementById('addKPIModal');
    if (event.target === modal) {
        closeModal();
    }
}
