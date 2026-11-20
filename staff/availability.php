<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Staff Availability Indicator & Batch Slot Generator
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/validation.php';

require_login(STAFF_ROLES);
$currentUser = current_user();
$staffId = (int)$currentUser['id'];
$pdo = get_db_connection();

$batchErrors = [];

// Handle Batch Slot Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_batch_slots') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $batchErrors['general'] = 'Security validation failed.';
    } else {
        $date = trim($_POST['batch_date'] ?? '');
        $startTime = trim($_POST['batch_start_time'] ?? '');
        $slotCount = (int)($_POST['batch_count'] ?? 3);
        $duration = (int)($_POST['batch_duration'] ?? 45); // in minutes
        $breakMins = (int)($_POST['batch_break'] ?? 5);

        if (empty($date) || $date < date('Y-m-d')) {
            $batchErrors['date'] = 'Please choose a valid future date.';
        }
        if (empty($startTime)) {
            $batchErrors['start_time'] = 'Start time is required.';
        }
        if ($slotCount < 1 || $slotCount > 8) {
            $batchErrors['count'] = 'Slot count must be between 1 and 8.';
        }

        if (empty($batchErrors)) {
            $currentStart = strtotime($startTime);
            $createdSlots = 0;
            $skippedSlots = 0;

            for ($i = 0; $i < $slotCount; $i++) {
                $slotStartStr = date('H:i:s', $currentStart);
                $slotEndSecs = $currentStart + ($duration * 60);
                $slotEndStr = date('H:i:s', $slotEndSecs);

                // Check overlap
                $overlapCheck = validate_slot($staffId, $date, $slotStartStr, $slotEndStr);
                if (empty($overlapCheck)) {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO consultation_slots (staff_id, consultation_date, start_time, end_time, status, created_at)
                        VALUES (:staff_id, :cdate, :start_time, :end_time, 'available', NOW())
                    ");
                    $insertStmt->execute([
                        ':staff_id'   => $staffId,
                        ':cdate'      => $date,
                        ':start_time' => $slotStartStr,
                        ':end_time'   => $slotEndStr
                    ]);
                    $createdSlots++;
                } else {
                    $skippedSlots++;
                }

                // Advance to next slot with break
                $currentStart = $slotEndSecs + ($breakMins * 60);
            }

            if ($createdSlots > 0) {
                set_flash_message('success', sprintf('Successfully generated %d consultation slot%s for %s! %s', $createdSlots, ($createdSlots > 1 ? 's' : ''), format_date($date), ($skippedSlots > 0 ? "({$skippedSlots} skipped due to overlapping conflicts)" : '')));
            } else {
                set_flash_message('warning', 'All requested slots conflicted with your existing schedule on that date.');
            }
            redirect('staff/availability.php');
        }
    }
}

// Fetch availability metrics
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM consultation_slots 
    WHERE staff_id = :sid 
    AND status = 'available' 
    AND (consultation_date > CURRENT_DATE() OR (consultation_date = CURRENT_DATE() AND start_time > CURRENT_TIME()))
");
$stmt->execute([':sid' => $staffId]);
$availableCount = (int)$stmt->fetchColumn();
$avail = get_staff_availability_badge($availableCount);

// Fetch upcoming slots grouped by date
$stmt = $pdo->prepare("
    SELECT * FROM consultation_slots 
    WHERE staff_id = :sid 
    AND (consultation_date > CURRENT_DATE() OR (consultation_date = CURRENT_DATE() AND start_time >= CURRENT_TIME()))
    ORDER BY consultation_date ASC, start_time ASC
");
$stmt->execute([':sid' => $staffId]);
$upcomingSlots = $stmt->fetchAll();

$page_title = 'Manage Availability';
$active_nav = 'availability';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold text-navy mb-1"><i class="bi bi-calendar-check text-gold me-2"></i>Availability Management</h3>
        <p class="text-muted mb-0">Monitor your dynamic availability status and generate recurring consultation slots</p>
    </div>
    <a href="<?= url('staff/manage_slots.php') ?>" class="btn btn-navy rounded-pill px-4">
        <i class="bi bi-plus-circle me-1"></i> Add Single Slot
    </a>
</div>

<!-- Dynamic Availability Indicator Status Card -->
<div class="card card-modern mb-4">
    <div class="card-body p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-md-8">
                <small class="text-uppercase fw-bold text-muted letter-spacing-1">Current Public Directory Status</small>
                <div class="d-flex align-items-center gap-3 my-2">
                    <span class="badge <?= $avail['class'] ?> fs-5 px-3 py-2">
                        <i class="bi <?= $avail['icon'] ?> me-2"></i><?= $avail['status'] ?>
                    </span>
                    <h4 class="mb-0 fw-bold text-navy"><?= $avail['count'] ?> Open Slot<?= $avail['count'] !== 1 ? 's' : '' ?></h4>
                </div>
                <p class="text-muted small mb-0">
                    The university directory calculates this indicator dynamically based on open future slots.
                    Students see your status as:
                    <strong class="text-dark"><?= $avail['status'] ?></strong> (<?= $avail['desc'] ?>).
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="p-3 bg-light rounded-3 text-start small border">
                    <div class="fw-bold text-navy mb-1"><i class="bi bi-info-circle text-gold me-1"></i> Availability Rules:</div>
                    <div>&bull; <strong class="text-success">Available:</strong> &gt; 2 open slots</div>
                    <div>&bull; <strong class="text-warning">Limited Slots:</strong> 1 or 2 slots</div>
                    <div>&bull; <strong class="text-secondary">Fully Booked:</strong> 0 open slots</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Quick Batch Slot Generator Form -->
    <div class="col-lg-5">
        <div class="card card-modern h-100">
            <div class="card-header bg-navy text-white py-3 px-4 rounded-top-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-lightning-charge text-gold me-1"></i> Quick Slot Generator</h5>
                <small class="text-white-50">Generate a series of consultation slots in one click</small>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($batchErrors['general'])): ?>
                    <div class="alert alert-danger small mb-3"><?= e($batchErrors['general']) ?></div>
                <?php endif; ?>

                <form action="<?= url('staff/availability.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="generate_batch_slots">

                    <div class="mb-3">
                        <label for="batch_date" class="form-label fw-semibold small text-muted">Consultation Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control min-today <?= isset($batchErrors['date']) ? 'is-invalid' : '' ?>" id="batch_date" name="batch_date" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                        <?php if (isset($batchErrors['date'])): ?>
                            <div class="invalid-feedback"><?= e($batchErrors['date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="batch_start_time" class="form-label fw-semibold small text-muted">First Slot Start <span class="text-danger">*</span></label>
                            <input type="time" class="form-control <?= isset($batchErrors['start_time']) ? 'is-invalid' : '' ?>" id="batch_start_time" name="batch_start_time" value="09:00" required>
                        </div>
                        <div class="col-6">
                            <label for="batch_count" class="form-label fw-semibold small text-muted">Number of Slots</label>
                            <select class="form-select" id="batch_count" name="batch_count">
                                <option value="2">2 Slots</option>
                                <option value="3" selected>3 Slots</option>
                                <option value="4">4 Slots</option>
                                <option value="5">5 Slots</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <label for="batch_duration" class="form-label fw-semibold small text-muted">Slot Duration</label>
                            <select class="form-select" id="batch_duration" name="batch_duration">
                                <option value="30">30 Minutes</option>
                                <option value="45" selected>45 Minutes</option>
                                <option value="60">60 Minutes</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="batch_break" class="form-label fw-semibold small text-muted">Break Between</label>
                            <select class="form-select" id="batch_break" name="batch_break">
                                <option value="0">0 mins</option>
                                <option value="5" selected>5 mins</option>
                                <option value="10">10 mins</option>
                                <option value="15">15 mins</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-navy w-100 rounded-pill py-2 fw-semibold">
                        <i class="bi bi-magic me-1"></i> Generate Consultation Slots
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Upcoming Schedule Preview -->
    <div class="col-lg-7">
        <div class="card card-modern h-100">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="fw-bold text-navy mb-0">Upcoming Schedule Snapshot</h5>
                    <small class="text-muted">Total: <?= count($upcomingSlots) ?> future slots</small>
                </div>
                <a href="<?= url('staff/manage_slots.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
                    Manage All Slots
                </a>
            </div>
            <div class="card-body p-4">
                <?php if (empty($upcomingSlots)): ?>
                    <div class="empty-state p-4">
                        <div class="empty-state-icon"><i class="bi bi-calendar-x"></i></div>
                        <h6 class="empty-state-title">No Slots Published Yet</h6>
                        <p class="empty-state-text">Use the generator on the left to quickly schedule your consultation sessions.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($upcomingSlots as $s): 
                            $isAvail = ($s['status'] === 'available');
                        ?>
                            <div class="col-sm-6">
                                <div class="p-3 border rounded-3 <?= $isAvail ? 'bg-light' : 'bg-primary-subtle border-primary-subtle' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="fw-bold text-navy"><?= format_date($s['consultation_date']) ?></small>
                                        <span class="badge <?= $isAvail ? 'bg-success' : 'bg-primary' ?> px-2 py-1" style="font-size: 0.7rem;">
                                            <?= ucfirst($s['status']) ?>
                                        </span>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="bi bi-clock me-1"></i><?= format_time($s['start_time']) ?> &ndash; <?= format_time($s['end_time']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
