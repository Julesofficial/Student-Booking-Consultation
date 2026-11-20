-- =======================================================
-- KABARAK UNIVERSITY - STUDENT CONSULTATION BOOKING SYSTEM
-- Seed Data: kabarak_consultation
-- =======================================================

USE `kabarak_consultation`;

-- All users have password: Password123!
-- Bcrypt Hash: $2y$10$vjXDuOyZ38Ld0HTqQ1ajIu68GM2DTik/lsAKxwnU/2Nc239gJdFqO
SET @DEFAULT_PASSWORD = '$2y$10$vjXDuOyZ38Ld0HTqQ1ajIu68GM2DTik/lsAKxwnU/2Nc239gJdFqO';

-- -------------------------------------------------------
-- 1. Seed Departments
-- -------------------------------------------------------
INSERT INTO `departments` (`id`, `department_name`, `description`) VALUES
(1, 'Computer Science & Informatics', 'Department responsible for Computer Science, Software Engineering, and Data Science degree programs.'),
(2, 'Information Technology', 'Department offering Information Technology, Cyber Security, and Cloud Computing curricula.'),
(3, 'Business Administration & Management', 'School of Business covering Accounting, Finance, Marketing, and Project Management.'),
(4, 'Education & Languages', 'Faculty of Education preparing future secondary and tertiary educators and language specialists.'),
(5, 'Health Sciences & Nursing', 'Department training clinical medical, nursing, and public health practitioners.'),
(6, 'Dean of Students & Guidance Counseling', 'Student welfare office offering psychological support, career counseling, and extracurricular life management.'),
(7, 'Admissions & Academic Registry', 'University central registry responsible for admissions, records, transcripts, and graduation clearance.');

-- -------------------------------------------------------
-- 2. Seed Users
-- -------------------------------------------------------
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `course`, `department`, `service`, `description`, `is_active`) VALUES
-- 1. System Administrator
(1, 'System Administrator', 'admin@kabarak.ac.ke', @DEFAULT_PASSWORD, 'admin', NULL, 'Admissions & Academic Registry', 'University System Administration & User Management', 'Main administrative account for university consultation oversight, department governance, and system-wide configurations.', 1),

-- 2-5. Lecturers
(2, 'Dr Alice Mwangi', 'alice.mwangi@kabarak.ac.ke', @DEFAULT_PASSWORD, 'lecturer', NULL, 'Computer Science & Informatics', 'Algorithms, Software Engineering & Project Supervision', 'Senior Lecturer in Computer Science. Research focus on distributed systems, enterprise software engineering, and undergraduate capstone research supervision.', 1),

(3, 'Dr Brian Kiptoo', 'brian.kiptoo@kabarak.ac.ke', @DEFAULT_PASSWORD, 'lecturer', NULL, 'Information Technology', 'Network Security, Cloud Architecture & Database Systems', 'Lecturer in IT and Cyber Security. Available for queries regarding database design, network protocols, server administration, and cybersecurity labs.', 1),

(4, 'Prof Caroline Wafula', 'caroline.wafula@kabarak.ac.ke', @DEFAULT_PASSWORD, 'lecturer', NULL, 'Education & Languages', 'Curriculum Design, Pedagogical Methods & Research Writing', 'Professor of Education and Curriculum Studies. Office consultations available for academic thesis guidance, pedagogical methods, and education practicum support.', 1),

(5, 'Dr David Otieno', 'david.otieno@kabarak.ac.ke', @DEFAULT_PASSWORD, 'lecturer', NULL, 'Computer Science & Informatics', 'Artificial Intelligence & Machine Learning Projects', 'Associate Professor specialized in deep learning, natural language processing, and advanced algorithm design.', 1),

-- 6-7. Academic Advisors
(6, 'Grace Njeri', 'grace.njeri@kabarak.ac.ke', @DEFAULT_PASSWORD, 'academic_advisor', NULL, 'Computer Science & Informatics', 'Course Registration, Degree Audit & Academic Progression', 'Dedicated Academic Advisor for Computing & Informatics students. Available for prerequisite clearances, GPA improvement strategies, and degree track reviews.', 1),

(7, 'Samuel Korir', 'samuel.korir@kabarak.ac.ke', @DEFAULT_PASSWORD, 'academic_advisor', NULL, 'Business Administration & Management', 'Business Major Selection, Credit Transfers & Internship Advice', 'Academic advisor assisting commerce and business information technology students with credit transfers, retake advice, and internship placement timing.', 1),

-- 8-9. Department Heads
(8, 'Prof Peter Cheruiyot', 'prof.cheruiyot@kabarak.ac.ke', @DEFAULT_PASSWORD, 'department_head', NULL, 'Computer Science & Informatics', 'Departmental Appeals, Special Exam Approvals & Faculty Policies', 'Head of Department, Computer Science & Informatics. Consultations regarding student petitions, special examination approvals, and academic misconduct hearings.', 1),

(9, 'Dr Martha Kamau', 'dr.martha.kamau@kabarak.ac.ke', @DEFAULT_PASSWORD, 'department_head', NULL, 'Information Technology', 'IT Department Policies, Industry Projects & Student Grievances', 'Chairperson, Department of Information Technology. Handles departmental transfer requests, curriculum inquiries, and industry partnership project approvals.', 1),

-- 10-11. Counselors
(10, 'Esther Kinyua', 'esther.kinyua@kabarak.ac.ke', @DEFAULT_PASSWORD, 'counselor', NULL, 'Dean of Students & Guidance Counseling', 'Confidential Mental Health, Stress Relief & Personal Counseling', 'Licensed University Counseling Psychologist. Strictly confidential consultations for anxiety, academic pressure, grief, personal growth, and emotional wellbeing.', 1),

(11, 'Paul Rono', 'paul.rono@kabarak.ac.ke', @DEFAULT_PASSWORD, 'counselor', NULL, 'Dean of Students & Guidance Counseling', 'Crisis Support, Substance Awareness & Relationship Mentorship', 'Student counselor dedicated to personal counseling, adjustment to campus life, peer mediation, and stress management.', 1),

-- 12-13. University Staff
(12, 'Mary Chebet', 'mary.chebet@kabarak.ac.ke', @DEFAULT_PASSWORD, 'university_staff', NULL, 'Admissions & Academic Registry', 'Transcripts, Deferment Requests & Student ID Issues', 'Senior Administrative Assistant at Academic Registry. Consultations on official academic transcripts, course deferrals, inter-school transfers, and certificate collection.', 1),

(13, 'John Mutua', 'john.mutua@kabarak.ac.ke', @DEFAULT_PASSWORD, 'university_staff', NULL, 'Admissions & Academic Registry', 'HELB Clearance, Bursary Verification & Fee Payment Plans', 'Financial Aid Officer. Consultations regarding government loan disbursements, university bursary eligibility, and fee installment approvals.', 1),

-- 14-17. Students
(14, 'Kelvin Kipkemboi', 'student.kelvin@kabarak.ac.ke', @DEFAULT_PASSWORD, 'student', 'BSc Computer Science', 'Computer Science & Informatics', NULL, 'Third year Computer Science student specializing in Web Architecture and Database Engineering.', 1),

(15, 'Faith Wanjiku', 'student.faith@kabarak.ac.ke', @DEFAULT_PASSWORD, 'student', 'BSc Information Technology', 'Information Technology', NULL, 'Second year IT student interested in Cybersecurity and Network Administration.', 1),

(16, 'Brian Omondi', 'student.brian@kabarak.ac.ke', @DEFAULT_PASSWORD, 'student', 'Bachelor of Business Information Technology', 'Business Administration & Management', NULL, 'Fourth year BBIT student working on final year enterprise information system project.', 1),

(17, 'Joyce Chepkemoi', 'student.joyce@kabarak.ac.ke', @DEFAULT_PASSWORD, 'student', 'BSc Computer Science', 'Computer Science & Informatics', NULL, 'First year student seeking academic mentoring and study plan guidance.', 1);

-- -------------------------------------------------------
-- 3. Seed Consultation Slots
-- -------------------------------------------------------
-- Dr Alice Mwangi (id: 2): 4 slots total (3 available, 1 booked) => Availability: "Available" (>2)
INSERT INTO `consultation_slots` (`id`, `staff_id`, `consultation_date`, `start_time`, `end_time`, `status`) VALUES
(1, 2, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '09:00:00', '09:45:00', 'booked'),
(2, 2, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '10:00:00', '10:45:00', 'available'),
(3, 2, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '14:00:00', '14:45:00', 'available'),
(4, 2, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '11:00:00', '11:45:00', 'available'),

-- Dr Brian Kiptoo (id: 3): 2 slots total (1 booked, 1 available) => Availability: "Limited Slots" (<=2, >=1)
(5, 3, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '14:00:00', '14:30:00', 'booked'),
(6, 3, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '15:00:00', '15:30:00', 'available'),

-- Prof Caroline Wafula (id: 4): 2 slots total (both booked) => Availability: "Fully Booked" (0 available)
(7, 4, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '08:30:00', '09:15:00', 'booked'),
(8, 4, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '10:00:00', '10:45:00', 'booked'),

-- Dr David Otieno (id: 5): 3 slots available => Availability: "Available"
(9, 5, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '09:00:00', '09:45:00', 'available'),
(10, 5, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '10:00:00', '10:45:00', 'available'),
(11, 5, DATE_ADD(CURRENT_DATE(), INTERVAL 4 DAY), '14:00:00', '14:45:00', 'available'),

-- Grace Njeri (Academic Advisor - id: 6): 3 slots (1 booked, 2 available) => Availability: "Limited Slots"
(12, 6, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '11:00:00', '11:30:00', 'booked'),
(13, 6, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '11:30:00', '12:00:00', 'available'),
(14, 6, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '14:30:00', '15:00:00', 'available'),

-- Samuel Korir (Academic Advisor - id: 7): 3 slots available => "Available"
(15, 7, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '10:00:00', '10:30:00', 'available'),
(16, 7, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '11:00:00', '11:30:00', 'available'),
(17, 7, DATE_ADD(CURRENT_DATE(), INTERVAL 4 DAY), '15:00:00', '15:30:00', 'available'),

-- Prof Peter Cheruiyot (Department Head - id: 8): 2 slots (1 booked, 1 available) => "Limited Slots"
(18, 8, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '14:00:00', '14:30:00', 'booked'),
(19, 8, DATE_ADD(CURRENT_DATE(), INTERVAL 4 DAY), '14:30:00', '15:00:00', 'available'),

-- Dr Martha Kamau (Department Head - id: 9): 3 slots available => "Available"
(20, 9, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '09:30:00', '10:15:00', 'available'),
(21, 9, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '11:00:00', '11:45:00', 'available'),
(22, 9, DATE_ADD(CURRENT_DATE(), INTERVAL 5 DAY), '14:00:00', '14:45:00', 'available'),

-- Esther Kinyua (Counselor - id: 10): 4 slots (1 booked, 3 available) => "Available"
(23, 10, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '10:00:00', '10:50:00', 'booked'),
(24, 10, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '11:00:00', '11:50:00', 'available'),
(25, 10, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '14:00:00', '14:50:00', 'available'),
(26, 10, DATE_ADD(CURRENT_DATE(), INTERVAL 4 DAY), '15:00:00', '15:50:00', 'available'),

-- Paul Rono (Counselor - id: 11): 3 slots available => "Available"
(27, 11, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '09:00:00', '09:50:00', 'available'),
(28, 11, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '13:00:00', '13:50:00', 'available'),
(29, 11, DATE_ADD(CURRENT_DATE(), INTERVAL 4 DAY), '14:00:00', '14:50:00', 'available'),

-- Mary Chebet (University Staff - id: 12): 2 slots (1 booked, 1 available) => "Limited Slots"
(30, 12, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '09:30:00', '10:00:00', 'booked'),
(31, 12, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '10:00:00', '10:30:00', 'available'),

-- John Mutua (University Staff - id: 13): 3 slots available => "Available"
(32, 13, DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), '11:00:00', '11:30:00', 'available'),
(33, 13, DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), '14:00:00', '14:30:00', 'available'),
(34, 13, DATE_ADD(CURRENT_DATE(), INTERVAL 4 DAY), '15:00:00', '15:30:00', 'available'),

-- Past slots for consultation history demonstration
(35, 2, DATE_SUB(CURRENT_DATE(), INTERVAL 5 DAY), '09:00:00', '09:45:00', 'booked'),
(36, 3, DATE_SUB(CURRENT_DATE(), INTERVAL 4 DAY), '14:00:00', '14:30:00', 'booked'),
(37, 6, DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY), '11:00:00', '11:30:00', 'booked');

-- -------------------------------------------------------
-- 4. Seed Bookings
-- -------------------------------------------------------
INSERT INTO `bookings` (`id`, `student_id`, `slot_id`, `consultation_reason`, `status`, `cancellation_reason`, `created_at`) VALUES
-- Upcoming Confirmed Bookings
(1, 14, 1, 'Guidance on BSc Computer Science Final Year Project proposal and methodology selection for microservices distributed tracing.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 15, 5, 'Clarification on network penetration testing lab configuration and Wireshark capture analysis for assignment 2.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 16, 7, 'Requesting advice regarding curriculum prerequisites for business information systems analytics thesis topic.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(4, 17, 8, 'Seeking tutoring guidance on technical communication and academic report writing.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 14, 12, 'Degree audit review before enrolling in fourth-year elective courses and clearing missing unit records.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(6, 15, 18, 'Submission of formal departmental petition for industrial attachment deferment letter approval.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(7, 16, 23, 'Personal consultation regarding managing work-study stress and balancing academic deadlines.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 17, 30, 'Requesting certified interim academic transcripts for master scholarship pre-application.', 'Confirmed', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Completed Past Bookings (for consultation history)
(9, 14, 35, 'Preliminary discussion on choosing research topic in high performance cloud databases.', 'Completed', NULL, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(10, 15, 36, 'Review of network routing lab setup and Cisco packet tracer topology assignment.', 'Completed', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(11, 16, 37, 'Academic progression review for Semester 1 GPA recovery plan.', 'Completed', NULL, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- -------------------------------------------------------
-- 5. Seed Notifications
-- -------------------------------------------------------
INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
-- Student Kelvin Kipkemboi
(1, 14, 'Booking Confirmed', 'Your consultation appointment with Dr Alice Mwangi has been confirmed for tomorrow at 09:00 AM.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 14, 'Consultation Completed', 'Your consultation with Dr Alice Mwangi on cloud databases was marked as completed. Thank you for attending!', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- Student Faith Wanjiku
(3, 15, 'Booking Confirmed', 'Your consultation appointment with Dr Brian Kiptoo has been confirmed.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Lecturer Dr Alice Mwangi
(4, 2, 'New Consultation Booking', 'Student Kelvin Kipkemboi booked a consultation with you regarding Final Year Project methodology.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Lecturer Dr Brian Kiptoo
(5, 3, 'New Consultation Booking', 'Student Faith Wanjiku booked a consultation regarding network penetration testing lab configuration.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Academic Advisor Grace Njeri
(6, 6, 'New Advising Booking', 'Student Kelvin Kipkemboi booked an academic advising consultation for degree audit review.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Counselor Esther Kinyua
(7, 10, 'Confidential Consultation Booked', 'A student has scheduled a confidential counseling session with you.', 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),

-- Admin
(8, 1, 'System Initialized', 'Kabarak University Consultation Booking System initialized with default departments, sample staff, and student accounts.', 0, NOW());
