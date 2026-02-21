<?php
require_once 'Db.php';
require_once 'Helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class BarangayHazardZones
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getBarangayHazardZones()
    {
        $sql = "SELECT * FROM barangay_hazard_zones WHERE is_active = 1 ORDER BY barangay ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return response('success', 'Barangay hazard zones fetched successfully.', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function addBarangayHazardZone($data)
    {
        $sql = "INSERT INTO barangay_hazard_zones (barangay, latitude, longitude, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, hazard_level, description) VALUES (:barangay, :latitude, :longitude, :typhoon_zone, :flood_zone, :landslide_zone, :liquefaction_zone, :storm_surge_zone, :hazard_level, :description)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':barangay', $data['barangay']);
        $stmt->bindParam(':latitude', $data['latitude']);
        $stmt->bindParam(':longitude', $data['longitude']);
        $stmt->bindParam(':typhoon_zone', $data['typhoon_zone']);
        $stmt->bindParam(':flood_zone', $data['flood_zone']);
        $stmt->bindParam(':landslide_zone', $data['landslide_zone']);
        $stmt->bindParam(':liquefaction_zone', $data['liquefaction_zone']);
        $stmt->bindParam(':storm_surge_zone', $data['storm_surge_zone']);
        $stmt->bindParam(':hazard_level', $data['hazard_level']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->execute();
        return response('success', 'Barangay hazard zone added successfully.', ['id' => $this->db->lastInsertId()]);
    }

    public function updateBarangayHazardZone($id, $data)
    {
        $sql = "UPDATE barangay_hazard_zones SET barangay = :barangay, latitude = :latitude, longitude = :longitude, typhoon_zone = :typhoon_zone, flood_zone = :flood_zone, landslide_zone = :landslide_zone, liquefaction_zone = :liquefaction_zone, storm_surge_zone = :storm_surge_zone, hazard_level = :hazard_level, description = :description WHERE hazard_zone_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':barangay', $data['barangay']);
        $stmt->bindParam(':latitude', $data['latitude']);
        $stmt->bindParam(':longitude', $data['longitude']);
        $stmt->bindParam(':typhoon_zone', $data['typhoon_zone']);
        $stmt->bindParam(':flood_zone', $data['flood_zone']);
        $stmt->bindParam(':landslide_zone', $data['landslide_zone']);
        $stmt->bindParam(':liquefaction_zone', $data['liquefaction_zone']);
        $stmt->bindParam(':storm_surge_zone', $data['storm_surge_zone']);
        $stmt->bindParam(':hazard_level', $data['hazard_level']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return response('success', 'Barangay hazard zone updated successfully.', null);
    }

    public function deleteBarangayHazardZone($id)
    {
        $sql = "UPDATE barangay_hazard_zones SET is_active = 0 WHERE hazard_zone_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return response('success', 'Barangay hazard zone deleted successfully.', null);
    }
}

