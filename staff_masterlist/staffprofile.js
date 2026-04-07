// staff_masterlist/staffprofile.js

function openAddKPIModal(staffId, staffName) {
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