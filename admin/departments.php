<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Administrator Academic Departments Management (CRUD)
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['admin']);
$currentUser = current_user();
$pdo = get_db_connection();

$errors = [];
$editDept = null;

// Handle Delete Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_department') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security validation failed.');
        redirect('admin/departments.php');
    }

    $deptId = (int)($_POST['department_id'] ?? 0);
    $deptName = trim($_POST['department_name'] ?? '');

    // Check if any users are affiliated with this department
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department = :dname");
    $check->execute([':dname' => $deptName]);
    $userCount = (int)$check->fetchColumn();

    if ($userCount > 0) {
        set_flash_message('danger', "Cannot delete department '{$deptName}' because {$userCount} users are currently affiliated with it. Please reassign those users first.");
    } else {
        $del = $pdo->prepare("DELETE FROM departments WHERE id = :id");
        $del->execute([':id' => $deptId]);
        set_flash_message('success', "Department '{$deptName}' deleted successfully.");
    }
    redirect('admin/departments.php');
}

// Handle Create / Update Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create_dept', 'update_dept'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Security validation failed (CSRF mismatch).';
    } else {
        $name = trim($_POST['department_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deptId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        if (empty($name)) {
            $errors['department_name'] = 'Department name is required.';
        } else {
            // Uniqueness check
            $check = $pdo->prepare("SELECT id FROM departments WHERE department_name = :name" . ($deptId ? " AND id != :id" : ""));
            $params = [':name' => $name];
            if ($deptId) $params[':id'] = $deptId;
            $check->execute($params);
            if ($check->fetch()) {
                $errors['department_name'] = 'A department with this name already exists.';
            }
        }

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'create_dept') {
                    $stmt = $pdo->prepare("INSERT INTO departments (department_name, description, created_at) VALUES (:name, :desc, NOW())");
                    $stmt->execute([':name' => $name, ':desc' => $description]);
                    set_flash_message('success', "Academic department '{$name}' created successfully.");
                } else {
                    $oldName = trim($_POST['old_department_name'] ?? '');
                    $stmt = $pdo->prepare("UPDATE departments SET department_name = :name, description = :desc WHERE id = :id");
                    $stmt->execute([':name' => $name, ':desc' => $description, ':id' => $deptId]);

                    // Update affiliated users' department name if changed
                    if (!empty($oldName) && $oldName !== $name) {
                        $upUser = $pdo->prepare("UPDATE users SET department = :new_name WHERE department = :old_name");
                        $upUser->execute([':new_name' => $name, ':old_name' => $oldName]);
                    }

                    set_flash_message('success', "Department '{$name}' updated successfully.");
                }
                redirect('admin/departments.php');
            } catch (PDOException $e) {
                error_log('Dept save error: ' . $e->getMessage());
                $errors['general'] = 'A database error occurred while saving the department.';
            }
        }
    }
}

// Edit Mode
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $editDept = $stmt->fetch();
}

// Fetch all departments with affiliated user counts
$stmt = $pdo->query("
    SELECT 
        d.id,
        d.department_name,
        d.description,
        d.created_at,
        COUNT(DISTINCT CASE WHEN u.role != 'student' THEN u.id END) AS staff_count,
        COUNT(DISTINCT CASE WHEN u.role = 'student' THEN u.id END) AS student_count
    FROM departments d
    LEFT JOIN users u ON d.department_name = u.department
    GROUP BY d.id
    ORDER BY d.department_name ASC
");
$departments = $stmt->fetchAll();

$page_title = 'Departments';
$active_nav = 'departments';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-building-fill text-gold me-2"></i>Academic Departments</h3>
        <p class="text-muted mb-0">Manage university faculties, schools, and administrative departments</p>
    </div>
</div>

<div class="row g-4">
    <!-- Department Form -->
    <div class="col-lg-4">
        <div class="card card-modern sticky-top" style="top: 20px;">
            <div class="card-header bg-navy text-white py-3 px-4 rounded-top-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi <?= $editDept ? 'bi-pencil-square' : 'bi-plus-circle-fill' ?> text-gold me-1"></i>
                    <?= $editDept ? 'Edit Department' : 'Create Department' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger small mb-3"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form action="<?= url('admin/departments.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editDept ? 'update_dept' : 'create_dept' ?>">
                    <?php if ($editDept): ?>
                        <input type="hidden" name="department_id" value="<?= $editDept['id'] ?>">
                        <input type="hidden" name="old_department_name" value="<?= e($editDept['department_name']) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="department_name" class="form-label fw-semibold small text-muted">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['department_name']) ? 'is-invalid' : '' ?>" id="department_name" name="department_name" value="<?= e($editDept['department_name'] ?? $_POST['department_name'] ?? '') ?>" placeholder="e.g. Computer Science & Informatics" required>
                        <?php if (isset($errors['department_name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['department_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label fw-semibold small text-muted">Department Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Overview of department focus, degree programs, and responsibilities..."><?= e($editDept['description'] ?? $_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-navy py-2 rounded-pill fw-semibold">
                            <i class="bi <?= $editDept ? 'bi-check-lg' : 'bi-plus-circle' ?> me-1"></i>
                            <?= $editDept ? 'Save Changes' : 'Create Department' ?>
                        </button>
                        <?php if ($editDept): ?>
                            <a href="<?= url('admin/departments.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                                Cancel Editing
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Departments Table -->
    <div class="col-lg-8">
        <div class="card card-modern">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold text-navy mb-0">Registered Departments (<?= count($departments) ?>)</h5>
                    <small class="text-muted">Faculties and support departments recognized by the consultation system</small>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($departments)): ?>
                    <div class="empty-state p-5">
                        <div class="empty-state-icon"><i class="bi bi-building"></i></div>
                        <h6 class="empty-state-title">No Departments Found</h6>
                        <p class="empty-state-text">Create your first university department using the form on the left.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>Faculty / Staff</th>
                                    <th>Students</th>
                                    <th>Description</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $d): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-navy"><?= e($d['department_name']) ?></div>
                                            <small class="text-muted">Created <?= format_date($d['created_at']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                <i class="bi bi-person-workspace me-1"></i><?= $d['staff_count'] ?> Staff
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-dark border px-2 py-1">
                                                <i class="bi bi-mortarboard me-1"></i><?= $d['student_count'] ?> Students
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted d-inline-block text-truncate" style="max-width: 220px;" title="<?= e($d['description']) ?>">
                                                <?= e($d['description'] ?? 'No description provided') ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= url('admin/departments.php?edit_id=' . $d['id']) ?>" class="btn btn-outline-secondary py-1" title="Edit Department">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="<?= url('admin/departments.php') ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_department">
                                                    <input type="hidden" name="department_id" value="<?= $d['id'] ?>">
                                                    <input type="hidden" name="department_name" value="<?= e($d['department_name']) ?>">
                                                    <button type="submit" class="btn btn-outline-danger py-1" title="Delete Department" data-confirm="Are you sure you want to delete this department?">
                                                        <i class="bi bi-trash"></i>
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
