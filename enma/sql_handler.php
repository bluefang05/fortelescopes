<?php

declare(strict_types=1);

if (!function_exists('enma_sql_init_history_table')) {
    function enma_sql_init_history_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS enma_sql_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                admin_user_id INT UNSIGNED DEFAULT NULL,
                admin_username VARCHAR(120) NOT NULL DEFAULT "",
                query_text MEDIUMTEXT NOT NULL,
                query_kind VARCHAR(20) NOT NULL DEFAULT "read",
                status VARCHAR(20) NOT NULL DEFAULT "ok",
                row_count INT NOT NULL DEFAULT 0,
                duration_ms DECIMAL(12,3) NOT NULL DEFAULT 0,
                error_message VARCHAR(500) NOT NULL DEFAULT "",
                created_at DATETIME NOT NULL,
                KEY idx_enma_sql_history_created (created_at),
                KEY idx_enma_sql_history_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

if (!function_exists('enma_sql_classify_query')) {
    function enma_sql_classify_query(string $query): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $query) ?? $query);
        $withoutLeadingComments = preg_replace('/^(?:\s*(?:--[^\n]*|#[^\n]*|\/\*.*?\*\/))*\s*/s', '', $normalized) ?? $normalized;
        $firstToken = strtolower((string) strtok($withoutLeadingComments, " \t\r\n("));
        $readOnly = in_array($firstToken, ['select', 'show', 'describe', 'desc', 'explain'], true);
        if ($firstToken === 'with') {
            $readOnly = preg_match('/\b(insert|update|delete|replace)\b/i', $withoutLeadingComments) !== 1;
        }
        $blocked = in_array($firstToken, ['drop', 'truncate', 'grant', 'revoke', 'load'], true)
            || preg_match('/\binto\s+outfile\b/i', $withoutLeadingComments) === 1
            || preg_match('/\binto\s+dumpfile\b/i', $withoutLeadingComments) === 1;

        return [
            'token' => $firstToken,
            'read_only' => $readOnly,
            'blocked' => $blocked,
            'kind' => $readOnly ? 'read' : 'write',
        ];
    }
}

if (!function_exists('enma_sql_has_multiple_statements')) {
    function enma_sql_has_multiple_statements(string $query): bool
    {
        $trimmed = trim($query);
        $trimmed = rtrim($trimmed, " \t\r\n;");
        return strpos($trimmed, ';') !== false;
    }
}

if (!function_exists('enma_sql_split_statements')) {
    function enma_sql_split_statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inLineComment) {
                $buffer .= $ch;
                if ($ch === "\n") {
                    $inLineComment = false;
                }
                continue;
            }
            if ($inBlockComment) {
                $buffer .= $ch;
                if ($ch === '*' && $next === '/') {
                    $buffer .= '/';
                    $i++;
                    $inBlockComment = false;
                }
                continue;
            }
            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($ch === '-' && $next === '-') {
                    $buffer .= $ch . $next;
                    $i++;
                    $inLineComment = true;
                    continue;
                }
                if ($ch === '#') {
                    $buffer .= $ch;
                    $inLineComment = true;
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $buffer .= $ch . $next;
                    $i++;
                    $inBlockComment = true;
                    continue;
                }
            }

            if ($ch === "'" && !$inDouble && !$inBacktick) {
                $prev = $i > 0 ? $sql[$i - 1] : '';
                if ($prev !== '\\') {
                    $inSingle = !$inSingle;
                }
            } elseif ($ch === '"' && !$inSingle && !$inBacktick) {
                $prev = $i > 0 ? $sql[$i - 1] : '';
                if ($prev !== '\\') {
                    $inDouble = !$inDouble;
                }
            } elseif ($ch === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}

if (!function_exists('enma_sql_record_history')) {
    function enma_sql_record_history(PDO $pdo, string $query, string $kind, string $status, int $rowCount, float $durationMs, string $error = ''): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO enma_sql_history (
                admin_user_id, admin_username, query_text, query_kind, status, row_count, duration_ms, error_message, created_at
             ) VALUES (
                :admin_user_id, :admin_username, :query_text, :query_kind, :status, :row_count, :duration_ms, :error_message, :created_at
             )'
        );
        $stmt->execute([
            ':admin_user_id' => isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null,
            ':admin_username' => mb_substr((string) ($_SESSION['admin_username'] ?? ''), 0, 120),
            ':query_text' => $query,
            ':query_kind' => $kind,
            ':status' => $status,
            ':row_count' => $rowCount,
            ':duration_ms' => number_format($durationMs, 3, '.', ''),
            ':error_message' => mb_substr($error, 0, 500),
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}

enma_sql_init_history_table($pdo);

$sqlConsoleQuery = '';
$sqlConsoleMaxRows = 100;
$sqlConsoleAllowWrite = false;
$sqlConsoleConfirmWrite = false;
$sqlConsoleBatchMode = false;
$sqlConsoleResultColumns = [];
$sqlConsoleResultRows = [];
$sqlConsoleAffectedRows = null;
$sqlConsoleDurationMs = null;
$sqlConsoleMessage = '';
$sqlConsoleError = '';
$sqlConsoleBatchLog = [];
$sqlConsoleTables = [];
$sqlConsoleSelectedTable = trim((string) ($_GET['sql_table'] ?? ''));
$sqlConsoleColumns = [];
$sqlConsoleHistory = [];

if ($authenticated && $activeTab === 'sql') {
    try {
        $stmt = $pdo->prepare(
            'SELECT table_name, table_rows
             FROM information_schema.tables
             WHERE table_schema = :schema
             ORDER BY table_name ASC'
        );
        $stmt->execute([':schema' => DB_NAME]);
        $sqlConsoleTables = $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        $sqlConsoleError = 'Could not load table list: ' . $e->getMessage();
    }

    if ($sqlConsoleSelectedTable !== '' && preg_match('/^[A-Za-z0-9_]+$/', $sqlConsoleSelectedTable)) {
        try {
            $stmt = $pdo->prepare(
                'SELECT column_name, column_type, is_nullable, column_key, column_default, extra
                 FROM information_schema.columns
                 WHERE table_schema = :schema AND table_name = :table
                 ORDER BY ordinal_position ASC'
            );
            $stmt->execute([':schema' => DB_NAME, ':table' => $sqlConsoleSelectedTable]);
            $sqlConsoleColumns = $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            $sqlConsoleError = 'Could not load columns: ' . $e->getMessage();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sql_console_run') {
        $sqlConsoleQuery = trim((string) ($_POST['sql_query'] ?? ''));
        $sqlConsoleMaxRows = max(1, min(1000, (int) ($_POST['max_rows'] ?? 100)));
        $sqlConsoleAllowWrite = !empty($_POST['allow_write']);
        $sqlConsoleConfirmWrite = !empty($_POST['confirm_write']);
        $sqlConsoleBatchMode = !empty($_POST['batch_mode']);
        // Auto-enable batch execution for scripts with multiple statements.
        if (enma_sql_has_multiple_statements($sqlConsoleQuery)) {
            $sqlConsoleBatchMode = true;
        }

        if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
            $sqlConsoleError = 'Invalid request token.';
        } elseif ($sqlConsoleQuery === '') {
            $sqlConsoleError = 'Enter a SQL query first.';
        } else {
            $started = microtime(true);
            $statements = $sqlConsoleBatchMode ? enma_sql_split_statements($sqlConsoleQuery) : [$sqlConsoleQuery];
            $totalRows = 0;
            $anyWrite = false;
            $overallKind = 'read';

            foreach ($statements as $statement) {
                $classification = enma_sql_classify_query($statement);
                if ($classification['blocked']) {
                    $sqlConsoleError = 'Blocked statement in batch: ' . strtoupper($classification['token']);
                    break;
                }
                if (!$classification['read_only']) {
                    $anyWrite = true;
                    $overallKind = 'write';
                }
            }

            if ($sqlConsoleError === '' && $anyWrite && (!$sqlConsoleAllowWrite || !$sqlConsoleConfirmWrite)) {
                $sqlConsoleError = 'Write queries require both write mode and confirmation.';
            }

            if ($sqlConsoleError === '') {
                try {
                    foreach ($statements as $idx => $statement) {
                        $classification = enma_sql_classify_query($statement);
                        $stmt = $pdo->prepare($statement);
                        $stmt->execute();

                        if ($classification['read_only']) {
                            $localRows = [];
                            for ($i = 0; $i < $sqlConsoleMaxRows; $i++) {
                                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($row === false) {
                                    break;
                                }
                                $localRows[] = $row;
                            }
                            $totalRows += count($localRows);
                            if ($idx === count($statements) - 1) {
                                $sqlConsoleResultRows = $localRows;
                                $sqlConsoleResultColumns = $localRows !== [] ? array_keys($localRows[0]) : [];
                            }
                            $sample = $localRows !== [] ? json_encode($localRows[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : 'no rows';
                            $sqlConsoleBatchLog[] = 'Statement ' . ($idx + 1) . ': read ' . count($localRows) . ' row(s), sample=' . (string) $sample;
                        } else {
                            $affected = max(0, $stmt->rowCount());
                            $sqlConsoleAffectedRows = ($sqlConsoleAffectedRows ?? 0) + $affected;
                            $totalRows += $affected;
                            $sqlConsoleBatchLog[] = 'Statement ' . ($idx + 1) . ': affected ' . $affected . ' row(s).';
                        }
                    }

                    $sqlConsoleDurationMs = (microtime(true) - $started) * 1000;
                    if (count($statements) > 1) {
                        $sqlConsoleMessage = 'Batch completed: ' . count($statements) . ' statements in ' . number_format((float) $sqlConsoleDurationMs, 2) . 'ms.';
                    } elseif ($overallKind === 'read') {
                        $sqlConsoleMessage = 'Query completed. Showing ' . number_format(count($sqlConsoleResultRows)) . ' row(s).';
                    } else {
                        $sqlConsoleMessage = 'Write query completed. Affected rows: ' . number_format((int) ($sqlConsoleAffectedRows ?? 0)) . '.';
                    }

                    enma_sql_record_history($pdo, $sqlConsoleQuery, $overallKind, 'ok', $totalRows, (float) $sqlConsoleDurationMs);
                } catch (Throwable $e) {
                    $sqlConsoleDurationMs = (microtime(true) - $started) * 1000;
                    $sqlConsoleError = $e->getMessage();
                    try {
                        enma_sql_record_history($pdo, $sqlConsoleQuery, $overallKind, 'fail', 0, (float) $sqlConsoleDurationMs, $sqlConsoleError);
                    } catch (Throwable $historyError) {
                        $sqlConsoleError .= ' | History write failed: ' . $historyError->getMessage();
                    }
                }
            }
        }
    }

    try {
        $stmt = $pdo->query(
            'SELECT id, admin_username, query_text, query_kind, status, row_count, duration_ms, error_message, created_at
             FROM enma_sql_history
             ORDER BY id DESC
             LIMIT 20'
        );
        $sqlConsoleHistory = $stmt !== false ? ($stmt->fetchAll() ?: []) : [];
    } catch (Throwable $e) {
        if ($sqlConsoleError === '') {
            $sqlConsoleError = 'Could not load SQL history: ' . $e->getMessage();
        }
    }
}
