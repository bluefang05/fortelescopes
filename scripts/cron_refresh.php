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

echo "Products refreshed: " . $stmt->rowCount() . PHP_EOL;
