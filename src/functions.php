<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Core Helper Functions
 */

require_once __DIR__ . '/config.php';

/**
 * Escape HTML output for XSS protection
 */
function e(?string $string): string
{
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

/**
 * Determine dynamic base URL path for XAMPP or PHP built-in server
 */
function get_app_base_path(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    if (php_sapi_name() === 'cli' || empty($_SERVER['DOCUMENT_ROOT'])) {
        $basePath = '';
        return $basePath;
    }

    $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
    $projectRoot = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)), '/');

    if (str_starts_with($projectRoot, $docRoot)) {
        $sub = substr($projectRoot, strlen($docRoot));
        $basePath = '/' . ltrim($sub, '/');
    } else {
        // Fallback: extract base path from SCRIPT_NAME if possible
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $parts = explode('/', trim($script, '/'));
        if (!empty($parts)) {
            // Check if top directory corresponds to project folder
            $folderName = basename(dirname(__DIR__));
            $pos = array_search($folderName, $parts);
            if ($pos !== false) {
                $basePath = '/' . implode('/', array_slice($parts, 0, $pos + 1));
            } else {
                $basePath = '';
            }
        } else {
            $basePath = '';
        }
    }

    $basePath = rtrim($basePath, '/');
    return $basePath;
}

/**
 * Generate full URL path within project
 */
function url(string $path = ''): string
{
    $base = get_app_base_path();
    $cleanPath = ltrim($path, '/');
    return ($cleanPath === '') ? ($base ?: '/') : ($base . '/' . $cleanPath);
}

/**
 * Safe redirect to a URL or internal path
 */
function redirect(string $path): void
{
    if (!preg_match('/^https?:\/\//i', $path)) {
        $target = url($path);
    } else {
        $target = $path;
    }

    header("Location: " . $target);
    exit;
}

/**
 * CSRF Protection: Generate or get existing token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST request
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set flash message for user feedback
 */
function set_flash_message(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type'    => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Check if flash messages exist
 */
function has_flash_messages(): bool
{
    return !empty($_SESSION['flash_messages']);
}

/**
 * Render flash messages as Bootstrap 5 alerts and clear them
 */
function render_flash_messages(): string
{
    if (empty($_SESSION['flash_messages'])) {
        return '';
    }

    $html = '<div class="flash-messages-container my-3">';
    foreach ($_SESSION['flash_messages'] as $msg) {
        $type = in_array($msg['type'], ['success', 'danger', 'warning', 'info']) ? $msg['type'] : 'info';
        $icon = match($type) {
            'success' => '<i class="bi bi-check-circle-fill me-2"></i>',
            'danger'  => '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
            'warning' => '<i class="bi bi-exclamation-circle-fill me-2"></i>',
            default   => '<i class="bi bi-info-circle-fill me-2"></i>',
        };

        $html .= sprintf(
            '<div class="alert alert-%s alert-dismissible fade show shadow-sm" role="alert">
                <div class="d-flex align-items-center">%s<div>%s</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>',
            e($type),
            $icon,
            e($msg['message'])
        );
    }
    $html .= '</div>';

    unset($_SESSION['flash_messages']);
    return $html;
}

/**
 * Format human-readable date
 */
function format_date(?string $dateStr, string $format = 'D, d M Y'): string
{
    if (empty($dateStr)) return 'N/A';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return $dateStr;
    }
}

/**
 * Format human-readable time (e.g. 09:00:00 -> 09:00 AM)
 */
function format_time(?string $timeStr, string $format = 'h:i A'): string
{
    if (empty($timeStr)) return 'N/A';
    try {
        $dt = new DateTime('1970-01-01 ' . $timeStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return $timeStr;
    }
}

/**
 * Format human-readable date and time
 */
function format_datetime(?string $dateTimeStr, string $format = 'd M Y, h:i A'): string
{
    if (empty($dateTimeStr)) return 'N/A';
    try {
        $dt = new DateTime($dateTimeStr);
        return $dt->format($format);
    } catch (Exception $e) {
        return $dateTimeStr;
    }
}

/**
 * Format database role string into display label
 */
function format_role(?string $role): string
{
    return match($role) {
        'student'          => 'Student',
        'lecturer'         => 'Lecturer',
        'academic_advisor' => 'Academic Advisor',
        'department_head'  => 'Department Head',
        'counselor'        => 'Counselor',
        'university_staff' => 'University Staff',
        'admin'            => 'System Administrator',
        default            => ucwords(str_replace('_', ' ', (string)$role))
    };
}

/**
 * Get CSS badge class for user roles
 */
function get_role_badge_class(?string $role): string
{
    return match($role) {
        'student'          => 'bg-secondary',
        'lecturer'         => 'bg-primary',
        'academic_advisor' => 'bg-info text-dark',
        'department_head'  => 'bg-dark',
        'counselor'        => 'bg-purple text-white',
        'university_staff' => 'bg-teal text-white',
        'admin'            => 'bg-danger',
        default            => 'bg-secondary'
    };
}

/**
 * Calculate dynamic availability status and badge for a staff member
 * Indicators per specification:
 * - Available: More than two available consultation slots (>2)
 * - Limited Slots: Two or fewer available consultation slots, but at least one remains (1 or 2)
 * - Fully Booked: No available consultation slots (0)
 */
function get_staff_availability_badge(int $availableSlotsCount): array
{
    if ($availableSlotsCount > 2) {
        return [
            'status' => 'Available',
            'class'  => 'bg-success',
            'icon'   => 'bi-check-circle',
            'count'  => $availableSlotsCount,
            'desc'   => $availableSlotsCount . ' slots open'
        ];
    } elseif ($availableSlotsCount >= 1) {
        return [
            'status' => 'Limited Slots',
            'class'  => 'bg-warning text-dark',
            'icon'   => 'bi-exclamation-circle',
            'count'  => $availableSlotsCount,
            'desc'   => $availableSlotsCount . ' slot' . ($availableSlotsCount > 1 ? 's' : '') . ' remaining'
        ];
    } else {
        return [
            'status' => 'Fully Booked',
            'class'  => 'bg-secondary',
            'icon'   => 'bi-x-circle',
            'count'  => 0,
            'desc'   => 'No slots currently open'
        ];
    }
}

/**
 * Get Bootstrap badge styling for booking status
 */
function get_booking_status_badge(string $status): string
{
    return match(strtolower(trim($status))) {
        'confirmed' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Confirmed</span>',
        'pending'   => '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Pending</span>',
        'completed' => '<span class="badge bg-primary"><i class="bi bi-patch-check me-1"></i>Completed</span>',
        'cancelled' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Cancelled</span>',
        'rejected'  => '<span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Rejected</span>',
        default     => '<span class="badge bg-light text-dark">' . e($status) . '</span>'
    };
}

/**
 * Generate user initials for avatar fallback
 */
function get_user_avatar_initials(string $name): string
{
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w) && !in_array(strtolower($w), ['dr', 'dr.', 'prof', 'prof.', 'mr', 'ms', 'mrs'])) {
            $initials .= strtoupper(mb_substr($w, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    return !empty($initials) ? $initials : strtoupper(mb_substr($name, 0, 2));
}
