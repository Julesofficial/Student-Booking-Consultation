# Kabarak University - Student Consultation Booking System

A secure, database-driven web application designed for Kabarak University. It enables students to discover, schedule, and manage consultation appointments with university lecturers, academic advisors, department heads, student counselors, and administrative staff members.

---

## 1. System Overview

Manual consultation booking often leads to scheduling conflicts, missed appointments, and communication breakdowns between students and faculty. The **Kabarak University Student Consultation Booking System** solves this by providing:
- Real-time consultation slot management with overlap prevention.
- Dynamic availability calculation (`Available`, `Limited Slots`, `Fully Booked`).
- Role-based portals tailored for **7 distinct university roles**.
- Comprehensive administrative governance, departmental reporting, and audit capabilities.

---

## 2. Technology Stack & Prerequisites

- **Server Environment**: Apache (via XAMPP)
- **Backend Language**: PHP 8.0+ (Tested on PHP 8.2 with PDO)
- **Database Engine**: MySQL 5.7+ / MariaDB 10.4+ (Managed via phpMyAdmin)
- **Database Name**: `kabarak_consultation`
- **Frontend Framework**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, Bootstrap Icons 1.11
- **Security Standard**: `PASSWORD_BCRYPT` password hashing, PDO prepared statements, CSRF protection, and role-based access control (RBAC).

---

## 3. Supported User Roles

The system natively supports 7 user roles with dedicated interfaces:

| Role | Portal / Access | Key Capabilities |
| :--- | :--- | :--- |
| **Student** | Student Portal | Register, discover staff by department/role, book available slots, cancel appointments, view history & profile. |
| **Lecturer** | Staff Portal | Set consultation availability, create/edit/delete slots, review student reasons, complete sessions, cancel with reason. |
| **Academic Advisor** | Staff Portal | Tailored advising portal, degree audit consultations, progression reviews, credit transfer guidance. |
| **Department Head** | Staff Portal | Departmental appeals, student petition reviews, special examination consultation workflows. |
| **Counselor** | Staff Portal | Confidential student mental health counseling, stress relief consultations, private appointment records. |
| **University Staff** | Staff Portal | Academic registry transcripts, deferment requests, financial aid / HELB clearance queries. |
| **Administrator** | Admin Portal | System-wide statistics, user activation/deactivation, staff provisioning, department CRUD, full booking audit, reports. |

---

## 4. Default Demonstration Accounts

All demo accounts use the standard password: `Password123!`

| Role | Full Name | University Email | Password |
| :--- | :--- | :--- | :--- |
| **Administrator** | System Administrator | `admin@kabarak.ac.ke` | `Password123!` |
| **Lecturer** | Dr Alice Mwangi | `alice.mwangi@kabarak.ac.ke` | `Password123!` |
| **Lecturer** | Dr Brian Kiptoo | `brian.kiptoo@kabarak.ac.ke` | `Password123!` |
| **Lecturer** | Prof Caroline Wafula | `caroline.wafula@kabarak.ac.ke` | `Password123!` |
| **Academic Advisor** | Grace Njeri | `grace.njeri@kabarak.ac.ke` | `Password123!` |
| **Department Head** | Prof Peter Cheruiyot | `prof.cheruiyot@kabarak.ac.ke` | `Password123!` |
| **Counselor** | Esther Kinyua | `esther.kinyua@kabarak.ac.ke` | `Password123!` |
| **University Staff** | Mary Chebet | `mary.chebet@kabarak.ac.ke` | `Password123!` |
| **Student** | Kelvin Kipkemboi | `student.kelvin@kabarak.ac.ke` | `Password123!` |

*(Note: The login page includes a "Quick Demo Accounts" panel with one-click credential autofill for immediate evaluation).*

---

## 5. Local Installation & Setup Instructions (XAMPP)

### Step 1: Clone or Place Project in XAMPP `htdocs`
Place this project inside your XAMPP web root directory:
```bash
# Windows default path:
C:\xampp\htdocs\Student-Booking-Consultation
```

### Step 2: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 3: Import Database via phpMyAdmin
1. Open your web browser and navigate to `http://localhost/phpmyadmin/`.
2. Click **Import** in the top navigation bar.
3. Choose the file `database/schema.sql` located inside the project repository and click **Go** (this creates the `kabarak_consultation` database and core tables).
4. Next, import `database/seed.sql` to populate sample departments, staff, students, consultation slots, bookings, and notifications.

*(Alternative via MySQL CLI)*:
```bash
mysql -u root -p < database/schema.sql
mysql -u root -p kabarak_consultation < database/seed.sql
```

### Step 4: Configure Application Settings
Copy `src/config.example.php` to `src/config.php` (if not already present):
```bash
cp src/config.example.php src/config.php
```
Ensure your database credentials in `src/config.php` match your local environment:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'kabarak_consultation');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty
```

### Step 5: Launch the Application
Open your web browser and navigate to:
```
http://localhost/Student-Booking-Consultation/
```
Or directly:
```
http://localhost/Student-Booking-Consultation/public/
```

---

## 6. Directory Structure

```
Student-Booking-Consultation/
│
├── admin/                         # Administrator Portal
│   ├── dashboard.php              # System statistics & overview
│   ├── users.php                  # User governance & activation/deactivation
│   ├── staff.php                  # Faculty provisioning & roster
│   ├── students.php               # Student account records
│   ├── departments.php            # Academic departments CRUD
│   ├── bookings.php               # System-wide booking audit
│   ├── reports.php                # Institutional analytics & print export
│   └── notifications.php          # Admin system notices
│
├── student/                       # Student Portal
│   ├── dashboard.php              # Student overview & stats
│   ├── staff.php                  # Staff discovery directory & search
│   ├── lecturers.php              # Dedicated lecturer shortcut
│   ├── staff_profile.php          # Staff profile & available slots
│   ├── book_consultation.php      # Transaction-safe booking workflow
│   ├── my_bookings.php            # Active bookings & cancellation
│   ├── consultation_history.php   # Past consultation records
│   ├── notifications.php          # In-app alerts & updates
│   └── profile.php                # Personal details & password change
│
├── staff/                         # Faculty & Staff Portal
│   ├── dashboard.php              # Role-adapted dashboard
│   ├── profile.php                # Service focus, bio & office hours
│   ├── availability.php           # Availability status & batch generator
│   ├── manage_slots.php           # Slot CRUD & overlap validation
│   ├── bookings.php               # Student bookings & status workflows
│   ├── consultation_history.php   # Concluded consultation records
│   └── notifications.php          # Appointment updates & alerts
│
├── public/                        # Public Gateway & Static Assets
│   ├── index.php                  # Public landing & hero page
│   ├── login.php                  # Authentication with demo quick-fill
│   ├── register.php               # Student registration form
│   ├── logout.php                 # Secure session destruction
│   └── assets/
│       ├── css/style.css          # University color palette & layout
│       └── js/main.js             # Live search, timers, validations
│
├── includes/                      # Reusable Layout Components
│   ├── header.php                 # HTML5 head, CDN links, alerts
│   ├── navbar.php                 # Topbar with profile & notification bell
│   ├── sidebar.php                # Dynamic role-based navigation sidebar
│   └── footer.php                 # Scripts, copyright, layout closure
│
├── src/                           # Backend Business Logic & Libraries
│   ├── config.php                 # Environment & session configuration
│   ├── config.example.php         # Clean configuration template
│   ├── database.php               # PDO singleton database connection
│   ├── auth.php                   # Authentication & RBAC middleware
│   ├── functions.php              # Formatting, XSS escaping, badges, URLs
│   ├── validation.php             # Form validation & conflict checking
│   └── notifications.php          # Notification dispatching engine
│
├── database/                      # Database Scripts
│   ├── schema.sql                 # Normalized DDL schema
│   └── seed.sql                   # Realistic Kenyan/Kabarak sample data
│
├── tests/                         # Automated Verification
│   └── test_suite.php             # Automated unit & integration tests
│
├── index.php                      # Root dispatcher
├── README.md                      # Documentation
└── .gitignore                     # Git exclusion rules
```

---

## 7. Availability Calculation Algorithm

Staff members' public availability indicators are computed dynamically from open upcoming slots:

$$\text{Available Slots} = \sum [\text{status} = \text{'available'} \land \text{date} \ge \text{today}]$$

- **Available** (`> 2` open slots): Green badge. Indicates healthy scheduling capacity.
- **Limited Slots** (`1` or `2` open slots): Amber/Warning badge. Alerts students to book promptly.
- **Fully Booked** (`0` open slots): Gray badge. Disables new booking requests until additional slots are published.

---
