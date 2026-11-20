<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Staff Discovery and Search Directory
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

require_login(['student']);
$currentUser = current_user();
$pdo = get_db_connection();

// Get filter inputs from query string
$searchQuery = trim($_GET['search'] ?? '');
$filterRole = trim($_GET['role'] ?? '');
$filterDept = trim($_GET['department'] ?? '');

// Fetch departments for filter dropdown
$departments = [];
try {
    $departments = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name ASC")->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Build query for staff members (all roles except student and admin)
$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.email,
        u.role,
        u.department,
        u.service,
        u.description,
        COUNT(CASE WHEN s.status = 'available' AND (s.consultation_date > CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time > CURRENT_TIME())) THEN 1 END) AS available_slots_count,
        COUNT(s.id) AS total_future_slots
    FROM users u
    LEFT JOIN consultation_slots s ON u.id = s.staff_id AND (s.consultation_date > CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time > CURRENT_TIME()))
    WHERE u.role IN ('lecturer', 'academic_advisor', 'department_head', 'counselor', 'university_staff')
    AND u.is_active = 1
";

$params = [];

if (!empty($searchQuery)) {
    $sql .= " AND (u.full_name LIKE :search OR u.service LIKE :search OR u.description LIKE :search)";
    $params[':search'] = '%' . $searchQuery . '%';
}

if (!empty($filterRole)) {
    $sql .= " AND u.role = :role";
    $params[':role'] = $filterRole;
}

if (!empty($filterDept)) {
    $sql .= " AND u.department = :dept";
    $params[':dept'] = $filterDept;
}

$sql .= " GROUP BY u.id ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffMembers = $stmt->fetchAll();

$page_title = 'Find Staff';
$active_nav = 'find_staff';
include __DIR__ . '/../includes/header.php';
?>

<!-- Directory Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-people-fill text-gold me-2"></i>University Staff Directory</h3>
        <p class="text-muted mb-0">Browse and book appointments with lecturers, academic advisors, department heads, and counselors</p>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card card-modern mb-4">
    <div class="card-body p-4">
        <form action="<?= url('student/staff.php') ?>" method="GET" class="row g-3 align-items-end">
            <!-- Search by Name or Service -->
            <div class="col-md-5">
                <label for="directorySearch" class="form-label fw-semibold small text-muted">Search by Name or Specialty</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" id="directorySearch" name="search" value="<?= e($searchQuery) ?>" placeholder="e.g. Dr Alice Mwangi or Cyber Security">
                </div>
            </div>

            <!-- Filter by Role -->
            <div class="col-md-3">
                <label for="roleFilter" class="form-label fw-semibold small text-muted">Professional Role</label>
                <select class="form-select" id="roleFilter" name="role">
                    <option value="">All University Roles</option>
                    <option value="lecturer" <?= $filterRole === 'lecturer' ? 'selected' : '' ?>>Lecturers</option>
                    <option value="academic_advisor" <?= $filterRole === 'academic_advisor' ? 'selected' : '' ?>>Academic Advisors</option>
                    <option value="department_head" <?= $filterRole === 'department_head' ? 'selected' : '' ?>>Department Heads</option>
                    <option value="counselor" <?= $filterRole === 'counselor' ? 'selected' : '' ?>>Counselors</option>
                    <option value="university_staff" <?= $filterRole === 'university_staff' ? 'selected' : '' ?>>University Staff</option>
                </select>
            </div>

            <!-- Filter by Department -->
            <div class="col-md-3">
                <label for="deptFilter" class="form-label fw-semibold small text-muted">Academic Department</label>
                <select class="form-select" id="deptFilter" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= e($dept['department_name']) ?>" <?= $filterDept === $dept['department_name'] ? 'selected' : '' ?>>
                            <?= e($dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-navy w-100" title="Apply Filters">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if (!empty($searchQuery) || !empty($filterRole) || !empty($filterDept)): ?>
                    <a href="<?= url('student/staff.php') ?>" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="bi bi-x-circle"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Results Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="text-muted small">
        Showing <span class="fw-bold text-dark"><?= count($staffMembers) ?></span> staff member<?= count($staffMembers) !== 1 ? 's' : '' ?>
    </div>
    <div class="d-flex align-items-center gap-2 small">
        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle me-1"></i>Available (>2 slots)</span>
        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-exclamation-circle me-1"></i>Limited (1-2 slots)</span>
        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1"><i class="bi bi-x-circle me-1"></i>Fully Booked</span>
    </div>
</div>

<!-- Staff Cards Container -->
<div class="row g-4" id="staffCardsContainer">
    <?php if (empty($staffMembers)): ?>
        <div class="col-12">
            <div class="empty-state p-5">
                <div class="empty-state-icon"><i class="bi bi-person-x"></i></div>
                <h5 class="empty-state-title">No Staff Members Found</h5>
                <p class="empty-state-text">No university staff members matched your search or filter criteria. Try adjusting your query or resetting the filters.</p>
                <a href="<?= url('student/staff.php') ?>" class="btn btn-outline-navy rounded-pill px-4">
                    <i class="bi bi-arrow-clockwise me-1"></i> Reset Directory Filters
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($staffMembers as $staff): 
            $avail = get_staff_availability_badge((int)$staff['available_slots_count']);
        ?>
            <div class="col-md-6 col-xl-4 staff-directory-item" 
                 data-name="<?= e(strtolower($staff['full_name'])) ?>"
                 data-role="<?= e(strtolower($staff['role'])) ?>"
                 data-department="<?= e(strtolower($staff['department'] ?? '')) ?>"
                 data-service="<?= e(strtolower($staff['service'] ?? '')) ?>">
                
                <div class="staff-card">
                    <!-- Staff Header -->
                    <div class="staff-card-header">
                        <div class="staff-avatar-placeholder">
                            <?= get_user_avatar_initials($staff['full_name']) ?>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <h5 class="fw-bold text-navy mb-1 text-truncate" title="<?= e($staff['full_name']) ?>">
                                <?= e($staff['full_name']) ?>
                            </h5>
                            <div class="d-flex align-items-center gap-1 mb-1">
                                <span class="badge <?= get_role_badge_class($staff['role']) ?>" style="font-size: 0.72rem;">
                                    <?= format_role($staff['role']) ?>
                                </span>
                            </div>
                            <small class="text-muted d-block text-truncate" title="<?= e($staff['department']) ?>">
                                <i class="bi bi-building me-1"></i><?= e($staff['department'] ?? 'General Faculty') ?>
                            </small>
                        </div>
                    </div>

                    <!-- Staff Body -->
                    <div class="staff-card-body">
                        <!-- Service / Specialty -->
                        <?php if (!empty($staff['service'])): ?>
                            <div class="mb-2 p-2 bg-light rounded-2 small">
                                <span class="fw-semibold text-navy d-block mb-1">
                                    <i class="bi bi-briefcase me-1 text-gold"></i>Consultation Service:
                                </span>
                                <span class="text-secondary"><?= e($staff['service']) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Description preview -->
                        <?php if (!empty($staff['description'])): ?>
                            <p class="text-muted small mb-3 lh-sm" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= e($staff['description']) ?>">
                                <?= e($staff['description']) ?>
                            </p>
                        <?php endif; ?>

                        <!-- Availability Indicator -->
                        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                            <span class="small text-muted fw-medium">Availability:</span>
                            <span class="badge <?= $avail['class'] ?> px-2 py-1" style="font-size: 0.75rem;">
                                <i class="bi <?= $avail['icon'] ?> me-1"></i><?= $avail['status'] ?> (<?= $avail['count'] ?>)
                            </span>
                        </div>
                    </div>

                    <!-- Staff Footer Actions -->
                    <div class="staff-card-footer d-flex gap-2">
                        <a href="<?= url('student/staff_profile.php?id=' . $staff['id']) ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                            <i class="bi bi-person me-1"></i> View Profile
                        </a>

                        <?php if ($staff['available_slots_count'] > 0): ?>
                            <a href="<?= url('student/book_consultation.php?staff_id=' . $staff['id']) ?>" class="btn btn-navy btn-sm flex-fill">
                                <i class="bi bi-calendar-plus me-1"></i> Book Slot
                            </a>
                        <?php else: ?>
                            <a href="<?= url('student/staff_profile.php?id=' . $staff['id']) ?>" class="btn btn-secondary btn-sm flex-fill disabled" aria-disabled="true" title="No available slots currently">
                                <i class="bi bi-calendar-x me-1"></i> Fully Booked
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Client-side No Results message (hidden initially) -->
<div id="noSearchResults" class="empty-state p-5 my-4" style="display: none;">
    <div class="empty-state-icon"><i class="bi bi-search"></i></div>
    <h5 class="empty-state-title">No Matching Staff</h5>
    <p class="empty-state-text">Your search term did not match any university staff on this page.</p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
