<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Secure User Logout Action
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

logout_user();
set_flash_message('info', 'You have been successfully logged out of your session.');
redirect('public/login.php');
