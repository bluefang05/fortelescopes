<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

/*
|--------------------------------------------------------------------------
| Credenciales simples por entorno
|--------------------------------------------------------------------------
|
| Edita el bloque "hosting" antes de subir a produccion si no quieres usar
| config/credentials.php ni variables de entorno. El codigo sigue aceptando
| overrides externos, pero ya no depende de ellos para funcionar.
|
*/
$inlineLocalDatabaseConfig = [
    'host' => '127.0.0.1',
    'name' => 'aspierd1_fortelescopes',
    'user' => 'aspierd1_admin',
    'pass' => 'UnoDosTresCuatroCinco12345...',
];

$inlineHostingDatabaseConfig = [
    'host' => 'localhost',
    'name' => 'aspierd1_fortelescopes',
    'user' => 'aspierd1_admin',
    'pass' => 'UnoDosTresCuatroCinco12345...',
];

$sharedDatabaseConfig = is_array(APP_CONFIG_CREDENTIALS['database'] ?? null) ? APP_CONFIG_CREDENTIALS['database'] : [];
$environmentDatabaseConfig = APP_IS_LOCAL
    ? (is_array(APP_CONFIG_CREDENTIALS['local']['database'] ?? null) ? APP_CONFIG_CREDENTIALS['local']['database'] : [])
    : (is_array(APP_CONFIG_CREDENTIALS['production']['database'] ?? null) ? APP_CONFIG_CREDENTIALS['production']['database'] : []);
$devDatabaseConfig = is_array(APP_DEV_CREDENTIALS['database'] ?? null) ? APP_DEV_CREDENTIALS['database'] : [];
$inlineDatabaseConfig = APP_IS_LOCAL ? $inlineLocalDatabaseConfig : $inlineHostingDatabaseConfig;
$databaseConfig = array_merge(
    $inlineDatabaseConfig,
    $sharedDatabaseConfig,
    $environmentDatabaseConfig,
    APP_IS_LOCAL ? $devDatabaseConfig : []
);

$dbHostingConfigHasPlaceholders = false;

if (!defined('DB_HOSTING_CONFIG_HAS_PLACEHOLDERS')) {
    define('DB_HOSTING_CONFIG_HAS_PLACEHOLDERS', $dbHostingConfigHasPlaceholders);
}

if (!defined('DB_CREDENTIALS_RUNTIME_MODE')) {
    define('DB_CREDENTIALS_RUNTIME_MODE', APP_IS_LOCAL ? 'local' : 'hosting');
}

$envDbMode = strtolower(trim((string) (getenv('FORTELESCOPES_DB_MODE') ?: '')));
$preferHosting = $envDbMode === 'hosting';
$preferLocal = $envDbMode === 'local';

$localConfig = array_merge(
    $inlineLocalDatabaseConfig,
    $sharedDatabaseConfig,
    is_array(APP_CONFIG_CREDENTIALS['local']['database'] ?? null) ? APP_CONFIG_CREDENTIALS['local']['database'] : [],
    $devDatabaseConfig
);
$hostingConfig = array_merge(
    $inlineHostingDatabaseConfig,
    $sharedDatabaseConfig,
    is_array(APP_CONFIG_CREDENTIALS['production']['database'] ?? null) ? APP_CONFIG_CREDENTIALS['production']['database'] : []
);

$dbCandidates = [];
if ($preferLocal) {
    $dbCandidates[] = ['mode' => 'local', 'config' => $localConfig];
    $dbCandidates[] = ['mode' => 'hosting', 'config' => $hostingConfig];
} elseif ($preferHosting || APP_IS_LOCAL) {
    $dbCandidates[] = ['mode' => 'hosting', 'config' => $hostingConfig];
    $dbCandidates[] = ['mode' => 'local', 'config' => $localConfig];
} else {
    $dbCandidates[] = ['mode' => 'hosting', 'config' => $hostingConfig];
}

$dbHost = '';
$dbName = '';
$dbUser = '';
$dbPass = '';
$pdo = null;
$dbConnectionError = '';

foreach ($dbCandidates as $candidate) {
    $mode = (string) $candidate['mode'];
    $config = (array) $candidate['config'];

    if ($mode === 'hosting' && APP_IS_LOCAL && DB_HOSTING_CONFIG_HAS_PLACEHOLDERS) {
        continue;
    }

    $dbHost = getenv('FORTELESCOPES_DB_HOST') ?: ($config['host'] ?? ($serverName === 'localhost' || $serverName === '127.0.0.1' ? '127.0.0.1' : 'localhost'));
    $dbName = getenv('FORTELESCOPES_DB_NAME') ?: ($config['name'] ?? 'aspierd1_fortelescopes');
    $dbUser = getenv('FORTELESCOPES_DB_USER') ?: ($config['user'] ?? 'aspierd1_admin');
    $dbPass = getenv('FORTELESCOPES_DB_PASS');

    if ($dbPass === false) {
        $dbPass = $config['pass'] ?? 'UnoDosTresCuatroCinco12345...';
    }

    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        if (!defined('DB_CREDENTIALS_RUNTIME_MODE')) {
            define('DB_CREDENTIALS_RUNTIME_MODE', $mode);
        }
        break;
    } catch (PDOException $exception) {
        $dbConnectionError = $exception->getMessage();
        error_log(sprintf(
            '[FORTELESCOPES][DB] mode=%s host=%s db=%s user=%s message=%s',
            $mode,
            (string) $dbHost,
            (string) $dbName,
            (string) $dbUser,
            $dbConnectionError
        ));
        $pdo = null;
    }
}

if (!$pdo instanceof PDO) {
    if (defined('DB_CONNECTION_OPTIONAL') && DB_CONNECTION_OPTIONAL === true) {
        $pdo = null;
    } else {
        http_response_code(500);
        if (defined('DB_HOSTING_CONFIG_HAS_PLACEHOLDERS') && DB_HOSTING_CONFIG_HAS_PLACEHOLDERS === true && !APP_IS_LOCAL) {
            die('Config de base de datos incompleta en hosting. Edita config/database.php (bloque hosting) o crea config/credentials.php o define variables FORTELESCOPES_DB_HOST, FORTELESCOPES_DB_NAME, FORTELESCOPES_DB_USER, FORTELESCOPES_DB_PASS.');
        }

        die('Error de conexion a base de datos.');
    }
}
