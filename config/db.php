<?php
$envPath = dirname(__DIR__) . '/.env';

if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $_ENV[trim($key)] = trim($value);
    }
}

/**
 * Debug mode
 */
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

/**
 * MySQLi connection
 */
function db(): mysqli
{
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_USER'] ?? 'root',
            $_ENV['DB_PASS'] ?? '',
            $_ENV['DB_NAME'] ?? 'invoicing'
        );

        if ($conn->connect_errno) {
            throw new RuntimeException(
                'Database connection failed: ' . $conn->connect_error
            );
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}