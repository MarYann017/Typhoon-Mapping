<?php
require_once 'Db.php';
require_once 'Helper.php';

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
        try {
            if (!$this->db->isConnected()) {
                throw new Exception('Database not connected');
            }
            
            $sql = "SELECT s.shelter_id, s.shelter_name, s.barangay, s.owner_name, s.full_address, s.description, s.contact_person, s.contact_number, s.contact_email, s.shelter_type, s.shelter_status, s.capacity, s.current_occupancy, s.is_full, s.typhoon_zone, s.flood_zone, s.landslide_zone, s.liquefaction_zone, s.storm_surge_zone, s.elevation, s.latitude, s.longitude, s.building_material_type, s.building_condition, s.water_supply, s.electricity, s.road_condition, s.estimated_travel_time, s.near_main_road, s.is_safe_shelter, s.is_active, d.name as disaster_name, d.type as disaster_type, d.severity as disaster_severity FROM shelters s LEFT JOIN disasters d ON s.current_disaster_id = d.disaster_id WHERE s.is_active = 1 ORDER BY s.shelter_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $shelters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($shelters as &$shelter) {
                try {
                    if ($this->db->isConnected()) {
                        $imageSql = "SELECT image_path FROM shelter_images WHERE shelter_id = ? ORDER BY uploaded_at ASC";
                        $imageStmt = $this->db->prepare($imageSql);
                        $imageStmt->execute([$shelter['shelter_id']]);
                        $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Convert image paths to full subdomain URLs
                        foreach ($images as &$image) {
                            $image['image_path'] = getImageUrl($image['image_path']);
                        }
                        $shelter['shelter_images'] = $images;
                    } else {
                        $shelter['shelter_images'] = [];
                    }
                } catch (Exception $e) {
                    $shelter['shelter_images'] = [];
                }
            }
            
            // Sync to JSON file while online
            try {
                // Prepare data for JSON (remove image URLs for offline storage)
                $sheltersForJson = [];
                foreach ($shelters as $shelter) {
                    $shelterData = $shelter;
                    // Remove image URLs from JSON storage (they're dynamic)
                    unset($shelterData['shelter_images']);
                    $sheltersForJson[] = $shelterData;
                }
                writeJsonFile('shelters.json', $sheltersForJson);
            } catch (Exception $e) {
                // Silently fail if JSON write fails
            }
            
            return response('success', 'Shelters fetched successfully.', $shelters);
        } catch (Exception $e) {
            // Fallback to JSON file
            $shelters = readJsonFile('shelters.json');
            foreach ($shelters as &$shelter) {
                $shelter['shelter_images'] = [];
            }
            return response('success', 'Shelters fetched from offline cache.', $shelters);
        }
    }

    public function addShelter($data)
    {
        $sql = "INSERT INTO shelters (shelter_name, barangay, owner_name, shelter_type, shelter_status, capacity, current_occupancy, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, elevation, latitude, longitude, building_material_type, building_condition, water_supply, electricity, road_condition, estimated_travel_time, near_main_road, is_safe_shelter) VALUES (:shelter_name, :barangay, :owner_name, :shelter_type, :shelter_status, :capacity, :current_occupancy, :typhoon_zone, :flood_zone, :landslide_zone, :liquefaction_zone, :storm_surge_zone, :elevation, :latitude, :longitude, :building_material_type, :building_condition, :water_supply, :electricity, :road_condition, :estimated_travel_time, :near_main_road, :is_safe_shelter)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':shelter_name', $data['shelter_name']);
        $stmt->bindParam(':barangay', $data['barangay']);
        $stmt->bindParam(':owner_name', $data['owner_name']);
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
        $stmt->execute();
        
        // Sync to JSON file after adding
        try {
            $this->syncSheltersToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Shelter added successfully.', ['id' => $this->db->lastInsertId()]);
    }

    public function updateShelter($id, $data)
    {
        $sql = "UPDATE shelters SET shelter_name = :shelter_name, barangay = :barangay, owner_name = :owner_name, shelter_type = :shelter_type, shelter_status = :shelter_status, capacity = :capacity, current_occupancy = :current_occupancy WHERE shelter_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':shelter_name', $data['shelter_name']);
        $stmt->bindParam(':barangay', $data['barangay']);
        $stmt->bindParam(':owner_name', $data['owner_name']);
        $stmt->bindParam(':shelter_type', $data['shelter_type']);
        $stmt->bindParam(':shelter_status', $data['shelter_status']);
        $stmt->bindParam(':capacity', $data['capacity']);
        $stmt->bindParam(':current_occupancy', $data['current_occupancy']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // Sync to JSON file after updating
        try {
            $this->syncSheltersToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Shelter updated successfully.', null);
    }

    public function deleteShelter($id)
    {
        $sql = "UPDATE shelters SET is_active = 0 WHERE shelter_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        // Sync to JSON file after deleting
        try {
            $this->syncSheltersToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Shelter deleted successfully.', null);
    }
    
    private function syncSheltersToJson()
    {
        if (!$this->db->isConnected()) {
            return;
        }
        
        $sql = "SELECT s.shelter_id, s.shelter_name, s.barangay, s.owner_name, s.full_address, s.description, s.contact_person, s.contact_number, s.contact_email, s.shelter_type, s.shelter_status, s.capacity, s.current_occupancy, s.is_full, s.typhoon_zone, s.flood_zone, s.landslide_zone, s.liquefaction_zone, s.storm_surge_zone, s.elevation, s.latitude, s.longitude, s.building_material_type, s.building_condition, s.water_supply, s.electricity, s.road_condition, s.estimated_travel_time, s.near_main_road, s.is_safe_shelter, s.is_active, d.name as disaster_name, d.type as disaster_type, d.severity as disaster_severity FROM shelters s LEFT JOIN disasters d ON s.current_disaster_id = d.disaster_id WHERE s.is_active = 1 ORDER BY s.shelter_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $shelters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        writeJsonFile('shelters.json', $shelters);
    }
}