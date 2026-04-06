<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    exit('Forbidden');
}

function link_check_request(string $url): array
{
    $headers = [
        'User-Agent: FortelescopesLinkChecker/1.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'error' => 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($statusCode === 0 || $statusCode >= 400) {
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
        }

        curl_close($ch);
        return ['status' => $statusCode, 'error' => $error];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    return [
        'status' => isset($matches[1]) ? (int) $matches[1] : ($response === false ? 0 : 200),
        'error' => $response === false ? 'request failed' : '',
    ];
}

function link_check_classify(string $url, int $statusCode): string
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    $isInternal = $host === SITE_DOMAIN;

    if ($statusCode >= 200 && $statusCode < 400) {
        return 'ok';
    }
    if (!$isInternal && in_array($statusCode, [403, 405, 429], true)) {
        return 'warning';
    }
    if ($statusCode === 0) {
        return 'warning';
    }
    return 'broken';
}

$internalUrls = array_map(static fn(array $entry): string => (string) $entry['loc'], get_sitemap_entries($pdo));

$externalUrls = [];
$posts = get_posts_by_types($pdo, ['post', 'guide'], 5000);
foreach ($posts as $post) {
    $content = (string) ($post['content_html'] ?? '');
    if ($content === '') {
        continue;
    }
    if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $content, $matches)) {
        foreach ($matches[1] as $href) {
            $href = trim((string) $href);
            if ($href === '' || !preg_match('/^https?:\/\//i', $href)) {
                continue;
            }
            $host = strtolower((string) (parse_url($href, PHP_URL_HOST) ?? ''));
            if ($host === '' || $host === SITE_DOMAIN || is_amazon_host($host)) {
                continue;
            }
            $externalUrls[$href] = true;
        }
    }
}

$urlsToCheck = [];
foreach ($internalUrls as $url) {
    $urlsToCheck[] = ['url' => $url, 'type' => 'internal'];
}
foreach (array_slice(array_keys($externalUrls), 0, 200) as $url) {
    $urlsToCheck[] = ['url' => $url, 'type' => 'external'];
}

$report = [
    'generated_at' => gmdate('c'),
    'summary' => [
        'checked' => 0,
        'ok' => 0,
        'warning' => 0,
        'broken' => 0,
    ],
    'results' => [],
];

foreach ($urlsToCheck as $item) {
    $result = link_check_request($item['url']);
    $state = link_check_classify($item['url'], (int) $result['status']);
    $report['summary']['checked']++;
    $report['summary'][$state]++;
    $report['results'][] = [
        'url' => $item['url'],
        'type' => $item['type'],
        'status_code' => (int) $result['status'],
        'state' => $state,
        'error' => (string) ($result['error'] ?? ''),
    ];
}

$reportDir = __DIR__ . '/../data/reports';
if (!is_dir($reportDir) && !mkdir($reportDir, 0775, true) && !is_dir($reportDir)) {
    throw new RuntimeException('Could not create reports directory.');
}
$reportPath = $reportDir . '/link_check_latest.json';
$reportSnapshotPath = $reportDir . '/link_check_' . gmdate('Ymd_His') . '.json';

if (file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    throw new RuntimeException('Could not write link report.');
}
if (file_put_contents($reportSnapshotPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    throw new RuntimeException('Could not write timestamped link report.');
}

$deletedReports = maintenance_prune_files('reports', 'link_check_*.json', 30);
maintenance_prune_files('logs', 'check-links_*.log', 30);
$lines = [
    'Checked: ' . $report['summary']['checked'],
    'OK: ' . $report['summary']['ok'],
    'Warnings: ' . $report['summary']['warning'],
    'Broken: ' . $report['summary']['broken'],
    'Report: ' . realpath($reportPath),
    'Snapshot: ' . realpath($reportSnapshotPath),
    'Deleted old reports: ' . $deletedReports,
];
$logPath = maintenance_append_log('check-links', $lines);
$lines[] = 'Log: ' . $logPath;
echo implode(PHP_EOL, $lines) . PHP_EOL;
