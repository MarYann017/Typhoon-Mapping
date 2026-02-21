<?php
require_once 'Db.php';
require_once 'Helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class ShelterStats
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getShelterStats()
    {
        $sql = "SELECT COUNT(*) as total_shelters, SUM(CASE WHEN shelter_status = 'Available' THEN 1 ELSE 0 END) as available_shelters, SUM(capacity) as total_capacity, SUM(current_occupancy) as current_occupancy, SUM(CASE WHEN shelter_status = 'Full' THEN 1 ELSE 0 END) as full_shelters, SUM(CASE WHEN shelter_status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance_shelters, SUM(CASE WHEN shelter_status = 'Closed' THEN 1 ELSE 0 END) as closed_shelters FROM shelters WHERE is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return response('success', 'Shelter stats fetched successfully.', $stmt->fetch(PDO::FETCH_ASSOC));
    }
}