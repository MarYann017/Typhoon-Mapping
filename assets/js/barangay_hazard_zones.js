let table;
$(document).ready(function() {
    table = $('#barangayHazardZonesTable').DataTable({
        responsive: true,
        pageLength: 20,
        lengthChange: false,
        ajax: {
            url: 'app/api.php?getBarangayHazardZones',
            dataSrc: function(json) {
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
                return [];
            }
        },
        columns: [
            { data: 'barangay' },
            { data: 'latitude' },
            { data: 'longitude' },
            { 
                data: 'typhoon_zone',
                render: function(data) {
                    const cls = data === 'Yes' ? 'danger' : 'success';
                    return `<span class="badge bg-${cls}">${data}</span>`;
                }
            },
            { 
                data: 'flood_zone',
                render: function(data) {
                    const cls = data === 'Yes' ? 'danger' : 'success';
                    return `<span class="badge bg-${cls}">${data}</span>`;
                }
            },
            { 
                data: 'landslide_zone',
                render: function(data) {
                    const cls = data === 'Yes' ? 'danger' : 'success';
                    return `<span class="badge bg-${cls}">${data}</span>`;
                }
            },
            { 
                data: 'liquefaction_zone',
                render: function(data) {
                    const cls = data === 'Yes' ? 'danger' : 'success';
                    return `<span class="badge bg-${cls}">${data}</span>`;
                }
            },
            { 
                data: 'storm_surge_zone',
                render: function(data) {
                    const cls = data === 'Yes' ? 'danger' : 'success';
                    return `<span class="badge bg-${cls}">${data}</span>`;
                }
            },
            { 
                data: 'hazard_level',
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
            {
                data: 'hazard_zone_id',
                render: function(data) {
                    return `
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="openEditModal(${data})" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger" onclick="deleteBarangayHazardZone(${data})" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    `;
                }
            }
        ],
        order: [[0, 'asc']],
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

    $('#barangayHazardZoneForm').submit(function(e) {
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
                    $('#barangayHazardZoneModal').modal('hide');
                    table.ajax.reload();
                    alert('Success!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
    });
});

function openAddModal() {
    $('#formAction').val('addBarangayHazardZone');
    $('#formId').val('');
    $('#modalTitle').text('Add Barangay Hazard Zone');
    $('#barangayHazardZoneForm')[0].reset();
    $('#typhoon_zone').val('No');
    $('#flood_zone').val('No');
    $('#landslide_zone').val('No');
    $('#liquefaction_zone').val('No');
    $('#storm_surge_zone').val('No');
    $('#hazard_level').val('Low');
    $('#barangayHazardZoneModal').modal('show');
}

function openEditModal(id) {
    $('#formAction').val('updateBarangayHazardZone');
    $('#formId').val(id);
    $('#modalTitle').text('Edit Barangay Hazard Zone');
    
    fetch('app/api.php?getBarangayHazardZones')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const zone = data.data.find(z => z.hazard_zone_id == id);
                if (zone) {
                    $('#barangay').val(zone.barangay);
                    $('#latitude').val(zone.latitude);
                    $('#longitude').val(zone.longitude);
                    $('#typhoon_zone').val(zone.typhoon_zone);
                    $('#flood_zone').val(zone.flood_zone);
                    $('#landslide_zone').val(zone.landslide_zone);
                    $('#liquefaction_zone').val(zone.liquefaction_zone);
                    $('#storm_surge_zone').val(zone.storm_surge_zone);
                    $('#hazard_level').val(zone.hazard_level);
                    $('#description').val(zone.description || '');
                }
            }
            $('#barangayHazardZoneModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            $('#barangayHazardZoneModal').modal('show');
        });
}

function deleteBarangayHazardZone(id) {
    if (confirm('Delete this barangay hazard zone?')) {
        const formData = new FormData();
        formData.append('deleteBarangayHazardZone', '1');
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
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
    }
}

