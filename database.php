<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

/*
|--------------------------------------------------------------------------
| Detección de entorno (basada en tu dominio real)
|--------------------------------------------------------------------------
*/
$host = $_SERVER['SERVER_NAME'] ?? '';

$isLocal  = $_SERVER['HTTP_HOST'] == "localhost";

/*
|--------------------------------------------------------------------------
| Credenciales por entorno
|--------------------------------------------------------------------------
*/
$localConfig = [
    'host' => 'localhost',
    'name' => 'fortelescopes',
    'user' => 'root',
    'pass' => '',
];

$hostingConfig = [
    'host' => 'localhost',
    'name' => 'aspierd1_fortelescopes',
    'user' => 'aspierd1_admin',
    'pass' => 'UnoDosTresCuatroCinco12345...',
];

/*
|--------------------------------------------------------------------------
| Selección de config
|--------------------------------------------------------------------------
*/
$config = $isLocal ? $localConfig : $hostingConfig;

/*
|--------------------------------------------------------------------------
| Override por variables de entorno (más seguro)
|--------------------------------------------------------------------------
*/
$dbHost = getenv('FORTELESCOPES_DB_HOST') ?: $config['host'];
$dbName = getenv('FORTELESCOPES_DB_NAME') ?: $config['name'];
$dbUser = getenv('FORTELESCOPES_DB_USER') ?: $config['user'];
$dbPass = getenv('FORTELESCOPES_DB_PASS') ?: $config['pass'];

/*
|--------------------------------------------------------------------------
| DEBUG opcional (descomenta si falla)
|--------------------------------------------------------------------------
*/
// var_dump([
//     'host' => $dbHost,
//     'db' => $dbName,
//     'user' => $dbUser,
//     'isLocal' => $isLocal,
//     'server' => $host
// ]);
// die();

/*
|--------------------------------------------------------------------------
| Conexión PDO
|--------------------------------------------------------------------------
*/
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);

    echo "<h3>Error de conexión a la base de datos</h3>";

    if ($isLocal) {
        echo "<pre>" . $e->getMessage() . "</pre>";
    }

    exit;
}