<?php
namespace Enma\Core;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Use same credentials as the main application (includes/config.php)
            $serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
            $httpHost   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $isCli      = PHP_SAPI === 'cli';
            
            // Detección mejorada de entorno local
            $isLocal = $isCli 
                       || in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
                       || in_array($httpHost,   ['localhost', '127.0.0.1', '::1'], true)
                       || str_contains($serverName, 'local')
                       || str_contains($httpHost, 'local');
            
            if ($isLocal) {
                $host = '127.0.0.1';
                $db   = 'fortelescopes';
                $user = 'root';
                $pass = '';
            } else {
                // Production hosting credentials
                $host = 'localhost';
                $db   = 'aspierd1_fortelescopes';
                $user = 'aspierd1_admin';
                $pass = 'UnoDosTresCuatroCinco12345...';
            }
            
            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}
