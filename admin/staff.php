<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Administrator Faculty & Staff Member Provisioning
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['admin']);
$currentUser = current_user();
$pdo = get_db_connection();

$errors = [];
$editStaff = null;

// Fetch departments for dropdown
$departments = [];
try {
    $departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Handle Add / Edit Staff Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create_staff', 'update_staff'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Security validation failed (CSRF mismatch).';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $service = trim($_POST['service'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $password = $_POST['password'] ?? '';
        $staffId = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : null;

        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid university email is required.';
        }
        if (!in_array($role, STAFF_ROLES)) {
            $errors['role'] = 'Please select a valid staff role.';
        }

        // Email uniqueness check
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email" . ($staffId ? " AND id != :id" : ""));
        $params = [':email' => $email];
        if ($staffId) $params[':id'] = $staffId;
        $checkStmt->execute($params);
        if ($checkStmt->fetch()) {
            $errors['email'] = 'This email address is already in use.';
        }

        if ($_POST['action'] === 'create_staff' && (empty($password) || strlen($password) < 6)) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'create_staff') {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, password_hash, role, department, service, description, is_active, created_at)
                        VALUES (:name, :email, :hash, :role, :dept, :service, :desc, 1, NOW())
                    ");
                    $stmt->execute([
                        ':name'    => $fullName,
                        ':email'   => $email,
                        ':hash'    => $hash,
                        ':role'    => $role,
                        ':dept'    => $department,
                        ':service' => $service,
                        ':desc'    => $description
                    ]);
                    set_flash_message('success', "Staff member {$fullName} successfully provisioned!");
                } else {
                    // Update
                    $sql = "UPDATE users SET full_name = :name, email = :email, role = :role, department = :dept, service = :service, description = :desc, updated_at = NOW()";
                    $params = [
                        ':name'    => $fullName,
                        ':email'   => $email,
                        ':role'    => $role,
                        ':dept'    => $department,
                        ':service' => $service,
                        ':desc'    => $description,
                        ':id'      => $staffId
                    ];

                    if (!empty($password) && strlen($password) >= 6) {
                        $sql .= ", password_hash = :hash";
                        $params[':hash'] = password_hash($password, PASSWORD_BCRYPT);
                    }
                    $sql .= " WHERE id = :id";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    set_flash_message('success', "Staff profile for {$fullName} updated successfully.");
                }
                redirect('admin/staff.php');
            } catch (PDOException $e) {
                error_log('Staff save error: ' . $e->getMessage());
                $errors['general'] = 'A database error occurred while saving the staff member.';
            }
        }
    }
}

// Edit Mode
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role IN ('lecturer','academic_advisor','department_head','counselor','university_staff') LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editStaff = $stmt->fetch();
}

// Fetch all staff members
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.role,
        u.department,
        u.service,
        u.is_active,
        COUNT(DISTINCT s.id) AS total_slots,
        COUNT(DISTINCT CASE WHEN s.status = 'available' AND (s.consultation_date > CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time > CURRENT_TIME())) THEN s.id END) AS open_slots,
        COUNT(DISTINCT b.id) AS total_bookings
    FROM users u
    LEFT JOIN consultation_slots s ON u.id = s.staff_id
    LEFT JOIN bookings b ON s.id = b.slot_id
    WHERE u.role IN ('lecturer','academic_advisor','department_head','counselor','university_staff')
";

$params = [];
if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR u.department LIKE :search OR u.service LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($roleFilter)) {
    $sql .= " AND u.role = :role";
    $params[':role'] = $roleFilter;
}
$sql .= " GROUP BY u.id ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffList = $stmt->fetchAll();

$page_title = 'Manage Staff';
$active_nav = 'staff';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-person-badge-fill text-gold me-2"></i>University Faculty & Staff Management</h3>
        <p class="text-muted mb-0">Register, manage profiles, and monitor consultation slots for all eligible university staff</p>
    </div>
</div>

<div class="row g-4">
    <!-- Staff Creation / Edit Form -->
    <div class="col-lg-4">
        <div class="card card-modern sticky-top" style="top: 20px;">
            <div class="card-header bg-navy text-white py-3 px-4 rounded-top-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi <?= $editStaff ? 'bi-pencil-square' : 'bi-person-plus-fill' ?> text-gold me-1"></i>
                    <?= $editStaff ? 'Edit Staff Profile' : 'Register New Staff Member' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger small mb-3"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form action="<?= url('admin/staff.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editStaff ? 'update_staff' : 'create_staff' ?>">
                    <?php if ($editStaff): ?>
                        <input type="hidden" name="staff_id" value="<?= $editStaff['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold small text-muted">Full Name & Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name" value="<?= e($editStaff['full_name'] ?? $_POST['full_name'] ?? '') ?>" placeholder="e.g. Dr Alice Mwangi" required>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small text-muted">University Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e($editStaff['email'] ?? $_POST['email'] ?? '') ?>" placeholder="e.g. alice.mwangi@kabarak.ac.ke" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label fw-semibold small text-muted">Professional Role <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="lecturer" <?= ($editStaff['role'] ?? $_POST['role'] ?? '') === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                            <option value="academic_advisor" <?= ($editStaff['role'] ?? $_POST['role'] ?? '') === 'academic_advisor' ? 'selected' : '' ?>>Academic Advisor</option>
                            <option value="department_head" <?= ($editStaff['role'] ?? $_POST['role'] ?? '') === 'department_head' ? 'selected' : '' ?>>Department Head</option>
                            <option value="counselor" <?= ($editStaff['role'] ?? $_POST['role'] ?? '') === 'counselor' ? 'selected' : '' ?>>Counselor</option>
                            <option value="university_staff" <?= ($editStaff['role'] ?? $_POST['role'] ?? '') === 'university_staff' ? 'selected' : '' ?>>University Staff</option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback"><?= e($errors['role']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label fw-semibold small text-muted">Academic Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= e($dept['department_name']) ?>" <?= ($editStaff['department'] ?? $_POST['department'] ?? '') === $dept['department_name'] ? 'selected' : '' ?>>
                                    <?= e($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="service" class="form-label fw-semibold small text-muted">Consultation Focus / Specialty</label>
                        <input type="text" class="form-control" id="service" name="service" value="<?= e($editStaff['service'] ?? $_POST['service'] ?? '') ?>" placeholder="e.g. Distributed Systems & Algorithms">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold small text-muted">
                            <?= $editStaff ? 'Password (Leave blank to keep current)' : 'Account Password *' ?>
                        </label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="At least 6 characters" <?= $editStaff ? '' : 'required' ?>>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label fw-semibold small text-muted">Office Hours & Background</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= e($editStaff['description'] ?? $_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-navy py-2 rounded-pill fw-semibold">
                            <i class="bi <?= $editStaff ? 'bi-check-lg' : 'bi-person-check-fill' ?> me-1"></i>
                            <?= $editStaff ? 'Save Changes' : 'Provision Staff Account' ?>
                        </button>
                        <?php if ($editStaff): ?>
                            <a href="<?= url('admin/staff.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                                Cancel Editing
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Staff Roster Table -->
    <div class="col-lg-8">
        <div class="card card-modern">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold text-navy mb-0">Faculty & Staff Directory (<?= count($staffList) ?>)</h5>
                    <small class="text-muted">Eligible consultation providers across all university units</small>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($staffList)): ?>
                    <div class="empty-state p-5">
                        <div class="empty-state-icon"><i class="bi bi-people"></i></div>
                        <h6 class="empty-state-title">No Staff Members Registered</h6>
                        <p class="empty-state-text">Use the form on the left to add your first faculty or staff member.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Role & Dept</th>
                                    <th>Consultation Service</th>
                                    <th>Availability</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffList as $stf): 
                                    $avail = get_staff_availability_badge((int)$stf['open_slots']);
                                    $isActive = (bool)$stf['is_active'];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem; font-weight: 600;">
                                                    <?= get_user_avatar_initials($stf['full_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-navy"><?= e($stf['full_name']) ?></div>
                                                    <small class="text-muted"><?= e($stf['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= get_role_badge_class($stf['role']) ?> mb-1">
                                                <?= format_role($stf['role']) ?>
                                            </span>
                                            <div class="small text-muted text-truncate" style="max-width: 170px;">
                                                <?= e($stf['department']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-secondary d-inline-block text-truncate" style="max-width: 180px;" title="<?= e($stf['service']) ?>">
                                                <?= e($stf['service'] ?? 'Consultations') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $avail['class'] ?>" style="font-size: 0.72rem;">
                                                <?= $avail['status'] ?> (<?= $avail['count'] ?>)
                                            </span>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;">
                                                <?= $stf['total_bookings'] ?> bookings total
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= url('admin/staff.php?edit_id=' . $stf['id']) ?>" class="btn btn-outline-secondary py-1" title="Edit Staff Profile">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="<?= url('admin/users.php') ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?= $stf['id'] ?>">
                                                    <input type="hidden" name="status" value="<?= $isActive ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-outline-<?= $isActive ? 'danger' : 'success' ?> py-1" title="<?= $isActive ? 'Deactivate' : 'Activate' ?>">
                                                        <i class="bi <?= $isActive ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                                                    </button>
                                                </form>
                                            </div>
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
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
