<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Staff Student Bookings & Consultation Management
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

require_login(STAFF_ROLES);
$currentUser = current_user();
$staffId = (int)$currentUser['id'];
$pdo = get_db_connection();

// Handle Booking Actions (Mark Completed, Cancel with Reason, Accept, Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security validation failed (CSRF mismatch).');
        redirect('staff/bookings.php');
    }

    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    // Verify booking belongs to this staff member
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,
            b.student_id,
            b.slot_id,
            b.status,
            s.consultation_date,
            s.start_time,
            u.full_name AS student_name,
            u.email AS student_email
        FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        JOIN users u ON b.student_id = u.id
        WHERE b.id = :bid AND s.staff_id = :sid
        LIMIT 1
    ");
    $stmt->execute([':bid' => $bookingId, ':sid' => $staffId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        set_flash_message('danger', 'Booking record not found or access denied.');
        redirect('staff/bookings.php');
    }

    $studentId = (int)$booking['student_id'];
    $slotId = (int)$booking['slot_id'];
    $dateStr = format_date($booking['consultation_date']);
    $timeStr = format_time($booking['start_time']);

    // 1. Mark as Completed
    if ($action === 'complete_booking') {
        try {
            $update = $pdo->prepare("UPDATE bookings SET status = 'Completed', updated_at = NOW() WHERE id = :bid");
            $update->execute([':bid' => $bookingId]);

            create_notification(
                $studentId,
                'Consultation Completed',
                sprintf('Your consultation with %s on %s has been marked as completed. Thank you for attending!', $currentUser['full_name'], $dateStr)
            );

            set_flash_message('success', 'Consultation marked as completed successfully.');
        } catch (PDOException $e) {
            set_flash_message('danger', 'Failed to update consultation status.');
        }
    }

    // 2. Accept / Confirm Pending Booking
    elseif ($action === 'accept_booking') {
        try {
            $update = $pdo->prepare("UPDATE bookings SET status = 'Confirmed', updated_at = NOW() WHERE id = :bid");
            $update->execute([':bid' => $bookingId]);

            create_notification(
                $studentId,
                'Consultation Request Accepted',
                sprintf('Your consultation appointment with %s on %s at %s has been confirmed.', $currentUser['full_name'], $dateStr, $timeStr)
            );

            set_flash_message('success', 'Consultation request accepted and confirmed.');
        } catch (PDOException $e) {
            set_flash_message('danger', 'Failed to accept consultation.');
        }
    }

    // 3. Cancel Consultation with Reason
    elseif ($action === 'cancel_booking') {
        $cancelReason = trim($_POST['cancellation_reason'] ?? '');
        if (empty($cancelReason)) {
            set_flash_message('danger', 'Please provide a reason for cancelling this consultation.');
            redirect('staff/bookings.php');
        }

        try {
            $pdo->beginTransaction();

            // Update booking to Cancelled
            $update = $pdo->prepare("UPDATE bookings SET status = 'Cancelled', cancellation_reason = :reason, updated_at = NOW() WHERE id = :bid");
            $update->execute([':reason' => $cancelReason, ':bid' => $bookingId]);

            // Restore slot availability if date is in the future
            if ($booking['consultation_date'] >= date('Y-m-d')) {
                $restoreSlot = $pdo->prepare("UPDATE consultation_slots SET status = 'available' WHERE id = :slot_id");
                $restoreSlot->execute([':slot_id' => $slotId]);
            }

            // Notify student
            create_notification(
                $studentId,
                'Consultation Cancelled by Faculty',
                sprintf('Your consultation with %s on %s at %s was cancelled. Reason: "%s". You can schedule another available slot.', $currentUser['full_name'], $dateStr, $timeStr, $cancelReason)
            );

            $pdo->commit();
            set_flash_message('success', 'Consultation cancelled and student has been notified.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash_message('danger', 'Failed to cancel consultation.');
        }
    }

    // 4. Reject Pending Booking
    elseif ($action === 'reject_booking') {
        $rejectReason = trim($_POST['rejection_reason'] ?? 'Schedule conflict or unavailable');
        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare("UPDATE bookings SET status = 'Rejected', cancellation_reason = :reason, updated_at = NOW() WHERE id = :bid");
            $update->execute([':reason' => $rejectReason, ':bid' => $bookingId]);

            // Restore slot
            $restoreSlot = $pdo->prepare("UPDATE consultation_slots SET status = 'available' WHERE id = :slot_id");
            $restoreSlot->execute([':slot_id' => $slotId]);

            create_notification(
                $studentId,
                'Consultation Request Declined',
                sprintf('%s could not accept your consultation request for %s. Reason: "%s". The slot has been released.', $currentUser['full_name'], $dateStr, $rejectReason)
            );

            $pdo->commit();
            set_flash_message('success', 'Consultation request rejected and slot reopened.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            set_flash_message('danger', 'Failed to decline request.');
        }
    }

    redirect('staff/bookings.php');
}

// Fetch all bookings for this staff member
$filterStatus = trim($_GET['status'] ?? '');
$sql = "
    SELECT 
        b.id AS booking_id,
        b.consultation_reason,
        b.status AS booking_status,
        b.cancellation_reason,
        b.created_at AS booked_at,
        s.id AS slot_id,
        s.consultation_date,
        s.start_time,
        s.end_time,
        u.id AS student_id,
        u.full_name AS student_name,
        u.email AS student_email,
        u.course AS student_course,
        u.department AS student_dept
    FROM bookings b
    JOIN consultation_slots s ON b.slot_id = s.id
    JOIN users u ON b.student_id = u.id
    WHERE s.staff_id = :sid
";

$params = [':sid' => $staffId];
if (!empty($filterStatus)) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $filterStatus;
}
$sql .= " ORDER BY s.consultation_date DESC, s.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$page_title = 'Student Bookings';
$active_nav = 'bookings';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-person-lines-fill text-gold me-2"></i>Student Consultations</h3>
        <p class="text-muted mb-0">Review consultation requests, student agenda details, and manage appointment progress</p>
    </div>
</div>

<!-- Status Filters -->
<div class="card card-modern mb-4">
    <div class="card-body p-2 d-flex flex-wrap gap-2">
        <a href="<?= url('staff/bookings.php') ?>" class="btn btn-sm <?= empty($filterStatus) ? 'btn-navy' : 'btn-light border' ?> rounded-pill px-3">
            All (<?= count($bookings) ?>)
        </a>
        <a href="<?= url('staff/bookings.php?status=Confirmed') ?>" class="btn btn-sm <?= $filterStatus === 'Confirmed' ? 'btn-success' : 'btn-light border' ?> rounded-pill px-3">
            Confirmed
        </a>
        <a href="<?= url('staff/bookings.php?status=Pending') ?>" class="btn btn-sm <?= $filterStatus === 'Pending' ? 'btn-warning' : 'btn-light border' ?> rounded-pill px-3">
            Pending Requests
        </a>
        <a href="<?= url('staff/bookings.php?status=Completed') ?>" class="btn btn-sm <?= $filterStatus === 'Completed' ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3">
            Completed
        </a>
        <a href="<?= url('staff/bookings.php?status=Cancelled') ?>" class="btn btn-sm <?= $filterStatus === 'Cancelled' ? 'btn-danger' : 'btn-light border' ?> rounded-pill px-3">
            Cancelled
        </a>
    </div>
</div>

<!-- Bookings Cards / Table -->
<?php if (empty($bookings)): ?>
    <div class="empty-state p-5">
        <div class="empty-state-icon"><i class="bi bi-journal-x"></i></div>
        <h5 class="empty-state-title">No Bookings Found</h5>
        <p class="empty-state-text">There are currently no student bookings registered under this filter.</p>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($bookings as $bk): ?>
            <div class="col-lg-6">
                <div class="card card-modern h-100">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="fw-bold text-navy">
                            <i class="bi bi-calendar3 text-gold me-1"></i> <?= format_date($bk['consultation_date']) ?>
                        </div>
                        <div><?= get_booking_status_badge($bk['booking_status']) ?></div>
                    </div>

                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-navy text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-weight: 600;">
                                    <?= get_user_avatar_initials($bk['student_name']) ?>
                                </div>
                                <div>
                                    <h5 class="fw-bold text-navy mb-0"><?= e($bk['student_name']) ?></h5>
                                    <small class="text-muted d-block"><?= e($bk['student_email']) ?></small>
                                    <span class="badge bg-light text-dark border" style="font-size: 0.72rem;"><?= e($bk['student_course'] ?? 'Undergraduate') ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <span class="text-muted d-block">Scheduled Time:</span>
                                    <strong class="text-navy"><i class="bi bi-clock me-1 text-primary"></i><?= format_time($bk['start_time']) ?> &ndash; <?= format_time($bk['end_time']) ?></strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">Department:</span>
                                    <strong class="text-dark"><?= e($bk['student_dept'] ?? 'Unspecified') ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="fw-semibold text-muted d-block mb-1">Student's Consultation Reason / Agenda:</small>
                            <p class="small text-secondary mb-0 bg-white p-2 border rounded-2 lh-sm">
                                <?= nl2br(e($bk['consultation_reason'])) ?>
                            </p>
                        </div>

                        <?php if (!empty($bk['cancellation_reason'])): ?>
                            <div class="p-2 alert alert-danger small mb-0">
                                <strong><i class="bi bi-x-circle me-1"></i>Cancellation / Rejection Note:</strong> <?= e($bk['cancellation_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer bg-light py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2 border-top">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            Requested on <?= format_datetime($bk['booked_at']) ?>
                        </small>

                        <div class="d-flex gap-2">
                            <?php if ($bk['booking_status'] === 'Pending'): ?>
                                <form action="<?= url('staff/bookings.php') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="accept_booking">
                                    <input type="hidden" name="booking_id" value="<?= $bk['booking_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                        <i class="bi bi-check-lg me-1"></i> Accept
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($bk['booking_status'] === 'Confirmed'): ?>
                                <form action="<?= url('staff/bookings.php') ?>" method="POST" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="complete_booking">
                                    <input type="hidden" name="booking_id" value="<?= $bk['booking_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3" data-confirm="Mark this consultation session as completed?">
                                        <i class="bi bi-patch-check me-1"></i> Mark Completed
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($bk['booking_status'], ['Confirmed', 'Pending'])): ?>
                                <!-- Trigger Staff Cancel Modal -->
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#staffCancelModal<?= $bk['booking_id'] ?>">
                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                </button>

                                <!-- Staff Cancel Modal -->
                                <div class="modal fade" id="staffCancelModal<?= $bk['booking_id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form action="<?= url('staff/bookings.php') ?>" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="cancel_booking">
                                                <input type="hidden" name="booking_id" value="<?= $bk['booking_id'] ?>">

                                                <div class="modal-header">
                                                    <h5 class="modal-title text-danger">
                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Cancel Consultation
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <p class="small text-muted mb-3">
                                                        You are cancelling the appointment with student <strong><?= e($bk['student_name']) ?></strong> scheduled for <strong><?= format_date($bk['consultation_date']) ?></strong>.
                                                    </p>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold small">State Cancellation Reason <span class="text-danger">*</span></label>
                                                        <textarea class="form-control" name="cancellation_reason" rows="3" placeholder="e.g. Urgent university meeting, lecture rescheduled, academic conference..." required></textarea>
                                                    </div>
                                                    <div class="alert alert-info py-2 small mb-0">
                                                        The student will be notified and this slot will be restored to 'available'.
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Keep Slot</button>
                                                    <button type="submit" class="btn btn-danger btn-sm">Confirm Cancellation</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
