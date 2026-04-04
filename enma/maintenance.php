<?php

declare(strict_types=1);

/**
 * Maintenance handler for ENMA admin panel
 * Handles maintenance tasks and advanced operations
 */

if (!$authenticated) {
    return;
}

// Advanced maintenance tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_advanced_run') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } elseif (!$advancedEnabled) {
        $errors[] = 'Advanced mode is disabled. Set ENMA_ADVANCED_KEY in .env first.';
    } else {
        $task = trim((string) ($_POST['task'] ?? ''));
        $advancedKey = (string) ($_POST['advanced_key'] ?? '');
        $confirmText = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));
        $expectedConfirm = 'RUN ' . strtoupper($task);

        if (!hash_equals(ENMA_ADVANCED_KEY, $advancedKey)) {
            $errors[] = 'Advanced key is invalid.';
        }
        if ($confirmText !== $expectedConfirm) {
            $errors[] = 'Invalid confirmation text. Use exactly: ' . $expectedConfirm;
        }

        $taskMap = [
            'refresh_sync_cli' => __DIR__ . '/../scripts/cron_refresh.php',
            'reseed_real_catalog' => __DIR__ . '/../scripts/seed_real_catalog.php',
            'seed_more_products' => __DIR__ . '/../scripts/seed_more_products.php',
        ];

        if (!isset($taskMap[$task])) {
            $errors[] = 'Unknown advanced task.';
        }
        

        if ($errors === []) {
            if (!defined('ENMA_ALLOW_WEB_RUN')) {
                define('ENMA_ALLOW_WEB_RUN', true);
            }

            $scriptPath = realpath($taskMap[$task] ?? '');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'Advanced task completed: ' . $task;
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'Advanced task failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Standard maintenance tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_run') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $task = trim((string) ($_POST['task'] ?? ''));

        if ($task === 'normalize_affiliate_urls') {
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

            $flash = "Affiliate normalization done. Checked: {$checked} | Updated: {$updated}";
            $maintenanceLog[] = 'Task: normalize_affiliate_urls';
            $maintenanceLog[] = 'Tag: ' . AMAZON_ASSOCIATE_TAG;
        } elseif ($task === 'update_db_schema') {
            $scriptPath = realpath(__DIR__ . '/../scripts/update_db_schema.php');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'DB schema updater completed.';
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                    $maintenanceLog[] = 'Task: update_db_schema';
                    $maintenanceLog[] = 'Mode: idempotent (CREATE IF NOT EXISTS)';
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'DB schema updater failed: ' . $e->getMessage();
                }
            }
        } elseif ($task === 'migrate_guides_to_db') {
            $scriptPath = realpath(__DIR__ . '/../scripts/migrate_guides_to_db.php');
            if ($scriptPath === false) {
                $errors[] = 'Migration script not found.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'Migration to DB completed.';
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'Migration failed: ' . $e->getMessage();
                }
            }
        } elseif ($task === 'refresh_sync_labels') {
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
            $flash = "Sync labels refreshed. Products updated: {$affected}";
            $maintenanceLog[] = 'Task: refresh_sync_labels';
            $maintenanceLog[] = 'Threshold: 23h';
        } elseif ($task === 'fix_product_images') {
            $scriptPath = realpath(__DIR__ . '/../scripts/fix_product_images.php');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'Image fix completed.';
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                    $maintenanceLog[] = 'Task: fix_product_images';
                    $maintenanceLog[] = 'Source: Amazon product page scrape + safe placeholder';
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'Image fix failed: ' . $e->getMessage();
                }
            }
        } elseif ($task === 'export_db_schema') {
            $scriptPath = realpath(__DIR__ . '/../scripts/export_db_schema.php');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'Database schema exported successfully.';
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                    $maintenanceLog[] = 'Task: export_db_schema';
                    $maintenanceLog[] = 'Output: /workspace/db_schema.sql';
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'Schema export failed: ' . $e->getMessage();
                }
            }
        } elseif ($task === 'generate_migration') {
            $scriptPath = realpath(__DIR__ . '/../scripts/generate_migration.php');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'Migration script generated. Check scripts folder for the new file.';
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                    $maintenanceLog[] = 'Task: generate_migration';
                    $maintenanceLog[] = 'Compares DB with db_schema.sql and creates migration file';
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'Migration generation failed: ' . $e->getMessage();
                }
            }
        } else {
            $errors[] = 'Unknown maintenance task.';
        }
    }
}
