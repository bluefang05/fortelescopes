<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    exit('Forbidden');
}

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$backupDir = __DIR__ . '/../data/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    throw new RuntimeException('Could not create backup directory.');
}

$timestamp = gmdate('Ymd_His');
$latestPath = $backupDir . '/db_backup_latest.sql';
$timestampedPath = $backupDir . '/db_backup_' . $timestamp . '.sql';

$tableRows = $pdo->query(
    "SELECT table_name
     FROM information_schema.tables
     WHERE table_schema = '" . DB_NAME . "'
     ORDER BY table_name"
)->fetchAll();
$tables = array_map(static fn(array $row): string => (string) $row['table_name'], $tableRows);

$sql = "-- Fortelescopes Data Backup\n";
$sql .= "-- Generated on " . gmdate('Y-m-d H:i:s') . " UTC\n\n";
$sql .= "SET NAMES utf8mb4;\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$tableCount = 0;
$rowCount = 0;

foreach ($tables as $table) {
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    $columns = array_map(static fn(array $row): string => (string) $row['Field'], $columnsStmt->fetchAll());
    if ($columns === []) {
        continue;
    }

    $rows = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY 1 ASC')->fetchAll(PDO::FETCH_ASSOC);

    $sql .= "-- ----------------------------\n";
    $sql .= "-- Data for table `{$table}`\n";
    $sql .= "-- ----------------------------\n";

    if ($rows === []) {
        $sql .= "-- No rows\n\n";
        $tableCount++;
        continue;
    }

    $sql .= "DELETE FROM `{$table}`;\n";
    $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";

    $valueLines = [];
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if ($value === null) {
                $values[] = 'NULL';
            } elseif (is_numeric($value) && !preg_match('/^0\d+/', (string) $value)) {
                $values[] = (string) $value;
            } else {
                $values[] = $pdo->quote((string) $value);
            }
        }
        $valueLines[] = '    (' . implode(', ', $values) . ')';
    }

    $sql .= implode(",\n", $valueLines) . ";\n\n";
    $tableCount++;
    $rowCount += count($rows);
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

if (file_put_contents($latestPath, $sql) === false) {
    throw new RuntimeException('Could not write latest backup file.');
}
if (file_put_contents($timestampedPath, $sql) === false) {
    throw new RuntimeException('Could not write timestamped backup file.');
}

$deletedBackups = maintenance_prune_files('backups', 'db_backup_*.sql', 21);
maintenance_prune_files('logs', 'backup-content-sql_*.log', 30);
$lines = [
    'Backup tables: ' . $tableCount,
    'Backup rows: ' . $rowCount,
    'Latest: ' . realpath($latestPath),
    'Snapshot: ' . realpath($timestampedPath),
    'Deleted old backups: ' . $deletedBackups,
];
$logPath = maintenance_append_log('backup-content-sql', $lines);
$lines[] = 'Log: ' . $logPath;
echo implode(PHP_EOL, $lines) . PHP_EOL;
