<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Configuration Template (Example)
 *
 * Copy this file to src/config.php and update with your local environment settings.
 */

if (!defined('APP_INIT')) {
    define('APP_INIT', true);

    define('APP_NAME', 'Kabarak University Consultation Booking System');
    define('APP_SHORT_NAME', 'KABU Consult');
    define('APP_VERSION', '1.0.0');

    date_default_timezone_set('Africa/Nairobi');

    // Database Configuration
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'kabarak_consultation');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');

    // Environment
    define('APP_ENV', 'development');
    if (APP_ENV === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(0);
        ini_set('display_errors', '0');
    }

    // Secure Sessions
    if (session_status() === PHP_SESSION_NONE) {
        $savePath = session_save_path();
        if (empty($savePath) || !@is_writable($savePath)) {
            $sysTmp = sys_get_temp_dir();
            if (@is_writable($sysTmp)) {
                @session_save_path($sysTmp);
            }
        }
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        @session_start();
    }
}
