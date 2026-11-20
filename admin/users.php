<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Administrator User Governance & Account Activation/Deactivation
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['admin']);
$currentUser = current_user();
$adminId = (int)$currentUser['id'];
$pdo = get_db_connection();

// Handle Account Activation / Deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security validation failed.');
        redirect('admin/users.php');
    }

    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $newStatus = (int)($_POST['status'] ?? 0);

    if ($targetUserId === $adminId) {
        set_flash_message('danger', 'You cannot deactivate your own administrative account.');
    } else {
        $stmt = $pdo->prepare("UPDATE users SET is_active = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $targetUserId]);

        $statusLabel = $newStatus === 1 ? 'activated' : 'deactivated';
        set_flash_message('success', "User account has been successfully {$statusLabel}.");
    }
    redirect('admin/users.php');
}

// Filters & Search
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.role,
        u.course,
        u.department,
        u.is_active,
        u.created_at,
        COUNT(DISTINCT b.id) AS total_bookings,
        COUNT(DISTINCT s.id) AS total_slots
    FROM users u
    LEFT JOIN bookings b ON u.id = b.student_id
    LEFT JOIN consultation_slots s ON u.id = s.staff_id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR u.department LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($roleFilter)) {
    $sql .= " AND u.role = :role";
    $params[':role'] = $roleFilter;
}

if ($statusFilter !== '') {
    $sql .= " AND u.is_active = :active";
    $params[':active'] = (int)$statusFilter;
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'User Management';
$active_nav = 'users';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-people-fill text-gold me-2"></i>User Accounts & Access Control</h3>
        <p class="text-muted mb-0">Manage registered university students, faculty members, counselors, and system administrators</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/staff.php?action=create') ?>" class="btn btn-navy rounded-pill px-3">
            <i class="bi bi-person-plus-fill me-1"></i> Add Faculty / Staff
        </a>
    </div>
</div>

<!-- Search & Filters -->
<div class="card card-modern mb-4">
    <div class="card-body p-3">
        <form action="<?= url('admin/users.php') ?>" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" value="<?= e($search) ?>" placeholder="Search by name, email, or department...">
                </div>
            </div>

            <div class="col-md-3">
                <select class="form-select" name="role">
                    <option value="">All User Roles</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Students</option>
                    <option value="lecturer" <?= $roleFilter === 'lecturer' ? 'selected' : '' ?>>Lecturers</option>
                    <option value="academic_advisor" <?= $roleFilter === 'academic_advisor' ? 'selected' : '' ?>>Academic Advisors</option>
                    <option value="department_head" <?= $roleFilter === 'department_head' ? 'selected' : '' ?>>Department Heads</option>
                    <option value="counselor" <?= $roleFilter === 'counselor' ? 'selected' : '' ?>>Counselors</option>
                    <option value="university_staff" <?= $roleFilter === 'university_staff' ? 'selected' : '' ?>>University Staff</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrators</option>
                </select>
            </div>

            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Active Only</option>
                    <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Deactivated</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-navy w-100"><i class="bi bi-funnel me-1"></i> Filter</button>
                <?php if (!empty($search) || !empty($roleFilter) || $statusFilter !== ''): ?>
                    <a href="<?= url('admin/users.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card card-modern">
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="empty-state p-5">
                <div class="empty-state-icon"><i class="bi bi-person-x"></i></div>
                <h5 class="empty-state-title">No Users Found</h5>
                <p class="empty-state-text">No user accounts matched your search and filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>Role</th>
                            <th>Department / Course</th>
                            <th>Activity Summary</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $isActive = (bool)$u['is_active'];
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.82rem; font-weight: 600;">
                                            <?= get_user_avatar_initials($u['full_name']) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-navy"><?= e($u['full_name']) ?></div>
                                            <small class="text-muted"><?= e($u['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= get_role_badge_class($u['role']) ?>">
                                        <?= format_role($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-medium text-dark"><?= e($u['department'] ?? 'Unspecified') ?></div>
                                    <?php if (!empty($u['course'])): ?>
                                        <small class="text-muted d-block"><?= e($u['course']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'student'): ?>
                                        <small class="text-muted"><i class="bi bi-calendar-check me-1"></i><?= $u['total_bookings'] ?> bookings</small>
                                    <?php elseif ($u['role'] !== 'admin'): ?>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= $u['total_slots'] ?> slots created</small>
                                    <?php else: ?>
                                        <small class="text-muted"><i class="bi bi-shield me-1"></i>Super User</small>
                                    <?php endif; ?>
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
                                    <small class="text-muted"><?= format_date($u['created_at']) ?></small>
                                </td>
                                <td class="text-end">
                                    <?php if ($u['id'] !== $adminId): ?>
                                        <form action="<?= url('admin/users.php') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $isActive ? '0' : '1' ?>">
                                            
                                            <?php if ($isActive): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1" title="Deactivate Account" data-confirm="Deactivate this user account? They will not be able to log in.">
                                                    <i class="bi bi-person-x"></i> Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success py-1" title="Activate Account" data-confirm="Activate this user account?">
                                                    <i class="bi bi-person-check"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">You</span>
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
