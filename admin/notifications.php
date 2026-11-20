<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Administrator Notifications Center
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

require_login(['admin']);
$currentUser = current_user();
$adminId = (int)$currentUser['id'];

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security validation failed.');
        redirect('admin/notifications.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        mark_all_notifications_as_read($adminId);
        set_flash_message('success', 'All administrative notifications marked as read.');
    } elseif ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if ($notifId > 0) {
            mark_notification_as_read($notifId, $adminId);
            set_flash_message('success', 'Notification marked as read.');
        }
    }
    redirect('admin/notifications.php');
}

$notifications = get_user_notifications($adminId, 50);

$page_title = 'Admin Notifications';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-bell-fill text-gold me-2"></i>System Notifications</h3>
        <p class="text-muted mb-0">System alerts, audit events, and user activity notices</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <form action="<?= url('admin/notifications.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-check2-all me-1"></i> Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="card card-modern">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="empty-state p-5">
                <div class="empty-state-icon"><i class="bi bi-bell-slash"></i></div>
                <h5 class="empty-state-title">No Notifications</h5>
                <p class="empty-state-text">No system notifications found for the administrator account.</p>
            </div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                    <li class="list-group-item p-4 <?= !$n['is_read'] ? 'bg-light-subtle border-start border-4 border-warning' : '' ?>">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle <?= !$n['is_read'] ? 'bg-navy text-gold' : 'bg-light text-secondary border' ?> d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                                    <i class="bi bi-shield-check fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-navy mb-1 <?= !$n['is_read'] ? '' : 'text-muted' ?>">
                                        <?= e($n['title']) ?>
                                        <?php if (!$n['is_read']): ?>
                                            <span class="badge bg-gold text-white ms-2" style="font-size: 0.65rem;">NEW</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="text-secondary small mb-1"><?= e($n['message']) ?></p>
                                    <small class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i><?= format_datetime($n['created_at']) ?></small>
                                </div>
                            </div>

                            <?php if (!$n['is_read']): ?>
                                <form action="<?= url('admin/notifications.php') ?>" method="POST" class="flex-shrink-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-light border rounded-pill px-2 py-1" title="Mark as read">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
