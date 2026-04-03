<?php
declare(strict_types=1);

// ================================================
// CONFIGURACIÓN HARDCODEADA (temporal - sin .env)
// ================================================

const APP_NAME = 'Fortelescopes';
const SITE_DOMAIN = 'fortelescopes.com';
const TIMEZONE = 'America/Santo_Domingo';

$serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$httpHost   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isCli      = PHP_SAPI === 'cli';

// Detección mejorada de entorno local
$isLocal = $isCli 
           || in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
           || in_array($httpHost,   ['localhost', '127.0.0.1', '::1'], true)
           || str_contains($serverName, 'local')
           || str_contains($httpHost, 'local');

// ======================
// BASE URL
// ======================
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$scheme = $https ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $scheme . '://' . $host);

// ======================
// BASE DE DATOS (valores según entorno)
// ======================
define('DB_DRIVER', 'mysql');

if ($isLocal) {
    // ==================== LOCAL (XAMPP / Laragon / etc) ====================
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'fortelescopes');     // ← cambia si tu BD local tiene otro nombre
    define('DB_USER', 'root');
    define('DB_PASS', '');                  // normalmente vacío en local
    define('DB_CHARSET', 'utf8mb4');
} else {
    // ==================== HOSTING (producción) ====================
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'aspierd1_fortelescopes');
    define('DB_USER', 'aspierd1_admin');
    define('DB_PASS', 'UnoDosTresCuatroCinco12345...');   // ← pon aquí tu contraseña real del hosting
    define('DB_CHARSET', 'utf8mb4');
}

// ======================
// Otras configuraciones
// ======================
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'change-this-now');
define('AMAZON_ASSOCIATE_TAG', 'fortelescopes-20');
define('ENMA_ADVANCED_KEY', '');

date_default_timezone_set(TIMEZONE);