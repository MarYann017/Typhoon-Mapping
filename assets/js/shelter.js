let table;
$(document).ready(function() {
    table = $('#sheltersTable').DataTable({
        responsive: true,
        pageLength: 20,
        lengthChange: false,
        ajax: {
            url: 'app/api.php?getAllShelters',
            dataSrc: function(json) {
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
                return [];
            }
        },
        columns: [{
                data: 'shelter_name'
            },
            {
                data: 'barangay'
            },
            {
                data: 'shelter_type'
            },
            {
                data: 'shelter_status',
                render: function(data) {
                    const classes = {
                        'Available': 'success',
                        'Full': 'danger',
                        'Under Maintenance': 'warning',
                        'Closed': 'secondary'
                    };
                    const cls = classes[data] || 'secondary';
                    return `<span class="badge bg-${cls}">${data}</span>`;
                }
            },
            {
                data: 'is_active',
                render: function(data) {
                    const isActive = data == 1 || data === true || data === '1';
                    const cls = isActive ? 'success' : 'secondary';
                    const text = isActive ? 'Active' : 'Inactive';
                    return `<span class="badge bg-${cls}">${text}</span>`;
                }
            },
            {
                data: 'capacity'
            },
            {
                data: 'current_occupancy',
                render: function(data, type, row) {
                    return `${data}/${row.capacity}`;
                }
            },
            {
                data: 'shelter_id',
                render: function(data) {
                    return `
                    <button class="btn btn-primary" onclick="openEditModal(${data})">
                        <i class="bi bi-pencil"></i>
                    </button>
                `;
                }
            }
        ],
        order: [
            [0, 'desc']
        ],
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

    // Image preview functionality
    $('#shelter_images').change(function() {
        const files = this.files;
        const previewGrid = $('#imagePreviewGrid');
        previewGrid.empty();
        
        if (files.length > 0) {
            $('#imagePreviewContainer').show();
            Array.from(files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewGrid.append(`
                        <div class="col-md-3 mb-2">
                            <div class="position-relative">
                                <img src="${e.target.result}" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;">
                                <button type="button" class="btn btn-danger position-absolute top-0 end-0 m-1" onclick="removeImagePreview(${index})">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        </div>
                    `);
                };
                reader.readAsDataURL(file);
            });
        } else {
            $('#imagePreviewContainer').hide();
        }
    });

    // Auto-update status based on capacity/occupancy
    $('#capacity, #current_occupancy').on('input', function() {
        updateShelterStatus();
    });

    function updateShelterStatus() {
        const capacity = parseInt($('#capacity').val()) || 0;
        const occupancy = parseInt($('#current_occupancy').val()) || 0;
        
        if (capacity === 0) return; // Don't update if capacity is 0
        
        if (occupancy >= capacity) {
            $('#shelter_status').val('Full');
        } else if (occupancy > 0) {
            $('#shelter_status').val('Available');
        }
        // If occupancy is 0, keep current status or set to Available
        else if ($('#shelter_status').val() === 'Full') {
            $('#shelter_status').val('Available');
        }
    }

    $('#shelterForm').submit(function(e) {
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
                    $('#shelterModal').modal('hide');
                    table.ajax.reload();
                    alert('Success!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving.');
            });
    });

    // Bulk upload form submission
    $('#bulkUploadForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('bulkUploadShelters', '1');
        
        fetch('app/api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' || data.status === 'warning') {
                $('#bulkUploadModal').modal('hide');
                table.ajax.reload();
                
                let message = data.message;
                if (data.data && data.data.errors && data.data.errors.length > 0) {
                    message += '\n\nErrors:\n' + data.data.errors.slice(0, 10).join('\n');
                    if (data.data.errors.length > 10) {
                        message += '\n... and ' + (data.data.errors.length - 10) + ' more errors.';
                    }
                }
                alert(message);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while uploading.');
        });
    });
});

function openAddModal() {
    $('#formAction').val('addShelter');
    $('#formId').val('');
    $('#modalTitle').text('Add Shelter');
    $('#shelterForm')[0].reset();
    $('#imagePreviewGrid').empty();
    $('#existingImagesGrid').empty();
    $('#imagePreviewContainer').hide();
    $('#existingImagesContainer').hide();
    $('#shelterModal').modal('show');
}

function openBulkUploadModal() {
    $('#bulkUploadModal').modal('show');
}

function openEditModal(id) {
    $('#formAction').val('updateShelter');
    $('#formId').val(id);
    $('#modalTitle').text('Edit Shelter');
    $('#imagePreviewGrid').empty();
    $('#existingImagesGrid').empty();
    $('#imagePreviewContainer').hide();
    $('#existingImagesContainer').hide();
    
    // Fetch shelter data
    fetch('app/api.php?getAllShelters')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const shelter = data.data.find(s => s.shelter_id == id);
                if (shelter) {
                    $('#shelter_name').val(shelter.shelter_name);
                    $('#barangay').val(shelter.barangay);
                    $('#owner_name').val(shelter.owner_name);
                    $('#full_address').val(shelter.full_address);
                    $('#description').val(shelter.description);
                    $('#contact_person').val(shelter.contact_person);
                    $('#contact_number').val(shelter.contact_number);
                    $('#contact_email').val(shelter.contact_email);
                    $('#shelter_type').val(shelter.shelter_type);
                    $('#shelter_status').val(shelter.shelter_status);
                    $('#is_active').val(shelter.is_active ? '1' : '0');
                    $('#capacity').val(shelter.capacity);
                    $('#current_occupancy').val(shelter.current_occupancy);
                    $('#typhoon_zone').val(shelter.typhoon_zone);
                    $('#flood_zone').val(shelter.flood_zone);
                    $('#landslide_zone').val(shelter.landslide_zone);
                    $('#liquefaction_zone').val(shelter.liquefaction_zone);
                    $('#storm_surge_zone').val(shelter.storm_surge_zone);
                    $('#elevation').val(shelter.elevation);
                    $('#latitude').val(shelter.latitude);
                    $('#longitude').val(shelter.longitude);
                    $('#building_material_type').val(shelter.building_material_type);
                    $('#building_condition').val(shelter.building_condition);
                    $('#water_supply').val(shelter.water_supply);
                    $('#electricity').val(shelter.electricity);
                    $('#road_condition').val(shelter.road_condition);
                    $('#estimated_travel_time').val(shelter.estimated_travel_time);
                    $('#near_main_road').val(shelter.near_main_road);
                    $('#is_safe_shelter').val(shelter.is_safe_shelter ? '1' : '0');

                    // Display existing images
                    if (shelter.shelter_images && shelter.shelter_images.length > 0) {
                        $('#existingImagesContainer').show();
                        shelter.shelter_images.forEach(image => {
                            $('#existingImagesGrid').append(`
                                <div class="col-md-3 mb-2" id="existingImage_${image.image_id}">
                                    <div class="position-relative">
                                        <img src="${image.image_path}" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;" onerror="this.src='assets/img/logo.png'">
                                        <button type="button" class="btn btn-danger position-absolute top-0 end-0 m-1" onclick="deleteShelterImage(${image.image_id})">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            `);
                        });
                    }
                }
            }
            $('#shelterModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            $('#shelterModal').modal('show');
        });
}

function removeImagePreview(index) {
    const input = document.getElementById('shelter_images');
    const dt = new DataTransfer();
    const files = Array.from(input.files);
    
    files.forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    input.files = dt.files;
    $('#shelter_images').trigger('change');
}

function deleteShelterImage(imageId) {
    if (confirm('Delete this image?')) {
        const formData = new FormData();
        formData.append('deleteShelterImage', '1');
        formData.append('image_id', imageId);
        fetch('app/api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Remove the image from the display
                    $('#existingImage_' + imageId).remove();
                    alert('Image deleted successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the image.');
            });
    }
}
