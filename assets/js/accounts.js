let table;
$(document).ready(function() {
    table = $('#accountsTable').DataTable({
        responsive: true,
        pageLength: 20,
        lengthChange: false,
        ajax: {
            url: 'app/api.php?getUsers',
            dataSrc: function(json) {
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
                return [];
            }
        },
        columns: [{
                data: 'username'
            },
            {
                data: 'is_admin',
                render: function(data) {
                    const badge = data ? '<span class="badge bg-danger">Admin</span>' :
                        '<span class="badge bg-secondary">User</span>';
                    return badge;
                }
            },
            {
                data: 'created_at'
            },
            {
                data: null,
                render: function(data, type, row) {
                    return `
                    <button class="btn btn-primary" onclick="openEditModal(${row.user_id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-danger" onclick="deleteAccount(${row.user_id})">
                        <i class="bi bi-trash"></i>
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

    $('#accountForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const action = $('#formAction').val();
        formData.append(action, '1');
        
        console.log('Form action:', action);
        console.log('Form data:', Object.fromEntries(formData));
        
        fetch('app/api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.status === 'success') {
                    $('#accountModal').modal('hide');
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
    $('#formAction').val('addUser');
    $('#formId').val('');
    $('#modalTitle').text('Add Account');
    $('#accountForm')[0].reset();
    $('#password').attr('required', 'required').removeAttr('placeholder');
    $('#accountModal').modal('show');
}

function openEditModal(id) {
    $('#formAction').val('updateUser');
    $('#formId').val(id);
    $('#modalTitle').text('Edit Account');
    
    // Fetch user data
    fetch('app/api.php?getUsers')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const user = data.data.find(u => u.user_id == id);
                if (user) {
                    $('#username').val(user.username);
                    $('#password').removeAttr('required').val('').attr('placeholder', 'Leave blank to keep current password');
                    $('#is_admin').val(user.is_admin);
                }
            }
            $('#accountModal').modal('show');
        })
        .catch(error => {
            console.error('Error:', error);
            $('#accountModal').modal('show');
        });
}

function deleteAccount(id) {
    if (confirm('Are you sure you want to delete this account?')) {
        const formData = new FormData();
        formData.append('deleteUser', '1');
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
