<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Staff Consultation History & Records
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(STAFF_ROLES);
$currentUser = current_user();
$staffId = (int)$currentUser['id'];
$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$historyStatus = trim($_GET['status'] ?? '');

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
        u.id AS student_id,
        u.full_name AS student_name,
        u.email AS student_email,
        u.course AS student_course
    FROM bookings b
    JOIN consultation_slots s ON b.slot_id = s.id
    JOIN users u ON b.student_id = u.id
    WHERE s.staff_id = :sid
    AND (
        b.status IN ('Completed', 'Cancelled', 'Rejected') 
        OR s.consultation_date < CURRENT_DATE()
        OR (s.consultation_date = CURRENT_DATE() AND s.end_time <= CURRENT_TIME())
    )
";

$params = [':sid' => $staffId];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE :search OR u.course LIKE :search OR b.consultation_reason LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($historyStatus)) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $historyStatus;
}

$sql .= " ORDER BY s.consultation_date DESC, s.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historyRecords = $stmt->fetchAll();

$page_title = 'Consultation History';
$active_nav = 'history';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-clock-history text-gold me-2"></i>Staff Consultation History</h3>
        <p class="text-muted mb-0">Historical records of completed, concluded, and cancelled student consultations</p>
    </div>
</div>

<!-- Filter Form -->
<div class="card card-modern mb-4">
    <div class="card-body p-3">
        <form action="<?= url('staff/consultation_history.php') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" value="<?= e($search) ?>" placeholder="Search student name, course or topic...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Past Records</option>
                    <option value="Completed" <?= $historyStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Cancelled" <?= $historyStatus === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="Rejected" <?= $historyStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-navy w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                <?php if (!empty($search) || !empty($historyStatus)): ?>
                    <a href="<?= url('staff/consultation_history.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="card card-modern">
    <div class="card-body p-0">
        <?php if (empty($historyRecords)): ?>
            <div class="empty-state p-5">
                <div class="empty-state-icon"><i class="bi bi-archive"></i></div>
                <h5 class="empty-state-title">No Historical Consultations</h5>
                <p class="empty-state-text">No past completed or cancelled appointments recorded matching your filter.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Consultation Reason</th>
                            <th>Remarks / Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historyRecords as $record): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-size: 0.8rem; font-weight: 600;">
                                            <?= get_user_avatar_initials($record['student_name']) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-navy"><?= e($record['student_name']) ?></div>
                                            <small class="text-muted"><?= e($record['student_email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-secondary"><?= e($record['student_course'] ?? 'N/A') ?></small>
                                </td>
                                <td>
                                    <div class="fw-medium"><?= format_date($record['consultation_date']) ?></div>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= format_time($record['start_time']) ?> &ndash; <?= format_time($record['end_time']) ?></small>
                                </td>
                                <td>
                                    <?= get_booking_status_badge($record['booking_status']) ?>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width: 220px;" title="<?= e($record['consultation_reason']) ?>">
                                        <?= e($record['consultation_reason']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($record['cancellation_reason'])): ?>
                                        <small class="text-danger"><i class="bi bi-x-circle me-1"></i><?= e($record['cancellation_reason']) ?></small>
                                    <?php elseif ($record['booking_status'] === 'Completed'): ?>
                                        <small class="text-success"><i class="bi bi-check-all me-1"></i>Completed</small>
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
