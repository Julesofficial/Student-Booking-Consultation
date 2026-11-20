<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Student Registration Form
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/validation.php';
require_once __DIR__ . '/../src/notifications.php';

if (is_logged_in()) {
    redirect(get_role_dashboard_url($_SESSION['user_role']));
}

$pdo = get_db_connection();

// Fetch available departments for dropdown
$departments = [];
try {
    $stmt = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

$errors = [];
$formData = [
    'full_name'   => '',
    'email'       => '',
    'course'      => '',
    'department'  => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Security validation failed (CSRF token mismatch). Please try again.';
    } else {
        $formData['full_name']  = trim($_POST['full_name'] ?? '');
        $formData['email']      = trim($_POST['email'] ?? '');
        $formData['course']     = trim($_POST['course'] ?? '');
        $formData['department'] = trim($_POST['department'] ?? '');
        $password               = $_POST['password'] ?? '';
        $passwordConfirm        = $_POST['password_confirm'] ?? '';

        $errors = validate_registration(array_merge($formData, [
            'password'         => $password,
            'password_confirm' => $passwordConfirm
        ]));

        if (empty($errors)) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, password_hash, role, course, department, is_active, created_at)
                    VALUES (:full_name, :email, :password_hash, 'student', :course, :department, 1, NOW())
                ");
                $insertStmt->execute([
                    ':full_name'     => $formData['full_name'],
                    ':email'         => $formData['email'],
                    ':password_hash' => $hash,
                    ':course'        => $formData['course'],
                    ':department'    => $formData['department']
                ]);

                $newUserId = (int)$pdo->lastInsertId();

                // Create initial welcome notification
                create_notification(
                    $newUserId,
                    'Welcome to Kabarak Consultation System',
                    'Your student account has been created successfully. You can now browse lecturers and advisors to schedule appointments.'
                );

                // Log the student in directly
                login_user($formData['email'], $password);
                set_flash_message('success', 'Your student account has been registered successfully! Welcome to the portal.');
                redirect('student/dashboard.php');
            } catch (PDOException $e) {
                $errors['general'] = 'An unexpected database error occurred while creating your account. Please try again.';
            }
        }
    }
}

$page_title = 'Student Registration';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-4">
    <div class="col-md-8 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-navy text-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 58px; height: 58px;">
                    <i class="bi bi-person-plus-fill fs-3 text-gold"></i>
                </div>
                <h3 class="fw-bold text-navy mb-1">Student Registration</h3>
                <p class="text-muted small">Create an account to browse university staff and book consultations</p>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?= e($errors['general']) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= url('public/register.php') ?>" method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="full_name" class="form-label fw-semibold small text-muted">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name" value="<?= e($formData['full_name']) ?>" placeholder="e.g. Kelvin Kipkemboi" required>
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold small text-muted">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e($formData['email']) ?>" placeholder="e.g. kelvin@kabarak.ac.ke" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="course" class="form-label fw-semibold small text-muted">Degree / Course <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['course']) ? 'is-invalid' : '' ?>" id="course" name="course" value="<?= e($formData['course']) ?>" placeholder="e.g. BSc Computer Science" required>
                        <?php if (isset($errors['course'])): ?>
                            <div class="invalid-feedback"><?= e($errors['course']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="department" class="form-label fw-semibold small text-muted">Academic Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">Select your department (Optional)</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= e($dept['department_name']) ?>" <?= $formData['department'] === $dept['department_name'] ? 'selected' : '' ?>>
                                    <?= e($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label fw-semibold small text-muted">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="At least 6 characters" required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirm" class="form-label fw-semibold small text-muted">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" id="password_confirm" name="password_confirm" placeholder="Repeat password" required>
                        <?php if (isset($errors['password_confirm'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-navy py-2 rounded-3 fw-semibold">
                        <i class="bi bi-person-check-fill me-1"></i> Register Student Account
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 pt-3 border-top">
                <span class="text-muted small">Already have an account? </span>
                <a href="<?= url('public/login.php') ?>" class="text-decoration-none fw-semibold text-navy small">Sign In</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
