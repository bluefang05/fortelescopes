<?php

declare(strict_types=1);

/**
 * Maintenance handler for ENMA admin panel
 * Handles maintenance tasks and advanced operations
 */

if (!$authenticated) {
    return;
}

if (!function_exists('enma_maintenance_resolve_script')) {
    function enma_maintenance_resolve_script(string $relativePath): ?string
    {
        $scriptPath = realpath(__DIR__ . '/../' . ltrim($relativePath, '/\\'));
        $scriptsRoot = realpath(__DIR__ . '/../scripts');

        if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
            return null;
        }

        return $scriptPath;
    }
}

if (!function_exists('enma_maintenance_init_usage_table')) {
    function enma_maintenance_init_usage_table(PDO $pdo): void
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
}

if (!function_exists('enma_maintenance_record_usage')) {
    function enma_maintenance_record_usage(PDO $pdo, string $taskKey, string $status, string $message = ''): void
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
}

$maintenanceTaskMeta = [
    'refresh_sync_labels' => [
        'label' => 'Refresh Sync Labels',
        'description' => 'Marca productos publicados como sincronizados recientemente.',
        'frequency' => 'Daily',
        'group' => 'daily',
    ],
    'normalize_affiliate_urls' => [
        'label' => 'Normalize Affiliate URLs',
        'description' => 'Corrige URLs Amazon para asegurar el tag de afiliado.',
        'frequency' => 'Daily',
        'group' => 'daily',
    ],
    'fix_product_images' => [
        'label' => 'Fix Product Images',
        'description' => 'Repara imágenes faltantes o rotas con fallback seguro.',
        'frequency' => 'Daily',
        'group' => 'daily',
        'script' => 'scripts/fix_product_images.php',
    ],
    'update_db_schema' => [
        'label' => 'Update DB Schema',
        'description' => 'Aplica cambios seguros a tablas existentes (idempotente).',
        'frequency' => 'Weekly',
        'group' => 'weekly',
        'script' => 'scripts/update_db_schema.php',
    ],
    'export_db_schema' => [
        'label' => 'Export Current DB Schema',
        'description' => 'Actualiza db_schema.sql con el estado actual de la DB.',
        'frequency' => 'Weekly',
        'group' => 'weekly',
        'script' => 'scripts/export_db_schema.php',
    ],
    'generate_migration' => [
        'label' => 'Generate Migration Script',
        'description' => 'Compara DB vs schema y crea script de migracion.',
        'frequency' => 'Weekly',
        'group' => 'weekly',
        'script' => 'scripts/generate_migration.php',
    ],
    'migrate_guides_to_db' => [
        'label' => 'Migrate Guides to DB',
        'description' => 'Migra contenido de guias legacy a tabla posts.',
        'frequency' => 'One time',
        'group' => 'as_needed',
        'script' => 'scripts/migrate_guides_to_db.php',
        'single_use' => true,
    ],
    'seed_more_products' => [
        'label' => 'Add New Seed Products (safe)',
        'description' => 'Agrega productos nuevos sin tocar los existentes.',
        'frequency' => 'As needed',
        'group' => 'as_needed',
        'script' => 'scripts/seed_more_products.php',
    ],
];

$advancedTaskMeta = [
    'refresh_sync_cli' => [
        'label' => 'Refresh Sync Labels (script)',
        'description' => 'Version CLI de refresh para grandes lotes.',
        'frequency' => 'As needed',
        'script' => 'scripts/cron_refresh.php',
    ],
    'reseed_real_catalog' => [
        'label' => 'Reseed Real Catalog',
        'description' => 'Resiembra catalogo real; puede archivar/sobrescribir entradas.',
        'frequency' => 'One time / supervised',
        'script' => 'scripts/seed_real_catalog.php',
        'single_use' => true,
    ],
];

$availableMaintenanceTasks = [];
foreach ($maintenanceTaskMeta as $taskKey => $taskConfig) {
    $taskConfig['script_path'] = null;
    $isAvailable = true;
    if (isset($taskConfig['script'])) {
        $scriptPath = enma_maintenance_resolve_script((string) $taskConfig['script']);
        if ($scriptPath === null) {
            $isAvailable = false;
        } else {
            $taskConfig['script_path'] = $scriptPath;
        }
    }

    if ($isAvailable) {
        $availableMaintenanceTasks[$taskKey] = $taskConfig;
    }
}

$availableAdvancedTasks = [];
foreach ($advancedTaskMeta as $taskKey => $taskConfig) {
    $scriptPath = enma_maintenance_resolve_script((string) $taskConfig['script']);
    if ($scriptPath === null) {
        continue;
    }
    $taskConfig['script_path'] = $scriptPath;
    $availableAdvancedTasks[$taskKey] = $taskConfig;
}

$maintenanceUsageMap = [];
try {
    enma_maintenance_init_usage_table($pdo);
    $usageRows = $pdo->query(
        'SELECT task_key, last_run_at, last_status, last_message, run_count
         FROM maintenance_task_usage'
    )->fetchAll();
    foreach ($usageRows as $usageRow) {
        $maintenanceUsageMap[(string) $usageRow['task_key']] = $usageRow;
    }
} catch (Throwable $e) {
    $maintenanceLog[] = 'Usage tracking disabled: ' . $e->getMessage();
}

// Advanced maintenance tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_advanced_run') {
    $advancedTask = trim((string) ($_POST['task'] ?? ''));
    $advancedRunOk = false;
    $advancedRunMessage = '';
    $advancedTaskMetaConfig = $availableAdvancedTasks[$advancedTask] ?? null;

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } elseif (!$advancedEnabled) {
        $errors[] = 'Advanced mode is disabled. Set ENMA_ADVANCED_KEY in .env first.';
    } elseif (!isset($availableAdvancedTasks[$advancedTask])) {
        $errors[] = 'Unknown or unavailable advanced task.';
    } elseif (
        is_array($advancedTaskMetaConfig)
        && !empty($advancedTaskMetaConfig['single_use'])
        && ((int) ($maintenanceUsageMap[$advancedTask]['run_count'] ?? 0) > 0)
    ) {
        $errors[] = 'This advanced task is single-use and was already executed.';
    } else {
        $advancedKey = (string) ($_POST['advanced_key'] ?? '');
        $confirmText = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));
        $expectedConfirm = 'RUN ' . strtoupper($advancedTask);

        if (!hash_equals(ENMA_ADVANCED_KEY, $advancedKey)) {
            $errors[] = 'Advanced key is invalid.';
        }
        if ($confirmText !== $expectedConfirm) {
            $errors[] = 'Invalid confirmation text. Use exactly: ' . $expectedConfirm;
        }

        if ($errors === []) {
            if (!defined('ENMA_ALLOW_WEB_RUN')) {
                define('ENMA_ALLOW_WEB_RUN', true);
            }

            $scriptPath = (string) ($availableAdvancedTasks[$advancedTask]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Advanced task completed: ' . $advancedTask;
                $advancedRunOk = true;
                $advancedRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                }
            } catch (Throwable $e) {
                ob_end_clean();
                $advancedRunMessage = 'Advanced task failed: ' . $e->getMessage();
                $errors[] = $advancedRunMessage;
            }
        }
    }

    if ($advancedTask !== '' && isset($advancedTaskMeta[$advancedTask])) {
        try {
            enma_maintenance_record_usage($pdo, $advancedTask, $advancedRunOk ? 'ok' : 'fail', $advancedRunMessage);
        } catch (Throwable $e) {
            $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
        }
    }
}

// Standard maintenance tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_run') {
    $task = trim((string) ($_POST['task'] ?? ''));
    $taskKnown = isset($availableMaintenanceTasks[$task]);
    $taskRunOk = false;
    $taskRunMessage = '';
    $taskMetaConfig = $availableMaintenanceTasks[$task] ?? null;

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        $taskRunMessage = 'Invalid request token.';
    } elseif (!$taskKnown) {
        $errors[] = 'Unknown or unavailable maintenance task.';
        $taskRunMessage = 'Unknown or unavailable maintenance task.';
    } elseif (
        is_array($taskMetaConfig)
        && !empty($taskMetaConfig['single_use'])
        && ((int) ($maintenanceUsageMap[$task]['run_count'] ?? 0) > 0)
    ) {
        $errors[] = 'This maintenance task is single-use and was already executed.';
        $taskRunMessage = 'Single-use task already executed.';
    } else {
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
            $taskRunOk = true;
            $taskRunMessage = $flash;
            $maintenanceLog[] = 'Task: normalize_affiliate_urls';
            $maintenanceLog[] = 'Tag: ' . AMAZON_ASSOCIATE_TAG;
        } elseif ($task === 'update_db_schema') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'DB schema updater completed.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
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
                $taskRunMessage = 'DB schema updater failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'migrate_guides_to_db') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Migration to DB completed.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                }
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Migration failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
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
            $taskRunOk = true;
            $taskRunMessage = $flash;
            $maintenanceLog[] = 'Task: refresh_sync_labels';
            $maintenanceLog[] = 'Threshold: 23h';
        } elseif ($task === 'fix_product_images') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Image fix completed.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
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
                $taskRunMessage = 'Image fix failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'export_db_schema') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Database schema exported successfully.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
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
                $taskRunMessage = 'Schema export failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'generate_migration') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Migration script generated. Check scripts folder for the new file.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
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
                $taskRunMessage = 'Migration generation failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'seed_more_products') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                if (!defined('ENMA_ALLOW_WEB_RUN')) {
                    define('ENMA_ALLOW_WEB_RUN', true);
                }
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'New products seed completed.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                }
                $maintenanceLog[] = 'Task: seed_more_products';
                $maintenanceLog[] = 'Mode: safe add (INSERT OR IGNORE)';
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Seed more products failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        }
    }

    if ($taskKnown) {
        try {
            enma_maintenance_record_usage($pdo, $task, $taskRunOk ? 'ok' : 'fail', $taskRunMessage);
        } catch (Throwable $e) {
            $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
        }
    }
}
