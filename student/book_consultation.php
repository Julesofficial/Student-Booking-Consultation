<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Consultation Booking Workflow & Transaction Execution
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/validation.php';
require_once __DIR__ . '/../src/notifications.php';

require_login(['student']);
$currentUser = current_user();
$studentId = (int)$currentUser['id'];
$pdo = get_db_connection();

$slotId = (int)($_GET['slot_id'] ?? $_POST['slot_id'] ?? 0);
$staffId = (int)($_GET['staff_id'] ?? 0);

$errors = [];
$selectedSlot = null;
$selectedStaff = null;
$reasonInput = trim($_POST['consultation_reason'] ?? '');

// If a specific slot_id is provided, fetch slot and staff details
if ($slotId > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            s.id AS slot_id,
            s.consultation_date,
            s.start_time,
            s.end_time,
            s.status AS slot_status,
            u.id AS staff_id,
            u.full_name AS staff_name,
            u.email AS staff_email,
            u.role AS staff_role,
            u.department AS staff_dept,
            u.service AS staff_service
        FROM consultation_slots s
        JOIN users u ON s.staff_id = u.id
        WHERE s.id = :slot_id
        LIMIT 1
    ");
    $stmt->execute([':slot_id' => $slotId]);
    $selectedSlot = $stmt->fetch();

    if ($selectedSlot) {
        $staffId = (int)$selectedSlot['staff_id'];
    }
}

// If staff_id is provided, fetch staff details and their available slots
$availableStaffSlots = [];
if ($staffId > 0) {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, role, department, service, description
        FROM users
        WHERE id = :id AND role IN ('lecturer', 'academic_advisor', 'department_head', 'counselor', 'university_staff') AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':id' => $staffId]);
    $selectedStaff = $stmt->fetch();

    if ($selectedStaff) {
        $slotStmt = $pdo->prepare("
            SELECT id, consultation_date, start_time, end_time, status
            FROM consultation_slots
            WHERE staff_id = :staff_id
            AND status = 'available'
            AND (consultation_date > CURRENT_DATE() OR (consultation_date = CURRENT_DATE() AND start_time > CURRENT_TIME()))
            ORDER BY consultation_date ASC, start_time ASC
        ");
        $slotStmt->execute([':staff_id' => $staffId]);
        $availableStaffSlots = $slotStmt->fetchAll();
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Security validation failed (CSRF mismatch). Please reload and try again.';
    } else {
        $submittedSlotId = (int)($_POST['slot_id'] ?? 0);
        $reason = trim($_POST['consultation_reason'] ?? '');

        if ($submittedSlotId <= 0) {
            $errors['slot_id'] = 'Please select a valid consultation slot.';
        } else {
            // Validate booking rules
            $validationErrors = validate_booking($studentId, $submittedSlotId, $reason);
            if (!empty($validationErrors)) {
                $errors = array_merge($errors, $validationErrors);
            }
        }

        // Execute Transaction if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Lock slot row to prevent race conditions / double bookings
                $lockStmt = $pdo->prepare("
                    SELECT id, staff_id, consultation_date, start_time, end_time, status 
                    FROM consultation_slots 
                    WHERE id = :slot_id 
                    FOR UPDATE
                ");
                $lockStmt->execute([':slot_id' => $submittedSlotId]);
                $lockedSlot = $lockStmt->fetch();

                if (!$lockedSlot || $lockedSlot['status'] !== 'available') {
                    $pdo->rollBack();
                    $errors['general'] = 'Unfortunately, this slot was just booked by another student. Please select an alternate slot.';
                } else {
                    // 1. Insert Booking Record
                    $bookingStmt = $pdo->prepare("
                        INSERT INTO bookings (student_id, slot_id, consultation_reason, status, created_at)
                        VALUES (:student_id, :slot_id, :reason, 'Confirmed', NOW())
                    ");
                    $bookingStmt->execute([
                        ':student_id' => $studentId,
                        ':slot_id'    => $submittedSlotId,
                        ':reason'     => $reason
                    ]);
                    $bookingId = (int)$pdo->lastInsertId();

                    // 2. Mark Slot as Booked
                    $updateSlotStmt = $pdo->prepare("
                        UPDATE consultation_slots 
                        SET status = 'booked' 
                        WHERE id = :slot_id
                    ");
                    $updateSlotStmt->execute([':slot_id' => $submittedSlotId]);

                    // 3. Dispatch Notification to Staff Member
                    $slotDateFormatted = format_date($lockedSlot['consultation_date']);
                    $slotTimeFormatted = format_time($lockedSlot['start_time']);

                    create_notification(
                        (int)$lockedSlot['staff_id'],
                        'New Consultation Booked',
                        sprintf(
                            'Student %s booked a consultation on %s at %s. Topic: "%s".',
                            $currentUser['full_name'],
                            $slotDateFormatted,
                            $slotTimeFormatted,
                            substr($reason, 0, 75) . (strlen($reason) > 75 ? '...' : '')
                        )
                    );

                    // 4. Dispatch Confirmation Notification to Student
                    create_notification(
                        $studentId,
                        'Consultation Booking Confirmed',
                        sprintf(
                            'Your appointment on %s at %s has been confirmed.',
                            $slotDateFormatted,
                            $slotTimeFormatted
                        )
                    );

                    // Commit Transaction
                    $pdo->commit();

                    set_flash_message('success', 'Your consultation appointment has been successfully booked and confirmed!');
                    redirect('student/my_bookings.php');
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Consultation booking error: ' . $e->getMessage());
                $errors['general'] = 'A system error occurred while processing your booking. Please try again or contact the administrator.';
            }
        }
    }
}

$page_title = 'Book Consultation';
$active_nav = 'book_consultation';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Breadcrumb / Back Link -->
        <div class="mb-3">
            <a href="<?= url('student/staff.php') ?>" class="text-decoration-none text-muted small">
                <i class="bi bi-arrow-left me-1"></i> Back to Staff Directory
            </a>
        </div>

        <div class="card card-modern">
            <div class="card-header bg-navy text-white py-3 px-4 rounded-top-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-check-fill fs-5 text-gold"></i>
                    <div>
                        <h5 class="mb-0 fw-bold">Schedule Consultation Appointment</h5>
                        <small class="text-white-50">Review slot details and submit your consultation agenda</small>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 p-md-5">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= e($errors['general']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['conflict'])): ?>
                    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                        <div><?= e($errors['conflict']) ?></div>
                    </div>
                <?php endif; ?>

                <form action="<?= url('student/book_consultation.php' . ($slotId ? '?slot_id=' . $slotId : ($staffId ? '?staff_id=' . $staffId : ''))) ?>" method="POST">
                    <?= csrf_field() ?>

                    <!-- Staff Member & Slot Information Card -->
                    <?php if ($selectedSlot): ?>
                        <input type="hidden" name="slot_id" value="<?= (int)$selectedSlot['slot_id'] ?>">

                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="staff-avatar-placeholder" style="width: 48px; height: 48px; font-size: 1.1rem;">
                                        <?= get_user_avatar_initials($selectedSlot['staff_name']) ?>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-navy mb-0"><?= e($selectedSlot['staff_name']) ?></h5>
                                        <span class="badge <?= get_role_badge_class($selectedSlot['staff_role']) ?>">
                                            <?= format_role($selectedSlot['staff_role']) ?>
                                        </span>
                                        <small class="text-muted ms-1">&bull; <?= e($selectedSlot['staff_dept']) ?></small>
                                    </div>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                    <i class="bi bi-check-circle me-1"></i>Available
                                </span>
                            </div>

                            <hr class="my-2">

                            <div class="row g-2 text-dark mt-1">
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Consultation Date:</small>
                                    <strong><i class="bi bi-calendar-event me-1 text-primary"></i><?= format_date($selectedSlot['consultation_date']) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Scheduled Time:</small>
                                    <strong><i class="bi bi-clock me-1 text-primary"></i><?= format_time($selectedSlot['start_time']) ?> &ndash; <?= format_time($selectedSlot['end_time']) ?></strong>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($selectedStaff): ?>
                        <!-- Slot selector if staff was chosen -->
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="staff-avatar-placeholder" style="width: 48px; height: 48px; font-size: 1.1rem;">
                                    <?= get_user_avatar_initials($selectedStaff['full_name']) ?>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-navy mb-0"><?= e($selectedStaff['full_name']) ?></h5>
                                    <span class="badge <?= get_role_badge_class($selectedStaff['role']) ?>">
                                        <?= format_role($selectedStaff['role']) ?>
                                    </span>
                                    <small class="text-muted ms-1">&bull; <?= e($selectedStaff['department']) ?></small>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label for="slot_id" class="form-label fw-semibold small text-muted">Select Available Consultation Slot <span class="text-danger">*</span></label>
                                <?php if (empty($availableStaffSlots)): ?>
                                    <div class="alert alert-warning mb-0 small">
                                        <i class="bi bi-exclamation-circle me-1"></i> This staff member does not have any open consultation slots right now.
                                    </div>
                                <?php else: ?>
                                    <select class="form-select <?= isset($errors['slot_id']) || isset($errors['slot']) ? 'is-invalid' : '' ?>" id="slot_id" name="slot_id" required>
                                        <option value="">-- Choose an available date & time --</option>
                                        <?php foreach ($availableStaffSlots as $aslot): ?>
                                            <option value="<?= $aslot['id'] ?>" <?= ($slotId === (int)$aslot['id']) ? 'selected' : '' ?>>
                                                <?= format_date($aslot['consultation_date']) ?> &bull; <?= format_time($aslot['start_time']) ?> &ndash; <?= format_time($aslot['end_time']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['slot_id']) || isset($errors['slot'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['slot_id'] ?? $errors['slot']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i> Please first select a university staff member or an available consultation slot from the <a href="<?= url('student/staff.php') ?>" class="alert-link">Staff Directory</a>.
                        </div>
                    <?php endif; ?>

                    <?php if ($selectedSlot || !empty($availableStaffSlots)): ?>
                        <!-- Consultation Reason / Description -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="consultation_reason" class="form-label fw-semibold small text-muted mb-0">
                                    Reason or Agenda for Consultation <span class="text-danger">*</span>
                                </label>
                                <small class="text-muted" id="reasonCharCounter">0 / 1000 characters</small>
                            </div>
                            <textarea 
                                class="form-control <?= isset($errors['consultation_reason']) ? 'is-invalid' : '' ?>" 
                                id="consultation_reason" 
                                name="consultation_reason" 
                                rows="5" 
                                maxlength="1000"
                                data-char-count="reasonCharCounter"
                                placeholder="Explain specifically what you would like to discuss (e.g., questions regarding coursework topic, final year project research proposal, course degree requirements, academic petition, or confidential guidance)..." 
                                required><?= e($reasonInput) ?></textarea>
                            <?php if (isset($errors['consultation_reason'])): ?>
                                <div class="invalid-feedback"><?= e($errors['consultation_reason']) ?></div>
                            <?php else: ?>
                                <div class="form-text small">Providing clear details helps the faculty member prepare relevant materials before your session.</div>
                            <?php endif; ?>
                        </div>

                        <!-- Anti-Conflict Notice -->
                        <div class="p-3 rounded-3 bg-light-subtle border mb-4 small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            <strong>Integrity Guarantee:</strong> Submitting this booking will reserve the slot immediately and protect against overlapping appointments.
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?= url('student/staff.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-navy rounded-pill px-5 fw-semibold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Confirm & Book Consultation
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
