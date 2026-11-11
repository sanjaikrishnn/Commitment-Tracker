# CommitTrack — Student & Admin Commitment Tracking Portal

A complete, responsive web application built with **PHP 8**, **MySQL**, **HTML5**, **CSS3**, and **Vanilla JavaScript** running locally on **XAMPP (Windows)**.

CommitTrack enables students to join classrooms, track coursework deadlines with high/medium/low priorities, update progress, and request deadline extensions. Instructors/Administrators have full classroom oversight, task assignments, deadline extension approval workflows, real-time academic analytics, and 1-click multi-format report generation (PDF, Excel, CSV, and HTML Print Preview).

---

## 🎨 Visual Reference & Theme

The project interface strictly adheres to the dark-green and cream design system:
- **Primary Brand Color**: Deep Forest Green (`#173f35`)
- **Accent Tints**: Soft Sage & Mint (`#eaf4ef`, `#dcfce7`)
- **Background**: Warm Cream & Crisp White (`#f9faf8`, `#ffffff`)
- **Components**: Rounded cards (`18px`), custom botanical accents, responsive grids, and accessible forms.

---

## 📂 Project Structure

```text
c:\xampp\htdocs\committrack\
├── index.php                 # Landing page with hero section & feature overview
├── signup.php                # User registration (Student self-signup, Admin PIN protected)
├── login.php                 # Authentication with password_verify() and session security
├── logout.php                # Secure session destruction & cookie cleanup
├── dashboard.php             # Protected dashboard (Live dynamic Student & Admin analytics)
├── classrooms.php            # Admin: Create & manage classrooms, generate invite links
├── classroom_detail.php      # Admin: View students, task progress & edit classroom
├── tasks.php                 # Admin: Create, edit, assign (class/student), prioritize & delete tasks
├── students.php              # Admin: View all enrolled students and global progress
├── extensions.php            # Admin: Review, approve, and reject deadline extension requests
├── reports.php               # Admin: One-click report generator (HTML, CSV, Excel, PDF)
├── join.php                  # Student: Join classroom via unique invite link
├── my_classrooms.php         # Student: View joined classrooms & quick-join input
├── my_tasks.php              # Student: View assigned tasks, update status & request extensions
├── my_extensions.php         # Student: View extension requests & instructor decisions
├── request_extension.php     # Student: Extension submission processor
├── db.php                    # PDO database connection & shared auth/CSRF helpers
├── create_admin.php          # Controlled Admin account provisioning tool
├── partials/
│   ├── sidebar.php           # Shared responsive sidebar with extension badge counters
│   └── topbar.php            # Shared topbar with live search & user badge
├── style.css                 # Shared responsive design system & component styles
├── script.js                 # Vanilla JS validation, live search, & interactions
├── database.sql              # Complete MySQL database schema & pre-seeded demo accounts
├── migration.sql             # Migration v2 (classrooms, tasks, assignments)
├── migration_v3.sql          # Migration v3 (extension_requests, custom & original deadlines)
└── README.md                 # Setup, operational instructions & testing guide
```

---

## 🚀 Quick Setup Instructions (XAMPP on Windows)

### Step 1: Start Apache and MySQL in XAMPP
1. Open the **XAMPP Control Panel** (`c:\xampp\xampp-control.exe`).
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both module indicators should turn green.

---

### Step 2: Import the Database via phpMyAdmin
1. Open your web browser and navigate to:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click the **Import** tab located at the top navigation bar.
3. Under **File to import**, click **Choose File** (or **Browse**).
4. Select `C:\xampp\htdocs\committrack\database.sql`.
5. Scroll down and click **Import** (or **Go**).
6. phpMyAdmin creates the `committrack` database and tables (`users`, `classrooms`, `classroom_members`, `tasks`, `task_assignments`, `extension_requests`) with pre-seeded demo accounts.

---

### Step 3: Access the Application
Open your browser and navigate to:
```
http://localhost/committrack/
```

---

## 🔑 Pre-Seeded Demo Credentials

| Role | Email | Password |
|---|---|---|
| **Student** | `student@committrack.com` | `Student@123` |
| **Admin** | `admin@committrack.com` | `Admin@123` |

> **Controlled Admin Creation**:
> Public self-registration on `signup.php` creates Student accounts. To create an Admin account through registration, enter the setup PIN `commit2026`. Alternatively, provision admins via CLI:
> ```bash
> c:\xampp\php\php.exe c:\xampp\htdocs\committrack\create_admin.php
> ```

---

## 🔄 Core Feature Highlights

### 1. Priority-Based Task Management
- **Three Priority Levels**: High, Medium, Low.
- **Smart Urgency Sorting**:
  1. Overdue High-Priority Tasks
  2. Upcoming High-Priority Tasks
  3. Medium-Priority Tasks
  4. Low-Priority Tasks
  *(Nearest deadline first within each category)*.
- Prominent visual highlighting for overdue and urgent tasks on both student and instructor dashboards.

### 2. Deadline Extension Request Workflow
- **Student Submission**: Students can request deadline extensions on pending or in-progress tasks, choosing either **Individual (Just for me)** or **Classroom / Group**.
- **Instructor Review**: Instructors review requests on `extensions.php`, inspect original vs requested deadlines and reasons, and Approve or Reject with feedback.
- **Database Consistency**: Approvals use atomic transactions. Individual approvals set `custom_deadline` for that student only, preserving classroom deadlines. Rejections never alter task deadlines.

### 3. Teacher Analytics Dashboard
- Live MySQL metrics: Total classrooms, enrolled students, total tasks, overall completion rate, in-progress count, pending count, and overdue assignments.
- Classroom-specific progress bars.
- High-priority incomplete tasks watchlist.
- Students needing attention (behind schedule / overdue tasks).

### 4. One-Click Academic Reports
Accessible on `reports.php` for instructors:
1. **Overall Academic Progress Report**
2. **Classroom-Wise Progress Report**
3. **Individual Student Performance Report**
4. **Task Completion & Deadline Report**
5. **Deadline Extension Requests Report**
- **Exports Supported**: HTML Print Preview (`window.print()`), Clean CSV (`.csv` with UTF-8 BOM), Excel (`.xls`), and PDF export.
