<?php

declare(strict_types=1);

/**
 * Maintenance handler for ENMA admin panel
 * Handles maintenance tasks and advanced operations
 */

if (!$authenticated) {
    return;
}

// -----------------------------------------------------------------------------
// Safe availability checker configuration (explicitly requested for maintenance)
// -----------------------------------------------------------------------------
$availabilityCheckerDbHost = DB_HOST;
$availabilityCheckerDbName = DB_NAME;
$availabilityCheckerDbUser = DB_USER;
$availabilityCheckerDbPass = DB_PASS;
$availabilityCheckerTable = 'products';

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

if (!function_exists('enma_maintenance_init_product_link_checks_table')) {
    function enma_maintenance_init_product_link_checks_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS product_link_checks (
                product_id INT UNSIGNED NOT NULL PRIMARY KEY,
                asin VARCHAR(32) NOT NULL DEFAULT "",
                affiliate_url TEXT NULL,
                http_status INT NOT NULL DEFAULT 0,
                state VARCHAR(20) NOT NULL DEFAULT "unknown",
                final_url TEXT NULL,
                error_message VARCHAR(255) NOT NULL DEFAULT "",
                checked_at VARCHAR(40) NOT NULL
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

if (!function_exists('enma_maintenance_run_cli')) {
    function enma_maintenance_resolve_php_cli_binary(): string
    {
        $candidates = [];

        $envCli = trim((string) getenv('PHP_CLI'));
        if ($envCli !== '') {
            $candidates[] = $envCli;
        }

        // Prefer plain "php" first (usually points to CLI binary in PATH).
        $candidates[] = 'php';

        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && trim(PHP_BINARY) !== '') {
            $candidates[] = trim(PHP_BINARY);
        }

        $seen = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || isset($seen[$candidate])) {
                continue;
            }
            $seen[$candidate] = true;

            $probeCmd = escapeshellarg($candidate) . ' -v 2>&1';
            $probeOutput = [];
            $probeExit = 1;
            @exec($probeCmd, $probeOutput, $probeExit);
            if ($probeExit === 0) {
                return $candidate;
            }
        }

        return 'php';
    }

    function enma_maintenance_run_cli(string $scriptPath, array $args): array
    {
        $phpBinary = enma_maintenance_resolve_php_cli_binary();

        $parts = [escapeshellarg($phpBinary), escapeshellarg($scriptPath)];
        foreach ($args as $name => $value) {
            $argName = trim((string) $name);
            if ($argName === '') {
                continue;
            }
            $parts[] = '--' . $argName . '=' . escapeshellarg((string) $value);
        }

        $command = implode(' ', $parts) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return [
            'php_binary' => $phpBinary,
            'command' => $command,
            'output_lines' => $output,
            'exit_code' => $exitCode,
        ];
    }
}

if (!function_exists('enma_maintenance_table_exists')) {
    function enma_maintenance_table_exists(PDO $pdo, string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = :schema
               AND table_name = :table_name
             LIMIT 1'
        );
        $stmt->execute([
            ':schema' => DB_NAME,
            ':table_name' => $tableName,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('enma_maintenance_column_exists')) {
    function enma_maintenance_column_exists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $tableName = trim($tableName);
        $columnName = trim($columnName);
        if ($tableName === '' || $columnName === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = :schema
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1'
        );
        $stmt->execute([
            ':schema' => DB_NAME,
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('enma_maintenance_init_job_runs_table')) {
    function enma_maintenance_init_job_runs_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS maintenance_job_runs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                task_key VARCHAR(64) NOT NULL,
                status VARCHAR(20) NOT NULL,
                message VARCHAR(255) NOT NULL DEFAULT "",
                duration_seconds DECIMAL(10,3) NOT NULL DEFAULT 0,
                metrics_json TEXT NULL,
                created_at VARCHAR(40) NOT NULL,
                INDEX idx_task_created (task_key, created_at),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('enma_maintenance_record_job_run')) {
    function enma_maintenance_record_job_run(
        PDO $pdo,
        string $taskKey,
        string $status,
        string $message,
        float $durationSeconds,
        array $metrics = []
    ): void {
        $taskKey = trim($taskKey);
        if ($taskKey === '') {
            return;
        }
        $status = strtolower($status) === 'ok' ? 'ok' : 'fail';
        $message = mb_substr(trim($message), 0, 255);
        $durationSeconds = max(0.0, $durationSeconds);
        $metricsPayload = $metrics !== [] ? json_encode($metrics, JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO maintenance_job_runs (
                task_key, status, message, duration_seconds, metrics_json, created_at
             ) VALUES (
                :task_key, :status, :message, :duration_seconds, :metrics_json, :created_at
             )'
        );
        $stmt->execute([
            ':task_key' => $taskKey,
            ':status' => $status,
            ':message' => $message,
            ':duration_seconds' => number_format($durationSeconds, 3, '.', ''),
            ':metrics_json' => $metricsPayload,
            ':created_at' => now_iso(),
        ]);
    }
}

if (!function_exists('enma_maintenance_parse_numeric_metrics_from_lines')) {
    function enma_maintenance_parse_numeric_metrics_from_lines(array $lines): array
    {
        $metrics = [];
        foreach ($lines as $line) {
            $text = trim((string) $line);
            if ($text === '') {
                continue;
            }
            if (preg_match('/^([A-Za-z0-9 ()\/_-]+):\s*(-?\d+)/', $text, $m) !== 1) {
                continue;
            }
            $label = strtolower(trim((string) $m[1]));
            $value = (int) $m[2];

            $key = $label;
            $key = str_replace(['(', ')', '/', '-'], ' ', $key);
            $key = preg_replace('/[^a-z0-9 ]+/', ' ', $key) ?? $key;
            $key = preg_replace('/\s+/', '_', trim($key)) ?? $key;
            if ($key === '') {
                continue;
            }
            $metrics[$key] = $value;
        }
        return $metrics;
    }
}

if (!function_exists('enma_maintenance_safe_int_metric')) {
    function enma_maintenance_safe_int_metric(array $metrics, string $key): int
    {
        return isset($metrics[$key]) && is_numeric((string) $metrics[$key]) ? (int) $metrics[$key] : 0;
    }
}

if (!function_exists('enma_maintenance_safe_table_name')) {
    function enma_maintenance_safe_table_name(string $tableName): string
    {
        $tableName = trim($tableName);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            throw new InvalidArgumentException('Invalid table name for safe availability checker.');
        }

        return '`' . $tableName . '`';
    }
}

if (!function_exists('enma_maintenance_init_availability_columns')) {
    function enma_maintenance_init_availability_columns(PDO $pdo, string $tableName, array &$logLines = []): void
    {
        $safeTable = enma_maintenance_safe_table_name($tableName);
        $required = [
            'is_available' => 'ALTER TABLE ' . $safeTable . ' ADD COLUMN is_available TINYINT(1) NOT NULL DEFAULT 1',
            'last_checked_at' => 'ALTER TABLE ' . $safeTable . ' ADD COLUMN last_checked_at DATETIME NULL DEFAULT NULL',
            'check_priority' => 'ALTER TABLE ' . $safeTable . ' ADD COLUMN check_priority TINYINT NOT NULL DEFAULT 0',
        ];

        foreach ($required as $column => $sql) {
            if (enma_maintenance_column_exists($pdo, $tableName, $column)) {
                continue;
            }
            $pdo->exec($sql);
            $logLines[] = 'Schema auto-fix: added column ' . $column . ' on ' . $tableName;
        }
    }
}

if (!function_exists('enma_maintenance_detect_product_url_column')) {
    function enma_maintenance_detect_product_url_column(PDO $pdo, string $tableName): ?string
    {
        if (enma_maintenance_column_exists($pdo, $tableName, 'amazon_url')) {
            return 'amazon_url';
        }
        if (enma_maintenance_column_exists($pdo, $tableName, 'affiliate_url')) {
            return 'affiliate_url';
        }
        return null;
    }
}

if (!function_exists('enma_maintenance_availability_log_path')) {
    function enma_maintenance_availability_log_path(): string
    {
        return __DIR__ . '/../availability_log.txt';
    }
}

if (!function_exists('enma_maintenance_append_availability_log')) {
    function enma_maintenance_append_availability_log(string $line): void
    {
        $payload = '[' . gmdate('Y-m-d H:i:s') . ' UTC] ' . trim($line) . PHP_EOL;
        @file_put_contents(enma_maintenance_availability_log_path(), $payload, FILE_APPEND);
    }
}

if (!function_exists('enma_maintenance_pick_next_product_for_availability')) {
    function enma_maintenance_pick_next_product_for_availability(PDO $pdo, string $tableName, string $urlColumn): ?array
    {
        $safeTable = enma_maintenance_safe_table_name($tableName);
        $safeUrlColumn = '`' . $urlColumn . '`';
        $sql = 'SELECT id, asin, title, status, is_available, last_checked_at, ' . $safeUrlColumn . ' AS product_url
                FROM ' . $safeTable . '
                ORDER BY (last_checked_at IS NULL) DESC, last_checked_at ASC, id ASC
                LIMIT 1';
        $stmt = $pdo->query($sql);
        $row = $stmt !== false ? $stmt->fetch() : false;
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('enma_maintenance_safe_user_agents')) {
    function enma_maintenance_safe_user_agents(): array
    {
        return [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:137.0) Gecko/20100101 Firefox/137.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.4; rv:137.0) Gecko/20100101 Firefox/137.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/135.0.0.0 Chrome/135.0.0.0 Safari/537.36',
        ];
    }
}

if (!function_exists('enma_maintenance_safe_check_next_product_once')) {
    function enma_maintenance_safe_check_next_product_once(PDO $pdo, string $tableName, string $urlColumn): array
    {
        $next = enma_maintenance_pick_next_product_for_availability($pdo, $tableName, $urlColumn);
        if ($next === null) {
            return [
                'ok' => false,
                'message' => 'No product found to check.',
                'next' => null,
            ];
        }

        $productId = (int) ($next['id'] ?? 0);
        $url = trim((string) ($next['product_url'] ?? ''));
        if ($productId <= 0 || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $updateStmt = $pdo->prepare(
                'UPDATE ' . enma_maintenance_safe_table_name($tableName) . '
                 SET last_checked_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([':id' => $productId]);
            $error = 'invalid_url';
            enma_maintenance_append_availability_log(
                'product_id=' . $productId . ' | url=' . $url . ' | new_status=unchanged | error=' . $error
            );
            return [
                'ok' => false,
                'message' => 'Product has invalid URL; status preserved.',
                'product_id' => $productId,
                'url' => $url,
                'new_status' => null,
                'error' => $error,
            ];
        }

        // Required safety delay: one product per execution with human-like pause.
        sleep(random_int(3, 6));

        if (!function_exists('curl_init')) {
            $updateStmt = $pdo->prepare(
                'UPDATE ' . enma_maintenance_safe_table_name($tableName) . '
                 SET last_checked_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([':id' => $productId]);
            $error = 'curl_not_available';
            enma_maintenance_append_availability_log(
                'product_id=' . $productId . ' | url=' . $url . ' | new_status=unchanged | error=' . $error
            );
            return [
                'ok' => false,
                'message' => 'cURL is not available on this host.',
                'product_id' => $productId,
                'url' => $url,
                'new_status' => null,
                'error' => $error,
            ];
        }

        $userAgents = enma_maintenance_safe_user_agents();
        $userAgent = $userAgents[array_rand($userAgents)];

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'ok' => false,
                'message' => 'Could not initialize cURL.',
                'product_id' => $productId,
                'url' => $url,
                'new_status' => null,
                'error' => 'curl_init_failed',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = trim((string) curl_error($ch));
        curl_close($ch);

        $bodyText = is_string($body) ? strtolower($body) : '';
        $error = '';
        $newStatus = null;

        if ($curlError !== '') {
            $error = 'curl_error:' . $curlError;
        } elseif ($statusCode === 503 || $statusCode === 429) {
            $error = 'http_' . $statusCode;
        } elseif (strpos($bodyText, 'captcha') !== false || strpos($bodyText, 'robot check') !== false || strpos($bodyText, 'enter the characters you see below') !== false) {
            $error = 'captcha_or_bot_challenge';
        } else {
            $unavailableMarkers = [
                'out of stock',
                'currently unavailable',
                'see all buying options',
            ];
            $availableMarkers = [
                'in stock',
                'add to cart',
                'buy now',
                'buybox',
                'ships from and sold by',
            ];

            foreach ($unavailableMarkers as $marker) {
                if (strpos($bodyText, $marker) !== false) {
                    $newStatus = 0;
                    break;
                }
            }

            if ($newStatus === null) {
                foreach ($availableMarkers as $marker) {
                    if (strpos($bodyText, $marker) !== false) {
                        $newStatus = 1;
                        break;
                    }
                }
            }

            if ($newStatus === null) {
                $error = 'stock_signal_not_detected';
            }
        }

        if ($newStatus === null) {
            $updateStmt = $pdo->prepare(
                'UPDATE ' . enma_maintenance_safe_table_name($tableName) . '
                 SET last_checked_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([':id' => $productId]);
        } else {
            $updateStmt = $pdo->prepare(
                'UPDATE ' . enma_maintenance_safe_table_name($tableName) . '
                 SET is_available = :is_available,
                     last_checked_at = NOW()
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':is_available' => $newStatus,
                ':id' => $productId,
            ]);
        }

        enma_maintenance_append_availability_log(
            'product_id=' . $productId
            . ' | url=' . $url
            . ' | new_status=' . ($newStatus === null ? 'unchanged' : (string) $newStatus)
            . ' | error=' . ($error !== '' ? $error : '-')
            . ' | http=' . $statusCode
        );

        return [
            'ok' => $newStatus !== null,
            'message' => $newStatus === null
                ? 'Availability preserved due to request/parser error.'
                : 'Availability updated successfully.',
            'product_id' => $productId,
            'url' => $url,
            'new_status' => $newStatus,
            'error' => $error,
            'http_status' => $statusCode,
            'user_agent' => $userAgent,
        ];
    }
}

if (!function_exists('enma_maintenance_build_products_export_sql')) {
    function enma_maintenance_build_products_export_sql(PDO $pdo): array
    {
        $generatedAt = gmdate('Y-m-d H:i:s');
        $rows = $pdo->query(
            'SELECT
                asin, slug, title, description, category_slug, category_name,
                price_amount, price_currency, image_url, affiliate_url, status,
                last_synced_at, created_at, updated_at
             FROM products
             ORDER BY id ASC'
        )->fetchAll();

        $sql = "-- Fortelescopes Products Export\n";
        $sql .= "-- Generated on {$generatedAt} UTC\n\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        if ($rows === []) {
            $sql .= "-- No product rows found.\n";
        } else {
            $columns = [
                'asin', 'slug', 'title', 'description', 'category_slug', 'category_name',
                'price_amount', 'price_currency', 'image_url', 'affiliate_url', 'status',
                'last_synced_at', 'created_at', 'updated_at',
            ];

            $sql .= "INSERT INTO `products` (`" . implode('`, `', $columns) . "`) VALUES\n";
            $valueLines = [];

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    if ($value === null || $value === '') {
                        $values[] = 'NULL';
                        continue;
                    }

                    if ($column === 'price_amount' && is_numeric((string) $value)) {
                        $values[] = number_format((float) $value, 2, '.', '');
                        continue;
                    }

                    $values[] = $pdo->quote((string) $value);
                }

                $valueLines[] = '    (' . implode(', ', $values) . ')';
            }

            $sql .= implode(",\n", $valueLines) . "\n";
            $sql .= "ON DUPLICATE KEY UPDATE\n";
            $sql .= "    `title` = VALUES(`title`),\n";
            $sql .= "    `description` = VALUES(`description`),\n";
            $sql .= "    `category_slug` = VALUES(`category_slug`),\n";
            $sql .= "    `category_name` = VALUES(`category_name`),\n";
            $sql .= "    `price_amount` = VALUES(`price_amount`),\n";
            $sql .= "    `price_currency` = VALUES(`price_currency`),\n";
            $sql .= "    `image_url` = VALUES(`image_url`),\n";
            $sql .= "    `affiliate_url` = VALUES(`affiliate_url`),\n";
            $sql .= "    `status` = VALUES(`status`),\n";
            $sql .= "    `last_synced_at` = VALUES(`last_synced_at`),\n";
            $sql .= "    `created_at` = VALUES(`created_at`),\n";
            $sql .= "    `updated_at` = VALUES(`updated_at`);\n";
        }

        return [
            'filename' => 'products_export_' . gmdate('Ymd_His') . '.sql',
            'content_type' => 'application/sql; charset=UTF-8',
            'content' => $sql,
            'row_count' => count($rows),
        ];
    }
}

if (!function_exists('enma_maintenance_build_posts_export_json')) {
    function enma_maintenance_build_posts_export_json(PDO $pdo): array
    {
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

        return [
            'filename' => 'posts_table_' . gmdate('Ymd_His') . '.json',
            'content_type' => 'application/json; charset=UTF-8',
            'content' => $payload . PHP_EOL,
            'row_count' => count($rows),
        ];
    }
}

if (!function_exists('enma_maintenance_stream_download')) {
    function enma_maintenance_stream_download(string $filename, string $contentType, string $content): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $fallbackFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download.dat';
        if ($fallbackFilename === '') {
            $fallbackFilename = 'download.dat';
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fallbackFilename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $content;
        exit;
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
    'export_full_database' => [
        'label' => 'Export Full Database',
        'description' => 'Genera backup completo (schema + data) y valida page_views.',
        'frequency' => 'As needed',
        'group' => 'as_needed',
        'script' => 'scripts/export_full_database.php',
    ],
    'prune_old_logs' => [
        'label' => 'Prune Old Logs',
        'description' => 'Limpia logs y tablas historicas antiguas para evitar crecimiento innecesario.',
        'frequency' => 'Weekly',
        'group' => 'weekly',
        'script' => 'scripts/prune_old_logs.php',
    ],
    'check_links' => [
        'label' => 'Check Links',
        'description' => 'Verifica enlaces internos del sitemap y enlaces externos dentro del contenido editorial.',
        'frequency' => 'Weekly',
        'group' => 'weekly',
        'script' => 'scripts/check_links.php',
    ],
    'sync_post_indexation_tracker' => [
        'label' => 'Sync Post Indexation Tracker',
        'description' => 'Actualiza la tabla de seguimiento de indexacion con todos los posts/guias.',
        'frequency' => 'Daily',
        'group' => 'seo',
        'script' => 'scripts/sync_post_indexation_tracker.php',
    ],
    'clean_not_found_products' => [
        'label' => 'Clean Not Found Products',
        'description' => 'Revisa affiliate URLs y archiva productos publicados que devuelven not found (404/410 o página Amazon inexistente).',
        'frequency' => 'As needed',
        'group' => 'as_needed',
        'script' => 'scripts/clean_not_found_products.php',
    ],
    'delete_not_found_products' => [
        'label' => 'Delete Not Found Products',
        'description' => 'Elimina permanentemente productos marcados como not_found en product_link_checks.',
        'frequency' => 'As needed',
        'group' => 'as_needed',
    ],
    'generate_sitemap' => [
        'label' => 'Generate Sitemap',
        'description' => 'Genera sitemap.xml solo con URLs publicas del sitio.',
        'frequency' => 'As needed',
        'group' => 'seo',
        'script' => 'scripts/generate_sitemap.php',
    ],
    'export_products_sql' => [
        'label' => 'Export Products SQL',
        'description' => 'Exporta products a SQL bulk insert reutilizable para una DB futura.',
        'frequency' => 'Weekly',
        'group' => 'weekly',
        'script' => 'scripts/export_products_sql.php',
    ],
    'export_posts_pastebin' => [
        'label' => 'Export Posts Table JSON',
        'description' => 'Exporta la tabla posts completa a JSON (latest + snapshot).',
        'frequency' => 'As needed',
        'group' => 'as_needed',
        'script' => 'scripts/export_posts_pastebin.php',
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

$postAutosaveEnabled = false;
try {
    $postAutosaveEnabled = enma_maintenance_table_exists($pdo, 'post_autosaves');
} catch (Throwable $e) {
    $postAutosaveEnabled = false;
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

$affiliateDraftForm = [
    'auto_mode' => '1',
    'topic' => '',
    'keyword' => '',
    'product' => '',
    'category' => '',
    'model' => 'gemini-2.0-flash',
];
$affiliateDraftResult = null;
$catalogImportForm = [
    'payload' => '',
];
$catalogImportResult = null;
$notFoundReviewRows = [];
$notFoundReviewPage = max(1, (int) ($_GET['nf_review_page'] ?? 1));
$notFoundReviewPerPage = 15;
$notFoundReviewTotal = 0;
$notFoundReviewTotalPages = 1;
$availabilitySchemaFixLog = [];
$availabilityCheckerResult = null;
$availabilityCheckerUnlocked = true;
$availabilityCheckerDashboard = [
    'total_products' => 0,
    'available_products' => 0,
    'unavailable_products' => 0,
    'next_product_url' => '',
    'next_product_id' => 0,
    'last_checked_at' => '',
];
$availabilityCheckerUrlColumn = null;
$maintenanceProgress = [
    'image_last_run' => null,
    'image_last_metrics' => [],
    'image_weekly' => [
        'runs' => 0,
        'checked' => 0,
        'updated' => 0,
        'failed_fetches' => 0,
    ],
    'image_success_rate' => 0.0,
    'image_failure_rate' => 0.0,
    'image_remaining' => 0,
    'image_avg_updates_per_run' => 0.0,
    'image_eta_runs' => null,
    'safe_check_last_run' => null,
    'recent_runs' => [],
];

try {
    enma_maintenance_init_job_runs_table($pdo);
} catch (Throwable $e) {
    $maintenanceLog[] = 'Job metrics table unavailable: ' . $e->getMessage();
}

try {
    enma_maintenance_init_availability_columns($pdo, $availabilityCheckerTable, $availabilitySchemaFixLog);
    $availabilityCheckerUrlColumn = enma_maintenance_detect_product_url_column($pdo, $availabilityCheckerTable);
} catch (Throwable $e) {
    $maintenanceLog[] = 'Availability schema auto-fix failed: ' . $e->getMessage();
}
foreach ($availabilitySchemaFixLog as $schemaLine) {
    $maintenanceLog[] = (string) $schemaLine;
}

try {
    enma_maintenance_init_product_link_checks_table($pdo);
    $countStmt = $pdo->query(
        'SELECT COUNT(*)
         FROM product_link_checks plc
         JOIN products p ON p.id = plc.product_id
         WHERE plc.state IN ("not_found", "warning")'
    );
    $notFoundReviewTotal = (int) $countStmt->fetchColumn();
    $notFoundReviewTotalPages = max(1, (int) ceil(max(0, $notFoundReviewTotal) / $notFoundReviewPerPage));
    $notFoundReviewPage = min($notFoundReviewPage, $notFoundReviewTotalPages);

    $rowsStmt = $pdo->prepare(
        'SELECT
            p.id,
            p.asin,
            p.title,
            p.status,
            p.image_url,
            p.affiliate_url,
            plc.http_status,
            plc.state,
            plc.final_url,
            plc.error_message,
            plc.checked_at
         FROM product_link_checks plc
         JOIN products p ON p.id = plc.product_id
         WHERE plc.state IN ("not_found", "warning")
         ORDER BY plc.checked_at DESC, p.id DESC
         LIMIT :limit OFFSET :offset'
    );
    $rowsStmt->bindValue(':limit', $notFoundReviewPerPage, PDO::PARAM_INT);
    $rowsStmt->bindValue(':offset', ($notFoundReviewPage - 1) * $notFoundReviewPerPage, PDO::PARAM_INT);
    $rowsStmt->execute();
    $notFoundReviewRows = $rowsStmt->fetchAll();
} catch (Throwable $e) {
    $maintenanceLog[] = 'Not-found review unavailable: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'availability_safe_check_next') {
    $taskKey = 'availability_safe_check_next';
    $taskRunOk = false;
    $taskRunMessage = '';
    $taskMetrics = [];
    $startedAt = microtime(true);

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        $taskRunMessage = 'Invalid request token.';
    } elseif ($availabilityCheckerUrlColumn === null) {
        $errors[] = 'No URL column found (expected amazon_url or affiliate_url).';
        $taskRunMessage = 'Missing URL column for safe check.';
    } else {
        try {
            $availabilityCheckerResult = enma_maintenance_safe_check_next_product_once($pdo, $availabilityCheckerTable, $availabilityCheckerUrlColumn);
            $taskRunOk = !empty($availabilityCheckerResult['ok']);
            $taskRunMessage = (string) ($availabilityCheckerResult['message'] ?? ($taskRunOk ? 'Safe check completed.' : 'Safe check failed.'));
            $taskMetrics = [
                'checked' => 1,
                'updated' => $taskRunOk ? 1 : 0,
                'errors' => !empty($availabilityCheckerResult['error']) ? 1 : 0,
                'http_status' => (int) ($availabilityCheckerResult['http_status'] ?? 0),
            ];
            if ($taskRunOk) {
                $flash = $taskRunMessage;
            } else {
                $errors[] = $taskRunMessage;
            }
        } catch (Throwable $e) {
            $taskRunMessage = 'Safe availability check failed: ' . $e->getMessage();
            $errors[] = $taskRunMessage;
        }
    }

    try {
        enma_maintenance_record_usage($pdo, $taskKey, $taskRunOk ? 'ok' : 'fail', $taskRunMessage);
    } catch (Throwable $e) {
        $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
    }
    try {
        enma_maintenance_record_job_run(
            $pdo,
            $taskKey,
            $taskRunOk ? 'ok' : 'fail',
            $taskRunMessage,
            microtime(true) - $startedAt,
            $taskMetrics
        );
    } catch (Throwable $e) {
        $maintenanceLog[] = 'Job run record failed: ' . $e->getMessage();
    }
}

if (!function_exists('enma_maintenance_backups_dir')) {
    function enma_maintenance_backups_dir(): string
    {
        $dataDir = realpath(__DIR__ . '/../data');
        if ($dataDir === false) {
            $dataDir = __DIR__ . '/../data';
        }
        return rtrim($dataDir, '/\\') . DIRECTORY_SEPARATOR . 'backups';
    }
}

if (!function_exists('enma_maintenance_latest_db_backup_path')) {
    function enma_maintenance_latest_db_backup_path(): ?string
    {
        $dir = enma_maintenance_backups_dir();
        if (!is_dir($dir)) {
            return null;
        }
        $preferredLatest = $dir . DIRECTORY_SEPARATOR . 'fortelescopes_data_backup_latest.sql';
        if (is_file($preferredLatest)) {
            return $preferredLatest;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        if ($files === []) {
            return null;
        }
        usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        return $files[0] ?? null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_generate_affiliate_post') {
    $affiliateDraftForm['auto_mode'] = !empty($_POST['auto_mode']) ? '1' : '0';
    $affiliateDraftForm['topic'] = trim((string) ($_POST['topic'] ?? ''));
    $affiliateDraftForm['keyword'] = trim((string) ($_POST['keyword'] ?? ''));
    $affiliateDraftForm['product'] = trim((string) ($_POST['product'] ?? ''));
    $affiliateDraftForm['category'] = trim((string) ($_POST['category'] ?? ''));
    $affiliateDraftForm['model'] = trim((string) ($_POST['model'] ?? ''));
    if ($affiliateDraftForm['model'] === '') {
        $affiliateDraftForm['model'] = 'gemini-2.0-flash';
    }

    $scriptPath = enma_maintenance_resolve_script('scripts/generate_affiliate_post.php');
    $taskKey = 'maintenance_generate_affiliate_post';
    $taskRunOk = false;
    $taskRunMessage = '';

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        $taskRunMessage = 'Invalid request token.';
    } elseif ($scriptPath === null) {
        $errors[] = 'Generator script is unavailable.';
        $taskRunMessage = 'Generator script is unavailable.';
    } elseif (
        $affiliateDraftForm['auto_mode'] !== '1'
        && (
        $affiliateDraftForm['topic'] === ''
        || $affiliateDraftForm['keyword'] === ''
        || $affiliateDraftForm['product'] === ''
        || $affiliateDraftForm['category'] === ''
        )
    ) {
        $errors[] = 'Topic, keyword, product and category are required.';
        $taskRunMessage = 'Missing required generator parameters.';
    } else {
        try {
            $cliArgs = ['model' => $affiliateDraftForm['model']];
            if ($affiliateDraftForm['auto_mode'] === '1') {
                $cliArgs['auto'] = '1';
            } else {
                $cliArgs['topic'] = $affiliateDraftForm['topic'];
                $cliArgs['keyword'] = $affiliateDraftForm['keyword'];
                $cliArgs['product'] = $affiliateDraftForm['product'];
                $cliArgs['category'] = $affiliateDraftForm['category'];
            }
            $run = enma_maintenance_run_cli((string) $scriptPath, $cliArgs);
            $exitCode = (int) ($run['exit_code'] ?? 1);
            $outputLines = array_values(array_filter(array_map('trim', (array) ($run['output_lines'] ?? [])), static fn(string $line): bool => $line !== ''));

            $affiliateDraftResult = [
                'ok' => $exitCode === 0,
                'exit_code' => $exitCode,
                'php_binary' => (string) ($run['php_binary'] ?? ''),
                'output_lines' => $outputLines,
            ];

            if ($exitCode === 0) {
                $flash = 'Affiliate draft generation completed.';
                $taskRunOk = true;
                $taskRunMessage = $flash;
                $maintenanceLog[] = 'Task: maintenance_generate_affiliate_post';
                $maintenanceLog[] = 'Script: scripts/generate_affiliate_post.php';
                $maintenanceLog[] = 'Mode: ' . ($affiliateDraftForm['auto_mode'] === '1' ? 'auto' : 'manual');
                $maintenanceLog[] = 'PHP CLI: ' . (string) ($run['php_binary'] ?? 'php');
                foreach ($outputLines as $line) {
                    $maintenanceLog[] = $line;
                }
            } else {
                $taskRunMessage = 'Affiliate draft generation failed (exit code ' . $exitCode . ').';
                $errors[] = $taskRunMessage;
                $maintenanceLog[] = 'PHP CLI: ' . (string) ($run['php_binary'] ?? 'php');
                foreach ($outputLines as $line) {
                    $maintenanceLog[] = $line;
                }
            }
        } catch (Throwable $e) {
            $taskRunMessage = 'Affiliate draft generation failed: ' . $e->getMessage();
            $errors[] = $taskRunMessage;
        }
    }

    try {
        enma_maintenance_record_usage($pdo, $taskKey, $taskRunOk ? 'ok' : 'fail', $taskRunMessage);
    } catch (Throwable $e) {
        $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_import_catalog_array') {
    $catalogImportForm['payload'] = trim((string) ($_POST['catalog_payload'] ?? ''));
    $taskKey = 'maintenance_import_catalog_array';
    $taskRunOk = false;
    $taskRunMessage = '';
    $scriptPath = enma_maintenance_resolve_script('scripts/seed_real_catalog.php');

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        $taskRunMessage = 'Invalid request token.';
    } elseif ($catalogImportForm['payload'] === '') {
        $errors[] = 'Paste a PHP $products array first.';
        $taskRunMessage = 'Empty catalog payload.';
    } elseif ($scriptPath === null) {
        $errors[] = 'Catalog seed script is unavailable.';
        $taskRunMessage = 'Catalog seed script is unavailable.';
    } else {
        $tmpDir = __DIR__ . '/../data/tmp';
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            $errors[] = 'Could not create temporary directory for payload import.';
            $taskRunMessage = 'Temp directory creation failed.';
        } else {
            $tmpFile = $tmpDir . '/catalog_payload_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.txt';
            try {
                file_put_contents($tmpFile, $catalogImportForm['payload']);
                $run = enma_maintenance_run_cli((string) $scriptPath, ['payload_file' => $tmpFile]);
                $exitCode = (int) ($run['exit_code'] ?? 1);
                $outputLines = array_values(array_filter(array_map('trim', (array) ($run['output_lines'] ?? [])), static fn(string $line): bool => $line !== ''));

                $catalogImportResult = [
                    'ok' => $exitCode === 0,
                    'exit_code' => $exitCode,
                    'php_binary' => (string) ($run['php_binary'] ?? ''),
                    'output_lines' => $outputLines,
                ];

                if ($exitCode === 0) {
                    $flash = 'Catalog import completed and database updated.';
                    $taskRunOk = true;
                    $taskRunMessage = $flash;
                    $catalogImportForm['payload'] = '';
                    $maintenanceLog[] = 'Task: maintenance_import_catalog_array';
                    $maintenanceLog[] = 'Script: scripts/seed_real_catalog.php';
                    $maintenanceLog[] = 'Mode: pasted Claude array';
                    $maintenanceLog[] = 'PHP CLI: ' . (string) ($run['php_binary'] ?? 'php');
                    foreach ($outputLines as $line) {
                        $maintenanceLog[] = $line;
                    }
                } else {
                    $taskRunMessage = 'Catalog import failed (exit code ' . $exitCode . ').';
                    $errors[] = $taskRunMessage;
                    $maintenanceLog[] = 'PHP CLI: ' . (string) ($run['php_binary'] ?? 'php');
                    foreach ($outputLines as $line) {
                        $maintenanceLog[] = $line;
                    }
                }
            } catch (Throwable $e) {
                $taskRunMessage = 'Catalog import failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            } finally {
                if (isset($tmpFile) && is_file($tmpFile)) {
                    @unlink($tmpFile);
                }
            }
        }
    }

    try {
        enma_maintenance_record_usage($pdo, $taskKey, $taskRunOk ? 'ok' : 'fail', $taskRunMessage);
    } catch (Throwable $e) {
        $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_archive_review_product') {
    $taskKey = 'maintenance_archive_review_product';
    $taskRunOk = false;
    $taskRunMessage = '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        $taskRunMessage = 'Invalid request token.';
    } elseif ($productId <= 0) {
        $errors[] = 'Invalid product id.';
        $taskRunMessage = 'Invalid product id.';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE products SET status = "archived", updated_at = :updated_at WHERE id = :id');
            $stmt->execute([
                ':updated_at' => now_iso(),
                ':id' => $productId,
            ]);
            enma_record_activity($pdo, 'product.archive.from_review', 'product', $productId, []);
            $flash = 'Product archived from not-found review.';
            $taskRunOk = true;
            $taskRunMessage = $flash;
        } catch (Throwable $e) {
            $taskRunMessage = 'Archive from review failed: ' . $e->getMessage();
            $errors[] = $taskRunMessage;
        }
    }

    try {
        enma_maintenance_record_usage($pdo, $taskKey, $taskRunOk ? 'ok' : 'fail', $taskRunMessage);
    } catch (Throwable $e) {
        $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_delete_review_product') {
    $taskKey = 'maintenance_delete_review_product';
    $taskRunOk = false;
    $taskRunMessage = '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        $taskRunMessage = 'Invalid request token.';
    } elseif ($productId <= 0) {
        $errors[] = 'Invalid product id.';
        $taskRunMessage = 'Invalid product id.';
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute([':id' => $productId]);
            $cleanupStmt = $pdo->prepare('DELETE FROM product_link_checks WHERE product_id = :id');
            $cleanupStmt->execute([':id' => $productId]);
            enma_record_activity($pdo, 'product.delete.from_review', 'product', $productId, []);
            $flash = 'Product deleted from not-found review.';
            $taskRunOk = true;
            $taskRunMessage = $flash;
        } catch (Throwable $e) {
            $taskRunMessage = 'Delete from review failed: ' . $e->getMessage();
            $errors[] = $taskRunMessage;
        }
    }

    try {
        enma_maintenance_record_usage($pdo, $taskKey, $taskRunOk ? 'ok' : 'fail', $taskRunMessage);
    } catch (Throwable $e) {
        $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
    }
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
    $taskMetrics = [];
    $taskStartedAt = microtime(true);
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
            $taskMetrics = [
                'checked' => $checked,
                'updated' => $updated,
            ];
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
                    $outputLines = preg_split('/\r\n|\r|\n/', $output);
                    foreach ($outputLines as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                    $taskMetrics = enma_maintenance_parse_numeric_metrics_from_lines((array) $outputLines);
                }
                $maintenanceLog[] = 'Task: fix_product_images';
                $maintenanceLog[] = 'Source: Amazon product page scrape + safe placeholder';
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Image fix failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'backup_content_sql' || $task === 'export_full_database') {
            if ($task === 'backup_content_sql') {
                $task = 'export_full_database';
            }
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                $exportResult = require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Full database backup completed.';
                $taskRunOk = true;
                if (is_array($exportResult) && !empty($exportResult['summary'])) {
                    $taskRunMessage = (string) $exportResult['summary'];
                } else {
                    $taskRunMessage = $output !== '' ? $output : $flash;
                }
                if ($output !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                }
                $maintenanceLog[] = 'Task: export_full_database';
                if (is_array($exportResult)) {
                    $maintenanceLog[] = 'Output timestamped: ' . (string) ($exportResult['timestamped_path'] ?? '');
                    $maintenanceLog[] = 'Output latest: ' . (string) ($exportResult['latest_path'] ?? '');
                    $maintenanceLog[] = 'Summary: ' . (string) ($exportResult['summary'] ?? '');
                }
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Database full backup failed: ' . $e->getMessage();
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
        } elseif ($task === 'prune_old_logs') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Old logs pruned successfully.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                }
                $maintenanceLog[] = 'Task: prune_old_logs';
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Log pruning failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'check_links') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Link check completed.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                }
                $maintenanceLog[] = 'Task: check_links';
                $maintenanceLog[] = 'Output: /workspace/data/reports/link_check_latest.json';
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Link check failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'sync_post_indexation_tracker') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                if (!defined('ENMA_ALLOW_WEB_RUN')) {
                    define('ENMA_ALLOW_WEB_RUN', true);
                }
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Post indexation tracker synced.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    $outputLines = preg_split('/\r\n|\r|\n/', $output);
                    foreach ($outputLines as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                    $taskMetrics = enma_maintenance_parse_numeric_metrics_from_lines((array) $outputLines);
                }
                $maintenanceLog[] = 'Task: sync_post_indexation_tracker';
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Indexation tracker sync failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'clean_not_found_products') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                if (!defined('ENMA_ALLOW_WEB_RUN')) {
                    define('ENMA_ALLOW_WEB_RUN', true);
                }
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Not-found products cleanup completed.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    $outputLines = preg_split('/\r\n|\r|\n/', $output);
                    foreach ($outputLines as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                    $taskMetrics = enma_maintenance_parse_numeric_metrics_from_lines((array) $outputLines);
                }
                $maintenanceLog[] = 'Task: clean_not_found_products';
                $maintenanceLog[] = 'Mode: archive published products that resolve as not found';
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Not-found cleanup failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'delete_not_found_products') {
            try {
                $deleteStmt = $pdo->prepare(
                    'DELETE p
                     FROM products p
                     INNER JOIN product_link_checks plc ON plc.product_id = p.id
                     WHERE plc.state = "not_found"'
                );
                $deleteStmt->execute();
                $deletedProducts = (int) $deleteStmt->rowCount();

                $cleanupStmt = $pdo->prepare(
                    'DELETE plc
                     FROM product_link_checks plc
                     LEFT JOIN products p ON p.id = plc.product_id
                     WHERE plc.state = "not_found" OR p.id IS NULL'
                );
                $cleanupStmt->execute();
                $cleanedChecks = (int) $cleanupStmt->rowCount();

                $flash = 'Not-found delete completed. Products deleted: ' . $deletedProducts;
                $taskRunOk = true;
                $taskRunMessage = $flash;
                $taskMetrics = [
                    'deleted_products' => $deletedProducts,
                    'cleaned_product_link_checks_rows' => $cleanedChecks,
                ];
                $maintenanceLog[] = 'Task: delete_not_found_products';
                $maintenanceLog[] = 'Deleted products: ' . $deletedProducts;
                $maintenanceLog[] = 'Cleaned product_link_checks rows: ' . $cleanedChecks;
            } catch (Throwable $e) {
                $taskRunMessage = 'Not-found delete failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'generate_sitemap') {
            $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
            ob_start();
            try {
                if (!defined('ENMA_ALLOW_WEB_RUN')) {
                    define('ENMA_ALLOW_WEB_RUN', true);
                }
                require $scriptPath;
                $output = trim((string) ob_get_clean());
                $flash = 'Sitemap generated successfully.';
                $taskRunOk = true;
                $taskRunMessage = $output !== '' ? $output : $flash;
                if ($output !== '') {
                    foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                        if (trim((string) $line) !== '') {
                            $maintenanceLog[] = (string) $line;
                        }
                    }
                }
                $maintenanceLog[] = 'Task: generate_sitemap';
                $maintenanceLog[] = 'Output: /workspace/sitemap.xml';
            } catch (Throwable $e) {
                ob_end_clean();
                $taskRunMessage = 'Sitemap generation failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'export_products_sql') {
            try {
                $export = enma_maintenance_build_products_export_sql($pdo);
                $flash = 'Products SQL export download started.';
                $taskRunOk = true;
                $taskRunMessage = $flash;
                $maintenanceLog[] = 'Task: export_products_sql';
                $maintenanceLog[] = 'Mode: browser download';
                $maintenanceLog[] = 'Rows exported: ' . (int) ($export['row_count'] ?? 0);
                try {
                    enma_maintenance_record_usage($pdo, $task, 'ok', $taskRunMessage);
                } catch (Throwable $e) {
                    $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
                }
                enma_maintenance_stream_download(
                    (string) $export['filename'],
                    (string) $export['content_type'],
                    (string) $export['content']
                );
            } catch (Throwable $e) {
                $taskRunMessage = 'Products export failed: ' . $e->getMessage();
                $errors[] = $taskRunMessage;
            }
        } elseif ($task === 'export_posts_pastebin') {
            try {
                $export = enma_maintenance_build_posts_export_json($pdo);
                $flash = 'Posts table JSON download started.';
                $taskRunOk = true;
                $taskRunMessage = $flash;
                $maintenanceLog[] = 'Task: export_posts_pastebin';
                $maintenanceLog[] = 'Mode: browser download';
                $maintenanceLog[] = 'Rows exported: ' . (int) ($export['row_count'] ?? 0);
                try {
                    enma_maintenance_record_usage($pdo, $task, 'ok', $taskRunMessage);
                } catch (Throwable $e) {
                    $maintenanceLog[] = 'Usage record failed: ' . $e->getMessage();
                }
                enma_maintenance_stream_download(
                    (string) $export['filename'],
                    (string) $export['content_type'],
                    (string) $export['content']
                );
            } catch (Throwable $e) {
                $taskRunMessage = 'Posts table JSON export failed: ' . $e->getMessage();
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
        try {
            enma_maintenance_record_job_run(
                $pdo,
                $task,
                $taskRunOk ? 'ok' : 'fail',
                $taskRunMessage,
                microtime(true) - $taskStartedAt,
                $taskMetrics
            );
        } catch (Throwable $e) {
            $maintenanceLog[] = 'Job run record failed: ' . $e->getMessage();
        }
    }
}

if ($availabilityCheckerUrlColumn !== null) {
    try {
        $safeTable = enma_maintenance_safe_table_name($availabilityCheckerTable);
        $availabilityCheckerDashboard['total_products'] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $safeTable)->fetchColumn();
        $availabilityCheckerDashboard['available_products'] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $safeTable . ' WHERE is_available = 1')->fetchColumn();
        $availabilityCheckerDashboard['unavailable_products'] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $safeTable . ' WHERE is_available = 0')->fetchColumn();
        $availabilityCheckerDashboard['last_checked_at'] = (string) ($pdo->query('SELECT MAX(last_checked_at) FROM ' . $safeTable)->fetchColumn() ?: '');

        $nextProduct = enma_maintenance_pick_next_product_for_availability($pdo, $availabilityCheckerTable, $availabilityCheckerUrlColumn);
        if (is_array($nextProduct)) {
            $availabilityCheckerDashboard['next_product_id'] = (int) ($nextProduct['id'] ?? 0);
            $availabilityCheckerDashboard['next_product_url'] = trim((string) ($nextProduct['product_url'] ?? ''));
        }
    } catch (Throwable $e) {
        $maintenanceLog[] = 'Availability dashboard failed: ' . $e->getMessage();
    }
}

$dbBackupLatestPath = enma_maintenance_latest_db_backup_path();
$dbBackupLatestFilename = $dbBackupLatestPath !== null ? basename($dbBackupLatestPath) : '';
$dbBackupLatestContent = '';
$dbBackupAutoCopy = false;
if ($dbBackupLatestPath !== null && is_readable($dbBackupLatestPath)) {
    $raw = file_get_contents($dbBackupLatestPath);
    $dbBackupLatestContent = is_string($raw) ? $raw : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_db_backup_create') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token for DB backup.';
    } else {
        $task = 'export_full_database';
        $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
        if ($scriptPath === '') {
            $errors[] = 'Backup script not available.';
        } else {
            ob_start();
            try {
                $exportResult = require $scriptPath;
                ob_end_clean();
                $dbBackupLatestPath = enma_maintenance_latest_db_backup_path();
                $dbBackupLatestFilename = $dbBackupLatestPath !== null ? basename($dbBackupLatestPath) : '';
                $dbBackupLatestContent = ($dbBackupLatestPath !== null && is_readable($dbBackupLatestPath))
                    ? (string) file_get_contents($dbBackupLatestPath)
                    : '';
                $summary = is_array($exportResult) ? (string) ($exportResult['summary'] ?? '') : '';
                $flash = 'Database full backup created successfully.';
                $maintenanceLog[] = 'Task: maintenance_db_backup_create';
                $maintenanceLog[] = 'Output: ' . ($dbBackupLatestPath ?? 'no backup file found');
                if ($summary !== '') {
                    $maintenanceLog[] = 'Summary: ' . $summary;
                    enma_maintenance_record_usage($pdo, 'export_full_database', 'ok', $summary);
                }
            } catch (Throwable $e) {
                ob_end_clean();
                $errors[] = 'Database backup failed: ' . $e->getMessage();
                enma_maintenance_record_usage($pdo, 'export_full_database', 'fail', 'Backup failed: ' . $e->getMessage());
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_db_backup_download') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token for DB backup download.';
    } else {
        $task = 'export_full_database';
        $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
        if ($scriptPath === '') {
            $errors[] = 'Backup script not available.';
        } else {
            ob_start();
            try {
                $exportResult = require $scriptPath;
                ob_end_clean();
                $summary = is_array($exportResult) ? (string) ($exportResult['summary'] ?? '') : '';
                if ($summary !== '') {
                    enma_maintenance_record_usage($pdo, 'export_full_database', 'ok', $summary);
                }
            } catch (Throwable $e) {
                ob_end_clean();
                $errors[] = 'Database backup failed before download: ' . $e->getMessage();
                enma_maintenance_record_usage($pdo, 'export_full_database', 'fail', 'Backup failed before download: ' . $e->getMessage());
            }
        }
        $latestPath = enma_maintenance_latest_db_backup_path();
        if ($latestPath === null || !is_readable($latestPath)) {
            $errors[] = 'No DB backup file available to download.';
        } else {
            $content = (string) file_get_contents($latestPath);
            enma_maintenance_stream_download(
                basename($latestPath),
                'application/sql; charset=UTF-8',
                $content
            );
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_db_backup_copy') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token for DB backup copy.';
    } else {
        $task = 'export_full_database';
        $scriptPath = (string) ($availableMaintenanceTasks[$task]['script_path'] ?? '');
        if ($scriptPath === '') {
            $errors[] = 'Backup script not available.';
        } else {
            ob_start();
            try {
                $exportResult = require $scriptPath;
                ob_end_clean();
                $dbBackupLatestPath = enma_maintenance_latest_db_backup_path();
                $dbBackupLatestFilename = $dbBackupLatestPath !== null ? basename($dbBackupLatestPath) : '';
                $dbBackupLatestContent = ($dbBackupLatestPath !== null && is_readable($dbBackupLatestPath))
                    ? (string) file_get_contents($dbBackupLatestPath)
                    : '';
                $summary = is_array($exportResult) ? (string) ($exportResult['summary'] ?? '') : '';
                $dbBackupAutoCopy = true;
                $flash = 'Fresh DB backup generated. Copying to clipboard...';
                if ($summary !== '') {
                    enma_maintenance_record_usage($pdo, 'export_full_database', 'ok', $summary);
                }
            } catch (Throwable $e) {
                ob_end_clean();
                $errors[] = 'Database backup failed: ' . $e->getMessage();
                enma_maintenance_record_usage($pdo, 'export_full_database', 'fail', 'Backup failed before copy: ' . $e->getMessage());
            }
        }
    }
}

try {
    $recentRunsStmt = $pdo->prepare(
        'SELECT id, task_key, status, message, duration_seconds, metrics_json, created_at
         FROM maintenance_job_runs
         ORDER BY id DESC
         LIMIT 8'
    );
    $recentRunsStmt->execute();
    $maintenanceProgress['recent_runs'] = $recentRunsStmt->fetchAll();

    $imageRunsStmt = $pdo->prepare(
        'SELECT id, task_key, status, message, duration_seconds, metrics_json, created_at
         FROM maintenance_job_runs
         WHERE task_key = "fix_product_images"
         ORDER BY id DESC
         LIMIT 30'
    );
    $imageRunsStmt->execute();
    $imageRuns = $imageRunsStmt->fetchAll();
    if ($imageRuns !== []) {
        $maintenanceProgress['image_last_run'] = $imageRuns[0];
        $lastMetrics = json_decode((string) ($imageRuns[0]['metrics_json'] ?? ''), true);
        $maintenanceProgress['image_last_metrics'] = is_array($lastMetrics) ? $lastMetrics : [];
    }

    $weeklyRuns = 0;
    $weeklyChecked = 0;
    $weeklyUpdated = 0;
    $weeklyFailedFetches = 0;
    $updatesAccumulator = 0;
    $updateRunsCount = 0;
    $weekCutoffTs = time() - (7 * 86400);

    foreach ($imageRuns as $run) {
        $createdTs = strtotime((string) ($run['created_at'] ?? ''));
        $metrics = json_decode((string) ($run['metrics_json'] ?? ''), true);
        $metrics = is_array($metrics) ? $metrics : [];

        $updated = enma_maintenance_safe_int_metric($metrics, 'updated');
        if ($updated > 0) {
            $updatesAccumulator += $updated;
            $updateRunsCount++;
        }

        if ($createdTs !== false && $createdTs >= $weekCutoffTs) {
            $weeklyRuns++;
            $weeklyChecked += enma_maintenance_safe_int_metric($metrics, 'checked');
            $weeklyUpdated += $updated;
            $weeklyFailedFetches += enma_maintenance_safe_int_metric($metrics, 'failed_fetches');
        }
    }

    $maintenanceProgress['image_weekly'] = [
        'runs' => $weeklyRuns,
        'checked' => $weeklyChecked,
        'updated' => $weeklyUpdated,
        'failed_fetches' => $weeklyFailedFetches,
    ];
    $maintenanceProgress['image_success_rate'] = $weeklyChecked > 0 ? round(($weeklyUpdated / $weeklyChecked) * 100, 2) : 0.0;
    $maintenanceProgress['image_failure_rate'] = $weeklyChecked > 0 ? round(($weeklyFailedFetches / $weeklyChecked) * 100, 2) : 0.0;
    $maintenanceProgress['image_avg_updates_per_run'] = $updateRunsCount > 0 ? round($updatesAccumulator / $updateRunsCount, 2) : 0.0;

    $remainingStmt = $pdo->query(
        'SELECT COUNT(*)
         FROM products
         WHERE status = "published"
           AND (
                image_url IS NULL
                OR image_url = ""
                OR image_url LIKE "%product-placeholder.svg%"
                OR image_url LIKE "%image-uploading%"
                OR image_url LIKE "%URL_%"
                OR image_url LIKE "%/assets/logo/%"
           )'
    );
    $maintenanceProgress['image_remaining'] = (int) $remainingStmt->fetchColumn();
    if ($maintenanceProgress['image_avg_updates_per_run'] > 0) {
        $maintenanceProgress['image_eta_runs'] = (int) ceil(
            $maintenanceProgress['image_remaining'] / max(0.01, (float) $maintenanceProgress['image_avg_updates_per_run'])
        );
    }

    $safeCheckLastStmt = $pdo->prepare(
        'SELECT id, task_key, status, message, duration_seconds, metrics_json, created_at
         FROM maintenance_job_runs
         WHERE task_key = "availability_safe_check_next"
         ORDER BY id DESC
         LIMIT 1'
    );
    $safeCheckLastStmt->execute();
    $maintenanceProgress['safe_check_last_run'] = $safeCheckLastStmt->fetch() ?: null;
} catch (Throwable $e) {
    $maintenanceLog[] = 'Maintenance progress metrics unavailable: ' . $e->getMessage();
}
