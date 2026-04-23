<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    exit('Forbidden');
}

function clean_nf_parse_args(array $argv): array
{
    $args = [];
    foreach ($argv as $arg) {
        if (!is_string($arg) || !str_starts_with($arg, '--')) {
            continue;
        }
        $raw = substr($arg, 2);
        if ($raw === '') {
            continue;
        }
        $pair = explode('=', $raw, 2);
        $key = trim($pair[0]);
        if ($key === '') {
            continue;
        }
        $args[$key] = isset($pair[1]) ? (string) $pair[1] : '1';
    }
    return $args;
}

function clean_nf_request(string $url): array
{
    $headers = [
        'User-Agent: FortelescopesNotFoundCleaner/1.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ];

    if (function_exists('curl_init')) {
        $body = '';
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'final_url' => $url, 'body' => '', 'error' => 'curl_init failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 14,
            CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$body): int {
                if (strlen($body) < 180000) {
                    $body .= $chunk;
                }
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'status' => $status,
            'final_url' => $finalUrl !== '' ? $finalUrl : $url,
            'body' => $body,
            'error' => $error,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 14,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $m);
    $status = isset($m[1]) ? (int) $m[1] : ($body === false ? 0 : 200);
    return [
        'status' => $status,
        'final_url' => $url,
        'body' => is_string($body) ? $body : '',
        'error' => $body === false ? 'request failed' : '',
    ];
}

function clean_nf_init_checks_table(PDO $pdo): void
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

function clean_nf_is_not_found(array $response, string $expectedAsin = ''): bool
{
    $status = (int) ($response['status'] ?? 0);
    $finalUrl = strtolower(trim((string) ($response['final_url'] ?? '')));
    $body = strtolower((string) ($response['body'] ?? ''));

    if (in_array($status, [404, 410], true)) {
        return true;
    }
    if ($status === 0) {
        return false;
    }

    $host = strtolower((string) (parse_url($finalUrl, PHP_URL_HOST) ?? ''));
    if (!is_amazon_host($host)) {
        return false;
    }

    if ($status >= 400) {
        return true;
    }

    $notFoundPathHints = ['/errors/404', '/gp/errors/404', '/404'];
    foreach ($notFoundPathHints as $hint) {
        if (strpos($finalUrl, $hint) !== false) {
            return true;
        }
    }

    $expectedAsin = strtoupper(trim($expectedAsin));
    if ($expectedAsin !== '') {
        $isSearchRedirect = strpos($finalUrl, '/s?') !== false || strpos($finalUrl, '/gp/aw/s') !== false;
        if ($isSearchRedirect && strpos(strtoupper($finalUrl), $expectedAsin) === false) {
            return true;
        }
    }

    $amazonNotFoundMarkers = [
        "sorry! we couldn't find that page",
        "the web address you entered is not a functioning page on our site",
        "dogsofamazon",
        "looking for something?",
        "page not found",
        "link you followed may be outdated",
        "we can't find that page",
        "the page you requested was not found",
    ];
    foreach ($amazonNotFoundMarkers as $marker) {
        if ($marker !== '' && strpos($body, $marker) !== false) {
            return true;
        }
    }

    return false;
}

$args = clean_nf_parse_args($argv ?? []);
$dryRun = in_array(strtolower((string) ($args['dry_run'] ?? '0')), ['1', 'true', 'yes', 'on'], true);
$limit = max(1, min(5000, (int) ($args['limit'] ?? '300')));

$stmt = $pdo->prepare(
    'SELECT id, asin, title, affiliate_url
     FROM products
     WHERE status = "published"
     ORDER BY id ASC
     LIMIT :limit'
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$checked = 0;
$archived = 0;
$invalidUrl = 0;
$warnings = 0;
$archivedAsins = [];
$warningAsins = [];

$archiveStmt = $pdo->prepare(
    'UPDATE products
     SET status = "archived", updated_at = :updated_at
     WHERE id = :id'
);
clean_nf_init_checks_table($pdo);
$checksUpsertStmt = $pdo->prepare(
    'INSERT INTO product_link_checks (
        product_id, asin, affiliate_url, http_status, state, final_url, error_message, checked_at
     ) VALUES (
        :product_id, :asin, :affiliate_url, :http_status, :state, :final_url, :error_message, :checked_at
     )
     ON DUPLICATE KEY UPDATE
        asin = VALUES(asin),
        affiliate_url = VALUES(affiliate_url),
        http_status = VALUES(http_status),
        state = VALUES(state),
        final_url = VALUES(final_url),
        error_message = VALUES(error_message),
        checked_at = VALUES(checked_at)'
);

foreach ($rows as $row) {
    $checked++;
    $id = (int) ($row['id'] ?? 0);
    $asin = trim((string) ($row['asin'] ?? ''));
    $url = trim((string) ($row['affiliate_url'] ?? ''));

    if ($id <= 0 || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        $invalidUrl++;
        $checksUpsertStmt->execute([
            ':product_id' => $id,
            ':asin' => $asin,
            ':affiliate_url' => $url,
            ':http_status' => 0,
            ':state' => 'not_found',
            ':final_url' => $url,
            ':error_message' => 'invalid_url',
            ':checked_at' => now_iso(),
        ]);
        if (!$dryRun && $id > 0) {
            $archiveStmt->execute([
                ':updated_at' => now_iso(),
                ':id' => $id,
            ]);
            $archived++;
            $archivedAsins[] = $asin !== '' ? $asin : ('#' . $id);
        }
        continue;
    }

    $response = clean_nf_request($url);
    $state = 'ok';
    if (clean_nf_is_not_found($response, $asin)) {
        $state = 'not_found';
    } else {
        $status = (int) ($response['status'] ?? 0);
        if ($status === 0 || $status === 403 || $status === 429) {
            $state = 'warning';
        }
    }

    $checksUpsertStmt->execute([
        ':product_id' => $id,
        ':asin' => $asin,
        ':affiliate_url' => $url,
        ':http_status' => (int) ($response['status'] ?? 0),
        ':state' => $state,
        ':final_url' => (string) ($response['final_url'] ?? $url),
        ':error_message' => mb_substr((string) ($response['error'] ?? ''), 0, 255),
        ':checked_at' => now_iso(),
    ]);

    if ($state === 'not_found') {
        if (!$dryRun) {
            $archiveStmt->execute([
                ':updated_at' => now_iso(),
                ':id' => $id,
            ]);
            $archived++;
            $archivedAsins[] = $asin !== '' ? $asin : ('#' . $id);
        }
        continue;
    }

    if ($state === 'warning') {
        $status = (int) ($response['status'] ?? 0);
        $warnings++;
        $warningAsins[] = ($asin !== '' ? $asin : ('#' . $id)) . '(status:' . $status . ')';
    }
}

$lines = [
    'Mode: ' . ($dryRun ? 'dry-run' : 'apply'),
    'Checked published products: ' . $checked,
    'Archived (not found): ' . $archived,
    'Invalid URL rows: ' . $invalidUrl,
    'Network/block warnings: ' . $warnings,
];

if ($archivedAsins !== []) {
    $lines[] = 'Archived ASINs: ' . implode(', ', $archivedAsins);
}
if ($warningAsins !== []) {
    $lines[] = 'Warning ASINs (blocked/unreachable): ' . implode(', ', $warningAsins);
}

maintenance_prune_files('logs', 'clean-not-found-products_*.log', 30);
$logPath = maintenance_append_log('clean-not-found-products', $lines);
$lines[] = 'Log: ' . $logPath;

echo implode(PHP_EOL, $lines) . PHP_EOL;
