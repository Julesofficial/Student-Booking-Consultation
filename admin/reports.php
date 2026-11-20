<?php
/**
 * Kabarak University - Student Consultation Booking System
 * System Reports, Analytics & Performance Metrics
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['admin']);
$currentUser = current_user();
$pdo = get_db_connection();

// Status Breakdown
$statusCounts = [
    'Confirmed' => 0,
    'Pending'   => 0,
    'Completed' => 0,
    'Cancelled' => 0,
    'Rejected'  => 0
];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM bookings GROUP BY status");
    while ($row = $stmt->fetch()) {
        $statusCounts[$row['status']] = (int)$row['cnt'];
    }
} catch (PDOException $e) {}

$totalBookings = array_sum($statusCounts);

// Department-level Consultation Analytics
$deptAnalytics = [];
try {
    $stmt = $pdo->query("
        SELECT 
            d.department_name,
            COUNT(DISTINCT u.id) AS staff_count,
            COUNT(DISTINCT s.id) AS slot_count,
            COUNT(DISTINCT b.id) AS booking_count,
            COUNT(DISTINCT CASE WHEN b.status = 'Completed' THEN b.id END) AS completed_count
        FROM departments d
        LEFT JOIN users u ON d.department_name = u.department AND u.role != 'student'
        LEFT JOIN consultation_slots s ON u.id = s.staff_id
        LEFT JOIN bookings b ON s.id = b.slot_id
        GROUP BY d.id
        ORDER BY booking_count DESC
    ");
    $deptAnalytics = $stmt->fetchAll();
} catch (PDOException $e) {}

// Top 5 Most Requested Faculty / Staff Members
$topStaff = [];
try {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.full_name,
            u.role,
            u.department,
            COUNT(b.id) AS total_bookings,
            COUNT(CASE WHEN b.status = 'Completed' THEN 1 END) AS completed_bookings
        FROM users u
        JOIN consultation_slots s ON u.id = s.staff_id
        JOIN bookings b ON s.id = b.slot_id
        WHERE u.role IN ('lecturer','academic_advisor','department_head','counselor','university_staff')
        GROUP BY u.id
        ORDER BY total_bookings DESC
        LIMIT 5
    ");
    $topStaff = $stmt->fetchAll();
} catch (PDOException $e) {}

$page_title = 'System Reports';
$active_nav = 'reports';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-graph-up-arrow text-gold me-2"></i>Institutional Consultation Reports</h3>
        <p class="text-muted mb-0">University-level analytics on consultation adoption, departmental performance, and service demand</p>
    </div>
    <button type="button" class="btn btn-outline-navy rounded-pill px-4" onclick="window.print()">
        <i class="bi bi-printer me-1"></i> Print / Export Report
    </button>
</div>

<!-- Status Distribution Cards -->
<div class="row g-3 mb-4">
    <div class="col-md">
        <div class="card card-modern text-center p-3">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Total Bookings</small>
            <div class="fs-3 fw-bold text-navy my-1"><?= $totalBookings ?></div>
            <small class="text-muted">100% of all sessions</small>
        </div>
    </div>
    <div class="col-md">
        <div class="card card-modern text-center p-3 border-success-subtle">
            <small class="text-success text-uppercase fw-semibold" style="font-size: 0.75rem;">Completed</small>
            <div class="fs-3 fw-bold text-success my-1"><?= $statusCounts['Completed'] ?></div>
            <small class="text-muted"><?= $totalBookings > 0 ? round(($statusCounts['Completed'] / $totalBookings) * 100, 1) : 0 ?>% success rate</small>
        </div>
    </div>
    <div class="col-md">
        <div class="card card-modern text-center p-3 border-primary-subtle">
            <small class="text-primary text-uppercase fw-semibold" style="font-size: 0.75rem;">Upcoming Confirmed</small>
            <div class="fs-3 fw-bold text-primary my-1"><?= $statusCounts['Confirmed'] ?></div>
            <small class="text-muted"><?= $totalBookings > 0 ? round(($statusCounts['Confirmed'] / $totalBookings) * 100, 1) : 0 ?>% upcoming</small>
        </div>
    </div>
    <div class="col-md">
        <div class="card card-modern text-center p-3 border-warning-subtle">
            <small class="text-warning-emphasis text-uppercase fw-semibold" style="font-size: 0.75rem;">Pending</small>
            <div class="fs-3 fw-bold text-warning-emphasis my-1"><?= $statusCounts['Pending'] ?></div>
            <small class="text-muted"><?= $totalBookings > 0 ? round(($statusCounts['Pending'] / $totalBookings) * 100, 1) : 0 ?>% awaiting</small>
        </div>
    </div>
    <div class="col-md">
        <div class="card card-modern text-center p-3 border-danger-subtle">
            <small class="text-danger text-uppercase fw-semibold" style="font-size: 0.75rem;">Cancelled</small>
            <div class="fs-3 fw-bold text-danger my-1"><?= $statusCounts['Cancelled'] ?></div>
            <small class="text-muted"><?= $totalBookings > 0 ? round(($statusCounts['Cancelled'] / $totalBookings) * 100, 1) : 0 ?>% cancelled</small>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Department Analytics Table -->
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="fw-bold text-navy mb-0">Departmental Consultation Distribution</h5>
                <small class="text-muted">Breakdown of staff capacity and student consultations across faculties</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Staff Active</th>
                                <th>Slots Created</th>
                                <th>Consultations</th>
                                <th>Fulfilled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptAnalytics as $da): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-navy"><?= e($da['department_name']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= $da['staff_count'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= $da['slot_count'] ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-navy"><?= $da['booking_count'] ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <?= $da['completed_count'] ?> completed
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Requested Faculty -->
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="fw-bold text-navy mb-0"><i class="bi bi-award-fill text-gold me-2"></i>Most Requested Staff</h5>
                <small class="text-muted">Top consultation providers</small>
            </div>
            <div class="card-body p-3">
                <?php if (empty($topStaff)): ?>
                    <p class="text-muted small text-center my-4">No consultation data available yet.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topStaff as $idx => $ts): ?>
                            <li class="list-group-item px-2 py-3 border-0 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-navy rounded-circle" style="width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div>
                                        <div class="fw-bold text-navy small"><?= e($ts['full_name']) ?></div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;"><?= format_role($ts['role']) ?> &bull; <?= e($ts['department']) ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary px-2 py-1"><?= $ts['total_bookings'] ?> bookings</span>
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
