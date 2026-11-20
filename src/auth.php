<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Authentication and Role-Based Access Control (RBAC)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

const STAFF_ROLES = [
    'lecturer',
    'academic_advisor',
    'department_head',
    'counselor',
    'university_staff'
];

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

/**
 * Retrieve the current logged-in user details
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && $cachedUser['id'] === $_SESSION['user_id']) {
        return $cachedUser;
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id, full_name, email, role, course, department, service, description, is_active, created_at FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !(bool)$user['is_active']) {
        logout_user();
        return null;
    }

    $cachedUser = $user;
    return $user;
}

/**
 * Check if the given role is an eligible staff member
 */
function is_staff_role(?string $role): bool
{
    return in_array($role, STAFF_ROLES, true);
}

/**
 * Check if current user has one of the specified roles
 */
function has_role(string|array $roles): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $userRole = $_SESSION['user_role'] ?? '';
    if (is_array($roles)) {
        return in_array($userRole, $roles, true);
    }
    return $userRole === $roles;
}

/**
 * Determine default dashboard path for a role
 */
function get_role_dashboard_url(?string $role): string
{
    return match($role) {
        'admin'   => 'admin/dashboard.php',
        'student' => 'student/dashboard.php',
        'lecturer',
        'academic_advisor',
        'department_head',
        'counselor',
        'university_staff' => 'staff/dashboard.php',
        default            => 'public/login.php'
    };
}

/**
 * Enforce authentication and authorized roles
 */
function require_login(array $allowed_roles = []): void
{
    if (!is_logged_in()) {
        set_flash_message('danger', 'Please log in to access this page.');
        redirect('public/login.php');
    }

    $user = current_user();
    if (!$user) {
        set_flash_message('danger', 'Your session has expired. Please log in again.');
        redirect('public/login.php');
    }

    if (!empty($allowed_roles) && !in_array($user['role'], $allowed_roles, true)) {
        set_flash_message('danger', 'Access denied. You do not have permission to access that section.');
        redirect(get_role_dashboard_url($user['role']));
    }
}

/**
 * Authenticate user credentials and establish session
 */
function login_user(string $email, string $password): array
{
    $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));
    if (empty($email) || empty($password)) {
        return ['success' => false, 'error' => 'Please enter both your email address and password.'];
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email address or password. Please try again.'];
    }

    if (!(bool)$user['is_active']) {
        return ['success' => false, 'error' => 'Your account has been deactivated by the university administration.'];
    }

    // Prevent session fixation attack
    session_regenerate_id(true);

    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['logged_in_time'] = time();

    return [
        'success'  => true,
        'user'     => $user,
        'redirect' => get_role_dashboard_url($user['role'])
    ];
}

/**
 * Terminate user session securely
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    // Start fresh session for flash message
    session_start();
    session_regenerate_id(true);
}
