<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    exit('Forbidden');
}

$policies = [
    [
        'table' => 'page_view_hits',
        'column' => 'view_date',
        'cutoff' => gmdate('Y-m-d', strtotime('-90 days')),
    ],
    [
        'table' => 'page_views',
        'column' => 'view_date',
        'cutoff' => gmdate('Y-m-d', strtotime('-365 days')),
    ],
    [
        'table' => 'outbound_clicks',
        'column' => 'click_date',
        'cutoff' => gmdate('Y-m-d', strtotime('-365 days')),
    ],
    [
        'table' => 'admin_activity_log',
        'column' => 'created_at',
        'cutoff' => gmdate('c', strtotime('-180 days')),
    ],
];

foreach ($policies as $policy) {
    $stmt = $pdo->prepare(
        'DELETE FROM `' . $policy['table'] . '`
         WHERE `' . $policy['column'] . '` < :cutoff'
    );
    $stmt->execute([':cutoff' => $policy['cutoff']]);
    $lines[] = $policy['table'] . ': deleted ' . $stmt->rowCount() . ' rows before ' . $policy['cutoff'];
}

$deletedLogs = maintenance_prune_files('logs', '*.log', 30);
$lines[] = 'Deleted old log files: ' . $deletedLogs;
$logPath = maintenance_append_log('prune-old-logs', $lines);
$lines[] = 'Log: ' . $logPath;
echo implode(PHP_EOL, $lines) . PHP_EOL;
