<?php

declare(strict_types=1);

/**
 * ENMA Maintenance Handler
 * 
 * Handles all maintenance-related operations
 */

require_once __DIR__ . '/helpers.php';

/**
 * Run standard maintenance task
 */
function enma_run_maintenance_task(string $task, array &$errors, array &$log): ?string
{
    global $pdo;
    
    if ($task === 'normalize_affiliate_urls') {
        return enma_maintenance_normalize_affiliate($pdo, $log);
    } elseif ($task === 'update_db_schema') {
        return enma_maintenance_update_db_schema($log, $errors);
    } elseif ($task === 'refresh_sync_labels') {
        return enma_maintenance_refresh_sync_labels($pdo, $log);
    } elseif ($task === 'fix_product_images') {
        return enma_maintenance_fix_product_images($log, $errors);
    } else {
        $errors[] = 'Unknown maintenance task.';
        return null;
    }
}

/**
 * Normalize affiliate URLs for all products
 */
function enma_maintenance_normalize_affiliate(PDO $pdo, array &$log): string
{
    $stmt = $pdo->query('SELECT id, affiliate_url FROM products');
    $rows = $stmt->fetchAll();
    $updated = 0;
    $checked = 0;

    $updateStmt = $pdo->prepare(
        'UPDATE products
         SET affiliate_url = :affiliate_url, updated_at = :updated_at
         WHERE id = :id'
    );

    foreach ($rows as $row) {
        $checked++;
        $id = (int) ($row['id'] ?? 0);
        $current = (string) ($row['affiliate_url'] ?? '');
        $normalized = amazon_affiliate_url($current);

        if ($id <= 0 || $normalized === '' || $normalized === $current) {
            continue;
        }

        $updateStmt->execute([
            ':affiliate_url' => $normalized,
            ':updated_at' => now_iso(),
            ':id' => $id,
        ]);
        $updated++;
    }

    $log[] = 'Task: normalize_affiliate_urls';
    $log[] = 'Tag: ' . AMAZON_ASSOCIATE_TAG;
    
    return "Affiliate normalization done. Checked: {$checked} | Updated: {$updated}";
}

/**
 * Update database schema
 */
function enma_maintenance_update_db_schema(array &$log, array &$errors): ?string
{
    $scriptPath = realpath(__DIR__ . '/../../scripts/update_db_schema.php');
    $scriptsRoot = realpath(__DIR__ . '/../../scripts');

    if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
        $errors[] = 'Invalid script path.';
        return null;
    }

    ob_start();
    try {
        require $scriptPath;
        $output = trim((string) ob_get_clean());
        if ($output !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                if (trim((string) $line) !== '') {
                    $log[] = (string) $line;
                }
            }
        }
        $log[] = 'Task: update_db_schema';
        $log[] = 'Mode: idempotent (CREATE IF NOT EXISTS)';
        return 'DB schema updater completed.';
    } catch (Throwable $e) {
        ob_end_clean();
        $errors[] = 'DB schema updater failed: ' . $e->getMessage();
        return null;
    }
}

/**
 * Refresh sync labels for products
 */
function enma_maintenance_refresh_sync_labels(PDO $pdo, array &$log): string
{
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
    $affected = $stmt->rowCount();
    
    $log[] = 'Task: refresh_sync_labels';
    $log[] = 'Threshold: 23h';
    
    return "Sync labels refreshed. Products updated: {$affected}";
}

/**
 * Fix product images
 */
function enma_maintenance_fix_product_images(array &$log, array &$errors): ?string
{
    $scriptPath = realpath(__DIR__ . '/../../scripts/fix_product_images.php');
    $scriptsRoot = realpath(__DIR__ . '/../../scripts');

    if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
        $errors[] = 'Invalid script path.';
        return null;
    }

    ob_start();
    try {
        require $scriptPath;
        $output = trim((string) ob_get_clean());
        if ($output !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                if (trim((string) $line) !== '') {
                    $log[] = (string) $line;
                }
            }
        }
        $log[] = 'Task: fix_product_images';
        $log[] = 'Source: Amazon product page scrape + safe placeholder';
        return 'Image fix completed.';
    } catch (Throwable $e) {
        ob_end_clean();
        $errors[] = 'Image fix failed: ' . $e->getMessage();
        return null;
    }
}

/**
 * Get database table statistics
 */
function enma_get_db_stats(): array
{
    global $pdo;
    
    $tableNames = ['products', 'page_views', 'page_view_hits', 'outbound_clicks', 'posts'];
    $stats = [];
    
    foreach ($tableNames as $tableName) {
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $tableName)->fetchColumn();
        } catch (Throwable $e) {
            $count = -1;
        }
        $stats[] = ['name' => $tableName, 'rows' => $count];
    }
    
    return $stats;
}
