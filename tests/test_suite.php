<?php
/**
 * Automated Verification & Unit Test Suite
 * Tests Core Business Logic, Security, and Availability Rules
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/auth.php';

$testsPassed = 0;
$testsFailed = 0;

function assert_test(string $name, bool $condition, string $details = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "[PASS] " . $name . "\n";
        $testsPassed++;
    } else {
        echo "[FAIL] " . $name . ($details ? " - Details: " . $details : "") . "\n";
        $testsFailed++;
    }
}

echo "=======================================================\n";
echo "Running Kabarak Consultation Automated Verification\n";
echo "=======================================================\n\n";

// 1. Password Verification Test
$hash = '$2y$10$vjXDuOyZ38Ld0HTqQ1ajIu68GM2DTik/lsAKxwnU/2Nc239gJdFqO';
assert_test(
    "Password Hashing & Verification (Password123!)",
    password_verify('Password123!', $hash)
);

// 2. Role Formatting Tests
assert_test("Role Format: Student", format_role('student') === 'Student');
assert_test("Role Format: Lecturer", format_role('lecturer') === 'Lecturer');
assert_test("Role Format: Academic Advisor", format_role('academic_advisor') === 'Academic Advisor');
assert_test("Role Format: Department Head", format_role('department_head') === 'Department Head');
assert_test("Role Format: Counselor", format_role('counselor') === 'Counselor');
assert_test("Role Format: University Staff", format_role('university_staff') === 'University Staff');
assert_test("Role Format: Admin", format_role('admin') === 'System Administrator');

// 3. Availability Indicators (Section 10 specification)
// - Available: More than two available consultation slots (>2)
// - Limited Slots: Two or fewer available consultation slots, but at least one remains (1 or 2)
// - Fully Booked: No available consultation slots (0)
$avail3 = get_staff_availability_badge(3);
assert_test("Availability Indicator: 3 slots = Available", $avail3['status'] === 'Available' && $avail3['class'] === 'bg-success');

$avail4 = get_staff_availability_badge(5);
assert_test("Availability Indicator: 5 slots = Available", $avail4['status'] === 'Available');

$avail2 = get_staff_availability_badge(2);
assert_test("Availability Indicator: 2 slots = Limited Slots", $avail2['status'] === 'Limited Slots' && str_contains($avail2['class'], 'bg-warning'));

$avail1 = get_staff_availability_badge(1);
assert_test("Availability Indicator: 1 slot = Limited Slots", $avail1['status'] === 'Limited Slots');

$avail0 = get_staff_availability_badge(0);
assert_test("Availability Indicator: 0 slots = Fully Booked", $avail0['status'] === 'Fully Booked' && $avail0['class'] === 'bg-secondary');

// 4. Booking Status Badges
assert_test("Booking Status Badge: Confirmed", str_contains(get_booking_status_badge('Confirmed'), 'bg-success'));
assert_test("Booking Status Badge: Pending", str_contains(get_booking_status_badge('Pending'), 'bg-warning'));
assert_test("Booking Status Badge: Completed", str_contains(get_booking_status_badge('Completed'), 'bg-primary'));
assert_test("Booking Status Badge: Cancelled", str_contains(get_booking_status_badge('Cancelled'), 'bg-danger'));
assert_test("Booking Status Badge: Rejected", str_contains(get_booking_status_badge('Rejected'), 'bg-secondary'));

// 5. CSRF Token Generation & Verification
$token = csrf_token();
assert_test("CSRF Token Non-empty 64-char Hex", strlen($token) === 64);
assert_test("CSRF Token Verification Success", verify_csrf_token($token) === true);
assert_test("CSRF Token Verification Rejection on Tampered", verify_csrf_token('invalid_token_1234') === false);

// 6. XSS Escaping
$rawXss = "<script>alert('xss');</script>&\"'";
$escaped = e($rawXss);
assert_test("XSS Escaping", !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'));

// 7. Time & Date Formatting
$formattedDate = format_date('2026-11-20', 'd M Y');
assert_test("Date Formatting (2026-11-20 -> 20 Nov 2026)", $formattedDate === '20 Nov 2026');

$formattedTime = format_time('09:30:00');
assert_test("Time Formatting (09:30:00 -> 09:30 AM)", $formattedTime === '09:30 AM');

$formattedTimePM = format_time('14:15:00');
assert_test("Time Formatting (14:15:00 -> 02:15 PM)", $formattedTimePM === '02:15 PM');

// 8. User Initials Generation
assert_test("Avatar Initials: Dr Alice Mwangi -> AM", get_user_avatar_initials('Dr Alice Mwangi') === 'AM');
assert_test("Avatar Initials: Prof Caroline Wafula -> CW", get_user_avatar_initials('Prof Caroline Wafula') === 'CW');
assert_test("Avatar Initials: Kelvin Kipkemboi -> KK", get_user_avatar_initials('Kelvin Kipkemboi') === 'KK');

// 9. Schema and Seed SQL Integrity
$schemaSql = file_get_contents(__DIR__ . '/../database/schema.sql');
assert_test("Schema Contains kabarak_consultation DB", str_contains($schemaSql, 'kabarak_consultation'));
assert_test("Schema Contains users Table", str_contains($schemaSql, 'CREATE TABLE `users`'));
assert_test("Schema Contains departments Table", str_contains($schemaSql, 'CREATE TABLE `departments`'));
assert_test("Schema Contains consultation_slots with staff_id", str_contains($schemaSql, 'CREATE TABLE `consultation_slots`') && str_contains($schemaSql, '`staff_id`'));
assert_test("Schema Contains bookings Table", str_contains($schemaSql, 'CREATE TABLE `bookings`'));
assert_test("Schema Contains notifications Table", str_contains($schemaSql, 'CREATE TABLE `notifications`'));

$seedSql = file_get_contents(__DIR__ . '/../database/seed.sql');
assert_test("Seed Contains Dr Alice Mwangi", str_contains($seedSql, 'Dr Alice Mwangi'));
assert_test("Seed Contains Dr Brian Kiptoo", str_contains($seedSql, 'Dr Brian Kiptoo'));
assert_test("Seed Contains Prof Caroline Wafula", str_contains($seedSql, 'Prof Caroline Wafula'));

echo "\n-------------------------------------------------------\n";
echo "Test Results: {$testsPassed} Passed, {$testsFailed} Failed\n";
echo "-------------------------------------------------------\n";

if ($testsFailed > 0) {
    exit(1);
}
exit(0);
