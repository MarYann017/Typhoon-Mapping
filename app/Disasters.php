<?php
require_once 'Db.php';
require_once 'Helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Disasters
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getDisasters()
    {
        try {
            if (!$this->db->isConnected()) {
                throw new Exception('Database not connected');
            }
            
            $sql = "SELECT * FROM disasters ORDER BY created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $disasters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sync to JSON file while online
            try {
                writeJsonFile('disasters.json', $disasters);
            } catch (Exception $e) {
                // Silently fail if JSON write fails
            }
            
            return response('success', 'Disasters fetched successfully.', $disasters);
        } catch (Exception $e) {
            // Fallback to JSON file
            $disasters = readJsonFile('disasters.json');
            return response('success', 'Disasters fetched from offline cache.', $disasters);
        }
    }

    public function addDisaster($data)
    {
        $sql = "INSERT INTO disasters (name, type, start_date, end_date, severity, description) VALUES (:name, :type, :start_date, :end_date, :severity, :description)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':type', $data['type']);
        $stmt->bindParam(':start_date', $data['start_date']);
        $stmt->bindParam(':end_date', $data['end_date']);
        $stmt->bindParam(':severity', $data['severity']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->execute();
        
        // Sync to JSON file after adding
        try {
            $this->syncDisastersToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Disaster added successfully.', ['id' => $this->db->lastInsertId()]);
    }

    public function updateDisaster($id, $data)
    {
        $sql = "UPDATE disasters SET name = :name, type = :type, start_date = :start_date, end_date = :end_date, severity = :severity, description = :description WHERE disaster_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':type', $data['type']);
        $stmt->bindParam(':start_date', $data['start_date']);
        $stmt->bindParam(':end_date', $data['end_date']);
        $stmt->bindParam(':severity', $data['severity']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // Sync to JSON file after updating
        try {
            $this->syncDisastersToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Disaster updated successfully.', null);
    }

    public function deleteDisaster($id)
    {
        $sql = "DELETE FROM disasters WHERE disaster_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        // Sync to JSON file after deleting
        try {
            $this->syncDisastersToJson();
        } catch (Exception $e) {
            // Silently fail if JSON sync fails
        }
        
        return response('success', 'Disaster deleted successfully.', null);
    }
    
    private function syncDisastersToJson()
    {
        if (!$this->db->isConnected()) {
            return;
        }
        
        $sql = "SELECT * FROM disasters ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $disasters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        writeJsonFile('disasters.json', $disasters);
    }
}