<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    echo "Forbidden.\n";
    exit(1);
}

function cron_refresh_parse_args(array $argv): array
{
    $options = [
        'hours' => 23,
        'dry-run' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        $arg = trim((string) $arg);
        if ($arg === '') {
            continue;
        }

        if ($arg === '--dry-run' || $arg === '--dry-run=1') {
            $options['dry-run'] = true;
            continue;
        }

        if (strpos($arg, '--hours=') === 0) {
            $hours = (int) substr($arg, 8);
            if ($hours > 0 && $hours <= 168) {
                $options['hours'] = $hours;
            }
        }
    }

    return $options;
}

function cron_refresh_ensure_usage_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS maintenance_task_usage (
            task_key VARCHAR(64) NOT NULL PRIMARY KEY,
            last_run_at VARCHAR(40) NOT NULL,
            last_status VARCHAR(20) NOT NULL,
            last_message VARCHAR(255) NOT NULL DEFAULT "",
            run_count INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function cron_refresh_record_usage(PDO $pdo, string $taskKey, string $status, string $message): void
{
    $now = now_iso();
    $status = strtolower($status) === 'ok' ? 'ok' : 'fail';
    $message = mb_substr(trim($message), 0, 255);

    $updateStmt = $pdo->prepare(
        'UPDATE maintenance_task_usage
         SET last_run_at = :last_run_at,
             last_status = :last_status,
             last_message = :last_message,
             run_count = run_count + 1
         WHERE task_key = :task_key'
    );
    $updateStmt->execute([
        ':last_run_at' => $now,
        ':last_status' => $status,
        ':last_message' => $message,
        ':task_key' => $taskKey,
    ]);

    if ($updateStmt->rowCount() > 0) {
        return;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO maintenance_task_usage (task_key, last_run_at, last_status, last_message, run_count)
         VALUES (:task_key, :last_run_at, :last_status, :last_message, 1)'
    );

    try {
        $insertStmt->execute([
            ':task_key' => $taskKey,
            ':last_run_at' => $now,
            ':last_status' => $status,
            ':last_message' => $message,
        ]);
    } catch (Throwable $e) {
        $updateStmt->execute([
            ':last_run_at' => $now,
            ':last_status' => $status,
            ':last_message' => $message,
            ':task_key' => $taskKey,
        ]);
    }
}

$options = cron_refresh_parse_args($argv ?? []);
$hours = max(1, (int) ($options['hours'] ?? 23));
$dryRun = !empty($options['dry-run']);
$threshold = gmdate('c', time() - ($hours * 3600));
$now = now_iso();

$summary = '';
$status = 'fail';

try {
    init_schema($pdo);
    cron_refresh_ensure_usage_table($pdo);

    if ($dryRun) {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM products
             WHERE status = "published"
               AND (last_synced_at IS NULL OR last_synced_at < :threshold)'
        );
        $countStmt->execute([':threshold' => $threshold]);
        $affected = (int) $countStmt->fetchColumn();
        $summary = 'Dry run only. Products pending refresh: ' . $affected . '. Threshold: ' . $hours . 'h.';
        $status = 'ok';
    } else {
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
        $affected = $stmt->rowCount();
        $summary = 'Sync labels refreshed. Products updated: ' . $affected . '. Threshold: ' . $hours . 'h.';
        $status = 'ok';
    }
} catch (Throwable $e) {
    $summary = 'Refresh failed: ' . $e->getMessage();
}

try {
    cron_refresh_record_usage($pdo, 'refresh_sync_cli', $status, $summary);
    cron_refresh_record_usage($pdo, 'refresh_sync_labels', $status, $summary);
} catch (Throwable $e) {
    echo '[WARN] Usage tracking update failed: ' . $e->getMessage() . PHP_EOL;
}

echo $summary . PHP_EOL;
exit($status === 'ok' ? 0 : 1);
