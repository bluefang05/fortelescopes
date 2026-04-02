<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

echo "Fortelescopes healthcheck\n";
echo "========================\n";
echo "PHP_VERSION: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";

echo "\nExtensions\n";
$exts = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
foreach ($exts as $ext) {
    echo $ext . ': ' . (extension_loaded($ext) ? 'YES' : 'NO') . "\n";
}

echo "\nEnv (.env)\n";
$envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';
$env = [];

if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

$dbDriver = strtolower($env['DB_DRIVER'] ?? 'mysql');

echo 'ENV_FILE_EXISTS: ' . (is_file($envFile) ? 'YES' : 'NO') . "\n";
echo 'DB_DRIVER_EFFECTIVE: ' . $dbDriver . "\n";
echo 'DB_HOST: ' . ($env['DB_HOST'] ?? '(not set)') . "\n";
echo 'DB_PORT: ' . ($env['DB_PORT'] ?? '(not set)') . "\n";
echo 'DB_NAME: ' . ($env['DB_NAME'] ?? '(not set)') . "\n";
echo 'DB_USER: ' . ($env['DB_USER'] ?? '(not set)') . "\n";

echo "\nPaths\n";
$root = __DIR__;
echo 'ROOT: ' . $root . "\n";
echo 'PUBLIC_ENV_ACCESS_SHOULD_BE_BLOCKED: YES' . "\n";

echo "\nPDO Test (MySQL only)\n";

if ($dbDriver !== 'mysql') {
    echo "DB_CONNECT: FAIL\n";
    echo "DB_ERROR: DB_DRIVER must be mysql\n";
} elseif (!extension_loaded('pdo_mysql')) {
    echo "DB_CONNECT: FAIL\n";
    echo "DB_ERROR: pdo_mysql is missing\n";
} elseif (empty($env['DB_NAME']) || empty($env['DB_USER'])) {
    echo "DB_CONNECT: FAIL\n";
    echo "DB_ERROR: DB_NAME/DB_USER missing in .env\n";
} else {
    try {
        $host = $env['DB_HOST'] ?? 'localhost';
        $port = $env['DB_PORT'] ?? '3306';
        $name = $env['DB_NAME'];
        $user = $env['DB_USER'];
        $pass = $env['DB_PASS'] ?? '';
        $charset = $env['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset
        );

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        echo "DB_CONNECT: OK\n";

        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
            echo "PRODUCTS_TABLE: YES\n";
            echo "PRODUCTS_COUNT: " . $count . "\n";
        } catch (Throwable $e) {
            echo "PRODUCTS_TABLE: NO_OR_UNREADABLE\n";
            echo "PRODUCTS_ERROR: " . $e->getMessage() . "\n";
        }
    } catch (Throwable $e) {
        echo "DB_CONNECT: FAIL\n";
        echo "DB_ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\nDone\n";