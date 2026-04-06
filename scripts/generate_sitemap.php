<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    exit('Forbidden');
}

$projectRoot = dirname(__DIR__);
$targetPath = $projectRoot . DIRECTORY_SEPARATOR . 'sitemap.xml';

$_SERVER['HTTP_HOST'] = 'fortelescopes.com';
$_SERVER['SERVER_NAME'] = 'fortelescopes.com';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/sitemap.xml';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$entries = get_sitemap_entries($pdo);
foreach ($entries as &$entry) {
    $loc = trim((string) ($entry['loc'] ?? ''));
    if ($loc === '') {
        continue;
    }

    $parsedPath = (string) (parse_url($loc, PHP_URL_PATH) ?? '/');
    $parsedQuery = (string) (parse_url($loc, PHP_URL_QUERY) ?? '');
    $normalizedPath = '/' . ltrim($parsedPath, '/');
    if ($normalizedPath === '//') {
        $normalizedPath = '/';
    }

    $entry['loc'] = 'https://' . SITE_DOMAIN . $normalizedPath . ($parsedQuery !== '' ? '?' . $parsedQuery : '');
}
unset($entry);

$xml = render_sitemap_xml($entries) . PHP_EOL;

if (file_put_contents($targetPath, $xml) === false) {
    throw new RuntimeException('Could not write sitemap.xml');
}

echo 'Sitemap generated: ' . $targetPath . PHP_EOL;
