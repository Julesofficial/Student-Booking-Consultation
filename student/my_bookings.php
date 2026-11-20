<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Student Bookings Management & Cancellation Workflow
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/notifications.php';

require_login(['student']);
$currentUser = current_user();
$studentId = (int)$currentUser['id'];
$pdo = get_db_connection();

// Handle Cancellation Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('danger', 'Security validation failed (CSRF mismatch).');
        redirect('student/my_bookings.php');
    }

    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $cancelReason = trim($_POST['cancellation_reason'] ?? '');

    if (empty($cancelReason)) {
        set_flash_message('danger', 'Please provide a reason for cancelling your consultation.');
        redirect('student/my_bookings.php');
    }

    // Verify booking ownership and cancellable status
    $stmt = $pdo->prepare("
        SELECT b.id, b.slot_id, b.status, s.staff_id, s.consultation_date, s.start_time, u.full_name AS staff_name
        FROM bookings b
        JOIN consultation_slots s ON b.slot_id = s.id
        JOIN users u ON s.staff_id = u.id
        WHERE b.id = :bid AND b.student_id = :sid
        LIMIT 1
    ");
    $stmt->execute([':bid' => $bookingId, ':sid' => $studentId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        set_flash_message('danger', 'Booking not found or access denied.');
        redirect('student/my_bookings.php');
    }

    if (!in_array($booking['status'], ['Confirmed', 'Pending'])) {
        set_flash_message('warning', 'Only upcoming Confirmed or Pending bookings can be cancelled.');
        redirect('student/my_bookings.php');
    }

    try {
        $pdo->beginTransaction();

        // 1. Update booking status to Cancelled and record reason
        $updateBooking = $pdo->prepare("
            UPDATE bookings 
            SET status = 'Cancelled', cancellation_reason = :reason, updated_at = NOW() 
            WHERE id = :bid
        ");
        $updateBooking->execute([
            ':reason' => $cancelReason,
            ':bid'    => $bookingId
        ]);

        // 2. Restore slot status to 'available' if the slot date is still in the future
        $currentDate = date('Y-m-d');
        if ($booking['consultation_date'] >= $currentDate) {
            $updateSlot = $pdo->prepare("UPDATE consultation_slots SET status = 'available' WHERE id = :slot_id");
            $updateSlot->execute([':slot_id' => $booking['slot_id']]);
        }

        // 3. Notify Staff Member
        $dateStr = format_date($booking['consultation_date']);
        $timeStr = format_time($booking['start_time']);
        create_notification(
            (int)$booking['staff_id'],
            'Consultation Cancelled by Student',
            sprintf(
                'Student %s has cancelled the consultation scheduled on %s at %s. Reason: "%s". The slot has been reopened.',
                $currentUser['full_name'],
                $dateStr,
                $timeStr,
                $cancelReason
            )
        );

        // 4. Notify Student
        create_notification(
            $studentId,
            'Consultation Cancelled',
            sprintf('You have cancelled your appointment with %s on %s at %s.', $booking['staff_name'], $dateStr, $timeStr)
        );

        $pdo->commit();
        set_flash_message('success', 'Your consultation has been successfully cancelled and the slot has been restored.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Cancellation error: ' . $e->getMessage());
        set_flash_message('danger', 'An unexpected error occurred while cancelling your consultation.');
    }

    redirect('student/my_bookings.php');
}

// Fetch all bookings for this student
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
        u.id AS staff_id,
        u.full_name AS staff_name,
        u.email AS staff_email,
        u.role AS staff_role,
        u.department AS staff_dept
    FROM bookings b
    JOIN consultation_slots s ON b.slot_id = s.id
    JOIN users u ON s.staff_id = u.id
    WHERE b.student_id = :sid
";

$params = [':sid' => $studentId];
if (!empty($filterStatus)) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $filterStatus;
}
$sql .= " ORDER BY s.consultation_date DESC, s.start_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$page_title = 'My Bookings';
$active_nav = 'my_bookings';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-calendar-check-fill text-gold me-2"></i>My Consultation Bookings</h3>
        <p class="text-muted mb-0">Manage and track your active, completed, and cancelled consultation appointments</p>
    </div>
    <a href="<?= url('student/staff.php') ?>" class="btn btn-navy rounded-pill px-4">
        <i class="bi bi-plus-circle me-1"></i> Book New Consultation
    </a>
</div>

<!-- Status Filters -->
<div class="card card-modern mb-4">
    <div class="card-body p-2 d-flex flex-wrap gap-2">
        <a href="<?= url('student/my_bookings.php') ?>" class="btn btn-sm <?= empty($filterStatus) ? 'btn-navy' : 'btn-light border' ?> rounded-pill px-3">
            All Bookings
        </a>
        <a href="<?= url('student/my_bookings.php?status=Confirmed') ?>" class="btn btn-sm <?= $filterStatus === 'Confirmed' ? 'btn-success' : 'btn-light border' ?> rounded-pill px-3">
            Confirmed
        </a>
        <a href="<?= url('student/my_bookings.php?status=Pending') ?>" class="btn btn-sm <?= $filterStatus === 'Pending' ? 'btn-warning' : 'btn-light border' ?> rounded-pill px-3">
            Pending
        </a>
        <a href="<?= url('student/my_bookings.php?status=Completed') ?>" class="btn btn-sm <?= $filterStatus === 'Completed' ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3">
            Completed
        </a>
        <a href="<?= url('student/my_bookings.php?status=Cancelled') ?>" class="btn btn-sm <?= $filterStatus === 'Cancelled' ? 'btn-danger' : 'btn-light border' ?> rounded-pill px-3">
            Cancelled
        </a>
    </div>
</div>

<!-- Bookings List -->
<?php if (empty($bookings)): ?>
    <div class="empty-state p-5">
        <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
        <h5 class="empty-state-title">No Consultations Found</h5>
        <p class="empty-state-text">You have no consultation bookings under this category. Visit the staff directory to find an available advisor or lecturer.</p>
        <a href="<?= url('student/staff.php') ?>" class="btn btn-navy btn-sm rounded-pill px-4">
            <i class="bi bi-search me-1"></i> Browse University Staff
        </a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($bookings as $bk): 
            $isUpcoming = in_array($bk['booking_status'], ['Confirmed', 'Pending']) && 
                          ($bk['consultation_date'] > date('Y-m-d') || ($bk['consultation_date'] === date('Y-m-d') && $bk['start_time'] >= date('H:i:s')));
        ?>
            <div class="col-lg-6">
                <div class="card card-modern h-100">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= get_role_badge_class($bk['staff_role']) ?>">
                                <?= format_role($bk['staff_role']) ?>
                            </span>
                            <small class="text-muted"><i class="bi bi-building me-1"></i><?= e($bk['staff_dept']) ?></small>
                        </div>
                        <div><?= get_booking_status_badge($bk['booking_status']) ?></div>
                    </div>

                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="staff-avatar-placeholder" style="width: 46px; height: 46px; font-size: 1.1rem;">
                                <?= get_user_avatar_initials($bk['staff_name']) ?>
                            </div>
                            <div>
                                <h5 class="fw-bold text-navy mb-0"><?= e($bk['staff_name']) ?></h5>
                                <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($bk['staff_email']) ?></small>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Consultation Date</small>
                                    <strong class="text-dark"><i class="bi bi-calendar-event me-1 text-primary"></i><?= format_date($bk['consultation_date']) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Time Slot</small>
                                    <strong class="text-dark"><i class="bi bi-clock me-1 text-primary"></i><?= format_time($bk['start_time']) ?> &ndash; <?= format_time($bk['end_time']) ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="fw-semibold text-muted d-block mb-1">Consultation Agenda / Reason:</small>
                            <p class="small text-secondary mb-0 bg-white p-2 border rounded-2 lh-sm">
                                <?= nl2br(e($bk['consultation_reason'])) ?>
                            </p>
                        </div>

                        <?php if (!empty($bk['cancellation_reason'])): ?>
                            <div class="p-2 alert alert-danger small mb-0">
                                <strong><i class="bi bi-x-circle me-1"></i>Cancellation Note:</strong> <?= e($bk['cancellation_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer bg-light py-3 px-4 d-flex align-items-center justify-content-between border-top">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            Booked on <?= format_datetime($bk['booked_at']) ?>
                        </small>

                        <?php if ($isUpcoming): ?>
                            <!-- Trigger Modal for Cancellation -->
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#cancelModal<?= $bk['booking_id'] ?>">
                                <i class="bi bi-x-circle me-1"></i> Cancel Booking
                            </button>

                            <!-- Cancel Modal -->
                            <div class="modal fade" id="cancelModal<?= $bk['booking_id'] ?>" tabindex="-1" aria-labelledby="cancelModalLabel<?= $bk['booking_id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form action="<?= url('student/my_bookings.php') ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel_booking">
                                            <input type="hidden" name="booking_id" value="<?= $bk['booking_id'] ?>">

                                            <div class="modal-header">
                                                <h5 class="modal-title text-danger" id="cancelModalLabel<?= $bk['booking_id'] ?>">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Cancel Consultation
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>

                                            <div class="modal-body">
                                                <p class="small text-muted mb-3">
                                                    You are about to cancel your consultation appointment with <strong><?= e($bk['staff_name']) ?></strong> on <strong><?= format_date($bk['consultation_date']) ?></strong> at <strong><?= format_time($bk['start_time']) ?></strong>.
                                                </p>
                                                <div class="mb-3">
                                                    <label for="cancellation_reason<?= $bk['booking_id'] ?>" class="form-label fw-semibold small">Please state your reason for cancellation <span class="text-danger">*</span></label>
                                                    <textarea class="form-control" id="cancellation_reason<?= $bk['booking_id'] ?>" name="cancellation_reason" rows="3" placeholder="e.g. Schedule clash with lecture, resolved query, unwell..." required></textarea>
                                                </div>
                                                <div class="alert alert-info py-2 small mb-0">
                                                    <i class="bi bi-info-circle me-1"></i> The consultation slot will be restored to 'available' for other students.
                                                </div>
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Keep Appointment</button>
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
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
