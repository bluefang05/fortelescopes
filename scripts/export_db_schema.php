<?php

declare(strict_types=1);

/**
 * Export current database schema to db_schema.sql file
 * This script reads the actual schema from MySQL and writes it to the workspace
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$schemaPath = __DIR__ . '/../db_schema.sql';
$generationDate = date('Y-m-d H:i:s');

// Get all tables from the database
$stmt = $pdo->query(
    "SELECT table_name 
     FROM information_schema.tables 
     WHERE table_schema = '" . DB_NAME . "' 
     ORDER BY table_name"
);
$tables = array_column($stmt->fetchAll(), 'table_name');

$sqlOutput = "-- Fortelescopes Database Schema\n";
$sqlOutput .= "-- Generated on {$generationDate}\n\n";
$sqlOutput .= "SET NAMES utf8mb4;\n";
$sqlOutput .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $tableName) {
    // Skip internal tables
    if (strpos($tableName, '_schema') === 0 || strpos($tableName, 'migrations') === 0) {
        continue;
    }

    // Get CREATE TABLE statement
    $stmt = $pdo->prepare("SHOW CREATE TABLE `" . $tableName . "`");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        continue;
    }

    $createStatement = $row['Create Table'] ?? '';
    
    if ($createStatement !== '') {
        $sqlOutput .= "-- ----------------------------\n";
        $sqlOutput .= "-- Table structure for {$tableName}\n";
        $sqlOutput .= "-- ----------------------------\n";
        
        // Convert to use CREATE TABLE IF NOT EXISTS
        $createStatement = str_replace(
            "CREATE TABLE `{$tableName}`",
            "CREATE TABLE IF NOT EXISTS `{$tableName}`",
            $createStatement
        );
        
        $sqlOutput .= $createStatement . ";\n\n";
    }
}

$sqlOutput .= "SET FOREIGN_KEY_CHECKS = 1;\n";

// Write to file
$bytesWritten = file_put_contents($schemaPath, $sqlOutput);

if ($bytesWritten === false) {
    echo "ERROR: Failed to write schema file.\n";
    exit(1);
}

echo "SUCCESS: Database schema exported to db_schema.sql\n";
echo "File size: " . number_format($bytesWritten) . " bytes\n";
echo "Tables exported: " . count($tables) . "\n";
echo "Location: " . realpath($schemaPath) . "\n";
