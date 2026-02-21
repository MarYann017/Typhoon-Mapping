<?php
require_once 'Db.php';
require_once 'Helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class EmergencyHotlines
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getEmergencyHotlines()
    {
        try {
            if (!$this->db->isConnected()) {
                throw new Exception('Database not connected');
            }
            
            $sql = "SELECT * FROM emergency_hotlines WHERE is_active = 1 ORDER BY priority_order ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $hotlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sync to JSON file while online
            try {
                // Get all hotlines (including inactive) for complete sync
                $allHotlinesSql = "SELECT * FROM emergency_hotlines ORDER BY priority_order ASC";
                $allHotlinesStmt = $this->db->prepare($allHotlinesSql);
                $allHotlinesStmt->execute();
                $allHotlines = $allHotlinesStmt->fetchAll(PDO::FETCH_ASSOC);
                writeJsonFile('hotlines.json', $allHotlines);
            } catch (Exception $e) {
                // Silently fail if JSON write fails
            }
            
            return response('success', 'Emergency hotlines fetched successfully.', $hotlines);
        } catch (Exception $e) {
            // Fallback to JSON file
            $hotlines = readJsonFile('hotlines.json');
            // Filter only active hotlines
            $hotlines = array_filter($hotlines, function($hotline) {
                return isset($hotline['is_active']) && $hotline['is_active'] == 1;
            });
            return response('success', 'Emergency hotlines fetched from offline cache.', array_values($hotlines));
        }
    }

    public function addHotline($data)
    {
        $sql = "INSERT INTO emergency_hotlines (agency_name, agency_code, phone_number, description, priority_order) VALUES (:agency_name, :agency_code, :phone_number, :description, :priority_order)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':agency_name', $data['agency_name']);
        $stmt->bindParam(':agency_code', $data['agency_code']);
        $stmt->bindParam(':phone_number', $data['phone_number']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':priority_order', $data['priority_order']);
        $stmt->execute();
        
        // Sync to JSON file after adding
        try {
            $this->syncHotlinesToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Hotline added successfully.', ['id' => $this->db->lastInsertId()]);
    }

    public function updateHotline($id, $data)
    {
        $sql = "UPDATE emergency_hotlines SET agency_name = :agency_name, agency_code = :agency_code, phone_number = :phone_number, description = :description, priority_order = :priority_order WHERE hotline_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':agency_name', $data['agency_name']);
        $stmt->bindParam(':agency_code', $data['agency_code']);
        $stmt->bindParam(':phone_number', $data['phone_number']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':priority_order', $data['priority_order']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // Sync to JSON file after updating
        try {
            $this->syncHotlinesToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Hotline updated successfully.', null);
    }

    public function deleteHotline($id)
    {
        $sql = "UPDATE emergency_hotlines SET is_active = 0 WHERE hotline_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        // Sync to JSON file after deleting
        try {
            $this->syncHotlinesToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Hotline deleted successfully.', null);
    }
    
    private function syncHotlinesToJson()
    {
        if (!$this->db->isConnected()) {
            return;
        }
        
        $sql = "SELECT * FROM emergency_hotlines ORDER BY priority_order ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $hotlines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        writeJsonFile('hotlines.json', $hotlines);
    }
}