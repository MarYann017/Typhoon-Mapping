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
        $sql = "SELECT * FROM disasters ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $disasters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add is_past flag to each disaster
        $today = date('Y-m-d');
        foreach ($disasters as &$disaster) {
            $endDate = $disaster['end_date'];
            $startDate = $disaster['start_date'];
            
            // Consider past if end_date exists and is before today, or if start_date is before today and no end_date
            if ($endDate && $endDate < $today) {
                $disaster['is_past'] = true;
            } elseif ($startDate && $startDate < $today && !$endDate) {
                $disaster['is_past'] = true;
            } else {
                $disaster['is_past'] = false;
            }
        }
        
        return response('success', 'Disasters fetched successfully.', $disasters);
    }

    public function getDisasterDetails($disasterId)
    {
        // Get disaster info
        $sql = "SELECT * FROM disasters WHERE disaster_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$disasterId]);
        $disaster = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$disaster) {
            return response('error', 'Disaster not found.', null);
        }
        
        // Get shelters used during this disaster with evacuee counts
        $startDate = $disaster['start_date'] ? $disaster['start_date'] : '1970-01-01';
        $endDate = $disaster['end_date'] ? $disaster['end_date'] : date('Y-m-d');
        
        $shelterSql = "SELECT 
            s.shelter_id,
            s.shelter_name,
            s.barangay,
            s.shelter_type,
            s.capacity,
            s.current_occupancy,
            COUNT(DISTINCT e.evacuee_id) as total_evacuees
        FROM shelters s
        LEFT JOIN evacuees e ON s.shelter_id = e.shelter_id 
            AND DATE(e.date_arrived) <= ?
            AND (e.date_left IS NULL OR DATE(e.date_left) >= ?)
        WHERE s.current_disaster_id = ?
        GROUP BY s.shelter_id, s.shelter_name, s.barangay, s.shelter_type, s.capacity, s.current_occupancy
        ORDER BY s.shelter_name";
        
        $shelterStmt = $this->db->prepare($shelterSql);
        $shelterStmt->execute([$endDate, $startDate, $disasterId]);
        $shelters = $shelterStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $disaster['shelters'] = $shelters;
        
        return response('success', 'Disaster details fetched successfully.', $disaster);
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
        return response('success', 'Disaster added successfully.', ['id' => $this->db->lastInsertId()]);
    }

    public function updateDisaster($id, $data)
    {
        // Check if disaster is past
        $checkSql = "SELECT end_date, start_date FROM disasters WHERE disaster_id = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $today = date('Y-m-d');
            $endDate = $existing['end_date'];
            $startDate = $existing['start_date'];
            
            // Check if past disaster
            if (($endDate && $endDate < $today) || ($startDate && $startDate < $today && !$endDate)) {
                return response('error', 'Cannot edit past disasters.', null);
            }
        }
        
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
        return response('success', 'Disaster updated successfully.', null);
    }

    public function deleteDisaster($id)
    {
        $sql = "DELETE FROM disasters WHERE disaster_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return response('success', 'Disaster deleted successfully.', null);
    }

    public function assignSheltersToDisaster($disasterId, $shelterIds)
    {
        try {
            // Validate inputs
            if (empty($disasterId)) {
                return response('error', 'Disaster ID is required.', null);
            }
            
            if (empty($shelterIds) || !is_array($shelterIds)) {
                return response('error', 'No shelters selected.', null);
            }
            
            // Check if disaster is past
            $checkSql = "SELECT end_date, start_date FROM disasters WHERE disaster_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$disasterId]);
            $disaster = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$disaster) {
                return response('error', 'Disaster not found.', null);
            }
            
            $today = date('Y-m-d');
            $endDate = $disaster['end_date'];
            $startDate = $disaster['start_date'];
            
            // Check if past disaster
            if (($endDate && $endDate < $today) || ($startDate && $startDate < $today && !$endDate)) {
                return response('error', 'Cannot assign shelters to past disasters.', null);
            }
            
            // Filter out empty values and ensure all are integers
            $shelterIds = array_filter(array_map('intval', $shelterIds));
            
            if (empty($shelterIds)) {
                return response('error', 'No valid shelters selected.', null);
            }
            
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();
            
            // Update selected shelters to assign them to this disaster
            $placeholders = implode(',', array_fill(0, count($shelterIds), '?'));
            $updateSql = "UPDATE shelters SET current_disaster_id = ? WHERE shelter_id IN ($placeholders) AND is_active = 1";
            $updateStmt = $this->db->prepare($updateSql);
            $params = array_merge([$disasterId], $shelterIds);
            $updateStmt->execute($params);
            
            $pdo->commit();
            return response('success', 'Shelters assigned successfully.', ['count' => count($shelterIds)]);
        } catch (PDOException $e) {
            $pdo = $this->db->getPdo();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return response('error', 'Database error: ' . $e->getMessage(), null);
        } catch (Exception $e) {
            $pdo = $this->db->getPdo();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return response('error', 'Failed to assign shelters: ' . $e->getMessage(), null);
        }
    }

    public function updateShelterOccupancy($shelterId, $disasterId, $currentOccupancy, $evacuees)
    {
        try {
            // Check if disaster is past
            $checkSql = "SELECT end_date, start_date FROM disasters WHERE disaster_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$disasterId]);
            $disaster = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$disaster) {
                return response('error', 'Disaster not found.', null);
            }
            
            $today = date('Y-m-d');
            $endDate = $disaster['end_date'];
            $startDate = $disaster['start_date'];
            
            // Check if past disaster
            if (($endDate && $endDate < $today) || ($startDate && $startDate < $today && !$endDate)) {
                return response('error', 'Cannot update occupancy for past disasters.', null);
            }
            
            // Validate shelter belongs to this disaster
            $shelterCheckSql = "SELECT shelter_id, capacity FROM shelters WHERE shelter_id = ? AND current_disaster_id = ?";
            $shelterCheckStmt = $this->db->prepare($shelterCheckSql);
            $shelterCheckStmt->execute([$shelterId, $disasterId]);
            $shelter = $shelterCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shelter) {
                return response('error', 'Shelter not found or not assigned to this disaster.', null);
            }
            
            // Validate occupancy doesn't exceed capacity
            if ($currentOccupancy > $shelter['capacity']) {
                return response('error', 'Current occupancy cannot exceed capacity.', null);
            }
            
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();
            
            // Get disaster start date for filtering evacuees
            $disasterStartDate = $disaster['start_date'] ? $disaster['start_date'] : '1970-01-01';
            $disasterEndDate = $disaster['end_date'] ? $disaster['end_date'] : date('Y-m-d');
            
            // Get current evacuee count for this shelter during this disaster period
            $currentEvacueesSql = "SELECT COUNT(*) as count FROM evacuees 
                                   WHERE shelter_id = ? 
                                   AND DATE(date_arrived) <= ? 
                                   AND (date_left IS NULL OR DATE(date_left) >= ?)";
            $currentEvacueesStmt = $this->db->prepare($currentEvacueesSql);
            $currentEvacueesStmt->execute([$shelterId, $disasterEndDate, $disasterStartDate]);
            $currentEvacueesResult = $currentEvacueesStmt->fetch(PDO::FETCH_ASSOC);
            $currentEvacueesCount = intval($currentEvacueesResult['count']);
            
            // Calculate difference
            $evacueesDifference = intval($evacuees) - $currentEvacueesCount;
            
            if ($evacueesDifference > 0) {
                // Need to add evacuees - get current max evacuee number for naming
                $maxEvacueeSql = "SELECT COUNT(*) as total FROM evacuees WHERE shelter_id = ?";
                $maxEvacueeStmt = $this->db->prepare($maxEvacueeSql);
                $maxEvacueeStmt->execute([$shelterId]);
                $maxEvacueeResult = $maxEvacueeStmt->fetch(PDO::FETCH_ASSOC);
                $baseNumber = intval($maxEvacueeResult['total']);
                
                // Add evacuees
                for ($i = 0; $i < $evacueesDifference; $i++) {
                    $insertEvacueeSql = "INSERT INTO evacuees (shelter_id, full_name, date_arrived) VALUES (?, ?, ?)";
                    $insertEvacueeStmt = $this->db->prepare($insertEvacueeSql);
                    $evacueeName = "Evacuee " . ($baseNumber + $i + 1);
                    $arrivalDate = $disasterStartDate . ' ' . date('H:i:s');
                    $insertEvacueeStmt->execute([$shelterId, $evacueeName, $arrivalDate]);
                }
            } elseif ($evacueesDifference < 0) {
                // Need to remove evacuees (mark as left) - get evacuees that haven't left yet
                $evacueesToRemove = abs($evacueesDifference);
                $getEvacueesSql = "SELECT evacuee_id FROM evacuees 
                                   WHERE shelter_id = ? 
                                   AND DATE(date_arrived) <= ? 
                                   AND (date_left IS NULL OR DATE(date_left) >= ?)
                                   AND date_left IS NULL
                                   ORDER BY date_arrived ASC
                                   LIMIT ?";
                $getEvacueesStmt = $this->db->prepare($getEvacueesSql);
                $getEvacueesStmt->execute([$shelterId, $disasterEndDate, $disasterStartDate, $evacueesToRemove]);
                $evacueesToMarkLeft = $getEvacueesStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($evacueesToMarkLeft)) {
                    $placeholders = implode(',', array_fill(0, count($evacueesToMarkLeft), '?'));
                    $removeEvacueesSql = "UPDATE evacuees 
                                          SET date_left = NOW() 
                                          WHERE evacuee_id IN ($placeholders)";
                    $removeEvacueesStmt = $this->db->prepare($removeEvacueesSql);
                    $removeEvacueesStmt->execute($evacueesToMarkLeft);
                }
            }
            
            // Update shelter occupancy after evacuee changes
            // The triggers will have updated occupancy, but we want to set it to the exact value specified
            $updateSql = "UPDATE shelters SET current_occupancy = ?, shelter_status = IF(? >= capacity, 'Full', IF(? > 0, 'Available', shelter_status)) WHERE shelter_id = ?";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$currentOccupancy, $currentOccupancy, $currentOccupancy, $shelterId]);
            
            $pdo->commit();
            return response('success', 'Shelter occupancy and evacuees updated successfully.', [
                'shelter_id' => $shelterId,
                'current_occupancy' => $currentOccupancy,
                'evacuees' => $evacuees,
                'evacuees_added' => $evacueesDifference > 0 ? $evacueesDifference : 0,
                'evacuees_removed' => $evacueesDifference < 0 ? abs($evacueesDifference) : 0
            ]);
        } catch (PDOException $e) {
            $pdo = $this->db->getPdo();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return response('error', 'Database error: ' . $e->getMessage(), null);
        } catch (Exception $e) {
            $pdo = $this->db->getPdo();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return response('error', 'Failed to update occupancy: ' . $e->getMessage(), null);
        }
    }
}