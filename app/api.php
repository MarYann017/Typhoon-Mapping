<?php
    // Suppress warnings/notices but keep errors for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Start output buffering early to catch any unexpected output
    ob_start();
    
    // Set error handler to return JSON on errors
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'PHP Error: ' . $errstr . ' in ' . $errfile . ' on line ' . $errline,
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit();
    });
    
    // Set exception handler
    set_exception_handler(function($exception) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Exception: ' . $exception->getMessage(),
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit();
    });
    
    try {
        require_once 'Db.php';
        require_once 'Helper.php';
        require_once 'Shelters.php';
        require_once 'Location.php';
        require_once 'Disasters.php';
        require_once 'ShelterStats.php';
        require_once 'EmergencyHotlines.php';
        require_once 'BarangayHazardZones.php';
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to load required files: ' . $e->getMessage(),
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json');

    $response = [
        'status' => '',
        'message' => '',
        'data' => '',
    ];

    try {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        if (isset($_GET['getAllShelters'])) {
            $shelters = new Shelters();
            $response = $shelters->getAllShelters();
        }

        if (isset($_GET['getCurrentLocation'])) {
            $location = new Location();
            $response = $location->getCurrentLocation();
        }

        if (isset($_GET['getDisasters'])) {
            $disasters = new Disasters();
            $response = $disasters->getDisasters();
        }

        if (isset($_GET['getShelterStats'])) {
            $shelterStats = new ShelterStats();
            $response = $shelterStats->getShelterStats();
        }

        if (isset($_GET['getEmergencyHotlines'])) {
            $emergencyHotlines = new EmergencyHotlines();
            $response = $emergencyHotlines->getEmergencyHotlines();
        }

        if (isset($_GET['getBarangayHazardZones'])) {
            $hazardZones = new BarangayHazardZones();
            $response = $hazardZones->getAllHazardZones();
        }

        if (isset($_GET['getEvacuees'])) {
            $db = new Db();
            $rows = $db->query("SELECT e.evacuee_id, e.shelter_id, s.shelter_name, e.full_name, e.age, e.gender, e.date_arrived, e.date_left FROM evacuees e JOIN shelters s ON s.shelter_id = e.shelter_id ORDER BY e.date_arrived DESC");
            $response = ['status'=>'success','message'=>'','data'=>$rows];
        }

        if (isset($_GET['getShelterImages'])) {
            $db = new Db();
            $rows = $db->query("SELECT si.image_id, si.shelter_id, s.shelter_name, si.image_path, si.uploaded_at FROM shelter_images si JOIN shelters s ON s.shelter_id = si.shelter_id ORDER BY si.uploaded_at DESC");
            $response = ['status'=>'success','message'=>'','data'=>$rows];
        }

        if (isset($_GET['getImportLogs'])) {
            $db = new Db();
            $rows = $db->query("SELECT import_id, admin_id, file_name, total_imported, status, created_at FROM import_logs ORDER BY created_at DESC");
            $response = ['status'=>'success','message'=>'','data'=>$rows];
        }

        if (isset($_GET['getAuditLogs'])) {
            $db = new Db();
            $rows = $db->query("SELECT log_id, user_id, action, target_table, target_id, details, created_at FROM audit_logs ORDER BY created_at DESC");
            $response = ['status'=>'success','message'=>'','data'=>$rows];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if (isset($_POST['addShelter'])) {
            $shelters = new Shelters();
            $response = $shelters->addShelter($_POST);
        }

        if (isset($_POST['updateShelter'])) {
            $shelters = new Shelters();
            $response = $shelters->updateShelter($_POST['id'], $_POST);
        }

        if (isset($_POST['deleteShelter'])) {
            $shelters = new Shelters();
            $response = $shelters->deleteShelter($_POST['id']);
        }

        if (isset($_POST['addDisaster'])) {
            $disasters = new Disasters();
            $response = $disasters->addDisaster($_POST);
        }

        if (isset($_POST['updateDisaster'])) {
            $disasters = new Disasters();
            $response = $disasters->updateDisaster($_POST['id'], $_POST);
        }

        if (isset($_POST['deleteDisaster'])) {
            $disasters = new Disasters();
            $response = $disasters->deleteDisaster($_POST['id']);
        }

        if (isset($_POST['addHotline'])) {
            $hotlines = new EmergencyHotlines();
            $response = $hotlines->addHotline($_POST);
        }

        if (isset($_POST['updateHotline'])) {
            $hotlines = new EmergencyHotlines();
            $response = $hotlines->updateHotline($_POST['id'], $_POST);
        }

        if (isset($_POST['deleteHotline'])) {
            $hotlines = new EmergencyHotlines();
            $response = $hotlines->deleteHotline($_POST['id']);
        }

        if (isset($_POST['addHazardZone'])) {
            $hazardZones = new BarangayHazardZones();
            $response = $hazardZones->addHazardZone($_POST);
        }

        if (isset($_POST['updateHazardZone'])) {
            $hazardZones = new BarangayHazardZones();
            $response = $hazardZones->updateHazardZone($_POST['id'], $_POST);
        }

        if (isset($_POST['deleteHazardZone'])) {
            $hazardZones = new BarangayHazardZones();
            $response = $hazardZones->deleteHazardZone($_POST['id']);
        }
    }

        if (!isset($response['status']) || empty($response['status'])) {
            $response = ['status' => 'error', 'message' => 'Invalid request', 'data' => null];
        }
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'API Error: ' . $e->getMessage(),
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    } catch (Error $e) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Fatal Error: ' . $e->getMessage(),
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
    exit();
?>