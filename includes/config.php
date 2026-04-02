<?php

declare(strict_types=1);

// Lightweight .env loader for local development / shared hosting.
$envFile = __DIR__ . '/../.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

const APP_NAME = 'Fortelescopes';
const SITE_DOMAIN = 'fortelescopes.com';
const TIMEZONE = 'America/Santo_Domingo';

$serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isCli = PHP_SAPI === 'cli';
$isLocalHost = in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
    || in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true);

// Base URL
$envBaseUrl = getenv('APP_BASE_URL');
if ($envBaseUrl !== false && $envBaseUrl !== '') {
    define('BASE_URL', rtrim($envBaseUrl, '/'));
} else {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $scheme . '://' . $host);
}

// MySQL only
define('DB_DRIVER', 'mysql');

// Safer host defaults
$envDbHost = getenv('DB_HOST');
$envDbPort = getenv('DB_PORT');
$envDbName = getenv('DB_NAME');
$envDbUser = getenv('DB_USER');
$envDbPass = getenv('DB_PASS');
$envDbCharset = getenv('DB_CHARSET');

define('DB_HOST', ($envDbHost !== false && $envDbHost !== '') ? $envDbHost : ($isLocalHost || $isCli ? '127.0.0.1' : 'localhost'));
define('DB_PORT', ($envDbPort !== false && $envDbPort !== '') ? $envDbPort : '3306');
define('DB_NAME', ($envDbName !== false) ? trim($envDbName) : '');
define('DB_USER', ($envDbUser !== false) ? trim($envDbUser) : '');
define('DB_PASS', ($envDbPass !== false) ? $envDbPass : '');
define('DB_CHARSET', ($envDbCharset !== false && $envDbCharset !== '') ? $envDbCharset : 'utf8mb4');

// App auth / integrations
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'change-this-now');
define('AMAZON_ASSOCIATE_TAG', getenv('AMAZON_ASSOCIATE_TAG') ?: 'fortelescopes-20');
define('ENMA_ADVANCED_KEY', getenv('ENMA_ADVANCED_KEY') ?: '');

date_default_timezone_set(TIMEZONE);