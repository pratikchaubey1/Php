<?php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'test_db';

try {
    $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Modify column to support text
    $sql = "ALTER TABLE banned_suppliers MODIFY banningPeriod VARCHAR(50)";
    $conn->exec($sql);
    
    echo "Database schema updated successfully: banningPeriod changed to VARCHAR(50).";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
