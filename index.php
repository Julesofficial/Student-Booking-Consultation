<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Root Gateway and Route Dispatcher
 */

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/functions.php';
require_once __DIR__ . '/src/auth.php';

if (is_logged_in()) {
    redirect(get_role_dashboard_url($_SESSION['user_role']));
} else {
    redirect('public/index.php');
}
