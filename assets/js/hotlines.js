let table;
$(document).ready(function() {
    table = $('#hotlinesTable').DataTable({
        responsive: true,
        pageLength: 20,
        lengthChange: false,
        ajax: {
            url: 'app/api.php?getEmergencyHotlines',
            dataSrc: function(json) {
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
                return [];
            }
        },
        columns: [
            { data: 'agency_name' },
            { 
                data: 'agency_code',
                render: function(data) {
                    return `<span class="badge bg-primary">${data}</span>`;
                }
            },
            { 
                data: 'phone_number',
                render: function(data) {
                    return `<a href="tel:${data}"><i class="bi bi-telephone"></i> ${data}</a>`;
                }
            },
            {
                data: 'hotline_id',
                render: function(data) {
                    return `
                        <button class="btn btn-primary" onclick="openEditModal(${data})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger" onclick="deleteHotline(${data})">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[3, 'desc']],
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

    $('#hotlineForm').submit(function(e) {
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
                    $('#hotlineModal').modal('hide');
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
    $('#formAction').val('addHotline');
    $('#formId').val('');
    $('#modalTitle').text('Add Hotline');
    $('#hotlineForm')[0].reset();
    $('#priority_order').val('1'); // Set default value
    $('#hotlineModal').modal('show');
}

function openEditModal(id) {
    $('#formAction').val('updateHotline');
    $('#formId').val(id);
    $('#modalTitle').text('Edit Hotline');
    
    // Fetch hotline data
    fetch('app/api.php?getEmergencyHotlines')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const hotline = data.data.find(h => h.hotline_id == id);
                if (hotline) {
                    $('#agency_name').val(hotline.agency_name);
                    $('#agency_code').val(hotline.agency_code);
                    $('#phone_number').val(hotline.phone_number);
                    $('#description').val(hotline.description);
                    $('#priority_order').val(hotline.priority_order);
                }
            }
            $('#hotlineModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            $('#hotlineModal').modal('show');
        });
}

function deleteHotline(id) {
    if (confirm('Delete this hotline?')) {
        const formData = new FormData();
        formData.append('deleteHotline', '1');
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
