<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$reportDir = maintenance_data_path('reports');
$latestPath = $reportDir . '/posts_table_latest.json';
$snapshotPath = $reportDir . '/posts_table_' . gmdate('Ymd_His') . '.json';

$rows = $pdo->query(
    'SELECT *
     FROM posts
     ORDER BY id DESC'
)->fetchAll();

$payloadData = [
    'generated_at' => gmdate('c'),
    'table' => 'posts',
    'total_rows' => count($rows),
    'rows' => $rows,
];
$payload = json_encode($payloadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($payload) || $payload === '') {
    throw new RuntimeException('Could not encode posts table export as JSON.');
}
$payload .= PHP_EOL;

if (file_put_contents($latestPath, $payload) === false) {
    throw new RuntimeException('Could not write latest posts table JSON export.');
}
if (file_put_contents($snapshotPath, $payload) === false) {
    throw new RuntimeException('Could not write snapshot posts table JSON export.');
}

$deletedReports = maintenance_prune_files('reports', 'posts_table_*.json', 30);
maintenance_prune_files('logs', 'export-posts-table-json_*.log', 30);

$outputLines = [
    'Posts table rows exported: ' . count($rows),
    'Latest: ' . $latestPath,
    'Snapshot: ' . $snapshotPath,
    'Deleted old snapshots: ' . $deletedReports,
];

$logPath = maintenance_append_log('export-posts-table-json', $outputLines);
$outputLines[] = 'Log: ' . $logPath;

echo implode(PHP_EOL, $outputLines) . PHP_EOL;
