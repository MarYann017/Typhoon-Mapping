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
<title>Shelters</title>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Shelters</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Shelters</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
                            <button class="btn btn-success" onclick="openBulkUploadModal()">
                                Bulk Upload
                            </button>
                            <button class="btn btn-primary" onclick="openAddModal()">
                                Add Shelter
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table id="sheltersTable" class="table table-striped table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Shelter Name</th>
                                        <th>Barangay</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Active Status</th>
                                        <th>Capacity</th>
                                        <th>Occupancy</th>
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

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload Shelters</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkUploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Excel/CSV File</label>
                        <input type="file" class="form-control" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" required>
                        <small class="text-muted">Supported formats: .xlsx, .xls, .csv</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="shelterModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Shelter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="shelterForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="formId">
                <div class="modal-body" style="max-height: 100%; overflow-y: auto;">
                    <!-- Basic Information -->
                    <h6 class="text-primary mb-3">Basic Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shelter Name</label>
                            <input type="text" class="form-control" name="shelter_name" id="shelter_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barangay</label>
                            <input type="text" class="form-control" name="barangay" id="barangay" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Owner Name</label>
                            <input type="text" class="form-control" name="owner_name" id="owner_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shelter Type</label>
                            <select class="form-control" name="shelter_type" id="shelter_type" required>
                                <option value="School">School</option>
                                <option value="House">House</option>
                                <option value="Barangay Hall">Barangay Hall</option>
                                <option value="Gym">Gym</option>
                                <option value="Church">Church</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Address</label>
                        <input type="text" class="form-control" name="full_address" id="full_address">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="description" rows="2"></textarea>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="text-primary mb-3 mt-4">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" id="contact_person">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" id="contact_number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" class="form-control" name="contact_email" id="contact_email">
                        </div>
                    </div>

                    <!-- Status & Capacity -->
                    <h6 class="text-primary mb-3 mt-4">Status & Capacity</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="shelter_status" id="shelter_status" required>
                                <option value="Available">Available</option>
                                <option value="Full">Full</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Active Status</label>
                            <select class="form-control" name="is_active" id="is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" id="capacity" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Current Occupancy</label>
                            <input type="number" class="form-control" name="current_occupancy"
                                id="current_occupancy" required>
                        </div>
                    </div>

                    <!-- Risk Zones -->
                    <h6 class="text-primary mb-3 mt-4">Risk Zones</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Typhoon Zone</label>
                            <select class="form-control" name="typhoon_zone" id="typhoon_zone" required>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Flood Zone</label>
                            <select class="form-control" name="flood_zone" id="flood_zone" required>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Landslide Zone</label>
                            <select class="form-control" name="landslide_zone" id="landslide_zone" required>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Liquefaction Zone</label>
                            <select class="form-control" name="liquefaction_zone" id="liquefaction_zone" required>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Storm Surge Zone</label>
                            <select class="form-control" name="storm_surge_zone" id="storm_surge_zone" required>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Elevation (meters)</label>
                            <input type="number" step="0.01" class="form-control" name="elevation"
                                id="elevation" required>
                        </div>
                    </div>

                    <!-- Location -->
                    <h6 class="text-primary mb-3 mt-4">Location</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Latitude</label>
                            <input type="number" step="0.00000001" class="form-control" name="latitude"
                                id="latitude" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Longitude</label>
                            <input type="number" step="0.00000001" class="form-control" name="longitude"
                                id="longitude" required>
                        </div>
                    </div>

                    <!-- Building Details -->
                    <h6 class="text-primary mb-3 mt-4">Building Details</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Building Material</label>
                            <select class="form-control" name="building_material_type" id="building_material_type"
                                required>
                                <option value="">Select Material</option>
                                <option value="Concrete">Concrete</option>
                                <option value="Steel">Steel</option>
                                <option value="Wood">Wood</option>
                                <option value="Semi-Concrete">Semi-Concrete</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Building Condition</label>
                            <select class="form-control" name="building_condition" id="building_condition" required>
                                <option value="">Select Condition</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Needs Repair">Needs Repair</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Water Supply</label>
                            <select class="form-control" name="water_supply" id="water_supply" required>
                                <option value="">Select Status</option>
                                <option value="Available">Available</option>
                                <option value="Limited">Limited</option>
                                <option value="None">None</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Electricity</label>
                            <select class="form-control" name="electricity" id="electricity" required>
                                <option value="">Select Status</option>
                                <option value="Available">Available</option>
                                <option value="Limited">Limited</option>
                                <option value="None">None</option>
                                <option value="Generator Only">Generator Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Road Condition</label>
                            <select class="form-control" name="road_condition" id="road_condition" required>
                                <option value="">Select Condition</option>
                                <option value="Good">Good</option>
                                <option value="Safe">Safe</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Rough">Rough</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estimated Travel Time</label>
                            <input type="text" class="form-control" name="estimated_travel_time"
                                id="estimated_travel_time" placeholder="e.g., 5 minutes" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Near Main Road</label>
                            <select class="form-control" name="near_main_road" id="near_main_road" required>
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Safe Shelter</label>
                            <select class="form-control" name="is_safe_shelter" id="is_safe_shelter" required>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                    </div>

                    <!-- Shelter Images -->
                    <h6 class="text-primary mb-3 mt-4">Shelter Images</h6>
                    <div class="mb-3">
                        <label class="form-label">Upload Images</label>
                        <input type="file" class="form-control" name="shelter_images[]" id="shelter_images"
                            accept="image/*" multiple>
                        <small class="text-muted">You can select multiple images (PNG, JPG, GIF)</small>
                    </div>
                    <div class="mb-3" id="imagePreviewContainer">
                        <label class="form-label">Image Preview</label>
                        <div class="row" id="imagePreviewGrid"></div>
                    </div>
                    <div class="mb-3" id="existingImagesContainer" style="display: none;">
                        <label class="form-label">Existing Images</label>
                        <div class="row" id="existingImagesGrid"></div>
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
<script src="assets/js/shelter.js"></script>
</body>

</html>
