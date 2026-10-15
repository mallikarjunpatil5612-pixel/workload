<?php
/**
 * Admin Central Command Dashboard
 * Master Monitoring, Timetable Designer, 5B1 Search, Workload Calculator & Image Generator
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();
$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Command Center - Faculty Workload & Class Tracking</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

  <?php include __DIR__ . '/includes/header.php'; ?>

  <main class="main-wrapper">

    <!-- Top Admin Overview & Live Metrics -->
    <div class="glass-panel mb-4">
      <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3">
        <div>
          <h2>Admin Command Center 🛡️</h2>
          <p class="text-muted text-sm">College Daily Class Operations, Faculty Workload Intelligence & Reports</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-primary btn-sm" onclick="openModal('planner-modal')">
            <span>📅</span> Design Tomorrow's Schedule
          </button>
          <button class="btn btn-secondary btn-sm" onclick="openModal('special-event-modal')">
            <span>📢</span> Announce Student Event / No Class
          </button>
        </div>
      </div>

      <!-- Quick Metrics Grid -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-icon icon-purple">👥</div>
          <div class="metric-info">
            <h3 id="stat-total-faculty">0</h3>
            <p>Active Faculty Members</p>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon icon-blue">📖</div>
          <div class="metric-info">
            <h3 id="stat-today-classes">0</h3>
            <p>Classes Logged Today</p>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon icon-green">💻</div>
          <div class="metric-info">
            <h3 id="stat-bca-today">0</h3>
            <p>BCA Sessions Today</p>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon icon-amber">📊</div>
          <div class="metric-info">
            <h3 id="stat-bcom-today">0</h3>
            <p>BCOM Sessions Today</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Tabs for Admin Modules -->
    <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3">
      <div class="tab-pills">
        <button class="tab-pill active" onclick="switchAdminTab('tab-matrix', this)">📋 Master Daily Class Sheet</button>
        <button class="tab-pill" onclick="switchAdminTab('tab-teacher-attendance', this)">👨‍🏫 Teacher Attendance & Daily Workload</button>
        <button class="tab-pill" onclick="switchAdminTab('tab-workload', this)">📈 Workload Reports</button>
        <button class="tab-pill" onclick="switchAdminTab('tab-faculty', this)">👥 Faculty Directory</button>
        <button class="tab-pill" onclick="switchAdminTab('tab-sections', this)">🏫 Sections & Semesters</button>
        <button class="tab-pill" onclick="switchAdminTab('tab-time-slots', this)">⏰ Session Time Slots</button>
      </div>

      <button id="btn-export-pdf" class="btn btn-danger btn-sm" onclick="openExportPdfModal()">
        <span>📄</span> Download / Print PDF Report
      </button>
    </div>

    <!-- TAB 1: Master Daily Class Sheet Matrix -->
    <section id="tab-matrix" class="admin-tab-content">
      <div class="glass-panel">
        <div class="glass-header">
          <div class="glass-title">
            <span>📅</span>
            <span>Master Daily Class Matrix</span>
          </div>

          <!-- Controls & Assign Action Buttons -->
          <div class="d-flex align-center gap-2 flex-wrap">

            <div class="d-flex align-center gap-1">
              <label class="filter-label" for="admin-dept-filter">Dept:</label>
              <select id="admin-dept-filter" class="form-select">
                <option value="ALL">All Departments</option>
                <option value="BCA">BCA Only</option>
                <option value="BCOM">BCOM Only</option>
              </select>
            </div>

            <div class="d-flex align-center gap-1">
              <label class="filter-label" for="admin-year-filter">Year:</label>
              <select id="admin-year-filter" class="form-select">
                <option value="0">All Years</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
              </select>
            </div>

            <div class="d-flex align-center gap-1">
              <label class="filter-label" for="admin-matrix-date">Date:</label>
              <input type="date" id="admin-matrix-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
        </div>

        <div id="matrix-table-container"></div>
      </div>
    </section>

    <!-- TAB: Teacher Attendance & Daily Workload -->
    <section id="tab-teacher-attendance" class="admin-tab-content" style="display: none;">
      <div class="glass-panel">
        <div class="glass-header">
          <div>
            <div class="glass-title">
              <span>👨‍🏫</span>
              <span>Daily Teacher Attendance & Live Workload Tracker</span>
            </div>
            <p class="text-muted text-sm mt-1">
              Real-time monitoring of which teachers have attended/logged sessions today, total daily hours credited, and individual class breakdown.
            </p>
          </div>

          <div class="filter-group">
            <div class="d-flex align-center gap-2">
              <label class="filter-label" for="attendance-dept-filter">Dept:</label>
              <select id="attendance-dept-filter" class="form-select">
                <option value="ALL">All Departments</option>
                <option value="BCA">BCA Only</option>
                <option value="BCOM">BCOM Only</option>
              </select>
            </div>

            <div class="d-flex align-center gap-2">
              <label class="filter-label" for="attendance-date">Date:</label>
              <input type="date" id="attendance-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="setAttendanceToday()">Today</button>
            <button type="button" id="btn-refresh-attendance" class="btn btn-primary btn-sm">🔄 Refresh</button>
          </div>
        </div>

        <!-- Attendance Quick Metrics -->
        <div class="metrics-grid mb-4">
          <div class="metric-card">
            <div class="metric-icon icon-purple">👥</div>
            <div class="metric-info">
              <h3 id="att-stat-total">0</h3>
              <p>Total Teachers</p>
            </div>
          </div>

          <div class="metric-card">
            <div class="metric-icon icon-green">✅</div>
            <div class="metric-info">
              <h3 id="att-stat-present">0</h3>
              <p>Attended / Logged Today</p>
            </div>
          </div>

          <div class="metric-card">
            <div class="metric-icon icon-amber">⏳</div>
            <div class="metric-info">
              <h3 id="att-stat-pending">0</h3>
              <p>Pending / No Class Yet</p>
            </div>
          </div>

          <div class="metric-card">
            <div class="metric-icon icon-blue">⏱️</div>
            <div class="metric-info">
              <h3 id="att-stat-hours">0.0</h3>
              <p>Total Daily Workload Hours</p>
            </div>
          </div>
        </div>

        <!-- Attendance & Daily Workload Table -->
        <div class="table-responsive">
          <table class="matrix-table">
            <thead>
              <tr>
                <th>Faculty Member</th>
                <th>Department</th>
                <th>Daily Attendance Status</th>
                <th>Sessions Logged</th>
                <th>Today's Total Workload</th>
                <th>Classes & Topics Covered Today</th>
              </tr>
            </thead>
            <tbody id="attendance-table-body">
              <tr>
                <td colspan="6" class="text-center text-muted p-4">Loading teacher attendance and daily workload...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- TAB 3: Faculty Workload Calculator & Report (Daily & Monthly) -->
    <section id="tab-workload" class="admin-tab-content" style="display: none;">
      <div class="glass-panel">
        <div class="glass-header">
          <div>
            <div class="glass-title">
              <span>📈</span>
              <span>Faculty Workload Calculation & Analytics</span>
            </div>
            <p class="text-muted text-sm mt-1">
              Aggregated daily and monthly workload calculation across all faculty members. <em>Rule enforced: Combined/Merged classes are counted as exactly 1.0 hour.</em>
            </p>
          </div>

          <div class="filter-group">
            <div class="d-flex align-center gap-2">
              <button type="button" class="btn btn-secondary btn-sm" onclick="setWorkloadPreset('today')">Today</button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="setWorkloadPreset('week')">This Week</button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="setWorkloadPreset('month')">This Month</button>
            </div>

            <select id="report-dept-filter" class="form-select">
              <option value="ALL">All Departments</option>
              <option value="BCA">BCA Only</option>
              <option value="BCOM">BCOM Only</option>
            </select>

            <input type="date" id="report-start-date" class="form-control" value="<?php echo date('Y-m-01'); ?>">
            <span>to</span>
            <input type="date" id="report-end-date" class="form-control" value="<?php echo date('Y-m-t'); ?>">

            <button type="button" id="btn-load-workload" class="btn btn-primary btn-sm">Calculate Workloads</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">🖨️ Print Report</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="matrix-table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Faculty Name & Emp ID</th>
                <th>Department</th>
                <th>Theory Hours</th>
                <th>Lab Hours</th>
                <th>Combined Classes</th>
                <th>Total Workload Hours</th>
              </tr>
            </thead>
            <tbody id="workload-report-body">
              <tr>
                <td colspan="7" class="text-center text-muted p-4">Loading workload data...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- TAB 4: Faculty Management -->
    <section id="tab-faculty" class="admin-tab-content" style="display: none;">
      <div class="glass-panel">
        <div class="glass-header">
          <div class="glass-title">
            <span>👨‍🏫</span>
            <span>Faculty Members Directory</span>
          </div>

          <button class="btn btn-primary btn-sm" onclick="openCreateFacultyModal()">
            <span>➕</span> Add New Faculty Member
          </button>
        </div>

        <div class="table-responsive">
          <table class="matrix-table">
            <thead>
              <tr>
                <th>Employee ID</th>
                <th>Faculty Name</th>
                <th>Email Address</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="faculty-table-body"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- TAB 5: Sections & Semester (Every 6 months rotation) -->
    <section id="tab-sections" class="admin-tab-content" style="display: none;">
      <div class="glass-panel">
        <div class="glass-header">
          <div>
            <div class="glass-title">
              <span>🏫</span>
              <span>Sections & Semester Management (6-Month Rotation)</span>
            </div>
            <p class="text-muted text-sm mt-1">
              Configure or rename sections (e.g. BCA 5B1, 5B2, BCOM Sections A-D) and semesters (1 to 6).
            </p>
          </div>

          <button class="btn btn-primary btn-sm" onclick="openCreateSectionModal()">
            <span>➕</span> Add New Section
          </button>
        </div>

        <div class="table-responsive">
          <table class="matrix-table">
            <thead>
              <tr>
                <th>Section Name</th>
                <th>Department</th>
                <th>Academic Year</th>
                <th>Semester</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="sections-table-body"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- TAB 6: Session & Time Slot Management -->
    <section id="tab-time-slots" class="admin-tab-content" style="display: none;">
      <div class="glass-panel">
        <div class="glass-header">
          <div>
            <div class="glass-title">
              <span>⏰</span>
              <span>Daily Session Time Slots & Schedule Periods</span>
            </div>
            <p class="text-muted text-sm mt-1">
              Configure session labels and period timings per Section or Department. Any changes update the Teacher Portal and Master Daily Matrix in real-time.
            </p>
          </div>

          <div class="d-flex gap-2 align-center flex-wrap">
            <div style="display: flex; align-items: center; gap: 8px; background: white; padding: 6px 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
              <span style="font-size: 13px; font-weight: 700; color: #1e293b;">🎓 Select Academic Year:</span>
              <select id="slots-year-filter" class="form-select font-bold" onchange="loadTimeSlotsData()" style="border: none; padding: 4px 8px; font-size: 13px; min-width: 270px;">
                <option value="">🌐 All Years (College Default)</option>
                <option value="1_1">🎓 BCA - 1st Year (All 1st Yr Sections)</option>
                <option value="1_2">🎓 BCA - 2nd Year (All 2nd Yr Sections)</option>
                <option value="1_3">🎓 BCA - 3rd Year (All 3rd Yr Sections: 5B1, 5B2...)</option>
                <option value="2_1">🎓 BCOM - 1st Year (All 1st Yr Sections)</option>
                <option value="2_2">🎓 BCOM - 2nd Year (All 2nd Yr Sections)</option>
                <option value="2_3">🎓 BCOM - 3rd Year (All 3rd Yr Sections)</option>
              </select>
            </div>

            <button class="btn btn-primary btn-sm glow-btn" onclick="openCreateTimeSlotModal(false)">
              <span>➕</span> Add Session Slot
            </button>
            <button class="btn btn-warning btn-sm" style="background: #f59e0b; color: white; border: none; font-weight: 700; box-shadow: 0 2px 6px rgba(245,158,11,0.3);" onclick="openCreateTimeSlotModal(true)">
              <span>⏸️</span> Add Break
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="matrix-table">
            <thead>
              <tr>
                <th>Slot #</th>
                <th>Target Academic Year</th>
                <th>Session Label / Name</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="time-slots-table-body">
              <tr>
                <td colspan="8" class="text-center text-muted p-4">Loading session time slots...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>

  <!-- MODAL: Timetable / Daily Schedule Designer (Tomorrow's plan) -->
  <div id="planner-modal" class="modal-backdrop">
    <div class="modal-dialog animate-slideDown">
      <div class="modal-header">
        <h3>Design Daily Schedule / Tomorrow's Plan</h3>
        <button type="button" class="modal-close" onclick="closeModal('planner-modal')">&times;</button>
      </div>

      <form id="schedule-slot-form">
        <div class="modal-body">
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label" for="planner-schedule-date">Target Date *</label>
              <input type="date" id="planner-schedule-date" name="schedule_date" class="form-control w-100" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="planner-session-number">Session Slot *</label>
              <select id="planner-session-number" name="session_number" class="form-select w-100" required>
                <option value="1">Session 1 (09:00 - 10:00 AM)</option>
                <option value="2">Session 2 (10:00 - 11:00 AM)</option>
                <option value="3">Session 3 (11:15 - 12:15 PM)</option>
                <option value="4">Session 4 (12:15 - 01:15 PM)</option>
                <option value="5">Session 5 (02:00 - 03:00 PM)</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="planner-section-id">Class Section *</label>
            <select id="planner-section-id" name="section_id" class="form-select w-100" required></select>
          </div>

          <div class="form-group">
            <label class="form-label" for="planner-expected-subject">Expected Subject *</label>
            <input type="text" id="planner-expected-subject" name="expected_subject" class="form-control w-100" placeholder="e.g. Data Structures / Income Tax / Cloud Computing" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="planner-assigned-faculty">Assigned Faculty Member</label>
            <select id="planner-assigned-faculty" name="assigned_faculty_id" class="form-select w-100"></select>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 8px;">
                <input type="checkbox" name="is_merged" value="1" id="planner-is-merged" onchange="document.getElementById('planner-merge-box').style.display = this.checked ? 'block' : 'none'">
                <span class="text-sm font-bold">Merge with another Class</span>
              </label>
            </div>

            <div class="form-group">
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 8px;">
                <input type="checkbox" name="is_lab" value="1">
                <span class="text-sm font-bold">Practical Lab Session</span>
              </label>
            </div>
          </div>

          <div id="planner-merge-box" class="form-group" style="display: none; background: #faf5ff; padding: 10px; border-radius: 8px;">
            <label class="form-label" for="planner-merged-section-id" style="color: #7c3aed;">Combine With Section:</label>
            <select id="planner-merged-section-id" name="merged_with_section_id" class="form-select w-100"></select>
          </div>

          <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="checkbox" name="is_no_class" value="1">
              <span class="text-sm text-danger font-bold">Mark as "No Class" / Suspended Slot</span>
            </label>
          </div>

          <div class="form-group">
            <label class="form-label" for="planner-special-note">Special Instructions / Notes</label>
            <input type="text" id="planner-special-note" name="special_note" class="form-control w-100" placeholder="e.g. Bring lab records, combined lecture in Audi-1">
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('planner-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary glow-btn">Publish Schedule Slot</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Special Event / No Class Announcement -->
  <div id="special-event-modal" class="modal-backdrop">
    <div class="modal-dialog animate-slideDown">
      <div class="modal-header">
        <h3>Announce Special Student Session / Holiday</h3>
        <button type="button" class="modal-close" onclick="closeModal('special-event-modal')">&times;</button>
      </div>

      <form id="special-event-form">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label" for="event-date">Event Date *</label>
            <input type="date" id="event-date" name="event_date" class="form-control w-100" value="<?php echo date('Y-m-d'); ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="event-title">Announcement Title *</label>
            <input type="text" id="event-title" name="title" class="form-control w-100" placeholder="e.g. Guest Lecture on AI / Campus Placement Drive / Holiday" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="event-type">Event Category</label>
            <select id="event-type" name="event_type" class="form-select w-100">
              <option value="student_session">Student Session / Seminar</option>
              <option value="workshop">Technical Workshop</option>
              <option value="exam">Internal Assessment / Exam</option>
              <option value="sports">College Sports / Cultural Meet</option>
              <option value="holiday">Official Holiday (No Classes)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="event-desc">Description / Instructions</label>
            <textarea id="event-desc" name="description" rows="3" class="form-control w-100" placeholder="Details about timing, venue, speaker or class suspension..."></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('special-event-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Publish Announcement</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Faculty CRUD -->
  <div id="faculty-modal" class="modal-backdrop">
    <div class="modal-dialog animate-slideDown">
      <div class="modal-header">
        <h3 id="faculty-modal-title">Faculty Profile</h3>
        <button type="button" class="modal-close" onclick="closeModal('faculty-modal')">&times;</button>
      </div>

      <form id="faculty-form">
        <div class="modal-body">
          <input type="hidden" id="faculty-id" name="id" value="0">

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label" for="faculty-emp-id">Employee ID *</label>
              <input type="text" id="faculty-emp-id" name="employee_id" class="form-control w-100" placeholder="e.g. BCA105" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="faculty-dept-input">Department *</label>
              <select id="faculty-dept-input" name="department" class="form-select w-100">
                <option value="BCA">BCA</option>
                <option value="BCOM">BCOM</option>
                <option value="BOTH">BOTH (Common Faculty)</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="faculty-name-input">Full Name *</label>
            <input type="text" id="faculty-name-input" name="name" class="form-control w-100" placeholder="e.g. Prof. Rajesh Sharma" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="faculty-email-input">Email Address *</label>
            <input type="email" id="faculty-email-input" name="email" class="form-control w-100" placeholder="faculty@college.edu" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="faculty-desig-input">Designation</label>
            <input type="text" id="faculty-desig-input" name="designation" class="form-control w-100" placeholder="Assistant Professor">
          </div>

          <div class="form-group">
            <label class="form-label" for="faculty-password-input">Password (Leave blank to keep current / default)</label>
            <input type="password" id="faculty-password-input" name="password" class="form-control w-100" placeholder="••••••••">
          </div>
        </div>

        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
          <div>
            <button type="button" id="btn-delete-faculty" class="btn btn-danger" style="display: none;" onclick="handleDeleteFaculty()">
              🗑️ Delete Faculty
            </button>
          </div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('faculty-modal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Faculty</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Section & Semester CRUD (Cleaned of room field) -->
  <div id="section-modal" class="modal-backdrop">
    <div class="modal-dialog animate-slideDown">
      <div class="modal-header">
        <h3 id="section-modal-title">Edit Section & Semester</h3>
        <button type="button" class="modal-close" onclick="closeModal('section-modal')">&times;</button>
      </div>

      <form id="section-form">
        <div class="modal-body">
          <input type="hidden" id="section-id" name="id" value="0">

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label" for="section-dept-id">Department *</label>
              <select id="section-dept-id" name="department_id" class="form-select w-100">
                <option value="1">BCA</option>
                <option value="2">BCOM</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="section-year-id">Academic Year *</label>
              <select id="section-year-id" name="year_id" class="form-select w-100">
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
              </select>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label" for="section-semester">Semester (1 to 6) *</label>
              <input type="number" id="section-semester" name="semester" min="1" max="6" class="form-control w-100" value="5" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="section-name-input">Section Name *</label>
              <input type="text" id="section-name-input" name="section_name" class="form-control w-100" placeholder="e.g. 5B1 or Section A" required>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('section-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Section</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Session / Time Slot Editor -->
  <div id="time-slot-modal" class="modal-backdrop">
    <div class="modal-dialog animate-slideDown">
      <div class="modal-header">
        <h3 id="time-slot-modal-title">Edit Session Time Slot</h3>
        <button type="button" class="modal-close" onclick="closeModal('time-slot-modal')">&times;</button>
      </div>

      <form id="time-slot-form">
        <div class="modal-body">
          <input type="hidden" id="time-slot-id" name="id" value="0">

          <div class="form-group mb-3">
            <label class="form-label font-bold" for="time-slot-target-scope">Target Academic Year *</label>
            <select id="time-slot-target-scope" name="target_scope" class="form-select w-100" style="font-weight: 600; font-size: 14px; padding: 8px 12px;" required>
              <option value="">🌐 All College Classes (Default)</option>
              <option value="1_1">🎓 BCA - 1st Year (Applies to all 1st Year BCA Sections)</option>
              <option value="1_2">🎓 BCA - 2nd Year (Applies to all 2nd Year BCA Sections)</option>
              <option value="1_3">🎓 BCA - 3rd Year (Applies to all 3rd Year BCA Sections: 5B1, 5B2, 5B3, 5B4)</option>
              <option value="2_1">🎓 BCOM - 1st Year (Applies to all 1st Year BCOM Sections)</option>
              <option value="2_2">🎓 BCOM - 2nd Year (Applies to all 2nd Year BCOM Sections)</option>
              <option value="2_3">🎓 BCOM - 3rd Year (Applies to all 3rd Year BCOM Sections)</option>
            </select>
            <small class="text-muted" style="display: block; margin-top: 4px;">Changing timings for an Academic Year automatically updates all sections under that year.</small>
          </div>

          <div class="form-group mb-3">
            <label class="form-label font-bold" for="time-slot-session-number">Session Period / Break Slot Type *</label>
            <select id="time-slot-session-number" name="session_number" class="form-select w-100" style="font-weight: 600;" required onchange="handleSessionTypePreset(this.value)">
              <optgroup label="📖 Regular Teaching Sessions">
                <option value="1">Session 1 (Period 1)</option>
                <option value="2">Session 2 (Period 2)</option>
                <option value="3">Session 3 (Period 3)</option>
                <option value="4">Session 4 (Period 4)</option>
                <option value="5">Session 5 (Period 5)</option>
                <option value="6">Session 6 (Period 6)</option>
                <option value="7">Session 7 (Period 7)</option>
                <option value="8">Session 8 (Period 8)</option>
              </optgroup>
              <optgroup label="⏸️ College Break Hours">
                <option value="101">⏸️ Break / Morning Interval</option>
                <option value="102">🍱 Lunch Break Hour</option>
              </optgroup>
            </select>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label" for="time-slot-start-time">Start Time *</label>
              <input type="time" id="time-slot-start-time" name="start_time" class="form-control w-100" required>
            </div>

            <div class="form-group">
              <label class="form-label" for="time-slot-end-time">End Time *</label>
              <input type="time" id="time-slot-end-time" name="end_time" class="form-control w-100" required>
            </div>
          </div>

          <div class="form-group">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 6px;">
              <input type="checkbox" id="time-slot-is-active" name="is_active" value="1" checked>
              <span class="text-sm font-bold">Active in Daily Timetables & Sheets</span>
            </label>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('time-slot-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary glow-btn">Save Session Slot</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Export / Download PDF Report (Single Section or All Sections) -->
  <div id="export-pdf-modal" class="modal-backdrop">
    <div class="modal-dialog animate-slideDown" style="max-width: 550px;">
      <div class="modal-header">
        <h3>📄 Download & Print Class Report (PDF)</h3>
        <button type="button" class="modal-close" onclick="closeModal('export-pdf-modal')">&times;</button>
      </div>

      <div class="modal-body">
        <div class="form-group mb-3">
          <label class="form-label font-bold" for="pdf-report-date">Target Date *</label>
          <input type="date" id="pdf-report-date" class="form-control w-100" value="<?php echo date('Y-m-d'); ?>">
        </div>

        <div class="form-group mb-3">
          <label class="form-label font-bold">Select PDF Generation Type *</label>
          <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 6px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
              <input type="radio" name="pdf_scope" value="single" id="pdf-scope-single" checked onchange="togglePdfScope()">
              <span><strong>1. Section-wise PDF Report</strong> (e.g. Single Section like 5B1, BCA-1A, BCOM-3A)</span>
            </label>

            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
              <input type="radio" name="pdf_scope" value="dept" id="pdf-scope-dept" onchange="togglePdfScope()">
              <span><strong>2. Department-wise PDF Sheet</strong> (Complete BCA or BCOM Department Table)</span>
            </label>
          </div>
        </div>

        <div id="pdf-section-wrapper" class="form-group mb-3" style="background: #faf5ff; padding: 12px; border-radius: 8px; border: 1px solid #c084fc;">
          <label class="form-label font-bold" for="pdf-section-select" style="color: #6b21a8;">Select Specific Section to Download:</label>
          <select id="pdf-section-select" class="form-select w-100"></select>
        </div>

        <div id="pdf-dept-wrapper" class="form-group mb-3" style="display: none; background: #f0fdf4; padding: 12px; border-radius: 8px; border: 1px solid #86efac;">
          <label class="form-label font-bold" for="pdf-dept-select" style="color: #166534;">Select Department:</label>
          <select id="pdf-dept-select" class="form-select w-100">
            <option value="BCA">BCA (Bachelor of Computer Applications)</option>
            <option value="BCOM">BCOM (Bachelor of Commerce)</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('export-pdf-modal')">Cancel</button>
        <button type="button" class="btn btn-danger glow-btn" onclick="generateAndPrintPdf()">
          <span>🖨️</span> Generate & Save PDF
        </button>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/admin.js?v=<?php echo time(); ?>"></script>
  <script>
    function switchAdminTab(tabId, btn) {
      document.querySelectorAll('.admin-tab-content').forEach(el => el.style.display = 'none');
      document.querySelectorAll('.tab-pill').forEach(el => el.classList.remove('active'));
      document.getElementById(tabId).style.display = 'block';
      btn.classList.add('active');
    }

    function openCreateFacultyModal() {
      document.getElementById('faculty-id').value = '0';
      document.getElementById('faculty-form').reset();
      document.getElementById('faculty-modal-title').innerText = 'Add New Faculty Member';
      const delBtn = document.getElementById('btn-delete-faculty');
      if (delBtn) delBtn.style.display = 'none';
      openModal('faculty-modal');
    }

    function openCreateSectionModal() {
      document.getElementById('section-id').value = '0';
      document.getElementById('section-form').reset();
      document.getElementById('section-modal-title').innerText = 'Add New Section';
      openModal('section-modal');
    }

    function openCreateTimeSlotModal(isBreak = false) {
      document.getElementById('time-slot-id').value = '0';
      document.getElementById('time-slot-form').reset();
      const currentYearFilter = document.getElementById('slots-year-filter')?.value;
      if (document.getElementById('time-slot-target-scope')) {
        document.getElementById('time-slot-target-scope').value = currentYearFilter || '';
      }
      document.getElementById('time-slot-is-active').checked = true;
      
      const sessSelect = document.getElementById('time-slot-session-number');
      if (isBreak) {
        sessSelect.value = '101';
        document.getElementById('time-slot-modal-title').innerText = 'Add College Break Hour';
        if (typeof handleSessionTypePreset === 'function') {
          handleSessionTypePreset('101');
        }
      } else {
        sessSelect.value = '1';
        document.getElementById('time-slot-modal-title').innerText = 'Add New Session Time Slot';
      }
      openModal('time-slot-modal');
    }
  </script>
</body>
</html>
