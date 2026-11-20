<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Staff & Faculty Portal Dashboard
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

require_login(STAFF_ROLES);
$currentUser = current_user();
$staffId = (int)$currentUser['id'];
$pdo = get_db_connection();

// Dashboard Statistics
$stats = [
    'total_slots' => 0,
    'available_slots' => 0,
    'booked_slots' => 0,
    'pending_requests' => 0,
    'upcoming' => 0,
    'completed' => 0
];

try {
    // Total slots
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultation_slots WHERE staff_id = :sid");
    $stmt->execute([':sid' => $staffId]);
    $stats['total_slots'] = (int)$stmt->fetchColumn();

    // Available future slots
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM consultation_slots 
        WHERE staff_id = :sid 
        AND status = 'available' 
        AND (consultation_date > CURRENT_DATE() OR (consultation_date = CURRENT_DATE() AND start_time > CURRENT_TIME()))
    ");
    $stmt->execute([':sid' => $staffId]);
    $stats['available_slots'] = (int)$stmt->fetchColumn();

    // Booked slots
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultation_slots WHERE staff_id = :sid AND status = 'booked'");
    $stmt->execute([':sid' => $staffId]);
    $stats['booked_slots'] = (int)$stmt->fetchColumn();

    // Pending requests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        WHERE s.staff_id = :sid AND b.status = 'Pending'
    ");
    $stmt->execute([':sid' => $staffId]);
    $stats['pending_requests'] = (int)$stmt->fetchColumn();

    // Upcoming appointments (Confirmed or Pending with date >= today)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        WHERE s.staff_id = :sid 
        AND b.status IN ('Confirmed', 'Pending')
        AND (s.consultation_date > CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time >= CURRENT_TIME()))
    ");
    $stmt->execute([':sid' => $staffId]);
    $stats['upcoming'] = (int)$stmt->fetchColumn();

    // Completed consultations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        WHERE s.staff_id = :sid AND b.status = 'Completed'
    ");
    $stmt->execute([':sid' => $staffId]);
    $stats['completed'] = (int)$stmt->fetchColumn();

    // Fetch Upcoming Appointments (next 5)
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,
            b.consultation_reason,
            b.status AS booking_status,
            s.id AS slot_id,
            s.consultation_date,
            s.start_time,
            s.end_time,
            u.id AS student_id,
            u.full_name AS student_name,
            u.email AS student_email,
            u.course AS student_course
        FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        JOIN users u ON b.student_id = u.id
        WHERE s.staff_id = :sid
        AND b.status IN ('Confirmed', 'Pending')
        AND (s.consultation_date > CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time >= CURRENT_TIME()))
        ORDER BY s.consultation_date ASC, s.start_time ASC
        LIMIT 5
    ");
    $stmt->execute([':sid' => $staffId]);
    $upcomingAppointments = $stmt->fetchAll();

    // Fetch recent notifications
    $recentNotifications = get_user_notifications($staffId, 4);

} catch (PDOException $e) {
    error_log('Staff dashboard error: ' . $e->getMessage());
    $upcomingAppointments = [];
    $recentNotifications = [];
}

$avail = get_staff_availability_badge($stats['available_slots']);

$page_title = 'Staff Dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner with Role Adaptations -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-navy text-white overflow-hidden">
    <div class="card-body p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge <?= get_role_badge_class($currentUser['role']) ?> px-3 py-1">
                        <?= format_role($currentUser['role']) ?>
                    </span>
                    <span class="badge <?= $avail['class'] ?> px-3 py-1">
                        <i class="bi <?= $avail['icon'] ?> me-1"></i><?= $avail['status'] ?> (<?= $avail['count'] ?> open)
                    </span>
                </div>
                <h2 class="fw-bold mb-1">Welcome, <?= e($currentUser['full_name']) ?>!</h2>
                <p class="text-white-50 mb-3">
                    <?= e($currentUser['department'] ?? 'Department of Computer Science & Informatics') ?> &bull; 
                    <?= e($currentUser['service'] ?? 'Academic Consultations') ?>
                </p>

                <!-- Role Specific Focus Alerts -->
                <?php if ($currentUser['role'] === 'counselor'): ?>
                    <div class="p-2 px-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-25 small mb-3">
                        <i class="bi bi-shield-shaded text-gold me-1"></i>
                        <strong>Counseling Confidentiality:</strong> All student appointments and personal disclosures are strictly protected under university psychological ethics protocols.
                    </div>
                <?php elseif ($currentUser['role'] === 'department_head'): ?>
                    <div class="p-2 px-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-25 small mb-3">
                        <i class="bi bi-diagram-3 text-gold me-1"></i>
                        <strong>Department Head Oversight:</strong> Review student departmental petitions, academic appeals, and faculty policy consultations.
                    </div>
                <?php elseif ($currentUser['role'] === 'academic_advisor'): ?>
                    <div class="p-2 px-3 rounded-3 bg-white bg-opacity-10 border border-white border-opacity-25 small mb-3">
                        <i class="bi bi-mortarboard text-gold me-1"></i>
                        <strong>Advisory Focus:</strong> Provide guidance on course enrollment prerequisites, degree completion audits, and graduation requirements.
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('staff/manage_slots.php') ?>" class="btn btn-gold btn-sm px-3 rounded-pill text-white fw-semibold">
                        <i class="bi bi-plus-circle me-1"></i> Add Consultation Slots
                    </a>
                    <a href="<?= url('staff/bookings.php') ?>" class="btn btn-outline-light btn-sm px-3 rounded-pill">
                        <i class="bi bi-people me-1"></i> View Student Bookings
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-end d-none d-lg-block">
                <i class="bi bi-person-workspace display-2 text-white-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number"><?= $stats['total_slots'] ?></div>
                <div class="stat-label">Total Slots</div>
            </div>
            <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-success"><?= $stats['available_slots'] ?></div>
                <div class="stat-label">Available Slots</div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-check-circle"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-primary"><?= $stats['booked_slots'] ?></div>
                <div class="stat-label">Booked Slots</div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-calendar2-check"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-warning"><?= $stats['pending_requests'] ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-hourglass-split"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-info"><?= $stats['upcoming'] ?></div>
                <div class="stat-label">Upcoming</div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-calendar-event"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-purple"><?= $stats['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-icon bg-purple bg-opacity-10 text-purple">
                <i class="bi bi-patch-check"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Upcoming Appointments Table -->
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold text-navy mb-0">Upcoming Student Appointments</h5>
                    <small class="text-muted">Review sessions scheduled with students</small>
                </div>
                <a href="<?= url('staff/bookings.php') ?>" class="btn btn-sm btn-outline-navy rounded-pill">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="card-body p-0">
                <?php if (empty($upcomingAppointments)): ?>
                    <div class="empty-state p-5">
                        <div class="empty-state-icon"><i class="bi bi-calendar2-x"></i></div>
                        <h6 class="empty-state-title">No Upcoming Appointments</h6>
                        <p class="empty-state-text">You have no upcoming student consultations. Make sure your consultation slots are open so students can book.</p>
                        <a href="<?= url('staff/manage_slots.php') ?>" class="btn btn-navy btn-sm rounded-pill px-4">
                            <i class="bi bi-plus-circle me-1"></i> Add Consultation Slots
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Date & Time</th>
                                    <th>Reason / Agenda</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingAppointments as $app): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.8rem; font-weight: 600;">
                                                    <?= get_user_avatar_initials($app['student_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-navy"><?= e($app['student_name']) ?></div>
                                                    <small class="text-muted"><?= e($app['student_email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-secondary"><?= e($app['student_course'] ?? 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= format_date($app['consultation_date']) ?></div>
                                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= format_time($app['start_time']) ?> &ndash; <?= format_time($app['end_time']) ?></small>
                                        </td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 170px;" title="<?= e($app['consultation_reason']) ?>">
                                                <?= e($app['consultation_reason']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= url('staff/bookings.php') ?>" class="btn btn-sm btn-outline-navy px-2 py-1 rounded">
                                                <i class="bi bi-gear me-1"></i> Manage
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

    <!-- Quick Slot Creator & Notifications -->
    <div class="col-lg-4">
        <div class="card card-modern mb-4">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-navy bg-opacity-10 text-navy d-inline-flex p-3 mb-3">
                    <i class="bi bi-calendar-plus fs-3"></i>
                </div>
                <h5 class="fw-bold text-navy">Manage Schedule</h5>
                <p class="text-muted small mb-3">Publish new consultation slots or set regular office hours for students.</p>
                <div class="d-grid gap-2">
                    <a href="<?= url('staff/manage_slots.php') ?>" class="btn btn-navy rounded-pill py-2">
                        <i class="bi bi-plus-circle me-1"></i> Add Single Slot
                    </a>
                    <a href="<?= url('staff/availability.php') ?>" class="btn btn-outline-secondary rounded-pill py-2">
                        <i class="bi bi-lightning-charge me-1"></i> Quick Slot Generator
                    </a>
                </div>
            </div>
        </div>

        <div class="card card-modern">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy mb-0"><i class="bi bi-bell-fill text-gold me-2"></i>Notifications</h6>
                <a href="<?= url('staff/notifications.php') ?>" class="small text-decoration-none">View All</a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($recentNotifications)): ?>
                    <p class="text-muted small text-center my-3">No new notifications</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentNotifications as $notif): ?>
                            <li class="list-group-item px-1 py-2 border-0 border-bottom">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-bell text-gold mt-1"></i>
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
