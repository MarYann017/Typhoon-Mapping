<?php
    error_reporting(E_ALL);
    ini_set('display_errors', 0);

    ob_start();

    require_once 'Db.php';
    require_once 'Accounts.php';
    require_once 'Helper.php';
    require_once 'Shelters.php';
    require_once 'Location.php';
    require_once 'Disasters.php';
    require_once 'ShelterStats.php';
    require_once 'EmergencyHotlines.php';
    require_once 'BarangayHazardZones.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json');

    $response = [
        'status' => 'error',
        'message' => 'No action specified',
        'data' => null,
    ];

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

        if (isset($_GET['getDisasterDetails'])) {
            $disasters = new Disasters();
            $response = $disasters->getDisasterDetails($_GET['disaster_id']);
        }

        if (isset($_GET['getShelterStats'])) {
            $shelterStats = new ShelterStats();
            $response = $shelterStats->getShelterStats();
        }

        if (isset($_GET['getEmergencyHotlines'])) {
            $emergencyHotlines = new EmergencyHotlines();
            $response = $emergencyHotlines->getEmergencyHotlines();
        }

        if (isset($_GET['getUsers'])) {
            $accounts = new Accounts();
            $response = $accounts->getUsers();
        }

        if (isset($_GET['getBarangayHazardZones'])) {
            $barangayHazardZones = new BarangayHazardZones();
            $response = $barangayHazardZones->getBarangayHazardZones();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['login'])) {
            $accounts = new Accounts();
            $response = $accounts->login();
        }

        if (isset($_POST['addShelter'])) {
            $shelters = new Shelters();
            $response = $shelters->addShelter($_POST, $_FILES);
        }

        if (isset($_POST['updateShelter'])) {
            $shelters = new Shelters();
            $response = $shelters->updateShelter($_POST['id'], $_POST, $_FILES);
        }

        if (isset($_POST['deleteShelterImage'])) {
            $shelters = new Shelters();
            $response = $shelters->deleteShelterImage($_POST['image_id']);
        }

        if (isset($_POST['bulkUploadShelters'])) {
            $shelters = new Shelters();
            $response = $shelters->bulkUploadShelters($_FILES['excel_file']);
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

        if (isset($_POST['addBarangayHazardZone'])) {
            $barangayHazardZones = new BarangayHazardZones();
            $response = $barangayHazardZones->addBarangayHazardZone($_POST);
        }

        if (isset($_POST['updateBarangayHazardZone'])) {
            $barangayHazardZones = new BarangayHazardZones();
            $response = $barangayHazardZones->updateBarangayHazardZone($_POST['id'], $_POST);
        }

        if (isset($_POST['deleteBarangayHazardZone'])) {
            $barangayHazardZones = new BarangayHazardZones();
            $response = $barangayHazardZones->deleteBarangayHazardZone($_POST['id']);
        }

        if (isset($_POST['addUser'])) {
            $accounts = new Accounts();
            $response = $accounts->addUser($_POST);
        }

        if (isset($_POST['updateUser'])) {
            $accounts = new Accounts();
            $response = $accounts->updateUser($_POST['id'], $_POST);
        }

        if (isset($_POST['deleteUser'])) {
            $accounts = new Accounts();
            $response = $accounts->deleteUser($_POST['id']);
        }

        if (isset($_POST['assignSheltersToDisaster'])) {
            try {
                $disasters = new Disasters();
                $shelterIds = [];
                if (isset($_POST['shelter_ids']) && !empty($_POST['shelter_ids'])) {
                    $decoded = json_decode($_POST['shelter_ids'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $shelterIds = $decoded;
                    }
                }
                if (!isset($_POST['disaster_id']) || empty($_POST['disaster_id'])) {
                    $response = response('error', 'Disaster ID is required.', null);
                } else {
                    $response = $disasters->assignSheltersToDisaster($_POST['disaster_id'], $shelterIds);
                }
            } catch (Exception $e) {
                $response = response('error', 'Error: ' . $e->getMessage(), null);
            }
        }

        if (isset($_POST['updateShelterOccupancy'])) {
            try {
                $disasters = new Disasters();
                $shelterId = isset($_POST['shelter_id']) ? intval($_POST['shelter_id']) : 0;
                $disasterId = isset($_POST['disaster_id']) ? intval($_POST['disaster_id']) : 0;
                $currentOccupancy = isset($_POST['current_occupancy']) ? intval($_POST['current_occupancy']) : 0;
                $evacuees = isset($_POST['evacuees']) ? intval($_POST['evacuees']) : 0;
                
                if (!$shelterId || !$disasterId) {
                    $response = response('error', 'Shelter ID and Disaster ID are required.', null);
                } else {
                    $response = $disasters->updateShelterOccupancy($shelterId, $disasterId, $currentOccupancy, $evacuees);
                }
            } catch (Exception $e) {
                $response = response('error', 'Error: ' . $e->getMessage(), null);
            }
        }
    }

    ob_clean();
    echo json_encode($response);
    exit();
?>
