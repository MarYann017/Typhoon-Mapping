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
<title>Disasters</title>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Disasters</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Disasters</li>
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
                                Add Disaster
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table id="disastersTable" class="table table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Severity</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
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
<div class="modal fade" id="disasterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Disaster</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="disasterForm">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="formId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-control" name="type" id="type" required>
                            <option value="Typhoon">Typhoon</option>
                            <option value="Flood">Flood</option>
                            <option value="Earthquake">Earthquake</option>
                            <option value="Landslide">Landslide</option>
                            <option value="Fire">Fire</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity</label>
                        <select class="form-control" name="severity" id="severity" required>
                            <option value="Low">Low</option>
                            <option value="Moderate">Moderate</option>
                            <option value="High">High</option>
                            <option value="Severe">Severe</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" id="start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" id="end_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="3"></textarea>
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

<!-- Add Shelters Modal -->
<div class="modal fade" id="addSheltersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Shelters to Disaster</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="shelterSearchInput" placeholder="Search shelters...">
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAllShelters" onchange="toggleAllShelters()">
                                </th>
                                <th>Shelter Name</th>
                                <th>Barangay</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="sheltersListBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSheltersToDisaster()">Add Selected Shelters</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Shelter Occupancy Modal -->
<div class="modal fade" id="editShelterOccupancyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Shelter Occupancy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editShelterId">
                <input type="hidden" id="editShelterDisasterId">
                <div class="mb-3">
                    <label class="form-label"><strong>Shelter Name:</strong></label>
                    <p id="editShelterName" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Current Occupancy <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="editCurrentOccupancy" min="0" required>
                    <small class="text-muted">Current number of people in the shelter</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Evacuees <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="editEvacuees" min="0" required>
                    <small class="text-muted">Number of evacuees during this disaster</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-success" onclick="saveShelterOccupancy()">
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDisasterModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalTitle">Disaster Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-primary">Disaster Information</h6>
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Name:</th>
                                <td id="viewModalTitleText"></td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td id="viewDisasterType"></td>
                            </tr>
                            <tr>
                                <th>Severity:</th>
                                <td id="viewDisasterSeverity"></td>
                            </tr>
                            <tr>
                                <th>Start Date:</th>
                                <td id="viewDisasterStartDate"></td>
                            </tr>
                            <tr>
                                <th>End Date:</th>
                                <td id="viewDisasterEndDate"></td>
                            </tr>
                            <tr>
                                <th>Description:</th>
                                <td id="viewDisasterDescription"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary">Summary</h6>
                        <div class="card">
                            <div class="card-body">
                                <p><strong>Total Shelters Used:</strong> <span id="viewSheltersCount" class="text-primary">0</span></p>
                                <p><strong>Total Evacuees:</strong> <span id="viewTotalEvacuees" class="text-danger">0</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-primary mb-0">Shelters Used During This Disaster</h6>
                    <button type="button" class="btn btn-success" id="addSheltersBtn" onclick="openAddSheltersModal()" style="display: none;">
                      Add Shelters
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="viewSheltersTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Shelter Name</th>
                                <th>Barangay</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Current Occupancy</th>
                                <th>Evacuees</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>
<script src="assets/js/disasters.js"></script>
</body>

</html>
