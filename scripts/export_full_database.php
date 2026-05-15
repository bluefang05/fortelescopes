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
$timestampedPath = $backupDir . '/fortelescopes_data_backup_' . $timestamp . '.sql';
$latestPath = $backupDir . '/fortelescopes_data_backup_latest.sql';

$tables = $pdo->query(
    'SELECT table_name
     FROM information_schema.tables
     WHERE table_schema = ' . $pdo->quote(DB_NAME) . '
     ORDER BY table_name ASC'
)->fetchAll(PDO::FETCH_COLUMN);
$tables = array_values(array_filter(array_map(static fn($t): string => trim((string) $t), $tables)));

$verification = $pdo->query(
    'SELECT
        MIN(view_date) AS first_view_date,
        MAX(view_date) AS last_view_date,
        COUNT(*) AS total_records,
        COALESCE(SUM(views), 0) AS total_views
     FROM page_views'
)->fetch(PDO::FETCH_ASSOC) ?: [
    'first_view_date' => null,
    'last_view_date' => null,
    'total_records' => 0,
    'total_views' => 0,
];

$sql = "-- Fortelescopes Data Backup\n";
$sql .= "-- Generated on " . gmdate('Y-m-d H:i:s') . " UTC\n";
$sql .= "-- Database: " . DB_NAME . "\n";
$sql .= "-- Verification: SELECT MIN(view_date), MAX(view_date), COUNT(*), SUM(views) FROM page_views;\n";
$sql .= '-- Result: first_view_date=' . (string) ($verification['first_view_date'] ?? 'NULL')
    . ', last_view_date=' . (string) ($verification['last_view_date'] ?? 'NULL')
    . ', total_records=' . number_format((int) ($verification['total_records'] ?? 0), 0, '.', '')
    . ', total_views=' . number_format((int) ($verification['total_views'] ?? 0), 0, '.', '')
    . "\n\n";
$sql .= "SET NAMES utf8mb4;\n";
$sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$tablesExported = 0;
$rowsExported = 0;

foreach ($tables as $table) {
    $createRow = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_ASSOC);
    $createSql = (string) ($createRow['Create Table'] ?? '');
    if ($createSql === '') {
        continue;
    }

    $sql .= "-- --------------------------------------------------------\n";
    $sql .= '-- Table structure for `' . $table . "`\n";
    $sql .= "-- --------------------------------------------------------\n";
    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $sql .= $createSql . ";\n\n";

    $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
    $sql .= "-- Data for table `{$table}`\n";
    if ($rows === []) {
        $sql .= "-- No rows\n\n";
        $tablesExported++;
        continue;
    }

    $columns = array_keys($rows[0]);
    $colSql = '`' . implode('`,`', array_map(static fn($c): string => str_replace('`', '``', (string) $c), $columns)) . '`';
    $valueLines = [];
    foreach ($rows as $row) {
        $vals = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if ($value === null) {
                $vals[] = 'NULL';
            } elseif (is_int($value) || is_float($value)) {
                $vals[] = (string) $value;
            } elseif (is_numeric((string) $value) && preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/', (string) $value) === 1 && !preg_match('/^0[0-9]+$/', (string) $value)) {
                $vals[] = (string) $value;
            } else {
                $vals[] = $pdo->quote((string) $value);
            }
        }
        $valueLines[] = '(' . implode(', ', $vals) . ')';
    }

    $sql .= "INSERT INTO `{$table}` ({$colSql}) VALUES\n";
    $sql .= implode(",\n", $valueLines) . ";\n\n";
    $tablesExported++;
    $rowsExported += count($rows);
}

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

if (file_put_contents($timestampedPath, $sql) === false) {
    throw new RuntimeException('Could not write timestamped full backup file.');
}
if (file_put_contents($latestPath, $sql) === false) {
    throw new RuntimeException('Could not write latest full backup file.');
}

$deletedBackups = maintenance_prune_files('backups', 'fortelescopes_data_backup_*.sql', 30);
$fileSize = filesize($timestampedPath);
$fileSize = $fileSize === false ? 0 : (int) $fileSize;
$summary = sprintf(
    'path=%s | size_bytes=%d | tables=%d | page_views[min=%s max=%s count=%d sum=%d]',
    $timestampedPath,
    $fileSize,
    $tablesExported,
    (string) ($verification['first_view_date'] ?? 'NULL'),
    (string) ($verification['last_view_date'] ?? 'NULL'),
    (int) ($verification['total_records'] ?? 0),
    (int) ($verification['total_views'] ?? 0)
);

$lines = [
    'Task: export_full_database',
    'Output timestamped: ' . $timestampedPath,
    'Output latest: ' . $latestPath,
    'Size bytes: ' . $fileSize,
    'Tables exported: ' . $tablesExported,
    'Rows exported: ' . $rowsExported,
    'Deleted old backups: ' . $deletedBackups,
    'Verification page_views: first=' . (string) ($verification['first_view_date'] ?? 'NULL')
        . ' | last=' . (string) ($verification['last_view_date'] ?? 'NULL')
        . ' | count=' . (int) ($verification['total_records'] ?? 0)
        . ' | sum=' . (int) ($verification['total_views'] ?? 0),
];
$logPath = maintenance_append_log('export-full-database', $lines);
$lines[] = 'Log: ' . $logPath;
echo implode(PHP_EOL, $lines) . PHP_EOL;

return [
    'timestamped_path' => $timestampedPath,
    'latest_path' => $latestPath,
    'file_size' => $fileSize,
    'tables_exported' => $tablesExported,
    'rows_exported' => $rowsExported,
    'verification' => [
        'first_view_date' => (string) ($verification['first_view_date'] ?? ''),
        'last_view_date' => (string) ($verification['last_view_date'] ?? ''),
        'total_records' => (int) ($verification['total_records'] ?? 0),
        'total_views' => (int) ($verification['total_views'] ?? 0),
    ],
    'summary' => $summary,
];

