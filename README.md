# 🎓 College Faculty Daily Class & Workload Management System (EduLog)

A production-ready, AI-powered web application built with **HTML5, CSS3, Vanilla JavaScript, PHP (PDO), and MySQL / SQLite High-Availability Database** to eliminate manual paper logging sheets and automate college faculty workload calculations.

---

## 🌟 Key Features & Architecture

### 1. 👨‍🏫 Faculty Portal (`faculty-dashboard.php`)
- **Secure Authentication & Auto-Identity**: Faculty name and employee ID are automatically populated from the logged-in session.
- **Dynamic Daily Class Sheet**: 
  - Switch between **BCA** and **BCOM** departments.
  - Switch between **1st Year**, **2nd Year**, and **3rd Year** (Semesters 1 to 6).
  - Pick any calendar date (defaults to today).
  - Interactive grid: Click any session slot (Session 1 to 5) to instantly log Subject Name, Topic Covered, and Class Nature.
- **Combined / Merged Class Engine**:
  - Allows combining two sections (e.g., Section A + Section B) under a single faculty.
  - Updates both sections in the master college daily sheet.
  - **Workload Rule Strictly Enforced**: Counts as exactly **1.0 hour** in the faculty's total workload calculation.
- **Personal Workload Intelligence**:
  - Live metric cards: **Today's Hours**, **Weekly Hours**, and **Monthly Total Workload**.
  - Custom date range workload calculator.
  - Interactive history log of all past sessions.

---

### 2. 🛡️ Admin Command Center (`admin-dashboard.php`)
- **Master Daily Class Matrix**:
  - Live full-college class matrix showing all 24 sections, session slots, assigned faculties, and topics covered.
- **Section Audit Drilldown (e.g., "5B1" Query Engine)**:
  - Answers the prompt requirement: *"Who all took classes for Section 5B1 on date X or any date?"*
  - Instant search across section codes (5B1, BCA-1A, etc.) with date filters.
- **Monthly Faculty Workload Calculator**:
  - Aggregates total hours, theory hours, lab sessions, and merged class hours across all faculty members.
  - Ready for 1-click Print / PDF / CSV reporting.
- **Daily Timetable / Schedule Designer & Publisher**:
  - Design tomorrow's or future schedules in advance.
  - Pre-allocate faculty, expected subjects, lab slots, or merged classes.
- **Special Student Sessions & No-Class Day Announcements**:
  - Publish seminars, workshops, assessment days, or official holidays.
- **Semester Rotation Manager (Every 6 Months)**:
  - Create and edit sections, semester numbers (1 to 6), and room assignments.
- **📸 Shareable WhatsApp / Telegram Daily Report Image Generator**:
  - Renders a high-resolution `.PNG` image of the daily table with College Header, Date, Department, and full verified class table ready to post directly to college groups.

---

## 🚀 Default Credentials

| Portal | Role | Email | Password |
| :--- | :--- | :--- | :--- |
| **Admin Control** | Dean / Administrator | `malli123@gmail.com` | `12345` |
| **BCA Faculty** | Associate Professor (CS) | `ananya.bca@college.edu` | `Faculty@123` |
| **BCA Faculty** | Assistant Professor (DBMS) | `vikram.bca@college.edu` | `Faculty@123` |
| **BCOM Faculty** | HOD Commerce | `suresh.bcom@college.edu` | `Faculty@123` |
| **BCOM Faculty** | Assistant Professor (Taxation) | `priya.bcom@college.edu` | `Faculty@123` |

---

## 🛠️ Local Installation & Running

### Option A: Using Built-in PHP Server (Fastest)
1. Open PowerShell or Terminal in the project folder:
   ```bash
   php -S localhost:8000
   ```
2. Open your browser and navigate to:
   ```
   http://localhost:8000
   ```

### Option B: Using XAMPP / WAMP / Live Hosting (Apache + MySQL)
1. Place the project folder in `C:/xampp/htdocs/faculty-workload`.
2. Start Apache and MySQL from XAMPP Control Panel.
3. Visit `http://localhost/faculty-workload/install.php` to initialize the database with 1-click.
4. Log in and start managing your college faculty records!

---

## 📁 Project Structure

```
├── config/
│   ├── database.php          # High-availability PDO connection (MySQL + SQLite Auto-Fallback)
│   └── auth.php              # Session management, role guards & CSRF protection
├── includes/
│   ├── functions.php         # Core business logic, workload calculator & matrix queries
│   ├── header.php            # Navigation bar, user profile chip & switcher
│   └── footer.php            # Scripts & Toast notification container
├── api/
│   ├── auth.php              # Login, logout & session checks
│   ├── faculty.php           # Dynamic table data, class log submissions, personal workload
│   └── admin.php             # Master matrix, 5B1 search, workload reports, scheduler
├── assets/
│   ├── css/style.css         # Modern glassmorphism & responsive design system
│   └── js/
│       ├── app.js            # Toast notifications & modal handlers
│       ├── faculty.js        # Dynamic class logger & personal analytics
│       └── admin.js          # Master matrix, 5B1 query & WhatsApp image exporter
├── database/
│   └── schema.sql            # MySQL schema & initial seed data
├── index.php                 # Login gateway with 1-Click test login buttons
├── faculty-dashboard.php     # Faculty daily class logger & workload dashboard
├── admin-dashboard.php       # Admin master control center & report generator
└── install.php               # 1-Click Database Setup & Reset Initializer
```
