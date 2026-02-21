<?php

$MYSQL_USER = "root";
$MYSQL_PASS = "";

try {
    $conn = new PDO("mysql:host=localhost", $MYSQL_USER, $MYSQL_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Dropping evacuation_shelter database...\n";
    $conn->exec("DROP DATABASE IF EXISTS evacuation_shelter");
    
    echo "Importing database.sql...\n";
    $sql = file_get_contents('database.sql');
    $conn->exec($sql);
    
    echo "Database reset and import complete.\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>