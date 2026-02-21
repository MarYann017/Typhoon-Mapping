<?php
require_once __DIR__ . '/app/Helper.php';

// Check if user is authenticated
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sideNav.php';
?>
<title>Emergency Hotlines</title>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Emergency Hotlines</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Emergency Hotlines</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-end align-items-center mb-3">
                            <button class="btn btn-primary" onclick="openAddModal()">
                                Add Hotline
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table id="hotlinesTable" class="table table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Agency</th>
                                        <th>Code</th>
                                        <th>Phone Number</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Add/Edit Modal -->
<div class="modal fade" id="hotlineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Hotline</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="hotlineForm">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="formId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Agency Name</label>
                        <input type="text" class="form-control" name="agency_name" id="agency_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Agency Code</label>
                        <input type="text" class="form-control" name="agency_code" id="agency_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone_number" id="phone_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority Order</label>
                        <input type="number" class="form-control" name="priority_order" id="priority_order"
                            value="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>
<script src="assets/js/hotlines.js"></script>
</body>

</html>
