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
        $sql = "SELECT * FROM mycurrentlocation ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return response('success', 'Current location fetched successfully.', $stmt->fetch(PDO::FETCH_ASSOC));
    }
}