<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$threshold = gmdate('c', time() - (23 * 3600));
$now = now_iso();

$stmt = $pdo->prepare(
    'UPDATE products
     SET last_synced_at = :now, updated_at = :now
     WHERE status = "published"
       AND (last_synced_at IS NULL OR last_synced_at < :threshold)'
);
$stmt->execute([
    ':now' => $now,
    ':threshold' => $threshold,
]);

$lines = [
    'Products refreshed: ' . $stmt->rowCount(),
];
maintenance_prune_files('logs', 'cron-refresh_*.log', 30);
$logPath = maintenance_append_log('cron-refresh', $lines);
$lines[] = 'Log: ' . $logPath;
echo implode(PHP_EOL, $lines) . PHP_EOL;
