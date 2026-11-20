<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Student Portal Dashboard
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

require_login(['student']);
$currentUser = current_user();
$studentId = (int)$currentUser['id'];
$pdo = get_db_connection();

// Calculate Dashboard Statistics
$stats = [
    'total_booked' => 0,
    'upcoming'     => 0,
    'completed'    => 0,
    'pending'      => 0
];

try {
    // Total consultations booked by student
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE student_id = :sid");
    $stmt->execute([':sid' => $studentId]);
    $stats['total_booked'] = (int)$stmt->fetchColumn();

    // Upcoming consultations (status Confirmed or Pending with date >= today)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        WHERE b.student_id = :sid
        AND b.status IN ('Confirmed', 'Pending')
        AND (s.consultation_date > CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time >= CURRENT_TIME()))
    ");
    $stmt->execute([':sid' => $studentId]);
    $stats['upcoming'] = (int)$stmt->fetchColumn();

    // Completed consultations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE student_id = :sid AND status = 'Completed'");
    $stmt->execute([':sid' => $studentId]);
    $stats['completed'] = (int)$stmt->fetchColumn();

    // Pending consultations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE student_id = :sid AND status = 'Pending'");
    $stmt->execute([':sid' => $studentId]);
    $stats['pending'] = (int)$stmt->fetchColumn();

    // Fetch Recently Booked Consultations (latest 5)
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,
            b.consultation_reason,
            b.status AS booking_status,
            b.created_at AS booked_at,
            s.id AS slot_id,
            s.consultation_date,
            s.start_time,
            s.end_time,
            u.id AS staff_id,
            u.full_name AS staff_name,
            u.role AS staff_role,
            u.department AS staff_dept
        FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        JOIN users u ON s.staff_id = u.id
        WHERE b.student_id = :sid
        ORDER BY s.consultation_date DESC, s.start_time DESC
        LIMIT 5
    ");
    $stmt->execute([':sid' => $studentId]);
    $recentBookings = $stmt->fetchAll();

    // Fetch recent notifications
    $recentNotifications = get_user_notifications($studentId, 4);

} catch (PDOException $e) {
    error_log('Student dashboard query error: ' . $e->getMessage());
    $recentBookings = [];
    $recentNotifications = [];
}

$page_title = 'Student Dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-navy text-white overflow-hidden position-relative">
    <div class="card-body p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-gold text-white px-3 py-1 rounded-pill mb-2 fw-semibold">
                    <i class="bi bi-mortarboard-fill me-1"></i> Student Portal
                </span>
                <h2 class="fw-bold mb-1">Welcome, <?= e($currentUser['full_name']) ?>!</h2>
                <p class="text-white-50 mb-3">
                    <?= e($currentUser['course'] ?? 'Undergraduate Degree Program') ?> &bull; 
                    <?= e($currentUser['department'] ?? 'Kabarak University') ?>
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('student/staff.php') ?>" class="btn btn-gold btn-sm px-3 rounded-pill text-white fw-semibold">
                        <i class="bi bi-search me-1"></i> Find University Staff
                    </a>
                    <a href="<?= url('student/my_bookings.php') ?>" class="btn btn-outline-light btn-sm px-3 rounded-pill">
                        <i class="bi bi-calendar2-check me-1"></i> View My Bookings
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-end d-none d-lg-block">
                <i class="bi bi-calendar2-week display-2 text-white-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number"><?= $stats['total_booked'] ?></div>
                <div class="stat-label">Total Consultations</div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number text-success"><?= $stats['upcoming'] ?></div>
                <div class="stat-label">Upcoming Sessions</div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-calendar-event"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number text-info"><?= $stats['completed'] ?></div>
                <div class="stat-label">Completed Sessions</div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-patch-check-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number text-warning"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recently Booked Consultations -->
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold text-navy mb-0">Recently Booked Consultations</h5>
                    <small class="text-muted">Track your recent consultation schedules</small>
                </div>
                <a href="<?= url('student/my_bookings.php') ?>" class="btn btn-sm btn-outline-navy rounded-pill">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="card-body p-0">
                <?php if (empty($recentBookings)): ?>
                    <div class="empty-state p-5">
                        <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                        <h6 class="empty-state-title">No Consultations Booked Yet</h6>
                        <p class="empty-state-text">You have not scheduled any consultation appointments. Browse available university staff to book your first slot.</p>
                        <a href="<?= url('student/staff.php') ?>" class="btn btn-navy btn-sm rounded-pill px-3">
                            <i class="bi bi-search me-1"></i> Browse Staff Directory
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $bk): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.8rem; font-weight: 600;">
                                                    <?= get_user_avatar_initials($bk['staff_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-navy"><?= e($bk['staff_name']) ?></div>
                                                    <small class="text-muted"><?= format_role($bk['staff_role']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= format_date($bk['consultation_date']) ?></div>
                                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= format_time($bk['start_time']) ?> - <?= format_time($bk['end_time']) ?></small>
                                        </td>
                                        <td>
                                            <?= get_booking_status_badge($bk['booking_status']) ?>
                                        </td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 170px;" title="<?= e($bk['consultation_reason']) ?>">
                                                <?= e($bk['consultation_reason']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('student/my_bookings.php') ?>" class="btn btn-sm btn-light border px-2 py-1 rounded" title="View details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions and Notifications Sidebar -->
    <div class="col-lg-4">
        <!-- Quick Booking Option -->
        <div class="card card-modern mb-4">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-gold bg-opacity-10 text-gold d-inline-flex p-3 mb-3">
                    <i class="bi bi-calendar-plus-fill fs-3 text-warning"></i>
                </div>
                <h5 class="fw-bold text-navy">Quick Booking</h5>
                <p class="text-muted small mb-3">Need guidance with research, coursework, degree audits, or mental wellbeing?</p>
                <a href="<?= url('student/staff.php') ?>" class="btn btn-navy w-100 rounded-pill py-2">
                    <i class="bi bi-search me-1"></i> Find Available Staff
                </a>
            </div>
        </div>

        <!-- Recent Notifications -->
        <div class="card card-modern">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-bell-fill text-gold me-2"></i>Recent Updates</h6>
                <a href="<?= url('student/notifications.php') ?>" class="small text-decoration-none">View All</a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($recentNotifications)): ?>
                    <p class="text-muted small text-center my-3">No new notifications</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentNotifications as $notif): ?>
                            <li class="list-group-item px-1 py-2 border-0 border-bottom">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                                    <div>
                                        <div class="fw-semibold small <?= $notif['is_read'] ? 'text-muted' : 'text-navy' ?>"><?= e($notif['title']) ?></div>
                                        <div class="small text-muted lh-sm"><?= e($notif['message']) ?></div>
                                        <small class="text-secondary" style="font-size: 0.7rem;"><?= format_datetime($notif['created_at']) ?></small>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
