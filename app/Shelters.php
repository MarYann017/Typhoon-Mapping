<?php
require_once 'Db.php';
require_once 'Helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Shelters
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getAllShelters()
    {
        $sql = "SELECT s.shelter_id, s.shelter_name, s.barangay, s.owner_name, s.full_address, s.description, s.contact_person, s.contact_number, s.contact_email, s.shelter_type, s.shelter_status, s.capacity, s.current_occupancy, s.is_full, s.typhoon_zone, s.flood_zone, s.landslide_zone, s.liquefaction_zone, s.storm_surge_zone, s.elevation, s.latitude, s.longitude, s.building_material_type, s.building_condition, s.water_supply, s.electricity, s.road_condition, s.estimated_travel_time, s.near_main_road, s.is_safe_shelter, s.is_active, d.name as disaster_name, d.type as disaster_type, d.severity as disaster_severity FROM shelters s LEFT JOIN disasters d ON s.current_disaster_id = d.disaster_id ORDER BY s.shelter_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $shelters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($shelters as &$shelter) {
            $imageSql = "SELECT image_id, image_path FROM shelter_images WHERE shelter_id = ? ORDER BY uploaded_at ASC";
            $imageStmt = $this->db->prepare($imageSql);
            $imageStmt->execute([$shelter['shelter_id']]);
            $shelter['shelter_images'] = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return response('success', 'Shelters fetched successfully.', $shelters);
    }

    public function addShelter($data, $files = [])
    {
        $sql = "INSERT INTO shelters (shelter_name, barangay, owner_name, full_address, description, contact_person, contact_number, contact_email, shelter_type, shelter_status, capacity, current_occupancy, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, elevation, latitude, longitude, building_material_type, building_condition, water_supply, electricity, road_condition, estimated_travel_time, near_main_road, is_safe_shelter, is_active) VALUES (:shelter_name, :barangay, :owner_name, :full_address, :description, :contact_person, :contact_number, :contact_email, :shelter_type, :shelter_status, :capacity, :current_occupancy, :typhoon_zone, :flood_zone, :landslide_zone, :liquefaction_zone, :storm_surge_zone, :elevation, :latitude, :longitude, :building_material_type, :building_condition, :water_supply, :electricity, :road_condition, :estimated_travel_time, :near_main_road, :is_safe_shelter, :is_active)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':shelter_name', $data['shelter_name']);
        $stmt->bindParam(':barangay', $data['barangay']);
        $stmt->bindParam(':owner_name', $data['owner_name']);
        $stmt->bindParam(':full_address', $data['full_address']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':contact_person', $data['contact_person']);
        $stmt->bindParam(':contact_number', $data['contact_number']);
        $stmt->bindParam(':contact_email', $data['contact_email']);
        $stmt->bindParam(':shelter_type', $data['shelter_type']);
        $stmt->bindParam(':shelter_status', $data['shelter_status']);
        $stmt->bindParam(':capacity', $data['capacity']);
        $stmt->bindParam(':current_occupancy', $data['current_occupancy']);
        $stmt->bindParam(':typhoon_zone', $data['typhoon_zone']);
        $stmt->bindParam(':flood_zone', $data['flood_zone']);
        $stmt->bindParam(':landslide_zone', $data['landslide_zone']);
        $stmt->bindParam(':liquefaction_zone', $data['liquefaction_zone']);
        $stmt->bindParam(':storm_surge_zone', $data['storm_surge_zone']);
        $stmt->bindParam(':elevation', $data['elevation']);
        $stmt->bindParam(':latitude', $data['latitude']);
        $stmt->bindParam(':longitude', $data['longitude']);
        $stmt->bindParam(':building_material_type', $data['building_material_type']);
        $stmt->bindParam(':building_condition', $data['building_condition']);
        $stmt->bindParam(':water_supply', $data['water_supply']);
        $stmt->bindParam(':electricity', $data['electricity']);
        $stmt->bindParam(':road_condition', $data['road_condition']);
        $stmt->bindParam(':estimated_travel_time', $data['estimated_travel_time']);
        $stmt->bindParam(':near_main_road', $data['near_main_road']);
        $stmt->bindParam(':is_safe_shelter', $data['is_safe_shelter']);
        $isActive = isset($data['is_active']) ? $data['is_active'] : 1;
        $stmt->bindParam(':is_active', $isActive);
        $stmt->execute();
        
        $shelterId = $this->db->lastInsertId();

        if(!empty($_FILES['shelter_images']['name']) && isset($_FILES['shelter_images']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../assets/img/shelters/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['shelter_images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['shelter_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = $shelterId . '_' . time() . '_' . $key . '_' . basename($_FILES['shelter_images']['name'][$key]);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $imagePath = 'assets/img/shelters/' . $fileName;
                        $imageSql = "INSERT INTO shelter_images (shelter_id, image_path) VALUES (?, ?)";
                        $imageStmt = $this->db->prepare($imageSql);
                        $imageStmt->execute([$shelterId, $imagePath]);
                    }
                }
            }
        }
        
        return response('success', 'Shelter added successfully.', ['id' => $shelterId]);
    }

    public function updateShelter($id, $data, $files = [])
    {
        $sql = "UPDATE shelters SET shelter_name = :shelter_name, barangay = :barangay, owner_name = :owner_name, full_address = :full_address, description = :description, contact_person = :contact_person, contact_number = :contact_number, contact_email = :contact_email, shelter_type = :shelter_type, shelter_status = :shelter_status, capacity = :capacity, current_occupancy = :current_occupancy, typhoon_zone = :typhoon_zone, flood_zone = :flood_zone, landslide_zone = :landslide_zone, liquefaction_zone = :liquefaction_zone, storm_surge_zone = :storm_surge_zone, elevation = :elevation, latitude = :latitude, longitude = :longitude, building_material_type = :building_material_type, building_condition = :building_condition, water_supply = :water_supply, electricity = :electricity, road_condition = :road_condition, estimated_travel_time = :estimated_travel_time, near_main_road = :near_main_road, is_safe_shelter = :is_safe_shelter, is_active = :is_active WHERE shelter_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':shelter_name', $data['shelter_name']);
        $stmt->bindParam(':barangay', $data['barangay']);
        $stmt->bindParam(':owner_name', $data['owner_name']);
        $stmt->bindParam(':full_address', $data['full_address']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':contact_person', $data['contact_person']);
        $stmt->bindParam(':contact_number', $data['contact_number']);
        $stmt->bindParam(':contact_email', $data['contact_email']);
        $stmt->bindParam(':shelter_type', $data['shelter_type']);
        $stmt->bindParam(':shelter_status', $data['shelter_status']);
        $stmt->bindParam(':capacity', $data['capacity']);
        $stmt->bindParam(':current_occupancy', $data['current_occupancy']);
        $stmt->bindParam(':typhoon_zone', $data['typhoon_zone']);
        $stmt->bindParam(':flood_zone', $data['flood_zone']);
        $stmt->bindParam(':landslide_zone', $data['landslide_zone']);
        $stmt->bindParam(':liquefaction_zone', $data['liquefaction_zone']);
        $stmt->bindParam(':storm_surge_zone', $data['storm_surge_zone']);
        $stmt->bindParam(':elevation', $data['elevation']);
        $stmt->bindParam(':latitude', $data['latitude']);
        $stmt->bindParam(':longitude', $data['longitude']);
        $stmt->bindParam(':building_material_type', $data['building_material_type']);
        $stmt->bindParam(':building_condition', $data['building_condition']);
        $stmt->bindParam(':water_supply', $data['water_supply']);
        $stmt->bindParam(':electricity', $data['electricity']);
        $stmt->bindParam(':road_condition', $data['road_condition']);
        $stmt->bindParam(':estimated_travel_time', $data['estimated_travel_time']);
        $stmt->bindParam(':near_main_road', $data['near_main_road']);
        $stmt->bindParam(':is_safe_shelter', $data['is_safe_shelter']);
        $stmt->bindParam(':is_active', $data['is_active']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if (!empty($_FILES['shelter_images']['name']) && isset($_FILES['shelter_images']['tmp_name'])) {
            $uploadDir = __DIR__ . '/../assets/img/shelters/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['shelter_images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['shelter_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = $id . '_' . time() . '_' . $key . '_' . basename($_FILES['shelter_images']['name'][$key]);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $imagePath = 'assets/img/shelters/' . $fileName;
                        $imageSql = "INSERT INTO shelter_images (shelter_id, image_path) VALUES (?, ?)";
                        $imageStmt = $this->db->prepare($imageSql);
                        $imageStmt->execute([$id, $imagePath]);
                    }
                }
            }
        }
        
        return response('success', 'Shelter updated successfully.', null);
    }

    public function deleteShelter($id)
    {
        $sql = "UPDATE shelters SET is_active = 0 WHERE shelter_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return response('success', 'Shelter deleted successfully.', null);
    }

    public function deleteShelterImage($imageId)
    {
        $selectSql = "SELECT image_path FROM shelter_images WHERE image_id = ?";
        $selectStmt = $this->db->prepare($selectSql);
        $selectStmt->execute([$imageId]);
        $image = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            $filePath = __DIR__ . '/../' . $image['image_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        $deleteSql = "DELETE FROM shelter_images WHERE image_id = ?";
        $deleteStmt = $this->db->prepare($deleteSql);
        $deleteStmt->execute([$imageId]);
        
        return response('success', 'Image deleted successfully.', null);
    }

    public function bulkUploadShelters($file)
    {
        try {
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $rows = [];
            
            if ($fileExtension === 'csv') {
                // Handle CSV file
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle === false) {
                    throw new Exception("Failed to open CSV file");
                }
                
                // Read CSV rows
                while (($row = fgetcsv($handle)) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            } else {
                // Handle Excel file (.xlsx, .xls)
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
            }
            
            // Remove header row
            array_shift($rows);
            
            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Detect format based on first non-empty row column count
            $isCsvFormat = false;
            foreach ($rows as $row) {
                $colCount = count(array_filter($row, function($val) { return $val !== '' && $val !== null; }));
                // CSV format has 25 columns (0-24), Excel format has 19 columns (0-18)
                // Check if row has enough columns to be CSV format (Shelter_ID + 24 other columns)
                if ($colCount > 0 && count($row) >= 20) {
                    $isCsvFormat = true;
                    break;
                } else if ($colCount > 0) {
                    // If we find a valid row with fewer columns, it's Excel format
                    break;
                }
            }
            
            foreach ($rows as $rowIndex => $row) {
                // Skip empty rows - for CSV check row[1] (Barangay), for Excel check row[0] (Barangay)
                $barangayIndex = $isCsvFormat ? 1 : 0;
                if (empty($row[$barangayIndex])) {
                    continue;
                }
                
                try {
                    $data = [];
                    
                    if ($isCsvFormat) {
                        // CSV Format: Shelter_ID, Barangay, Owner_Name, Description, Contact_Number, 
                        // Shelter_Type, Shelter_Status, Capacity, Current_Occupancy, Typhoon_Zone, Flood_Zone, 
                        // Landslide_Zone, Storm_Surge_Zone, Liquefaction_Zone, Elevation, 
                        // Latitude, Longitude, Building_Material_Type, Building_Condition, 
                        // Water_Supply, Electricity, Road_Condition, Estimated_Travel_time, 
                        // Near_Main_Road, Is_Safe_Shelter
                        $data['barangay'] = trim($row[1] ?? '');
                        $data['owner_name'] = trim($row[2] ?? '');
                        $data['description'] = trim($row[3] ?? '');
                        $data['contact_number'] = trim($row[4] ?? '');
                        $data['shelter_type'] = trim($row[5] ?? 'Other');
                        $data['shelter_status'] = trim($row[6] ?? 'Available');
                        
                        $capacityRaw = trim($row[7] ?? '0');
                        $capacityRaw = preg_replace('/[^0-9]/', '', $capacityRaw);
                        $data['capacity'] = (int)$capacityRaw;
                        
                        // Current_Occupancy is at index 8, but we'll set it to 0 or use the value if provided
                        $currentOccupancyRaw = trim($row[8] ?? '0');
                        $currentOccupancyRaw = preg_replace('/[^0-9]/', '', $currentOccupancyRaw);
                        $data['current_occupancy'] = (int)$currentOccupancyRaw;
                        
                        $data['typhoon_zone'] = isset($row[9]) && strtolower(trim($row[9])) === 'yes' ? 'Yes' : 'No';
                        $data['flood_zone'] = isset($row[10]) && strtolower(trim($row[10])) === 'yes' ? 'Yes' : 'No';
                        $data['landslide_zone'] = isset($row[11]) && strtolower(trim($row[11])) === 'yes' ? 'Yes' : 'No';
                        $data['storm_surge_zone'] = isset($row[12]) && strtolower(trim($row[12])) === 'yes' ? 'Yes' : 'No';
                        $data['liquefaction_zone'] = isset($row[13]) && strtolower(trim($row[13])) === 'yes' ? 'Yes' : 'No';
                        
                        $elevation = trim($row[14] ?? '0');
                        $elevation = str_replace(['m', ' '], '', $elevation);
                        $data['elevation'] = (float)$elevation;
                        
                        $data['latitude'] = (float)($row[15] ?? 0);
                        $longitudeRaw = trim($row[16] ?? '0');
                        if (preg_match('/(\d+\.\d+)/', $longitudeRaw, $matches)) {
                            $data['longitude'] = (float)$matches[1];
                        } else {
                            $data['longitude'] = (float)$longitudeRaw;
                        }
                        
                        $data['building_material_type'] = trim($row[17] ?? '');
                        $data['building_condition'] = trim($row[18] ?? '');
                        $data['water_supply'] = trim($row[19] ?? '');
                        $data['electricity'] = trim($row[20] ?? '');
                        $data['road_condition'] = trim($row[21] ?? '');
                        $data['estimated_travel_time'] = trim($row[22] ?? '');
                        $data['near_main_road'] = isset($row[23]) && strtolower(trim($row[23])) === 'yes' ? 'Yes' : 'No';
                        $data['is_safe_shelter'] = isset($row[24]) && ($row[24] == 1 || strtolower(trim($row[24])) === 'yes') ? '1' : '0';
                        
                        $data['full_address'] = '';
                        $data['contact_person'] = '';
                        $data['contact_email'] = '';
                    } else {
                        // Excel Format (old format)
                        $data['barangay'] = trim($row[0] ?? '');
                        $data['owner_name'] = trim($row[1] ?? '');
                        $capacityRaw = trim($row[2] ?? '0');
                        $capacityRaw = preg_replace('/[^0-9]/', '', $capacityRaw);
                        $data['capacity'] = (int)$capacityRaw;
                        
                        $data['typhoon_zone'] = isset($row[3]) && strtolower(trim($row[3])) === 'yes' ? 'Yes' : 'No';
                        $data['flood_zone'] = isset($row[4]) && strtolower(trim($row[4])) === 'yes' ? 'Yes' : 'No';
                        $data['landslide_zone'] = isset($row[5]) && strtolower(trim($row[5])) === 'yes' ? 'Yes' : 'No';
                        $data['storm_surge_zone'] = isset($row[6]) && strtolower(trim($row[6])) === 'yes' ? 'Yes' : 'No';
                        $data['liquefaction_zone'] = isset($row[7]) && strtolower(trim($row[7])) === 'yes' ? 'Yes' : 'No';
                        
                        $elevation = trim($row[8] ?? '0');
                        $elevation = str_replace(['m', ' '], '', $elevation);
                        $data['elevation'] = (float)$elevation;
                        
                        $data['latitude'] = (float)($row[9] ?? 0);
                        $longitudeRaw = trim($row[10] ?? '0');
                        if (preg_match('/(\d+\.\d+)/', $longitudeRaw, $matches)) {
                            $data['longitude'] = (float)$matches[1];
                        } else {
                            $data['longitude'] = (float)$longitudeRaw;
                        }
                        
                        $buildingMaterialRaw = trim($row[11] ?? '');
                        if (empty($buildingMaterialRaw) && isset($row[10])) {
                            $longitudeRaw = trim($row[10]);
                            if (preg_match('/([a-zA-Z]+)$/i', $longitudeRaw, $matches)) {
                                $buildingMaterialRaw = ucfirst(strtolower($matches[1]));
                            }
                        }
                        $data['building_material_type'] = $buildingMaterialRaw;
                        
                        $data['building_condition'] = trim($row[12] ?? '');
                        $data['water_supply'] = trim($row[13] ?? '');
                        $data['electricity'] = trim($row[14] ?? '');
                        $data['road_condition'] = trim($row[15] ?? '');
                        $data['estimated_travel_time'] = trim($row[16] ?? '');
                        $data['near_main_road'] = isset($row[17]) && strtolower(trim($row[17])) === 'yes' ? 'Yes' : 'No';
                        $data['is_safe_shelter'] = isset($row[18]) && ($row[18] == 1 || strtolower(trim($row[18])) === 'yes') ? '1' : '0';
                        
                        $data['shelter_type'] = 'Other';
                        $data['shelter_status'] = 'Available';
                        $data['full_address'] = '';
                        $data['description'] = '';
                        $data['contact_person'] = '';
                        $data['contact_number'] = '';
                        $data['contact_email'] = '';
                    }
                    
                    $data['shelter_name'] = trim($data['barangay'] . ' - ' . $data['owner_name']); // Use Barangay - Owner Name to make unique
                    // Only set current_occupancy to 0 if not already set from CSV
                    if (!isset($data['current_occupancy'])) {
                        $data['current_occupancy'] = 0;
                    }
                    
                    // Set defaults if empty
                    if (empty($data['shelter_type'])) $data['shelter_type'] = 'Other';
                    if (empty($data['shelter_status'])) $data['shelter_status'] = 'Available';
                    if (!isset($data['full_address'])) $data['full_address'] = '';
                    if (!isset($data['description'])) $data['description'] = '';
                    if (!isset($data['contact_person'])) $data['contact_person'] = '';
                    if (!isset($data['contact_number'])) $data['contact_number'] = '';
                    if (!isset($data['contact_email'])) $data['contact_email'] = '';
                    
                    if (empty($data['barangay']) || empty($data['owner_name']) || $data['capacity'] <= 0) {
                        throw new Exception("Row " . ($rowIndex + 2) . ": Missing required fields");
                    }
                    
                    $checkSql = "SELECT shelter_id FROM shelters WHERE shelter_name = ? AND is_active = 1";
                    $checkStmt = $this->db->prepare($checkSql);
                    $checkStmt->execute([$data['shelter_name']]);
                    $existingShelter = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingShelter) {
                        $updateSql = "UPDATE shelters SET barangay = ?, owner_name = ?, full_address = ?, description = ?, contact_person = ?, contact_number = ?, contact_email = ?, shelter_type = ?, shelter_status = ?, capacity = ?, current_occupancy = ?, typhoon_zone = ?, flood_zone = ?, landslide_zone = ?, liquefaction_zone = ?, storm_surge_zone = ?, elevation = ?, latitude = ?, longitude = ?, building_material_type = ?, building_condition = ?, water_supply = ?, electricity = ?, road_condition = ?, estimated_travel_time = ?, near_main_road = ?, is_safe_shelter = ? WHERE shelter_id = ?";
                        $updateStmt = $this->db->prepare($updateSql);
                        $updateStmt->execute([
                            $data['barangay'], $data['owner_name'], $data['full_address'], 
                            $data['description'], $data['contact_person'], $data['contact_number'], $data['contact_email'],
                            $data['shelter_type'], $data['shelter_status'], $data['capacity'], $data['current_occupancy'],
                            $data['typhoon_zone'], $data['flood_zone'], $data['landslide_zone'], $data['liquefaction_zone'],
                            $data['storm_surge_zone'], $data['elevation'], $data['latitude'], $data['longitude'],
                            $data['building_material_type'], $data['building_condition'], $data['water_supply'], $data['electricity'],
                            $data['road_condition'], $data['estimated_travel_time'], $data['near_main_road'], $data['is_safe_shelter'],
                            $existingShelter['shelter_id']
                        ]);
                    } else {
                        $sql = "INSERT INTO shelters (shelter_name, barangay, owner_name, full_address, description, contact_person, contact_number, contact_email, shelter_type, shelter_status, capacity, current_occupancy, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, elevation, latitude, longitude, building_material_type, building_condition, water_supply, electricity, road_condition, estimated_travel_time, near_main_road, is_safe_shelter) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            $data['shelter_name'], $data['barangay'], $data['owner_name'], $data['full_address'], 
                            $data['description'], $data['contact_person'], $data['contact_number'], $data['contact_email'],
                            $data['shelter_type'], $data['shelter_status'], $data['capacity'], $data['current_occupancy'],
                            $data['typhoon_zone'], $data['flood_zone'], $data['landslide_zone'], $data['liquefaction_zone'],
                            $data['storm_surge_zone'], $data['elevation'], $data['latitude'], $data['longitude'],
                            $data['building_material_type'], $data['building_condition'], $data['water_supply'], $data['electricity'],
                            $data['road_condition'], $data['estimated_travel_time'], $data['near_main_road'], $data['is_safe_shelter']
                        ]);
                    }
                    
                    $successCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }
            
            $message = "Bulk upload completed. Success: {$successCount}, Errors: {$errorCount}";
            
            return response(
                $errorCount > 0 ? 'warning' : 'success',
                $message,
                ['success_count' => $successCount, 'error_count' => $errorCount, 'errors' => $errors]
            );
            
        } catch (Exception $e) {
            return response('error', 'Bulk upload failed: ' . $e->getMessage(), null);
        }
    }
}