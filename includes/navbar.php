<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Authenticated Topbar Component
 */

if (!isset($currentUser)) {
    $currentUser = current_user();
}

$roleNotificationLink = match($currentUser['role'] ?? '') {
    'student' => url('student/notifications.php'),
    'admin'   => url('admin/notifications.php'),
    default   => url('staff/notifications.php'),
};

$roleProfileLink = match($currentUser['role'] ?? '') {
    'student' => url('student/profile.php'),
    'admin'   => url('admin/dashboard.php'),
    default   => url('staff/profile.php'),
};

$portalName = match($currentUser['role'] ?? '') {
    'student' => 'Student Portal',
    'admin'   => 'Administration Portal',
    default   => 'Faculty & Staff Portal',
};
?>
<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="d-none d-sm-block">
            <span class="badge bg-light text-dark border px-2 py-1 me-2"><?= e($portalName) ?></span>
            <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>Main Campus &bull; Nakuru</span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Notification Bell Dropdown -->
        <a href="<?= $roleNotificationLink ?>" class="position-relative text-dark p-2 rounded-circle hover-bg-light" title="Notifications">
            <i class="bi bi-bell fs-5 text-secondary"></i>
            <?php if (($unreadNotifications ?? 0) > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                    <?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
                    <span class="visually-hidden">unread notifications</span>
                </span>
            <?php endif; ?>
        </a>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 p-1 pe-3 border rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem; font-weight: 600;">
                    <?= get_user_avatar_initials($currentUser['full_name'] ?? 'User') ?>
                </div>
                <div class="text-start d-none d-md-block lh-sm">
                    <div class="fw-semibold small text-truncate" style="max-width: 140px;"><?= e($currentUser['full_name'] ?? 'User') ?></div>
                    <small class="text-muted" style="font-size: 0.72rem;"><?= format_role($currentUser['role'] ?? '') ?></small>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="min-width: 210px;">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-bold"><?= e($currentUser['full_name'] ?? '') ?></div>
                    <div class="small text-muted text-truncate"><?= e($currentUser['email'] ?? '') ?></div>
                    <span class="badge <?= get_role_badge_class($currentUser['role'] ?? '') ?> mt-1" style="font-size: 0.7rem;">
                        <?= format_role($currentUser['role'] ?? '') ?>
                    </span>
                </li>
                <li><a class="dropdown-item py-2" href="<?= $roleProfileLink ?>"><i class="bi bi-person me-2 text-primary"></i> My Profile</a></li>
                <li><a class="dropdown-item py-2" href="<?= $roleNotificationLink ?>"><i class="bi bi-bell me-2 text-warning"></i> Notifications</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= url('public/logout.php') ?>" data-confirm="Are you sure you want to log out?"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></li>
            </ul>
        </div>
    </div>
</header>
