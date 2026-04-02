// staff_masterlist/staff_list.js

const searchInput = document.getElementById('staffSearch');
const departmentFilter = document.getElementById('departmentFilter');
const sortBy = document.getElementById('sortBy');
const staffTableBody = document.getElementById('staffTableBody');
const staffCountSpan = document.getElementById('staffCount');

let currentStaffData = [];

// Initialize staff data from table rows
function initStaffData() {
    const rows = document.querySelectorAll('.staff-row');
    currentStaffData = Array.from(rows).map(row => ({
        element: row,
        name: row.dataset.name,
        staffId: row.dataset.staffId,
        department: row.dataset.department,
        kpi: parseFloat(row.dataset.kpi),
        trend: row.dataset.trend
    }));
}

function filterAndSort() {
    const searchValue = searchInput.value.toLowerCase().trim();
    const departmentValue = departmentFilter.value;
    const sortValue = sortBy.value;
    
    let filteredData = currentStaffData.filter(staff => {
        const matchesSearch = staff.name.includes(searchValue) || 
                             staff.staffId.includes(searchValue);
        const matchesDepartment = departmentValue === 'all' || 
                                 staff.department === departmentValue;
        return matchesSearch && matchesDepartment;
    });
    
    // Sort
    filteredData.sort((a, b) => {
        if (sortValue === 'name') {
            return a.name.localeCompare(b.name);
        } else if (sortValue === 'kpi') {
            return b.kpi - a.kpi;
        } else if (sortValue === 'trend') {
            const trendOrder = { up: 0, stable: 1, down: 2 };
            return trendOrder[a.trend] - trendOrder[b.trend];
        }
        return 0;
    });
    
    // Update UI
    filteredData.forEach((staff, index) => {
        staff.element.style.order = index;
        staff.element.style.display = '';
    });
    
    // Hide non-filtered rows
    currentStaffData.forEach(staff => {
        if (!filteredData.includes(staff)) {
            staff.element.style.display = 'none';
        }
    });
    
    // Update count
    staffCountSpan.textContent = `Showing ${filteredData.length} of ${currentStaffData.length} staff members`;
}

// Modal functions
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

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addKPIModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Event listeners
searchInput.addEventListener('input', filterAndSort);
departmentFilter.addEventListener('change', filterAndSort);
sortBy.addEventListener('change', filterAndSort);

// Initialize
initStaffData();
filterAndSort();