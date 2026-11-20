<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Database Connection using PDO
 */

require_once __DIR__ . '/config.php';

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                $errorMsg = "<div style='font-family:sans-serif; max-width:650px; margin:50px auto; padding:25px; border:1px solid #f5c2c7; background:#f8d7da; border-radius:8px; color:#842029;'>";
                $errorMsg .= "<h3 style='margin-top:0;'>Database Connection Error</h3>";
                $errorMsg .= "<p>Unable to connect to MySQL database <strong>" . htmlspecialchars(DB_NAME) . "</strong>.</p>";
                $errorMsg .= "<p><strong>Details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                $errorMsg .= "<p><em>Make sure MySQL is running in XAMPP and you have executed <code>database/schema.sql</code> and <code>database/seed.sql</code>.</em></p>";
                $errorMsg .= "</div>";
                die($errorMsg);
            } else {
                error_log('Database Connection Error: ' . $e->getMessage());
                die("A temporary database connection error occurred. Please contact the university system administrator.");
            }
        }
    }

    return $pdo;
}
