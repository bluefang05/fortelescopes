<?php
declare(strict_types=1);

const APP_NAME = 'Fortelescopes';
const SITE_DOMAIN = 'fortelescopes.com';

function load_env_file(string $path): void
{
    static $loaded = [];

    if (isset($loaded[$path]) || !is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        $loaded[$path] = true;
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, '\'') && str_ends_with($value, '\''))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    $loaded[$path] = true;
}

function env_value(string $key, ?string $fallback = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        return $fallback;
    }

    $value = trim((string) $value);
    return $value === '' ? $fallback : $value;
}

load_env_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

$serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isCli = PHP_SAPI === 'cli';
$isWindows = DIRECTORY_SEPARATOR === '\\';

$isLocalHost = in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
    || in_array($httpHost, ['localhost', '127.0.0.1', '::1'], true)
    || str_contains($serverName, '.local')
    || str_contains($httpHost, '.local');
$isLikelyLocalCli = $isCli && $isWindows;
$isLocal = $isLocalHost || $isLikelyLocalCli;

$configuredTimezone = env_value('APP_TIMEZONE')
    ?? env_value('TIMEZONE')
    ?? 'America/Santiago';
define('TIMEZONE', $configuredTimezone);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = env_value('APP_BASE_URL') ?? ($scheme . '://' . $host);
define('BASE_URL', rtrim($baseUrl, '/'));

$appEnv = env_value('APP_ENV') ?? ($isLocal ? 'local' : 'production');
$appDebug = strtolower((string) (env_value('APP_DEBUG') ?? ($isLocal ? '1' : '0')));
define('APP_ENV', $appEnv);
define('APP_DEBUG', in_array($appDebug, ['1', 'true', 'yes', 'on'], true));

$defaultDb = [
    'driver' => 'mysql',
    'host' => $isLocal ? '127.0.0.1' : 'localhost',
    'port' => '3306',
    'name' => $isLocal ? 'fortelescopes' : '',
    'user' => $isLocal ? 'root' : '',
    'pass' => '',
    'charset' => 'utf8mb4',
];

$dbDriver = env_value('DB_DRIVER')
    ?? env_value('FORTELESCOPES_DB_DRIVER')
    ?? $defaultDb['driver'];
$dbHost = env_value('DB_HOST')
    ?? env_value('FORTELESCOPES_DB_HOST')
    ?? $defaultDb['host'];
$dbPort = env_value('DB_PORT')
    ?? env_value('FORTELESCOPES_DB_PORT')
    ?? $defaultDb['port'];
$dbName = env_value('DB_NAME')
    ?? env_value('FORTELESCOPES_DB_NAME')
    ?? $defaultDb['name'];
$dbUser = env_value('DB_USER')
    ?? env_value('FORTELESCOPES_DB_USER')
    ?? $defaultDb['user'];
$dbPass = env_value('DB_PASS')
    ?? env_value('FORTELESCOPES_DB_PASS')
    ?? $defaultDb['pass'];
$dbCharset = env_value('DB_CHARSET')
    ?? env_value('FORTELESCOPES_DB_CHARSET')
    ?? $defaultDb['charset'];

define('DB_DRIVER', (string) $dbDriver);
define('DB_HOST', (string) $dbHost);
define('DB_PORT', (string) $dbPort);
define('DB_NAME', (string) $dbName);
define('DB_USER', (string) $dbUser);
define('DB_PASS', (string) $dbPass);
define('DB_CHARSET', (string) $dbCharset);

define('AMAZON_ASSOCIATE_TAG', (string) (env_value('AMAZON_ASSOCIATE_TAG') ?? 'fortelescopes-20'));
define('ENMA_ADVANCED_KEY', (string) (env_value('ENMA_ADVANCED_KEY') ?? ''));
define('INDEXNOW_KEY', (string) (env_value('INDEXNOW_KEY') ?? ''));

date_default_timezone_set(TIMEZONE);
