<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Staff Consultation Slot Management
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/validation.php';
require_once __DIR__ . '/../src/notifications.php';

require_login(STAFF_ROLES);
$currentUser = current_user();
$staffId = (int)$currentUser['id'];
$pdo = get_db_connection();

$errors = [];
$editSlot = null;

// Handle Delete Slot Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_slot') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security validation failed.');
        redirect('staff/manage_slots.php');
    }

    $slotId = (int)($_POST['slot_id'] ?? 0);

    // Verify slot exists, belongs to this staff member, and is NOT booked
    $stmt = $pdo->prepare("SELECT id, status FROM consultation_slots WHERE id = :id AND staff_id = :sid LIMIT 1");
    $stmt->execute([':id' => $slotId, ':sid' => $staffId]);
    $slot = $stmt->fetch();

    if (!$slot) {
        set_flash_message('danger', 'Slot not found or unauthorized.');
    } elseif ($slot['status'] === 'booked') {
        set_flash_message('danger', 'Cannot delete a slot that has already been booked by a student. Please cancel the booking first.');
    } else {
        $del = $pdo->prepare("DELETE FROM consultation_slots WHERE id = :id AND staff_id = :sid");
        $del->execute([':id' => $slotId, ':sid' => $staffId]);
        set_flash_message('success', 'Consultation slot deleted successfully.');
    }
    redirect('staff/manage_slots.php');
}

// Handle Add / Edit Slot Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create_slot', 'update_slot'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Security validation failed (CSRF mismatch).';
    } else {
        $slotDate = trim($_POST['consultation_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $editingId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : null;

        $errors = validate_slot($staffId, $slotDate, $startTime, $endTime, $editingId);

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'create_slot') {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO consultation_slots (staff_id, consultation_date, start_time, end_time, status, created_at)
                        VALUES (:staff_id, :cdate, :start_time, :end_time, 'available', NOW())
                    ");
                    $insertStmt->execute([
                        ':staff_id'   => $staffId,
                        ':cdate'      => $slotDate,
                        ':start_time' => $startTime,
                        ':end_time'   => $endTime
                    ]);
                    set_flash_message('success', 'New consultation slot successfully scheduled and published!');
                } else {
                    // Update existing unbooked slot
                    $updateStmt = $pdo->prepare("
                        UPDATE consultation_slots 
                        SET consultation_date = :cdate, start_time = :start_time, end_time = :end_time
                        WHERE id = :id AND staff_id = :staff_id AND status = 'available'
                    ");
                    $updateStmt->execute([
                        ':cdate'      => $slotDate,
                        ':start_time' => $startTime,
                        ':end_time'   => $endTime,
                        ':id'         => $editingId,
                        ':staff_id'   => $staffId
                    ]);
                    set_flash_message('success', 'Consultation slot updated successfully.');
                }
                redirect('staff/manage_slots.php');
            } catch (PDOException $e) {
                error_log('Slot save error: ' . $e->getMessage());
                $errors['general'] = 'A database error occurred while saving the slot.';
            }
        }
    }
}

// Check if editing a specific slot
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM consultation_slots WHERE id = :id AND staff_id = :sid AND status = 'available' LIMIT 1");
    $stmt->execute([':id' => $editId, ':sid' => $staffId]);
    $editSlot = $stmt->fetch();
}

// Fetch all slots for this staff member
$filterDate = trim($_GET['date_filter'] ?? 'upcoming');
$sql = "
    SELECT 
        s.id,
        s.consultation_date,
        s.start_time,
        s.end_time,
        s.status,
        s.created_at,
        b.id AS booking_id,
        u.full_name AS student_name,
        u.email AS student_email
    FROM consultation_slots s
    LEFT JOIN bookings b ON s.id = b.slot_id
    LEFT JOIN users u ON b.student_id = u.id
    WHERE s.staff_id = :sid
";

if ($filterDate === 'upcoming') {
    $sql .= " AND (s.consultation_date > CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time >= CURRENT_TIME()))";
} elseif ($filterDate === 'past') {
    $sql .= " AND (s.consultation_date < CURRENT_DATE() OR (s.consultation_date = CURRENT_DATE() AND s.start_time < CURRENT_TIME()))";
}

$sql .= " ORDER BY s.consultation_date ASC, s.start_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':sid' => $staffId]);
$slots = $stmt->fetchAll();

$page_title = 'Consultation Slots';
$active_nav = 'manage_slots';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-clock-fill text-gold me-2"></i>Consultation Slots Management</h3>
        <p class="text-muted mb-0">Create, view, and manage your consultation availability for university students</p>
    </div>
    <div>
        <a href="<?= url('staff/availability.php') ?>" class="btn btn-outline-navy rounded-pill px-3">
            <i class="bi bi-lightning-charge me-1"></i> Quick Batch Generator
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Slot Creation / Edit Form -->
    <div class="col-lg-4">
        <div class="card card-modern sticky-top" style="top: 20px;">
            <div class="card-header bg-navy text-white py-3 px-4 rounded-top-3">
                <h5 class="fw-bold mb-0">
                    <i class="bi <?= $editSlot ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-1 text-gold"></i>
                    <?= $editSlot ? 'Edit Consultation Slot' : 'Create Consultation Slot' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger small mb-3"><?= e($errors['general']) ?></div>
                <?php endif; ?>
                <?php if (!empty($errors['overlap'])): ?>
                    <div class="alert alert-warning small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= e($errors['overlap']) ?>
                    </div>
                <?php endif; ?>

                <form action="<?= url('staff/manage_slots.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editSlot ? 'update_slot' : 'create_slot' ?>">
                    <?php if ($editSlot): ?>
                        <input type="hidden" name="slot_id" value="<?= $editSlot['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="consultation_date" class="form-label fw-semibold small text-muted">Consultation Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control min-today <?= isset($errors['consultation_date']) ? 'is-invalid' : '' ?>" id="consultation_date" name="consultation_date" value="<?= e($editSlot['consultation_date'] ?? $_POST['consultation_date'] ?? date('Y-m-d', strtotime('+1 day'))) ?>" required>
                        <?php if (isset($errors['consultation_date'])): ?>
                            <div class="invalid-feedback"><?= e($errors['consultation_date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="start_time" class="form-label fw-semibold small text-muted">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control <?= isset($errors['start_time']) ? 'is-invalid' : '' ?>" id="start_time" name="start_time" value="<?= e(isset($editSlot['start_time']) ? substr($editSlot['start_time'], 0, 5) : ($_POST['start_time'] ?? '09:00')) ?>" required>
                            <?php if (isset($errors['start_time'])): ?>
                                <div class="invalid-feedback"><?= e($errors['start_time']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-6">
                            <label for="end_time" class="form-label fw-semibold small text-muted">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control <?= isset($errors['end_time']) ? 'is-invalid' : '' ?>" id="end_time" name="end_time" value="<?= e(isset($editSlot['end_time']) ? substr($editSlot['end_time'], 0, 5) : ($_POST['end_time'] ?? '09:45')) ?>" required>
                            <?php if (isset($errors['end_time'])): ?>
                                <div class="invalid-feedback"><?= e($errors['end_time']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="slot_duration" class="form-label fw-semibold small text-muted">Quick Duration Presets</label>
                        <select class="form-select form-select-sm" id="slot_duration">
                            <option value="45" selected>45 Minutes (Standard)</option>
                            <option value="30">30 Minutes (Short Briefing)</option>
                            <option value="60">60 Minutes (Deep Mentoring / Thesis)</option>
                        </select>
                        <div class="form-text small">Adjusts end time automatically when start time is chosen.</div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-navy py-2 rounded-pill fw-semibold">
                            <i class="bi <?= $editSlot ? 'bi-check-lg' : 'bi-plus-circle' ?> me-1"></i>
                            <?= $editSlot ? 'Save Changes' : 'Publish Slot' ?>
                        </button>
                        <?php if ($editSlot): ?>
                            <a href="<?= url('staff/manage_slots.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill">
                                Cancel Editing
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Slots Listing Table -->
    <div class="col-lg-8">
        <div class="card card-modern">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold text-navy mb-0">Scheduled Slots</h5>
                    <small class="text-muted">Total: <?= count($slots) ?> slots</small>
                </div>

                <!-- Date Filter Tabs -->
                <div class="btn-group btn-group-sm" role="group">
                    <a href="<?= url('staff/manage_slots.php?date_filter=upcoming') ?>" class="btn <?= $filterDate === 'upcoming' ? 'btn-navy' : 'btn-outline-secondary' ?>">Upcoming</a>
                    <a href="<?= url('staff/manage_slots.php?date_filter=past') ?>" class="btn <?= $filterDate === 'past' ? 'btn-navy' : 'btn-outline-secondary' ?>">Past</a>
                    <a href="<?= url('staff/manage_slots.php?date_filter=all') ?>" class="btn <?= $filterDate === 'all' ? 'btn-navy' : 'btn-outline-secondary' ?>">All</a>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($slots)): ?>
                    <div class="empty-state p-5">
                        <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                        <h6 class="empty-state-title">No Slots Found</h6>
                        <p class="empty-state-text">You have no consultation slots under this filter. Use the form on the left to publish your first slot.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Student Booking</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slots as $sl): 
                                    $isAvail = ($sl['status'] === 'available');
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-navy"><?= format_date($sl['consultation_date']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="bi bi-clock me-1"></i><?= format_time($sl['start_time']) ?> &ndash; <?= format_time($sl['end_time']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($isAvail): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bi bi-check-circle me-1"></i>Available
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                                    <i class="bi bi-bookmark-check me-1"></i>Booked
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($sl['student_name'])): ?>
                                                <div class="fw-medium small"><?= e($sl['student_name']) ?></div>
                                                <small class="text-muted" style="font-size: 0.72rem;"><?= e($sl['student_email']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted small">&mdash; Open for Booking &mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($isAvail): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?= url('staff/manage_slots.php?edit_id=' . $sl['id']) ?>" class="btn btn-outline-secondary py-1" title="Edit slot">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form action="<?= url('staff/manage_slots.php') ?>" method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="delete_slot">
                                                        <input type="hidden" name="slot_id" value="<?= $sl['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger py-1" title="Delete unbooked slot" data-confirm="Are you sure you want to delete this consultation slot?">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <a href="<?= url('staff/bookings.php') ?>" class="btn btn-sm btn-outline-navy py-1" title="View Booking">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            <?php endif; ?>
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
