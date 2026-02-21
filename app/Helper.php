<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function response($status, $message, $data)
{
    return [
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ];
}

function setAuthSession($user)
{
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = (bool) $user['is_admin'];
}

function isLoggedIn()
{
    return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
}

function isAdmin()
{
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function getImageUrl($imagePath)
{
    if (empty($imagePath)) {
        return $imagePath;
    }

    if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
        return $imagePath;
    }

    $imageBaseUrl = defined('IMAGE_BASE_URL') ? constant('IMAGE_BASE_URL') : 'https://api.evacuationshelter.online';

    if (strpos($imagePath, 'api/assets/img/shelters/') === 0) {
        $imagePath = str_replace('api/', '', $imagePath);
    }

    if (strpos($imagePath, 'assets/img/shelters/') === 0) {
        return $imageBaseUrl . '/' . $imagePath;
    }

    if (strpos($imagePath, '/') === false || strpos($imagePath, 'assets/') === false) {
        return $imageBaseUrl . '/assets/img/shelters/' . $imagePath;
    }

    return $imageBaseUrl . '/' . ltrim($imagePath, '/');
}

function readJsonFile($filename)
{
    $filePath = __DIR__ . '/../assets/data/' . $filename;
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        return $data !== null ? $data : [];
    }
    return [];
}

function writeJsonFile($filename, $data)
{
    try {
        $filePath = __DIR__ . '/../assets/data/' . $filename;
        $dir = dirname($filePath);
        
        // Create directory if it doesn't exist
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                return false;
            }
        }
        
        // Write JSON with pretty formatting
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        
        if (@file_put_contents($filePath, $json) === false) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        // Silently fail - don't throw exceptions
        return false;
    }
}

function isDbConnected($db)
{
    try {
        $db->getPdo()->query('SELECT 1');
        return true;
    } catch (Exception $e) {
        return false;
    }
}
