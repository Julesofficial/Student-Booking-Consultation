<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Staff Profile & Service Focus Settings
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(STAFF_ROLES);
$currentUser = current_user();
$staffId = (int)$currentUser['id'];
$pdo = get_db_connection();

// Fetch departments for dropdown
$departments = [];
try {
    $departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

$profileErrors = [];
$passwordErrors = [];

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $profileErrors['general'] = 'Security validation failed (CSRF mismatch).';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $service = trim($_POST['service'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($fullName)) {
            $profileErrors['full_name'] = 'Full name is required.';
        }

        if (empty($profileErrors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = :full_name, department = :department, service = :service, description = :description, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':full_name'   => $fullName,
                    ':department'  => $department,
                    ':service'     => $service,
                    ':description' => $description,
                    ':id'          => $staffId
                ]);

                $_SESSION['user_name'] = $fullName;
                set_flash_message('success', 'Your faculty profile details have been updated successfully.');
                redirect('staff/profile.php');
            } catch (PDOException $e) {
                $profileErrors['general'] = 'A database error occurred while updating profile.';
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $passwordErrors['general'] = 'Security validation failed (CSRF mismatch).';
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $staffId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $hash)) {
            $passwordErrors['current_password'] = 'Current password is incorrect.';
        }

        if (strlen($newPass) < 6) {
            $passwordErrors['new_password'] = 'New password must be at least 6 characters long.';
        }

        if ($newPass !== $confirmPass) {
            $passwordErrors['confirm_password'] = 'New passwords do not match.';
        }

        if (empty($passwordErrors)) {
            try {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':hash' => $newHash, ':id' => $staffId]);

                set_flash_message('success', 'Your password has been changed successfully.');
                redirect('staff/profile.php');
            } catch (PDOException $e) {
                $passwordErrors['general'] = 'An error occurred while changing password.';
            }
        }
    }
}

$page_title = 'Staff Profile';
$active_nav = 'profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Staff Profile Update Form -->
    <div class="col-lg-7">
        <div class="card card-modern h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold text-navy mb-0"><i class="bi bi-person-badge-fill text-gold me-2"></i>Faculty Profile</h5>
                        <small class="text-muted">Manage your public directory profile and consultation services</small>
                    </div>
                    <span class="badge <?= get_role_badge_class($currentUser['role']) ?>">
                        <?= format_role($currentUser['role']) ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($profileErrors['general'])): ?>
                    <div class="alert alert-danger small mb-3"><?= e($profileErrors['general']) ?></div>
                <?php endif; ?>

                <form action="<?= url('staff/profile.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold small text-muted">Full Name & Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($profileErrors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name" value="<?= e($currentUser['full_name']) ?>" required>
                        <?php if (isset($profileErrors['full_name'])): ?>
                            <div class="invalid-feedback"><?= e($profileErrors['full_name']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small text-muted">Official University Email</label>
                        <input type="email" class="form-control bg-light" id="email" value="<?= e($currentUser['email']) ?>" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label fw-semibold small text-muted">Academic Department</label>
                        <select class="form-select" id="department" name="department">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= e($dept['department_name']) ?>" <?= ($currentUser['department'] ?? '') === $dept['department_name'] ? 'selected' : '' ?>>
                                    <?= e($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="service" class="form-label fw-semibold small text-muted">Consultation Focus / Specialized Services</label>
                        <input type="text" class="form-control" id="service" name="service" value="<?= e($currentUser['service'] ?? '') ?>" placeholder="e.g. Distributed Systems, Capstone Projects, Degree Progression...">
                        <div class="form-text small">This is highlighted to students on your directory card.</div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label fw-semibold small text-muted">Bio, Research Background & Office Hours</label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Detail your background, office location, research interests, and preferred consultation guidelines..."><?= e($currentUser['description'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-navy rounded-pill px-4">
                        <i class="bi bi-check2-circle me-1"></i> Update Faculty Profile
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Form -->
    <div class="col-lg-5">
        <div class="card card-modern h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <h5 class="fw-bold text-navy mb-0"><i class="bi bi-shield-lock-fill text-gold me-2"></i>Change Password</h5>
                <small class="text-muted">Protect your university account security</small>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($passwordErrors['general'])): ?>
                    <div class="alert alert-danger small mb-3"><?= e($passwordErrors['general']) ?></div>
                <?php endif; ?>

                <form action="<?= url('staff/profile.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold small text-muted">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($passwordErrors['current_password']) ? 'is-invalid' : '' ?>" id="current_password" name="current_password" required>
                        <?php if (isset($passwordErrors['current_password'])): ?>
                            <div class="invalid-feedback"><?= e($passwordErrors['current_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold small text-muted">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($passwordErrors['new_password']) ? 'is-invalid' : '' ?>" id="new_password" name="new_password" placeholder="At least 6 characters" required>
                        <?php if (isset($passwordErrors['new_password'])): ?>
                            <div class="invalid-feedback"><?= e($passwordErrors['new_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold small text-muted">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($passwordErrors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" required>
                        <?php if (isset($passwordErrors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= e($passwordErrors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-navy rounded-pill px-4">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
