<?php
declare(strict_types=1);

// ================================================
// CONFIGURATION (temporary hardcoded defaults)
// ================================================

const APP_NAME = 'Fortelescopes';
const SITE_DOMAIN = 'fortelescopes.com';
const TIMEZONE = 'America/Santo_Domingo';

$serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isCli = PHP_SAPI === 'cli';
$isWindows = DIRECTORY_SEPARATOR === '\\';

// Do not treat every CLI context as local; cPanel cron is CLI too.
$isLocalHost = in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
    || in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)
    || str_contains($serverName, 'local')
    || str_contains($httpHost, 'local');
$isLikelyLocalCli = $isCli && $isWindows;
$isLocal = $isLocalHost || $isLikelyLocalCli;

// ======================
// BASE URL
// ======================
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host);

// ======================
// DATABASE
// ======================
$localDb = [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'fortelescopes',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4',
];

$hostingDb = [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'aspierd1_fortelescopes',
    'user' => 'aspierd1_admin',
    'pass' => 'UnoDosTresCuatroCinco12345...',
    'charset' => 'utf8mb4',
];

$selectedDb = $isLocal ? $localDb : $hostingDb;

// Optional env overrides (preferred for hosting/cron).
$dbDriver = getenv('DB_DRIVER') ?: getenv('FORTELESCOPES_DB_DRIVER') ?: $selectedDb['driver'];
$dbHost = getenv('DB_HOST') ?: getenv('FORTELESCOPES_DB_HOST') ?: $selectedDb['host'];
$dbPort = getenv('DB_PORT') ?: getenv('FORTELESCOPES_DB_PORT') ?: $selectedDb['port'];
$dbName = getenv('DB_NAME') ?: getenv('FORTELESCOPES_DB_NAME') ?: $selectedDb['name'];
$dbUser = getenv('DB_USER') ?: getenv('FORTELESCOPES_DB_USER') ?: $selectedDb['user'];
$dbPass = getenv('DB_PASS') ?: getenv('FORTELESCOPES_DB_PASS') ?: $selectedDb['pass'];
$dbCharset = getenv('DB_CHARSET') ?: getenv('FORTELESCOPES_DB_CHARSET') ?: $selectedDb['charset'];

define('DB_DRIVER', (string) $dbDriver);
define('DB_HOST', (string) $dbHost);
define('DB_PORT', (string) $dbPort);
define('DB_NAME', (string) $dbName);
define('DB_USER', (string) $dbUser);
define('DB_PASS', (string) $dbPass);
define('DB_CHARSET', (string) $dbCharset);

// ======================
// Other settings
// ======================
define('AMAZON_ASSOCIATE_TAG', 'fortelescopes-20');
define('ENMA_ADVANCED_KEY', '');
define('INDEXNOW_KEY', '15fd8574046040c7b6653ff63836ac8a');

date_default_timezone_set(TIMEZONE);