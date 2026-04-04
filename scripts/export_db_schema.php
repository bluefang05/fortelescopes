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

// Add seed data for users table if it exists
if (in_array('users', $tables, true)) {
    $sqlOutput .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";
    $sqlOutput .= "-- ----------------------------\n";
    $sqlOutput .= "-- Records for users\n";
    $sqlOutput .= "-- ----------------------------\n";
    $sqlOutput .= "-- Default admin user: username=admin, password=polilla05\n";
    $sqlOutput .= "-- Password hash generated with bcrypt (cost factor 10)\n";
    
    // Check if admin user exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $adminExists = (int) $stmt->fetchColumn() > 0;
    
    if (!$adminExists) {
        $sqlOutput .= "INSERT INTO `users` (`email`, `username`, `password_hash`, `display_name`, `role`, `status`, `created_at`, `updated_at`) \n";
        $sqlOutput .= "VALUES (\n";
        $sqlOutput .= "    'admin@fortlescopes.com',\n";
        $sqlOutput .= "    'admin',\n";
        $sqlOutput .= "    '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',\n";
        $sqlOutput .= "    'Administrator',\n";
        $sqlOutput .= "    'admin',\n";
        $sqlOutput .= "    'active',\n";
        $sqlOutput .= "    UTC_TIMESTAMP(),\n";
        $sqlOutput .= "    UTC_TIMESTAMP()\n";
        $sqlOutput .= ");\n";
    } else {
        $sqlOutput .= "-- Admin user already exists (skipped insert)\n";
    }
} else {
    $sqlOutput .= "SET FOREIGN_KEY_CHECKS = 1;\n";
}

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
