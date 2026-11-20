<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Layout Header Component
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

$currentUser = current_user();
$unreadNotifications = $currentUser ? get_unread_notifications_count($currentUser['id']) : 0;
$pageTitle = isset($page_title) ? $page_title . ' - ' . APP_SHORT_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">

    <!-- Bootstrap Icons 1.11 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= url('public/assets/css/style.css') ?>">
</head>
<body class="<?= isset($body_class) ? e($body_class) : '' ?>">
<?php if (is_logged_in()): ?>
<div class="sidebar-overlay"></div>
<div class="app-wrapper">
    <!-- Sidebar Component -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="app-content">
        <!-- Topbar Component -->
        <?php include __DIR__ . '/navbar.php'; ?>

        <!-- Application Body Container -->
        <main class="app-body">
            <?= render_flash_messages() ?>
<?php else: ?>
    <!-- Public Header / Navbar for Guest Users -->
    <nav class="navbar navbar-expand-lg public-navbar navbar-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('public/index.php') ?>">
                <i class="bi bi-mortarboard-fill text-gold fs-3"></i>
                <div>
                    <div class="fw-bold lh-1 text-white">KABARAK UNIVERSITY</div>
                    <small class="text-gold-light fw-medium" style="font-size: 0.72rem; letter-spacing: 0.8px;">CONSULTATION BOOKING SYSTEM</small>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= url('public/index.php') ?>"><i class="bi bi-house-door me-1"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?= url('public/login.php') ?>"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-gold btn-sm px-3 rounded-pill text-white" href="<?= url('public/register.php') ?>">
                            <i class="bi bi-person-plus me-1"></i> Student Register
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container my-4 flex-grow-1">
        <?= render_flash_messages() ?>
<?php endif; ?>
