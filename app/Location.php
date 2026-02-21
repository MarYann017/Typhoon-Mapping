<?php
require_once 'Db.php';
require_once 'Helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Location
{
    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    public function getCurrentLocation()
    {
        try {
            if (!$this->db->isConnected()) {
                throw new Exception('Database not connected');
            }
            
            $sql = "SELECT * FROM mycurrentlocation ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Sync to JSON file while online
            if ($location) {
                try {
                    writeJsonFile('mylocation.json', $location);
                } catch (Exception $e) {
                    // Silently fail if JSON write fails
                }
            }
            
            return response('success', 'Current location fetched successfully.', $location);
        } catch (Exception $e) {
            // Fallback to JSON file
            $location = readJsonFile('mylocation.json');
            return response('success', 'Current location fetched from offline cache.', $location);
        }
    }
}