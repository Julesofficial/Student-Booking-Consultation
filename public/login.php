<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Secure User Authentication (Sign In)
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(get_role_dashboard_url($_SESSION['user_role']));
}

$error = '';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Security validation failed (invalid CSRF token). Please try submitting again.';
    } else {
        $emailInput = trim($_POST['email'] ?? '');
        $passwordInput = $_POST['password'] ?? '';

        $result = login_user($emailInput, $passwordInput);
        if ($result['success']) {
            set_flash_message('success', 'Welcome back, ' . $result['user']['full_name'] . '!');
            redirect($result['redirect']);
        } else {
            $error = $result['error'];
        }
    }
}

$page_title = 'Sign In';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center my-4">
    <div class="col-md-7 col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-navy text-white d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 58px; height: 58px;">
                    <i class="bi bi-shield-lock-fill fs-3 text-gold"></i>
                </div>
                <h3 class="fw-bold text-navy mb-1">Sign In to Portal</h3>
                <p class="text-muted small">Enter your university credentials to access consultations</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= url('public/login.php') ?>" method="POST" autocomplete="off" id="loginForm">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold small text-muted">University Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= e($emailInput) ?>" placeholder="e.g. alice.mwangi@kabarak.ac.ke" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label fw-semibold small text-muted mb-0">Password</label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-navy py-2 rounded-3 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 pt-3 border-top">
                <span class="text-muted small">Are you a new student? </span>
                <a href="<?= url('public/register.php') ?>" class="text-decoration-none fw-semibold text-navy small">Create Student Account</a>
            </div>
        </div>

        <!-- Quick Demo Credentials Accordion for Evaluator Testing -->
        <div class="card border-0 shadow-sm rounded-4 mt-4 bg-light">
            <div class="card-body p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="fw-bold text-navy text-uppercase"><i class="bi bi-key-fill text-gold me-1"></i> Quick Demo Accounts</small>
                    <span class="badge bg-secondary-subtle text-dark small">Password: <code>Password123!</code></span>
                </div>
                <p class="text-muted small mb-2">Click any role below to autofill login credentials for quick testing:</p>
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 quick-fill-btn" data-email="student.kelvin@kabarak.ac.ke" style="font-size: 0.78rem;">Student</button>
                    <button type="button" class="btn btn-sm btn-outline-dark py-0 px-2 quick-fill-btn" data-email="alice.mwangi@kabarak.ac.ke" style="font-size: 0.78rem;">Lecturer (Dr Alice)</button>
                    <button type="button" class="btn btn-sm btn-outline-info py-0 px-2 quick-fill-btn" data-email="grace.njeri@kabarak.ac.ke" style="font-size: 0.78rem;">Academic Advisor</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 quick-fill-btn" data-email="prof.cheruiyot@kabarak.ac.ke" style="font-size: 0.78rem;">Dept Head</button>
                    <button type="button" class="btn btn-sm btn-outline-warning text-dark py-0 px-2 quick-fill-btn" data-email="esther.kinyua@kabarak.ac.ke" style="font-size: 0.78rem;">Counselor</button>
                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 quick-fill-btn" data-email="mary.chebet@kabarak.ac.ke" style="font-size: 0.78rem;">University Staff</button>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 quick-fill-btn" data-email="admin@kabarak.ac.ke" style="font-size: 0.78rem;">Administrator</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const emailField = document.getElementById('email');
    const passwordField = document.getElementById('password');
    const quickButtons = document.querySelectorAll('.quick-fill-btn');

    quickButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const email = btn.getAttribute('data-email');
            if (emailField && passwordField) {
                emailField.value = email;
                passwordField.value = 'Password123!';
                emailField.focus();
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
