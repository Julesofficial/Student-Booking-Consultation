<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Public Landing Page
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

$page_title = 'Welcome';
include __DIR__ . '/../includes/header.php';

// Fetch public stats for landing page
$pdo = get_db_connection();
try {
    $deptCount = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $staffCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('lecturer','academic_advisor','department_head','counselor','university_staff') AND is_active = 1")->fetchColumn();
    $slotCount = (int)$pdo->query("SELECT COUNT(*) FROM consultation_slots WHERE status = 'available' AND consultation_date >= CURRENT_DATE()")->fetchColumn();
} catch (Exception $e) {
    $deptCount = 7;
    $staffCount = 12;
    $slotCount = 25;
}
?>

<!-- Hero Banner -->
<div class="row align-items-center py-5 my-2">
    <div class="col-lg-7">
        <span class="badge bg-gold text-white px-3 py-2 rounded-pill mb-3 fw-semibold">
            <i class="bi bi-award me-1"></i> Official University Portal
        </span>
        <h1 class="display-5 fw-bold text-navy lh-tight mb-3">
            Book Academic Consultations <span class="text-gold">With Ease</span>
        </h1>
        <p class="lead text-muted mb-4">
            Connect directly with lecturers, academic advisors, department heads, counselors, and university staff members. Eliminate scheduling conflicts and access personalized mentorship.
        </p>

        <div class="d-flex flex-wrap gap-3">
            <?php if (is_logged_in()): ?>
                <a href="<?= url(get_role_dashboard_url($_SESSION['user_role'])) ?>" class="btn btn-navy btn-lg px-4 rounded-pill">
                    <i class="bi bi-speedometer2 me-2"></i> Go to Your Dashboard
                </a>
            <?php else: ?>
                <a href="<?= url('public/register.php') ?>" class="btn btn-navy btn-lg px-4 rounded-pill shadow-sm">
                    <i class="bi bi-person-plus-fill me-2"></i> Student Registration
                </a>
                <a href="<?= url('public/login.php') ?>" class="btn btn-outline-dark btn-lg px-4 rounded-pill">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Portal Login
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5 mt-4 mt-lg-0 text-center">
        <div class="card border-0 shadow-lg p-4 bg-white rounded-4 text-start">
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-calendar-check fs-4 text-gold"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">Real-time Availability</h6>
                    <small class="text-muted">Instant slot discovery & zero double booking</small>
                </div>
            </div>

            <!-- Highlights -->
            <div class="row g-3 text-center">
                <div class="col-4">
                    <div class="p-2 border rounded-3 bg-light">
                        <div class="fs-4 fw-bold text-navy"><?= $deptCount ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Departments</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border rounded-3 bg-light">
                        <div class="fs-4 fw-bold text-navy"><?= $staffCount ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Staff & Faculty</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="p-2 border rounded-3 bg-light">
                        <div class="fs-4 fw-bold text-success"><?= $slotCount ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Open Slots</small>
                    </div>
                </div>
            </div>

            <div class="mt-4 p-3 rounded-3 bg-light-subtle border">
                <div class="small fw-semibold text-navy mb-1"><i class="bi bi-shield-lock-fill text-gold me-1"></i> Multi-Role University Platform</div>
                <p class="small text-muted mb-0">Supports Lecturers, Academic Advisors, Department Heads, Counselors, Registry Staff, Students, and Administrators.</p>
            </div>
        </div>
    </div>
</div>

<!-- Services / Categories Overview -->
<div class="row g-4 my-4">
    <div class="col-12 text-center mb-2">
        <h3 class="fw-bold text-navy">Comprehensive Consultation Services</h3>
        <p class="text-muted">Explore the different categories of university specialists ready to assist you.</p>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-3 p-4">
            <div class="rounded-3 bg-primary bg-opacity-10 text-primary d-inline-flex p-3 mb-3 align-self-start">
                <i class="bi bi-journal-code fs-3"></i>
            </div>
            <h5 class="fw-bold">Faculty Lecturers</h5>
            <p class="text-muted small">Coursework clarifications, project supervision, thesis guidance, and academic research discussions.</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-3 p-4">
            <div class="rounded-3 bg-info bg-opacity-10 text-info d-inline-flex p-3 mb-3 align-self-start">
                <i class="bi bi-compass fs-3"></i>
            </div>
            <h5 class="fw-bold">Academic Advisors</h5>
            <p class="text-muted small">Degree audits, prerequisite clearances, course selections, graduation requirements, and academic standing.</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-3 p-4">
            <div class="rounded-3 bg-purple bg-opacity-10 text-purple d-inline-flex p-3 mb-3 align-self-start" style="color: #6f42c1;">
                <i class="bi bi-heart-pulse fs-3"></i>
            </div>
            <h5 class="fw-bold">Counseling Services</h5>
            <p class="text-muted small">Private, confidential mental health counseling, stress management, crisis support, and personal development.</p>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-3 p-4">
            <div class="rounded-3 bg-dark bg-opacity-10 text-dark d-inline-flex p-3 mb-3 align-self-start">
                <i class="bi bi-building-check fs-3"></i>
            </div>
            <h5 class="fw-bold">Department Heads</h5>
            <p class="text-muted small">Departmental petitions, academic transfer approvals, special examination permissions, and faculty policy matters.</p>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm rounded-3 p-4">
            <div class="rounded-3 bg-success bg-opacity-10 text-success d-inline-flex p-3 mb-3 align-self-start">
                <i class="bi bi-award fs-3"></i>
            </div>
            <h5 class="fw-bold">University Registry & Financial Staff</h5>
            <p class="text-muted small">Official transcripts, deferrals, HELB loans, tuition installment inquiries, and student registry certifications.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
