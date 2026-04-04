<?php

declare(strict_types=1);

/**
 * Generate database migration script based on schema differences
 * Compares current database schema with db_schema.sql and creates migration SQL
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$migrationPath = __DIR__ . '/migration_' . date('Y-m-d_His') . '.sql';
$schemaPath = __DIR__ . '/../db_schema.sql';

echo "=== Database Migration Generator ===\n\n";

// Read the reference schema file
$referenceSchema = '';
if (file_exists($schemaPath)) {
    $referenceSchema = file_get_contents($schemaPath);
    echo "Reference schema loaded: {$schemaPath}\n";
} else {
    echo "WARNING: Reference schema file not found. Creating fresh schema export.\n";
    // Run export instead
    require __DIR__ . '/export_db_schema.php';
    exit(0);
}

// Get current database tables
$stmt = $pdo->query(
    "SELECT table_name 
     FROM information_schema.tables 
     WHERE table_schema = '" . DB_NAME . "' 
     ORDER BY table_name"
);
$dbTables = array_column($stmt->fetchAll(), 'table_name');

// Parse reference schema to get expected tables
preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `(\w+)`/', $referenceSchema, $matches);
$expectedTables = $matches[1] ?? [];

$migrationStatements = [];
$migrationStatements[] = "-- Migration Script";
$migrationStatements[] = "-- Generated: " . date('Y-m-d H:i:s');
$migrationStatements[] = "-- Database: " . DB_NAME;
$migrationStatements[] = "";
$migrationStatements[] = "SET NAMES utf8mb4;";
$migrationStatements[] = "SET FOREIGN_KEY_CHECKS = 0;";
$migrationStatements[] = "";

$changesDetected = false;

// Check for missing tables (in reference but not in DB)
foreach ($expectedTables as $tableName) {
    if (!in_array($tableName, $dbTables, true)) {
        $changesDetected = true;
        $migrationStatements[] = "-- Create missing table: {$tableName}";
        
        // Extract CREATE TABLE statement from reference schema
        if (preg_match('/(CREATE TABLE(?: IF NOT EXISTS)? `' . preg_quote($tableName, '/') . '`[^;]+;)/s', $referenceSchema, $match)) {
            $migrationStatements[] = $match[1];
            $migrationStatements[] = "";
        }
    }
}

// Check for extra tables (in DB but not in reference)
foreach ($dbTables as $tableName) {
    if (!in_array($tableName, $expectedTables, true) && 
        strpos($tableName, '_schema') !== 0 && 
        strpos($tableName, 'migrations') !== 0) {
        $changesDetected = true;
        $migrationStatements[] = "-- WARNING: Table '{$tableName}' exists in DB but not in reference schema";
        $migrationStatements[] = "-- Consider adding it to db_schema.sql or dropping it";
        $migrationStatements[] = "";
    }
}

// Check column differences for common tables
$commonTables = array_intersect($dbTables, $expectedTables);

foreach ($commonTables as $tableName) {
    // Get columns from DB
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . $tableName . "`");
    $stmt->execute();
    $dbColumns = array_column($stmt->fetchAll(), 'Field');
    
    // Extract columns from reference schema
    if (preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `' . preg_quote($tableName, '/') . '` \((.*?)\)(?: ENGINE|\;|$)/s', $referenceSchema, $match)) {
        $tableDef = $match[1] ?? '';
        $refColumns = [];
        
        foreach (explode("\n", $tableDef) as $line) {
            $line = trim($line);
            if (preg_match('/^`(\w+)`/', $line, $colMatch)) {
                $refColumns[] = $colMatch[1];
            }
        }
        
        // Find missing columns
        $missingColumns = array_diff($refColumns, $dbColumns);
        foreach ($missingColumns as $colName) {
            $changesDetected = true;
            $migrationStatements[] = "-- Add missing column '{$colName}' to table '{$tableName}'";
            $migrationStatements[] = "-- Please define the ALTER TABLE statement manually";
            $migrationStatements[] = "-- ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` ...;";
            $migrationStatements[] = "";
        }
        
        // Find extra columns
        $extraColumns = array_diff($dbColumns, $refColumns);
        foreach ($extraColumns as $colName) {
            $migrationStatements[] = "-- INFO: Column '{$colName}' exists in DB table '{$tableName}' but not in reference";
        }
        if (!empty($extraColumns)) {
            $migrationStatements[] = "";
        }
    }
}

$migrationStatements[] = "SET FOREIGN_KEY_CHECKS = 1;";
$migrationStatements[] = "";
$migrationStatements[] = "-- End of migration script";

if (!$changesDetected) {
    echo "\nNo structural changes detected between database and reference schema.\n";
    echo "Database is up to date with db_schema.sql\n";
} else {
    $migrationContent = implode("\n", $migrationStatements);
    
    // Write migration file
    $bytesWritten = file_put_contents($migrationPath, $migrationContent);
    
    if ($bytesWritten === false) {
        echo "ERROR: Failed to write migration file.\n";
        exit(1);
    }
    
    echo "\nMigration script generated: {$migrationPath}\n";
    echo "File size: " . number_format($bytesWritten) . " bytes\n";
    echo "\nReview the migration file and execute it manually if needed:\n";
    echo "  mysql -u " . DB_USER . " -p " . DB_NAME . " < {$migrationPath}\n";
}

echo "\n=== Summary ===\n";
echo "Database tables: " . count($dbTables) . "\n";
echo "Expected tables: " . count($expectedTables) . "\n";
echo "Common tables: " . count($commonTables) . "\n";
