// staff.js – Full filtering (name + staff code), sorting, and modal handling

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('staffSearch');
    const deptFilter = document.getElementById('departmentFilter');
    const statusFilter = document.getElementById('staffFilter');
    const sortSelect = document.getElementById('staffSort');
    const staffGrid = document.getElementById('staffGrid');
    
    if (!staffGrid) return;
    
    let staffCards = Array.from(document.querySelectorAll('.staff-card'));
    
    // Helper: get status from score percentage
    function getStatusFromScore(score) {
        const percent = parseFloat(score);
        if (percent >= 85) return 'excellence';
        if (percent >= 70) return 'good';
        if (percent >= 50) return 'moderate';
        return 'at risk';
    }
    
    // Helper: get department from data-department
    function getDepartment(card) {
        return card.getAttribute('data-department') || '';
    }
    
    // Helper: get name from data-name
    function getName(card) {
        return card.getAttribute('data-name') || '';
    }
    
    // Helper: get staff code from data-staffcode
    function getStaffCode(card) {
        return card.getAttribute('data-staffcode') || '';
    }
    
    // Helper: get score from data-score
    function getScore(card) {
        return parseFloat(card.getAttribute('data-score')) || 0;
    }
    
    // Filtering function (searches name OR staff code)
    function filterCards() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedDept = deptFilter ? deptFilter.value : 'all';
        const selectedStatus = statusFilter ? statusFilter.value : 'all';
        
        staffCards.forEach(card => {
            const name = getName(card);
            const staffCode = getStaffCode(card);
            const dept = getDepartment(card);
            const score = getScore(card);
            const status = getStatusFromScore(score);
            
            let match = true;
            
            // Search filter: match if name OR staff code contains search term
            if (searchTerm && !name.includes(searchTerm) && !staffCode.includes(searchTerm)) {
                match = false;
            }
            if (match && selectedDept !== 'all' && dept !== selectedDept) match = false;
            if (match && selectedStatus !== 'all' && status !== selectedStatus) match = false;
            
            card.style.display = match ? '' : 'none';
        });
        
        sortCards();
    }
    
    // Sorting function
    function sortCards() {
        const sortBy = sortSelect ? sortSelect.value : 'name-asc';
        let visibleCards = staffCards.filter(card => card.style.display !== 'none');
        
        visibleCards.sort((a, b) => {
            if (sortBy === 'name-asc') return getName(a).localeCompare(getName(b));
            if (sortBy === 'name-desc') return getName(b).localeCompare(getName(a));
            if (sortBy === 'score-high') return getScore(b) - getScore(a);
            if (sortBy === 'score-low') return getScore(a) - getScore(b);
            return 0;
        });
        
        visibleCards.forEach(card => staffGrid.appendChild(card));
        staffCards = Array.from(document.querySelectorAll('.staff-card'));
    }
    
    // Event listeners
    if (searchInput) searchInput.addEventListener('input', filterCards);
    if (deptFilter) deptFilter.addEventListener('change', filterCards);
    if (statusFilter) statusFilter.addEventListener('change', filterCards);
    if (sortSelect) sortSelect.addEventListener('change', sortCards);
    
    sortCards();
});

// ===== MODAL FUNCTIONS =====
function openAddKPIModal(staffId, staffName) {
    const modal = document.getElementById('addKPIModal');
    const target = document.getElementById('modalContentTarget');
    if (!modal || !target) return;
    
    modal.style.display = 'flex';
    
    fetch(`edit_kpi.php?staff_id=${staffId}`)
        .then(response => response.text())
        .then(html => {
            target.innerHTML = html;
            target.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                newScript.textContent = oldScript.textContent;
                document.body.appendChild(newScript);
            });
        })
        .catch(() => {
            target.innerHTML = `<div style="padding:20px;">Error loading form.</div>`;
        });
}

function closeModal() {
    const modal = document.getElementById('addKPIModal');
    if (modal) modal.style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('addKPIModal');
    if (event.target === modal) closeModal();
};