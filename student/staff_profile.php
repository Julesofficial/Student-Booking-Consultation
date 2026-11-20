<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Individual Staff Member Profile & Consultation Slots View
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['student']);
$currentUser = current_user();
$pdo = get_db_connection();

$staffId = (int)($_GET['id'] ?? 0);
if ($staffId <= 0) {
    set_flash_message('danger', 'Invalid staff member specified.');
    redirect('student/staff.php');
}

// Fetch staff details
$stmt = $pdo->prepare("
    SELECT id, full_name, email, role, department, service, description, is_active
    FROM users
    WHERE id = :id 
    AND role IN ('lecturer', 'academic_advisor', 'department_head', 'counselor', 'university_staff')
    AND is_active = 1
    LIMIT 1
");
$stmt->execute([':id' => $staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    set_flash_message('danger', 'Staff member profile not found or is currently inactive.');
    redirect('student/staff.php');
}

// Fetch upcoming slots for this staff member (both available and booked so student sees schedule)
$stmt = $pdo->prepare("
    SELECT id, consultation_date, start_time, end_time, status
    FROM consultation_slots
    WHERE staff_id = :staff_id
    AND (consultation_date > CURRENT_DATE() OR (consultation_date = CURRENT_DATE() AND start_time > CURRENT_TIME()))
    ORDER BY consultation_date ASC, start_time ASC
");
$stmt->execute([':staff_id' => $staffId]);
$slots = $stmt->fetchAll();

// Count available slots
$availableCount = 0;
foreach ($slots as $slot) {
    if ($slot['status'] === 'available') {
        $availableCount++;
    }
}
$avail = get_staff_availability_badge($availableCount);

$page_title = $staff['full_name'] . ' - Profile';
$active_nav = 'find_staff';
include __DIR__ . '/../includes/header.php';
?>

<!-- Back navigation -->
<div class="mb-3">
    <a href="<?= url('student/staff.php') ?>" class="text-decoration-none text-muted small">
        <i class="bi bi-arrow-left me-1"></i> Back to Staff Directory
    </a>
</div>

<!-- Staff Header Card -->
<div class="card card-modern mb-4">
    <div class="card-body p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-md-auto text-center mb-3 mb-md-0">
                <div class="staff-avatar-placeholder mx-auto" style="width: 84px; height: 84px; font-size: 2rem;">
                    <?= get_user_avatar_initials($staff['full_name']) ?>
                </div>
            </div>
            <div class="col-md">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <h3 class="fw-bold text-navy mb-0"><?= e($staff['full_name']) ?></h3>
                    <span class="badge <?= get_role_badge_class($staff['role']) ?> px-3 py-1">
                        <?= format_role($staff['role']) ?>
                    </span>
                    <span class="badge <?= $avail['class'] ?> px-3 py-1">
                        <i class="bi <?= $avail['icon'] ?> me-1"></i><?= $avail['status'] ?> (<?= $avail['count'] ?> open)
                    </span>
                </div>

                <div class="text-muted mb-2">
                    <i class="bi bi-building me-1"></i> <?= e($staff['department'] ?? 'Department Unspecified') ?>
                    <span class="mx-2">&bull;</span>
                    <i class="bi bi-envelope me-1"></i> <?= e($staff['email']) ?>
                </div>

                <?php if (!empty($staff['service'])): ?>
                    <div class="p-3 bg-light rounded-3 mt-3">
                        <span class="fw-bold text-navy small d-block mb-1"><i class="bi bi-award-fill text-gold me-1"></i> Consultation Focus & Services:</span>
                        <div class="small text-secondary"><?= e($staff['service']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($staff['description'])): ?>
            <hr class="my-4">
            <h6 class="fw-bold text-navy mb-2">About & Office Consultation Overview</h6>
            <p class="text-secondary small mb-0 lh-base"><?= nl2br(e($staff['description'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Consultation Slots Section -->
<div class="card card-modern">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h5 class="fw-bold text-navy mb-0">Upcoming Consultation Slots</h5>
            <small class="text-muted">Select an available time slot below to schedule your appointment</small>
        </div>
        <span class="badge bg-light text-dark border">
            Total Slots: <?= count($slots) ?>
        </span>
    </div>

    <div class="card-body p-4">
        <?php if (empty($slots)): ?>
            <div class="empty-state p-4">
                <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                <h6 class="empty-state-title">No Upcoming Slots Available</h6>
                <p class="empty-state-text">This staff member has not posted any upcoming consultation slots yet. Please check back later or explore other faculty members.</p>
                <a href="<?= url('student/staff.php') ?>" class="btn btn-navy btn-sm rounded-pill px-3">
                    <i class="bi bi-search me-1"></i> Explore Other Staff
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($slots as $slot): 
                    $isAvailable = ($slot['status'] === 'available');
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border <?= $isAvailable ? 'border-success-subtle shadow-sm' : 'bg-light border-light-subtle' ?> rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="badge <?= $isAvailable ? 'bg-success' : 'bg-secondary' ?> px-2 py-1">
                                        <i class="bi <?= $isAvailable ? 'bi-check-circle' : 'bi-lock' ?> me-1"></i>
                                        <?= ucfirst($slot['status']) ?>
                                    </span>
                                    <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= format_date($slot['consultation_date'], 'D, M d') ?></small>
                                </div>

                                <div class="fs-5 fw-bold text-navy mb-2">
                                    <?= format_time($slot['start_time']) ?> &ndash; <?= format_time($slot['end_time']) ?>
                                </div>

                                <div class="small text-muted mb-3">
                                    <i class="bi bi-geo-alt me-1"></i>Staff Office / Online Consultation
                                </div>

                                <?php if ($isAvailable): ?>
                                    <a href="<?= url('student/book_consultation.php?slot_id=' . $slot['id']) ?>" class="btn btn-navy btn-sm w-100 rounded-pill">
                                        <i class="bi bi-calendar-check me-1"></i> Book This Slot
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm w-100 rounded-pill" disabled>
                                        <i class="bi bi-x-circle me-1"></i> Slot Booked
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
