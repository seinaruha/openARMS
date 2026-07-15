<?php
/**
 * openARMS Database Configuration
 * 
 * Industry-standard database configuration with environment variable support
 * Compatible with XAMPP default settings (localhost:3306)
 */

// Prevent direct access to config files
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__DIR__)));
}

// Environment detection (Development/Production)
define('ENVIRONMENT', getenv('OPENARMS_ENV') ?: 'development');

// Database configuration - XAMPP defaults
$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',      // XAMPP MySQL default port
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',    // XAMPP default: empty
    'database' => getenv('DB_NAME') ?: 'openARMS_db',
    'charset' => 'utf8mb4',
];

// Build DSN string
$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";

// PDO options for development/production
$options = [
    PDO::ATTR_ERRMODE => ENVIRONMENT === 'development' 
        ? PDO::ERRMODE_EXCEPTION 
        : PDO::ERRMODE_SILENT,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$dbConfig['charset']}",
];

/**
 * Get PDO database connection instance
 * 
 * @return PDO Database connection object
 * @throws PDOException If connection fails
 */
function getDBConnection(): PDO {
    global $dsn, $dbConfig, $options;
    
    try {
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log error in production, display in development
        if (ENVIRONMENT === 'development') {
            die("Database connection failed: " . $e->getMessage());
        }
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

// Legacy mysqli support (for existing code migration)
$mysqliHost = $dbConfig['host'];
$mysqliUser = $dbConfig['username'];
$mysqliPass = $dbConfig['password'];
$mysqliDbName = $dbConfig['database'];

/**
 * Get MySQLi database connection instance (Legacy support)
 * 
 * @return mysqli MySQLi connection object
 */
function getMysqliConnection(): mysqli {
    global $mysqliHost, $mysqliUser, $mysqliPass, $mysqliDbName;
    
    $conn = new mysqli($mysqliHost, $mysqliUser, $mysqliPass, $mysqliDbName);
    
    if ($conn->connect_error) {
        if (ENVIRONMENT === 'development') {
            die("DB connection failed: " . $conn->connect_error);
        }
        error_log("DB connection failed: " . $conn->connect_error);
        die("Database connection failed. Please try again later.");
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Application paths (XAMPP compatible)
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');  // XAMPP default: port 80
define('APP_ROOT', dirname(BASE_PATH) . '/public');
define('ASSETS_URL', APP_URL . '/assets');

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
