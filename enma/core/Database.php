<?php
namespace Enma\Core;

use PDO;

class Database
{
    private static ?self $instance = null;
    private PDO $connection;

    private function __construct()
    {
        require_once dirname(__DIR__, 2) . '/includes/config.php';
        require_once dirname(__DIR__, 2) . '/includes/db.php';

        $this->connection = \db();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
