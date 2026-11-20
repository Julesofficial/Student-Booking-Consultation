<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Dynamic Role-Based Sidebar Component
 */

if (!isset($currentUser)) {
    $currentUser = current_user();
}

$userRole = $currentUser['role'] ?? '';
$activeNav = $active_nav ?? '';
?>
<aside class="app-sidebar">
    <!-- Brand / Logo Area -->
    <div class="sidebar-header d-flex align-items-center gap-3">
        <div class="rounded-3 bg-gold text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px;">
            <i class="bi bi-mortarboard-fill fs-4"></i>
        </div>
        <div class="lh-1">
            <h1 class="brand-title">KABARAK</h1>
            <span class="brand-subtitle">CONSULTATION</span>
        </div>
    </div>

    <!-- Navigation Menu Items -->
    <nav class="sidebar-nav">
        <?php if ($userRole === 'student'): ?>
            <!-- Student Navigation Links -->
            <div class="nav-heading">Student Portal</div>
            <a href="<?= url('student/dashboard.php') ?>" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span>
            </a>
            <a href="<?= url('student/staff.php') ?>" class="nav-link <?= $activeNav === 'find_staff' ? 'active' : '' ?>">
                <i class="bi bi-search"></i> <span>Find Staff</span>
            </a>
            <a href="<?= url('student/staff.php?action=book') ?>" class="nav-link <?= $activeNav === 'book_consultation' ? 'active' : '' ?>">
                <i class="bi bi-calendar-plus"></i> <span>Book Consultation</span>
            </a>
            <a href="<?= url('student/my_bookings.php') ?>" class="nav-link <?= $activeNav === 'my_bookings' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> <span>My Bookings</span>
            </a>
            <a href="<?= url('student/consultation_history.php') ?>" class="nav-link <?= $activeNav === 'history' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> <span>Consultation History</span>
            </a>
            <a href="<?= url('student/notifications.php') ?>" class="nav-link <?= $activeNav === 'notifications' ? 'active' : '' ?>">
                <i class="bi bi-bell"></i> <span>Notifications</span>
                <?php if (($unreadNotifications ?? 0) > 0): ?>
                    <span class="badge bg-danger ms-auto"><?= $unreadNotifications ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('student/profile.php') ?>" class="nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> <span>My Profile</span>
            </a>

        <?php elseif ($userRole === 'admin'): ?>
            <!-- Administrator Navigation Links -->
            <div class="nav-heading">Admin Portal</div>
            <a href="<?= url('admin/dashboard.php') ?>" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
            </a>
            <a href="<?= url('admin/users.php') ?>" class="nav-link <?= $activeNav === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> <span>All Users</span>
            </a>
            <a href="<?= url('admin/staff.php') ?>" class="nav-link <?= $activeNav === 'staff' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i> <span>Manage Staff</span>
            </a>
            <a href="<?= url('admin/students.php') ?>" class="nav-link <?= $activeNav === 'students' ? 'active' : '' ?>">
                <i class="bi bi-mortarboard"></i> <span>Manage Students</span>
            </a>
            <a href="<?= url('admin/departments.php') ?>" class="nav-link <?= $activeNav === 'departments' ? 'active' : '' ?>">
                <i class="bi bi-building"></i> <span>Departments</span>
            </a>
            <a href="<?= url('admin/bookings.php') ?>" class="nav-link <?= $activeNav === 'bookings' ? 'active' : '' ?>">
                <i class="bi bi-calendar-range-fill"></i> <span>All Bookings</span>
            </a>
            <a href="<?= url('admin/reports.php') ?>" class="nav-link <?= $activeNav === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i> <span>System Reports</span>
            </a>

        <?php else: ?>
            <!-- University Staff Navigation Links (Lecturer, Advisor, Dept Head, Counselor, Staff) -->
            <div class="nav-heading"><?= format_role($userRole) ?></div>
            <a href="<?= url('staff/dashboard.php') ?>" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> <span>Dashboard</span>
            </a>
            <a href="<?= url('staff/availability.php') ?>" class="nav-link <?= $activeNav === 'availability' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i> <span>Manage Availability</span>
            </a>
            <a href="<?= url('staff/manage_slots.php') ?>" class="nav-link <?= $activeNav === 'manage_slots' ? 'active' : '' ?>">
                <i class="bi bi-clock"></i> <span>Consultation Slots</span>
            </a>
            <a href="<?= url('staff/bookings.php') ?>" class="nav-link <?= $activeNav === 'bookings' ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i> <span>Student Bookings</span>
            </a>
            <a href="<?= url('staff/consultation_history.php') ?>" class="nav-link <?= $activeNav === 'history' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> <span>Consultation History</span>
            </a>
            <a href="<?= url('staff/notifications.php') ?>" class="nav-link <?= $activeNav === 'notifications' ? 'active' : '' ?>">
                <i class="bi bi-bell"></i> <span>Notifications</span>
                <?php if (($unreadNotifications ?? 0) > 0): ?>
                    <span class="badge bg-danger ms-auto"><?= $unreadNotifications ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= url('staff/profile.php') ?>" class="nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> <span>My Profile</span>
            </a>
        <?php endif; ?>

        <!-- Logout Action Link -->
        <div class="nav-heading mt-3">Account</div>
        <a href="<?= url('public/logout.php') ?>" class="nav-link text-danger" data-confirm="Are you sure you want to log out from the portal?">
            <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
        </a>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer small d-flex align-items-center justify-content-between">
        <span class="text-white-50">v<?= APP_VERSION ?></span>
        <span class="badge bg-navy text-gold-light border border-secondary"><?= e(strtoupper($userRole)) ?></span>
    </div>
</aside>
