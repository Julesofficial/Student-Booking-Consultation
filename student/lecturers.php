<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Dedicated Lecturers Directory Shortcut
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['student']);

// Redirect to staff directory pre-filtered to lecturers
redirect('student/staff.php?role=lecturer');
