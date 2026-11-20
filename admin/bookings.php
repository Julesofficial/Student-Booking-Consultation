<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Administrator University-wide Bookings Oversight
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['admin']);
$currentUser = current_user();
$pdo = get_db_connection();

// Filters & Search
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$deptFilter = trim($_GET['department'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');

// Fetch departments for filter
$departments = [];
try {
    $departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

$sql = "
    SELECT 
        b.id AS booking_id,
        b.consultation_reason,
        b.status AS booking_status,
        b.cancellation_reason,
        b.created_at AS booked_at,
        b.updated_at,
        s.id AS slot_id,
        s.consultation_date,
        s.start_time,
        s.end_time,
        stu.id AS student_id,
        stu.full_name AS student_name,
        stu.email AS student_email,
        stu.course AS student_course,
        stf.id AS staff_id,
        stf.full_name AS staff_name,
        stf.email AS staff_email,
        stf.role AS staff_role,
        stf.department AS staff_dept
    FROM bookings b
    JOIN consultation_slots s ON b.slot_id = s.id
    JOIN users stu ON b.student_id = stu.id
    JOIN users stf ON s.staff_id = stf.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (stu.full_name LIKE :search OR stf.full_name LIKE :search OR b.consultation_reason LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($deptFilter)) {
    $sql .= " AND stf.department = :dept";
    $params[':dept'] = $deptFilter;
}

if (!empty($dateFilter)) {
    $sql .= " AND s.consultation_date = :cdate";
    $params[':cdate'] = $dateFilter;
}

$sql .= " ORDER BY s.consultation_date DESC, s.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$page_title = 'All Bookings';
$active_nav = 'bookings';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-calendar-range-fill text-gold me-2"></i>Consultation Bookings Audit</h3>
        <p class="text-muted mb-0">System-wide monitoring of all scheduled student-faculty consultation appointments</p>
    </div>
</div>

<!-- Search & Filters -->
<div class="card card-modern mb-4">
    <div class="card-body p-3">
        <form action="<?= url('admin/bookings.php') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" value="<?= e($search) ?>" placeholder="Student or staff name...">
                </div>
            </div>

            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="Confirmed" <?= $statusFilter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <div class="col-md-3">
                <select class="form-select" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= e($dept['department_name']) ?>" <?= $deptFilter === $dept['department_name'] ? 'selected' : '' ?>>
                            <?= e($dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <input type="date" class="form-control" name="date" value="<?= e($dateFilter) ?>" title="Filter by consultation date">
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-navy w-100" title="Apply Filter"><i class="bi bi-funnel"></i></button>
                <?php if (!empty($search) || !empty($statusFilter) || !empty($deptFilter) || !empty($dateFilter)): ?>
                    <a href="<?= url('admin/bookings.php') ?>" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bookings Table -->
<div class="card card-modern">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="fw-bold text-navy mb-0">Total Bookings Recorded (<?= count($bookings) ?>)</h5>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($bookings)): ?>
            <div class="empty-state p-5">
                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                <h5 class="empty-state-title">No Consultations Found</h5>
                <p class="empty-state-text">No consultation bookings matched your search or filter settings.</p>
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
                            <th>Consultation Reason</th>
                            <th>Cancellation / Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-navy"><?= e($b['student_name']) ?></div>
                                    <small class="text-muted"><?= e($b['student_course'] ?? $b['student_email']) ?></small>
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
                                    <div class="fw-medium"><?= format_date($b['consultation_date']) ?></div>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= format_time($b['start_time']) ?> &ndash; <?= format_time($b['end_time']) ?></small>
                                </td>
                                <td>
                                    <?= get_booking_status_badge($b['booking_status']) ?>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 190px;" title="<?= e($b['consultation_reason']) ?>">
                                        <?= e($b['consultation_reason']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($b['cancellation_reason'])): ?>
                                        <small class="text-danger"><i class="bi bi-info-circle me-1"></i><?= e($b['cancellation_reason']) ?></small>
                                    <?php elseif ($b['booking_status'] === 'Completed'): ?>
                                        <small class="text-success"><i class="bi bi-check2-circle me-1"></i>Finished</small>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
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
