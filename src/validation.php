<?php
/**
 * Kabarak University - Student Consultation Booking System
 * Input Validation and Sanitization Service
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Validate Student Registration form data
 */
function validate_registration(array $data): array
{
    $errors = [];
    $pdo = get_db_connection();

    $fullName = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $passwordConfirm = $data['password_confirm'] ?? '';
    $course = trim($data['course'] ?? '');
    $department = trim($data['department'] ?? '');

    // Full name
    if (empty($fullName)) {
        $errors['full_name'] = 'Full name is required.';
    } elseif (strlen($fullName) < 3 || strlen($fullName) > 150) {
        $errors['full_name'] = 'Full name must be between 3 and 150 characters.';
    }

    // Email
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email address is already registered in the system.';
        }
    }

    // Password
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    // Course
    if (empty($course)) {
        $errors['course'] = 'Please specify your degree program or course.';
    }

    return $errors;
}

/**
 * Validate Consultation Slot creation
 */
function validate_slot(int $staffId, string $date, string $startTime, string $endTime, ?int $ignoreSlotId = null): array
{
    $errors = [];
    $today = date('Y-m-d');

    if (empty($date)) {
        $errors['consultation_date'] = 'Consultation date is required.';
    } elseif ($date < $today) {
        $errors['consultation_date'] = 'Consultation date cannot be in the past.';
    }

    if (empty($startTime)) {
        $errors['start_time'] = 'Start time is required.';
    }

    if (empty($endTime)) {
        $errors['end_time'] = 'End time is required.';
    }

    if (!empty($startTime) && !empty($endTime)) {
        $startSeconds = strtotime($startTime);
        $endSeconds = strtotime($endTime);

        if ($endSeconds <= $startSeconds) {
            $errors['end_time'] = 'End time must be later than the start time.';
        } elseif (($endSeconds - $startSeconds) < 600) {
            $errors['end_time'] = 'Slot duration must be at least 10 minutes.';
        }
    }

    // Check for overlapping slots for the same staff member
    if (empty($errors)) {
        $pdo = get_db_connection();
        $sql = "SELECT id, start_time, end_time FROM consultation_slots 
                WHERE staff_id = :staff_id 
                AND consultation_date = :cdate 
                AND NOT (end_time <= :start_time OR start_time >= :end_time)";
        
        $params = [
            ':staff_id'   => $staffId,
            ':cdate'      => $date,
            ':start_time' => $startTime,
            ':end_time'   => $endTime
        ];

        if ($ignoreSlotId !== null) {
            $sql .= " AND id != :ignore_id";
            $params[':ignore_id'] = $ignoreSlotId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $conflict = $stmt->fetch();

        if ($conflict) {
            $errors['overlap'] = sprintf(
                'This slot conflicts with an existing slot scheduled from %s to %s on %s.',
                substr($conflict['start_time'], 0, 5),
                substr($conflict['end_time'], 0, 5),
                $date
            );
        }
    }

    return $errors;
}

/**
 * Validate Consultation Booking submission
 */
function validate_booking(int $studentId, int $slotId, string $reason): array
{
    $errors = [];
    $cleanReason = trim($reason);

    if (empty($cleanReason)) {
        $errors['consultation_reason'] = 'Please provide a reason or agenda for this consultation.';
    } elseif (strlen($cleanReason) < 10) {
        $errors['consultation_reason'] = 'Please provide a more detailed reason (at least 10 characters).';
    } elseif (strlen($cleanReason) > 1000) {
        $errors['consultation_reason'] = 'Reason must not exceed 1000 characters.';
    }

    $pdo = get_db_connection();

    // Check slot availability and date
    $stmt = $pdo->prepare("SELECT * FROM consultation_slots WHERE id = :slot_id LIMIT 1");
    $stmt->execute([':slot_id' => $slotId]);
    $slot = $stmt->fetch();

    if (!$slot) {
        $errors['slot'] = 'Selected consultation slot does not exist.';
    } elseif ($slot['status'] !== 'available') {
        $errors['slot'] = 'This slot has already been booked by another student.';
    } else {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');
        if ($slot['consultation_date'] < $currentDate || 
           ($slot['consultation_date'] === $currentDate && $slot['start_time'] <= $currentTime)) {
            $errors['slot'] = 'Cannot book a consultation slot in the past.';
        }

        // Check if student has a conflicting booking at the same date and time
        $checkStmt = $pdo->prepare("
            SELECT b.id FROM bookings b
            JOIN consultation_slots s ON b.slot_id = s.id
            WHERE b.student_id = :student_id
            AND b.status IN ('Pending', 'Confirmed')
            AND s.consultation_date = :cdate
            AND NOT (s.end_time <= :start_time OR s.start_time >= :end_time)
            LIMIT 1
        ");
        $checkStmt->execute([
            ':student_id' => $studentId,
            ':cdate'      => $slot['consultation_date'],
            ':start_time' => $slot['start_time'],
            ':end_time'   => $slot['end_time']
        ]);
        if ($checkStmt->fetch()) {
            $errors['conflict'] = 'You already have another active consultation booking that overlaps with this time slot.';
        }
    }

    return $errors;
}
