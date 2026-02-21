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

    public function getAllHazardZones()
    {
        $sql = "SELECT hazard_zone_id, barangay, latitude, longitude, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, hazard_level, description, is_active FROM barangay_hazard_zones WHERE is_active = 1 ORDER BY barangay";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return response('success', 'Barangay hazard zones fetched successfully.', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getHazardZonesByBarangay($barangay)
    {
        $sql = "SELECT * FROM barangay_hazard_zones WHERE barangay = ? AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$barangay]);
        return response('success', 'Hazard zone found.', $stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function addHazardZone($data)
    {
        $sql = "INSERT INTO barangay_hazard_zones (barangay, latitude, longitude, typhoon_zone, flood_zone, landslide_zone, liquefaction_zone, storm_surge_zone, hazard_level, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':barangay', $data['barangay'] ?? '');
        $stmt->bindParam(':latitude', $data['latitude'] ?? 0);
        $stmt->bindParam(':longitude', $data['longitude'] ?? 0);
        $stmt->bindParam(':typhoon_zone', $data['typhoon_zone'] ?? 'No');
        $stmt->bindParam(':flood_zone', $data['flood_zone'] ?? 'No');
        $stmt->bindParam(':landslide_zone', $data['landslide_zone'] ?? 'No');
        $stmt->bindParam(':liquefaction_zone', $data['liquefaction_zone'] ?? 'No');
        $stmt->bindParam(':storm_surge_zone', $data['storm_surge_zone'] ?? 'No');
        $stmt->bindParam(':hazard_level', $data['hazard_level'] ?? 'Low');
        $stmt->bindParam(':description', $data['description'] ?? null);
        $stmt->bindParam(':is_active', $data['is_active'] ?? 1);
        $stmt->execute();
        return response('success', 'Hazard zone added successfully.', null);
    }

    public function updateHazardZone($id, $data)
    {
        $sql = "UPDATE barangay_hazard_zones SET barangay = ?, latitude = ?, longitude = ?, typhoon_zone = ?, flood_zone = ?, landslide_zone = ?, liquefaction_zone = ?, storm_surge_zone = ?, hazard_level = ?, description = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE hazard_zone_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':barangay', $data['barangay'] ?? '');
        $stmt->bindParam(':latitude', $data['latitude'] ?? 0);
        $stmt->bindParam(':longitude', $data['longitude'] ?? 0);
        $stmt->bindParam(':typhoon_zone', $data['typhoon_zone'] ?? 'No');
        $stmt->bindParam(':flood_zone', $data['flood_zone'] ?? 'No');
        $stmt->bindParam(':landslide_zone', $data['landslide_zone'] ?? 'No');
        $stmt->bindParam(':liquefaction_zone', $data['liquefaction_zone'] ?? 'No');
        $stmt->bindParam(':storm_surge_zone', $data['storm_surge_zone'] ?? 'No');
        $stmt->bindParam(':hazard_level', $data['hazard_level'] ?? 'Low');
        $stmt->bindParam(':description', $data['description'] ?? null);
        $stmt->bindParam(':is_active', $data['is_active'] ?? 1);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return response('success', 'Hazard zone updated successfully.', null);
    }

    public function deleteHazardZone($id)
    {
        $sql = "UPDATE barangay_hazard_zones SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE hazard_zone_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return response('success', 'Hazard zone deleted successfully.', null);
    }
}