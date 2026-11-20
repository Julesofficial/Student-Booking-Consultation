<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Administrator Portal Dashboard
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['admin']);
$currentUser = current_user();
$pdo = get_db_connection();

// System-wide Statistics per Section 6
$stats = [
    'students'           => 0,
    'lecturers'          => 0,
    'advisors'           => 0,
    'dept_heads'         => 0,
    'counselors'         => 0,
    'university_staff'   => 0,
    'total_consultations'=> 0,
    'pending_bookings'   => 0,
    'completed_bookings' => 0,
    'cancelled_bookings' => 0,
    'total_departments'  => 0
];

try {
    // User counts by role
    $stmt = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
    $rolesMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stats['students']         = (int)($rolesMap['student'] ?? 0);
    $stats['lecturers']        = (int)($rolesMap['lecturer'] ?? 0);
    $stats['advisors']         = (int)($rolesMap['academic_advisor'] ?? 0);
    $stats['dept_heads']       = (int)($rolesMap['department_head'] ?? 0);
    $stats['counselors']       = (int)($rolesMap['counselor'] ?? 0);
    $stats['university_staff'] = (int)($rolesMap['university_staff'] ?? 0);

    // Bookings counts by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status");
    $bookingsMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stats['pending_bookings']   = (int)($bookingsMap['Pending'] ?? 0);
    $stats['completed_bookings'] = (int)($bookingsMap['Completed'] ?? 0);
    $stats['cancelled_bookings'] = (int)($bookingsMap['Cancelled'] ?? 0);
    $stats['total_consultations']= (int)array_sum($bookingsMap);

    // Departments count
    $stats['total_departments']  = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

    // Fetch Latest Bookings across university
    $stmt = $pdo->query("
        SELECT 
            b.id AS booking_id,
            b.status,
            b.consultation_reason,
            b.created_at,
            s.consultation_date,
            s.start_time,
            s.end_time,
            stu.full_name AS student_name,
            stu.email AS student_email,
            stf.full_name AS staff_name,
            stf.role AS staff_role,
            stf.department AS staff_dept
        FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        JOIN users stu ON b.student_id = stu.id
        JOIN users stf ON s.staff_id = stf.id
        ORDER BY b.created_at DESC
        LIMIT 6
    ");
    $recentBookings = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Admin dashboard query error: ' . $e->getMessage());
    $recentBookings = [];
}

$page_title = 'Admin Dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Admin Welcome Banner -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-navy text-white overflow-hidden">
    <div class="card-body p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-danger text-white px-3 py-1 rounded-pill mb-2 fw-semibold">
                    <i class="bi bi-shield-lock-fill me-1"></i> System Administration
                </span>
                <h2 class="fw-bold mb-1">Kabarak University Consultation Oversight</h2>
                <p class="text-white-50 mb-3">
                    Centralized governance of user roles, faculty availability, student consultations, and institutional departments.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('admin/staff.php') ?>" class="btn btn-gold btn-sm px-3 rounded-pill text-white fw-semibold">
                        <i class="bi bi-person-plus-fill me-1"></i> Register Faculty / Staff
                    </a>
                    <a href="<?= url('admin/bookings.php') ?>" class="btn btn-outline-light btn-sm px-3 rounded-pill">
                        <i class="bi bi-calendar2-range me-1"></i> View All Bookings
                    </a>
                    <a href="<?= url('admin/reports.php') ?>" class="btn btn-outline-light btn-sm px-3 rounded-pill">
                        <i class="bi bi-graph-up me-1"></i> View Reports
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-end d-none d-lg-block">
                <i class="bi bi-diagram-3-fill display-2 text-white-50"></i>
            </div>
        </div>
    </div>
</div>

<!-- Core System Statistics Grid -->
<div class="mb-2 text-muted fw-bold small text-uppercase letter-spacing-1">
    <i class="bi bi-people-fill text-gold me-1"></i> University User Demographics
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-primary"><?= $stats['students'] ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-mortarboard"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-navy"><?= $stats['lecturers'] ?></div>
                <div class="stat-label">Lecturers</div>
            </div>
            <div class="stat-icon bg-navy bg-opacity-10 text-navy">
                <i class="bi bi-journal-code"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-info"><?= $stats['advisors'] ?></div>
                <div class="stat-label">Advisors</div>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-compass"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-dark"><?= $stats['dept_heads'] ?></div>
                <div class="stat-label">Dept Heads</div>
            </div>
            <div class="stat-icon bg-dark bg-opacity-10 text-dark">
                <i class="bi bi-building"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-purple"><?= $stats['counselors'] ?></div>
                <div class="stat-label">Counselors</div>
            </div>
            <div class="stat-icon bg-purple bg-opacity-10 text-purple">
                <i class="bi bi-heart-pulse"></i>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card">
            <div>
                <div class="stat-number text-teal"><?= $stats['university_staff'] ?></div>
                <div class="stat-label">Univ. Staff</div>
            </div>
            <div class="stat-icon bg-teal bg-opacity-10 text-teal">
                <i class="bi bi-person-badge"></i>
            </div>
        </div>
    </div>
</div>

<!-- Consultation Bookings Metrics -->
<div class="mb-2 text-muted fw-bold small text-uppercase letter-spacing-1">
    <i class="bi bi-calendar-check-fill text-gold me-1"></i> Consultation Activity Metrics
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number"><?= $stats['total_consultations'] ?></div>
                <div class="stat-label">Total Consultations</div>
            </div>
            <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                <i class="bi bi-calendar-range"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number text-warning"><?= $stats['pending_bookings'] ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number text-success"><?= $stats['completed_bookings'] ?></div>
                <div class="stat-label">Completed Consultations</div>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-check2-circle"></i>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-number text-danger"><?= $stats['cancelled_bookings'] ?></div>
                <div class="stat-label">Cancelled Sessions</div>
            </div>
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-x-circle"></i>
            </div>
        </div>
    </div>
</div>

<!-- Recent Consultations Table -->
<div class="card card-modern">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="fw-bold text-navy mb-0">Recent University Consultations</h5>
            <small class="text-muted">Live audit of booked appointments across all departments</small>
        </div>
        <a href="<?= url('admin/bookings.php') ?>" class="btn btn-sm btn-outline-navy rounded-pill">
            View All Bookings <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <div class="card-body p-0">
        <?php if (empty($recentBookings)): ?>
            <div class="empty-state p-5">
                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                <h6 class="empty-state-title">No Consultations Recorded</h6>
                <p class="empty-state-text">No consultation appointments have been booked across the university system yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Staff Member</th>
                            <th>Department</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th class="text-end">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $b): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-navy"><?= e($b['student_name']) ?></div>
                                    <small class="text-muted"><?= e($b['student_email']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($b['staff_name']) ?></div>
                                    <span class="badge <?= get_role_badge_class($b['staff_role']) ?>" style="font-size: 0.7rem;">
                                        <?= format_role($b['staff_role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-secondary"><?= e($b['staff_dept']) ?></small>
                                </td>
                                <td>
                                    <div><?= format_date($b['consultation_date']) ?></div>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= format_time($b['start_time']) ?> &ndash; <?= format_time($b['end_time']) ?></small>
                                </td>
                                <td>
                                    <?= get_booking_status_badge($b['status']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('admin/bookings.php?search=' . urlencode($b['student_name'])) ?>" class="btn btn-sm btn-light border px-2 py-1 rounded" title="View in bookings">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
