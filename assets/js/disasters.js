let table;
$(document).ready(function() {
    table = $('#disastersTable').DataTable({
        responsive: true,
        pageLength: 20,
        lengthChange: false,
        ajax: {
            url: 'app/api.php?getDisasters',
            dataSrc: function(json) {
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
                return [];
            }
        },
        columns: [
            { data: 'name' },
            { data: 'type' },
            { 
                data: 'severity',
                render: function(data) {
                    const classes = {
                        'Low': 'success',
                        'Moderate': 'warning',
                        'High': 'danger',
                        'Severe': 'dark'
                    };
                    const cls = classes[data] || 'secondary';
                    return `<span class="badge bg-${cls}">${data}</span>`;
                }
            },
            { data: 'start_date' },
            { data: 'end_date' },
            {
                data: 'is_past',
                render: function(data) {
                    const isPast = data === true || data === 1 || data === '1';
                    const cls = isPast ? 'secondary' : 'success';
                    const text = isPast ? 'Past' : 'Active/Upcoming';
                    return `<span class="badge bg-${cls}">${text}</span>`;
                }
            },
            {
                data: 'disaster_id',
                render: function(data, type, row) {
                    const isPast = row.is_past === true || row.is_past === 1 || row.is_past === '1';
                    let buttons = `
                        <button class="btn btn-info" onclick="openViewModal(${data})" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    `;
                    
                    if (!isPast) {
                        buttons += `
                            <button class="btn btn-primary" onclick="openEditModal(${data})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                        `;
                    }
                    
                    buttons += `
                        <button class="btn btn-danger" onclick="deleteDisaster(${data})" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                    
                    return buttons;
                }
            }
        ],
        order: [[0, 'desc']],
        language: {
            search: "Search:",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });

    $('#disasterForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const action = $('#formAction').val();
        formData.append(action, '1');
        fetch('app/api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    $('#disasterModal').modal('hide');
                    table.ajax.reload();
                    alert('Success!');
                } else {
                    alert('Error: ' + data.message);
                }
            });
    });
});

function openAddModal() {
    $('#formAction').val('addDisaster');
    $('#formId').val('');
    $('#modalTitle').text('Add Disaster');
    $('#disasterForm')[0].reset();
    $('#disasterModal').modal('show');
}

function openEditModal(id) {
    // Check if disaster is past before allowing edit
    fetch('app/api.php?getDisasters')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const disaster = data.data.find(d => d.disaster_id == id);
                if (disaster) {
                    // Check if past disaster
                    if (disaster.is_past === true || disaster.is_past === 1 || disaster.is_past === '1') {
                        alert('Cannot edit past disasters. Please use the View button to see details.');
                        return;
                    }
                    
                    $('#formAction').val('updateDisaster');
                    $('#formId').val(id);
                    $('#modalTitle').text('Edit Disaster');
                    $('#name').val(disaster.name);
                    $('#type').val(disaster.type);
                    $('#start_date').val(disaster.start_date);
                    $('#end_date').val(disaster.end_date);
                    $('#severity').val(disaster.severity);
                    $('#description').val(disaster.description);
                    $('#disasterModal').modal('show');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while fetching disaster data.');
        });
}

let currentDisasterId = null;
let currentDisasterIsPast = false;

function openViewModal(id) {
    currentDisasterId = id;
    // Fetch disaster details with shelters
    fetch(`app/api.php?getDisasterDetails&disaster_id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const disaster = data.data;
                $('#viewModalTitle').text(disaster.name);
                $('#viewModalTitleText').text(disaster.name);
                $('#viewDisasterType').text(disaster.type);
                
                // Check if past disaster
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const endDate = disaster.end_date ? new Date(disaster.end_date) : null;
                const startDate = disaster.start_date ? new Date(disaster.start_date) : null;
                
                currentDisasterIsPast = false;
                if (endDate && endDate < today) {
                    currentDisasterIsPast = true;
                } else if (startDate && startDate < today && !endDate) {
                    currentDisasterIsPast = true;
                }
                
                // Show/hide Add Shelters button based on disaster status
                if (currentDisasterIsPast) {
                    $('#addSheltersBtn').hide();
                } else {
                    $('#addSheltersBtn').show();
                }
                
                // Add badge for severity
                const severityClasses = {
                    'Low': 'success',
                    'Moderate': 'warning',
                    'High': 'danger',
                    'Severe': 'dark'
                };
                const severityCls = severityClasses[disaster.severity] || 'secondary';
                $('#viewDisasterSeverity').html(`<span class="badge bg-${severityCls}">${disaster.severity}</span>`);
                
                $('#viewDisasterStartDate').text(disaster.start_date || 'N/A');
                $('#viewDisasterEndDate').text(disaster.end_date || 'N/A');
                $('#viewDisasterDescription').text(disaster.description || 'No description');
                
                // Populate shelters table
                const sheltersTable = $('#viewSheltersTable tbody');
                sheltersTable.empty();
                
                if (disaster.shelters && disaster.shelters.length > 0) {
                    let totalEvacuees = 0;
                    disaster.shelters.forEach(shelter => {
                        const evacueeCount = parseInt(shelter.total_evacuees) || 0;
                        totalEvacuees += evacueeCount;
                        const canEdit = !currentDisasterIsPast;
                        const shelterNameEscaped = shelter.shelter_name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        sheltersTable.append(`
                            <tr class="shelter-row-clickable ${canEdit ? 'table-hover' : ''}" 
                                style="cursor: ${canEdit ? 'pointer' : 'default'};" 
                                data-shelter-id="${shelter.shelter_id}"
                                data-shelter-name="${shelterNameEscaped}"
                                data-current-occupancy="${shelter.current_occupancy}"
                                data-evacuees="${evacueeCount}"
                                data-capacity="${shelter.capacity}"
                                ${canEdit ? `onclick="editShelterOccupancy(${shelter.shelter_id}, '${shelterNameEscaped}', ${shelter.current_occupancy}, ${evacueeCount}, ${shelter.capacity})"` : ''}>
                                <td>${shelter.shelter_name}</td>
                                <td>${shelter.barangay}</td>
                                <td>${shelter.shelter_type}</td>
                                <td>${shelter.capacity}</td>
                                <td>${shelter.current_occupancy}</td>
                                <td><strong class="text-danger">${evacueeCount}</strong></td>
                                <td onclick="event.stopPropagation();">
                                    ${canEdit ? `<button class="btn btn-primary" onclick="editShelterOccupancy(${shelter.shelter_id}, '${shelterNameEscaped}', ${shelter.current_occupancy}, ${evacueeCount}, ${shelter.capacity}, event)">
                                        <i class="bi bi-pencil"></i>
                                    </button>` : '<span class="text-muted">View Only</span>'}
                                </td>
                            </tr>
                        `);
                    });
                    $('#viewTotalEvacuees').text(totalEvacuees);
                    $('#viewSheltersCount').text(disaster.shelters.length);
                } else {
                    sheltersTable.append('<tr><td colspan="7" class="text-center">No shelters assigned to this disaster</td></tr>');
                    $('#viewTotalEvacuees').text('0');
                    $('#viewSheltersCount').text('0');
                }
                
                $('#viewDisasterModal').modal('show');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while fetching disaster details.');
        });
}

function openAddSheltersModal() {
    if (!currentDisasterId) {
        alert('No disaster selected.');
        return;
    }
    
    if (currentDisasterIsPast) {
        alert('Cannot add shelters to past disasters.');
        return;
    }
    
    // Fetch all shelters
    fetch('app/api.php?getAllShelters')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const shelters = data.data || [];
                const body = $('#sheltersListBody');
                body.empty();
                
                // Get currently assigned shelters for this disaster
                fetch(`app/api.php?getDisasterDetails&disaster_id=${currentDisasterId}`)
                    .then(response => response.json())
                    .then(disasterData => {
                        const assignedShelterIds = [];
                        if (disasterData.status === 'success' && disasterData.data.shelters) {
                            disasterData.data.shelters.forEach(s => {
                                assignedShelterIds.push(s.shelter_id);
                            });
                        }
                        
                        // Filter only active shelters
                        const activeShelters = shelters.filter(s => s.is_active == 1 || s.is_active === true);
                        
                        if (activeShelters.length === 0) {
                            body.append('<tr><td colspan="6" class="text-center">No active shelters available</td></tr>');
                        } else {
                            activeShelters.forEach(shelter => {
                                const isChecked = assignedShelterIds.includes(shelter.shelter_id);
                                const statusBadge = shelter.shelter_status === 'Available' ? 'success' : 
                                                   shelter.shelter_status === 'Full' ? 'danger' : 
                                                   shelter.shelter_status === 'Under Maintenance' ? 'warning' : 'secondary';
                                
                                body.append(`
                                    <tr class="shelter-row">
                                        <td>
                                            <input type="checkbox" class="shelter-checkbox" 
                                                   value="${shelter.shelter_id}" 
                                                   ${isChecked ? 'checked' : ''}
                                                   data-name="${shelter.shelter_name.toLowerCase()}">
                                        </td>
                                        <td>${shelter.shelter_name}</td>
                                        <td>${shelter.barangay}</td>
                                        <td>${shelter.shelter_type}</td>
                                        <td>${shelter.capacity}</td>
                                        <td><span class="badge bg-${statusBadge}">${shelter.shelter_status}</span></td>
                                    </tr>
                                `);
                            });
                        }
                        
                        $('#addSheltersModal').modal('show');
                    });
            } else {
                alert('Error loading shelters: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading shelters.');
        });
}

function toggleAllShelters() {
    const selectAll = $('#selectAllShelters').is(':checked');
    $('.shelter-checkbox').prop('checked', selectAll);
}

function saveSheltersToDisaster() {
    if (!currentDisasterId) {
        alert('No disaster selected.');
        return;
    }
    
    if (currentDisasterIsPast) {
        alert('Cannot add shelters to past disasters.');
        return;
    }
    
    const selectedShelters = [];
    $('.shelter-checkbox:checked').each(function() {
        selectedShelters.push($(this).val());
    });
    
    if (selectedShelters.length === 0) {
        alert('Please select at least one shelter.');
        return;
    }
    
    const formData = new FormData();
    formData.append('assignSheltersToDisaster', '1');
    formData.append('disaster_id', currentDisasterId);
    formData.append('shelter_ids', JSON.stringify(selectedShelters));
    
    fetch('app/api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.status === 'success') {
                $('#addSheltersModal').modal('hide');
                // Refresh the view modal
                openViewModal(currentDisasterId);
                alert('Shelters added successfully!');
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', text);
            alert('Error parsing server response. Please check the console for details.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while adding shelters: ' + error.message);
    });
}

// Search functionality for shelters
$(document).on('input', '#shelterSearchInput', function() {
    const searchTerm = $(this).val().toLowerCase();
    $('.shelter-row').each(function() {
        const rowText = $(this).text().toLowerCase();
        if (rowText.includes(searchTerm) || searchTerm === '') {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    // Update select all checkbox state based on visible checkboxes
    const visibleCheckboxes = $('.shelter-row:visible .shelter-checkbox');
    const checkedVisible = $('.shelter-row:visible .shelter-checkbox:checked');
    $('#selectAllShelters').prop('checked', visibleCheckboxes.length > 0 && visibleCheckboxes.length === checkedVisible.length);
});

// Reset search when modal is closed
$('#addSheltersModal').on('hidden.bs.modal', function() {
    $('#shelterSearchInput').val('');
});

// Edit shelter occupancy function
function editShelterOccupancy(shelterId, shelterName, currentOccupancy, evacuees, capacity, event) {
    if (event) {
        event.stopPropagation();
    }
    
    if (!currentDisasterId) {
        alert('No disaster selected.');
        return;
    }
    
    if (currentDisasterIsPast) {
        alert('Cannot edit occupancy for past disasters.');
        return;
    }
    
    $('#editShelterId').val(shelterId);
    $('#editShelterDisasterId').val(currentDisasterId);
    $('#editShelterName').text(shelterName);
    $('#editCurrentOccupancy').val(currentOccupancy);
    $('#editCurrentOccupancy').attr('max', capacity);
    $('#editEvacuees').val(evacuees);
    
    $('#editShelterOccupancyModal').modal('show');
}

// Save shelter occupancy
function saveShelterOccupancy() {
    const shelterId = $('#editShelterId').val();
    const disasterId = $('#editShelterDisasterId').val();
    const currentOccupancy = parseInt($('#editCurrentOccupancy').val()) || 0;
    const evacuees = parseInt($('#editEvacuees').val()) || 0;
    
    if (!shelterId || !disasterId) {
        alert('Missing required information.');
        return;
    }
    
    if (currentOccupancy < 0 || evacuees < 0) {
        alert('Occupancy and evacuees cannot be negative.');
        return;
    }
    
    const formData = new FormData();
    formData.append('updateShelterOccupancy', '1');
    formData.append('shelter_id', shelterId);
    formData.append('disaster_id', disasterId);
    formData.append('current_occupancy', currentOccupancy);
    formData.append('evacuees', evacuees);
    
    fetch('app/api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text();
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.status === 'success') {
                $('#editShelterOccupancyModal').modal('hide');
                // Refresh the view modal
                openViewModal(currentDisasterId);
                alert('Shelter occupancy updated successfully!');
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', text);
            alert('Error parsing server response. Please check the console for details.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating shelter occupancy: ' + error.message);
    });
}

function deleteDisaster(id) {
    if (confirm('Delete this disaster?')) {
        const formData = new FormData();
        formData.append('deleteDisaster', '1');
        formData.append('id', id);
        fetch('app/api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    table.ajax.reload();
                    alert('Deleted successfully!');
                }
            });
    }
}
