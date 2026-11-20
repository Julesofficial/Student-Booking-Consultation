<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Administrator Student Records & Account Management
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

$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.course,
        u.department,
        u.is_active,
        u.created_at,
        COUNT(b.id) AS total_bookings,
        COUNT(CASE WHEN b.status = 'Completed' THEN 1 END) AS completed_bookings,
        COUNT(CASE WHEN b.status IN ('Confirmed', 'Pending') THEN 1 END) AS active_bookings
    FROM users u
    LEFT JOIN bookings b ON u.id = b.student_id
    WHERE u.role = 'student'
";

$params = [];
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR u.course LIKE :search OR u.department LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $sql .= " AND u.is_active = :status";
    $params[':status'] = (int)$statusFilter;
}

$sql .= " GROUP BY u.id ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title = 'Manage Students';
$active_nav = 'students';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-mortarboard-fill text-gold me-2"></i>Student Accounts Governance</h3>
        <p class="text-muted mb-0">View registered university students, degree tracks, and consultation booking histories</p>
    </div>
</div>

<!-- Search & Filters -->
<div class="card card-modern mb-4">
    <div class="card-body p-3">
        <form action="<?= url('admin/students.php') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" value="<?= e($search) ?>" placeholder="Search student name, email, or degree program...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Account Statuses</option>
                    <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Active Only</option>
                    <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Deactivated</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-navy w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <?php if (!empty($search) || $statusFilter !== ''): ?>
                    <a href="<?= url('admin/students.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Students Table -->
<div class="card card-modern">
    <div class="card-body p-0">
        <?php if (empty($students)): ?>
            <div class="empty-state p-5">
                <div class="empty-state-icon"><i class="bi bi-mortarboard"></i></div>
                <h5 class="empty-state-title">No Students Found</h5>
                <p class="empty-state-text">No registered student accounts matched your query.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Degree / Course</th>
                            <th>Department</th>
                            <th>Total Consultations</th>
                            <th>Account Status</th>
                            <th>Registered Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu): 
                            $isActive = (bool)$stu['is_active'];
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem; font-weight: 600;">
                                            <?= get_user_avatar_initials($stu['full_name']) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-navy"><?= e($stu['full_name']) ?></div>
                                            <small class="text-muted"><?= e($stu['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($stu['course'] ?? 'Undergraduate') ?></span>
                                </td>
                                <td>
                                    <small class="text-secondary"><?= e($stu['department'] ?? 'Unspecified') ?></small>
                                </td>
                                <td>
                                    <span class="fw-bold text-navy"><?= $stu['total_bookings'] ?></span>
                                    <small class="text-muted">(<?= $stu['active_bookings'] ?> active, <?= $stu['completed_bookings'] ?> completed)</small>
                                </td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check-circle me-1"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                            <i class="bi bi-dash-circle me-1"></i>Deactivated
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_date($stu['created_at']) ?></small>
                                </td>
                                <td class="text-end">
                                    <form action="<?= url('admin/users.php') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= $stu['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $isActive ? '0' : '1' ?>">
                                        
                                        <?php if ($isActive): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-1" title="Deactivate Student Account" data-confirm="Deactivate this student account?">
                                                <i class="bi bi-person-x"></i> Deactivate
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success py-1" title="Activate Student Account" data-confirm="Activate this student account?">
                                                <i class="bi bi-person-check"></i> Activate
                                            </button>
                                        <?php endif; ?>
                                    </form>
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
