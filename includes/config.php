<?php

declare(strict_types=1);

// Lightweight .env loader for local development.
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
$isLocalHost = in_array($serverName, ['localhost', '127.0.0.1', '::1'], true);

$envBaseUrl = getenv('APP_BASE_URL');
if ($envBaseUrl !== false && $envBaseUrl !== '') {
    define('BASE_URL', rtrim($envBaseUrl, '/'));
} else {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $scheme . '://' . $host);
}

$envDbDriver = getenv('DB_DRIVER');
if ($envDbDriver !== false && $envDbDriver !== '') {
    $dbDriver = strtolower(trim($envDbDriver));
} else {
    // Auto-select driver when DB_DRIVER is not defined (common on shared hosting).
    if (extension_loaded('pdo_sqlite')) {
        $dbDriver = 'sqlite';
    } elseif (extension_loaded('pdo_mysql')) {
        $dbDriver = 'mysql';
    } else {
        $dbDriver = 'sqlite';
    }
}
define('DB_DRIVER', $dbDriver);

$dbPath = getenv('APP_DB_PATH');
if ($dbPath === false || $dbPath === '') {
    $dbPath = __DIR__ . '/../data/site.sqlite';
}
define('DB_PATH', $dbPath);

define('DB_HOST', getenv('DB_HOST') ?: (($isLocalHost || PHP_SAPI === 'cli') ? '127.0.0.1' : 'localhost'));
define('DB_PORT', getenv('DB_PORT') ?: '3306');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

$defaultDbName = 'aspierd1_fortelescopes';
$defaultDbUser = 'aspierd1_admin';
$defaultDbPass = 'UnoDosTresCuatroCinco12345...';

define('DB_NAME', ($dbName !== false && $dbName !== '') ? $dbName : $defaultDbName);
define('DB_USER', ($dbUser !== false && $dbUser !== '') ? $dbUser : $defaultDbUser);
define('DB_PASS', $dbPass !== false ? $dbPass : $defaultDbPass);
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'change-this-now');
define('AMAZON_ASSOCIATE_TAG', getenv('AMAZON_ASSOCIATE_TAG') ?: 'fortelescopes-20');
define('ENMA_ADVANCED_KEY', getenv('ENMA_ADVANCED_KEY') ?: '');

date_default_timezone_set(TIMEZONE);
