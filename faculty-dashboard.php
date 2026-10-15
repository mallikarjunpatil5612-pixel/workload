<?php
/**
 * Faculty Dashboard & Class Logging Portal
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_faculty();
$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Faculty Dashboard - Daily Class Logging</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <?php include __DIR__ . '/includes/header.php'; ?>

  <main class="main-wrapper">

    <!-- Welcome & Personal Workload Overview -->
    <div class="glass-panel mb-4">
      <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3">
        <div>
          <?php 
            $welcomeName = $user['name'] ?? 'Faculty Member';
            if (stripos($welcomeName, 'Admin ') === 0) {
                $welcomeName = 'Teacher ' . substr($welcomeName, 6);
            }
            $welcomeDesignation = ($user['designation'] === 'Academic Administrator') ? 'Faculty Teacher' : $user['designation'];
            $welcomeDept = (!empty($user['department']) && $user['department'] !== 'BOTH') ? $user['department'] : 'BOTH (BCA & BCOM)';
            $badgeClass = ($user['department'] === 'BCOM') ? 'badge-bcom' : (($user['department'] === 'BOTH') ? 'badge-admin' : 'badge-bca');
          ?>
          <h2>Welcome, <?php echo htmlspecialchars($welcomeName); ?> 👋</h2>
          <p class="text-muted text-sm"><?php echo htmlspecialchars($welcomeDesignation); ?> • Employee ID: <strong><?php echo htmlspecialchars($user['employee_id']); ?></strong></p>
        </div>
        <div>
          <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.85rem; padding: 6px 12px;">
            Department: <?php echo htmlspecialchars($welcomeDept); ?>
          </span>
        </div>
      </div>

      <!-- Personal Workload Overview Cards -->
      <div class="metrics-grid">
        <div class="metric-card">
          <div class="metric-icon icon-purple">📅</div>
          <div class="metric-info">
            <h3 id="stat-today-hours">0 hrs</h3>
            <p>Classes Taken Today</p>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon icon-blue">📊</div>
          <div class="metric-info">
            <h3 id="stat-weekly-hours">0 hrs</h3>
            <p>Current Week Workload</p>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-icon icon-green">🏆</div>
          <div class="metric-info">
            <h3 id="stat-monthly-hours">0 hrs</h3>
            <p>Current Month Workload</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Tabs for Faculty Modules -->
    <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3">
      <div class="tab-pills">
        <button id="pill-daily-sheet" class="tab-pill active" onclick="switchFacultyTab('tab-daily-sheet', this)">📋 Dynamic Daily Class Sheet</button>
        <button id="pill-workload-calc" class="tab-pill" onclick="switchFacultyTab('tab-workload-calc', this)">📊 Calculate Workload & Analytics</button>
        <button id="pill-recent-logs" class="tab-pill" onclick="switchFacultyTab('tab-recent-logs', this)">🕒 Class Activity History</button>
      </div>

      <div class="d-flex align-center gap-2">
        <button type="button" class="btn btn-primary btn-sm glow-btn" onclick="openWorkloadTab()">
          <span>⚡</span> Open Workload Calculator
        </button>
      </div>
    </div>

    <!-- TAB 1: Dynamic Daily Class Table Section -->
    <section id="tab-daily-sheet" class="faculty-tab-content">
      <div class="glass-panel mb-4">
        <div class="glass-header">
          <div class="glass-title">
            <span>📋</span>
            <span>Dynamic Daily Class Sheet</span>
          </div>

          <!-- Filter Controls for Department, Year & Date -->
          <div class="filter-group">
            <div class="d-flex align-center gap-2">
              <label class="filter-label" for="faculty-dept-select">Department:</label>
              <select id="faculty-dept-select" class="form-select">
                <option value="BOTH" <?php echo ($user['department'] === 'BOTH' || empty($user['department'])) ? 'selected' : ''; ?>>🌐 BOTH (All Departments / BCA & BCOM)</option>
                <option value="1" <?php echo ($user['department'] === 'BCA') ? 'selected' : ''; ?>>💻 BCA (Computer Applications)</option>
                <option value="2" <?php echo ($user['department'] === 'BCOM') ? 'selected' : ''; ?>>📊 BCOM (Commerce)</option>
              </select>
            </div>

            <div class="d-flex align-center gap-2">
              <label class="filter-label" for="faculty-year-select">Year:</label>
              <select id="faculty-year-select" class="form-select">
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3" selected>3rd Year (Sem 5/6)</option>
              </select>
            </div>

            <div class="d-flex align-center gap-2">
              <label class="filter-label" for="faculty-table-date">Date:</label>
              <input type="date" id="faculty-table-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
        </div>

        <!-- Container where dynamic table is rendered -->
        <div id="dynamic-table-content"></div>
      </div>
    </section>

    <!-- TAB 2: Faculty Workload Calculator & Analytics -->
    <section id="tab-workload-calc" class="faculty-tab-content" style="display: none;">
      <div class="glass-panel mb-4">
        <div class="glass-header flex-wrap gap-3">
          <div>
            <div class="glass-title">
              <span>📊</span>
              <span>Faculty Workload Calculator & Statement</span>
            </div>
            <p class="text-muted text-sm mt-1">
              Accurate calculation of your teaching workload hours across custom periods or quick presets.
              <em>Rule enforced: Combined/Merged sessions count as exactly 1.0 hour.</em>
            </p>
          </div>

          <!-- Quick Presets, Date Controls & Calculate Button -->
          <div class="filter-group flex-wrap gap-2">
            <div class="d-flex align-center gap-1">
              <button type="button" class="btn btn-secondary btn-sm" onclick="setFacultyWorkloadPreset('today')">Today</button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="setFacultyWorkloadPreset('week')">This Week</button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="setFacultyWorkloadPreset('month')">This Month</button>
              <button type="button" class="btn btn-secondary btn-sm" onclick="setFacultyWorkloadPreset('last_month')">Last Month</button>
            </div>

            <div class="d-flex align-center gap-2">
              <input type="date" id="workload-start-date" class="form-control" value="<?php echo date('Y-m-01'); ?>">
              <span>to</span>
              <input type="date" id="workload-end-date" class="form-control" value="<?php echo date('Y-m-t'); ?>">
            </div>

            <button type="button" id="btn-filter-workload" class="btn btn-primary btn-sm glow-btn">
              <span>⚡</span> Calculate Workload
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="printFacultyWorkloadStatement()">
              <span>🖨️</span> Print Statement
            </button>
          </div>
        </div>

        <!-- Range Total Indicator -->
        <div class="d-flex align-center justify-between flex-wrap gap-2 mb-3 p-3" style="background: rgba(99, 102, 241, 0.05); border-radius: var(--radius-md); border: 1px solid rgba(99, 102, 241, 0.15);">
          <div>
            <span class="text-sm text-muted">Calculation Range:</span>
            <strong id="stat-calc-range-label" style="color: var(--primary); font-size: 0.95rem; margin-left: 6px;"><?php echo date('M 01, Y'); ?> - <?php echo date('M t, Y'); ?></strong>
          </div>
          <div class="d-flex align-center gap-2">
            <span class="text-sm text-muted">Total Credited:</span>
            <strong id="stat-custom-hours" style="color: var(--primary); font-size: 1.25rem;">0 hrs</strong>
            <span id="stat-custom-entries" class="text-muted text-sm">(0 classes)</span>
          </div>
        </div>

        <!-- Calculated Summary Statistics Grid -->
        <div class="metrics-grid mb-4">
          <div class="metric-card" style="border-left: 4px solid #6366f1;">
            <div class="metric-icon icon-purple">⏱️</div>
            <div class="metric-info">
              <h3 id="stat-calc-total-hours">0 hrs</h3>
              <p>Total Workload Hours</p>
            </div>
          </div>

          <div class="metric-card" style="border-left: 4px solid #3b82f6;">
            <div class="metric-icon icon-blue">📖</div>
            <div class="metric-info">
              <h3 id="stat-calc-theory-hours">0 hrs</h3>
              <p>Theory Sessions</p>
            </div>
          </div>

          <div class="metric-card" style="border-left: 4px solid #10b981;">
            <div class="metric-icon icon-green">💻</div>
            <div class="metric-info">
              <h3 id="stat-calc-lab-hours">0 hrs</h3>
              <p>Practical / Lab Hours</p>
            </div>
          </div>

          <div class="metric-card" style="border-left: 4px solid #f59e0b;">
            <div class="metric-icon icon-amber">🔀</div>
            <div class="metric-info">
              <h3 id="stat-calc-merged-hours">0 hrs</h3>
              <p>Combined Classes (1 hr each)</p>
            </div>
          </div>

          <div class="metric-card" style="border-left: 4px solid #8b5cf6;">
            <div class="metric-icon icon-purple">📝</div>
            <div class="metric-info">
              <h3 id="stat-calc-total-entries">0</h3>
              <p>Total Classes Logged</p>
            </div>
          </div>
        </div>

        <!-- Itemized Class Logs for Selected Calculation Period -->
        <div class="glass-title mb-2" style="font-size: 1rem;">
          <span>📋</span>
          <span>Itemized Class Sessions for Selected Period</span>
        </div>
        <div class="table-responsive">
          <table class="matrix-table" id="table-workload-statement">
            <thead>
              <tr>
                <th>Date</th>
                <th>Section & Dept</th>
                <th>Session & Timing</th>
                <th>Subject Name</th>
                <th>Topic Covered</th>
                <th>Nature / Type</th>
                <th>Workload Credited</th>
              </tr>
            </thead>
            <tbody id="workload-statement-body">
              <tr>
                <td colspan="7" class="text-center text-muted p-4">Click "Calculate Workload" to view detailed breakdown...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- TAB 3: Recent Logs History Table -->
    <section id="tab-recent-logs" class="faculty-tab-content" style="display: none;">
      <div class="glass-panel">
        <div class="glass-header">
          <div class="glass-title">
            <span>🕒</span>
            <span>My Class Activity Logs History</span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="matrix-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Section</th>
                <th>Session / Timing</th>
                <th>Subject</th>
                <th>Topic Covered</th>
                <th>Session Type</th>
              </tr>
            </thead>
            <tbody id="faculty-recent-logs">
              <tr>
                <td colspan="6" class="text-center text-muted p-3">Loading logs...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>

  <!-- Modal: Log / Update Class Session -->
  <div id="class-log-modal" class="modal-backdrop">
    <div class="modal-dialog animate-slideDown">
      <div class="modal-header">
        <h3 id="class-log-modal-title">Record Daily Class Session</h3>
        <button type="button" class="modal-close" onclick="closeModal('class-log-modal')">&times;</button>
      </div>

      <form id="class-log-form">
        <div class="modal-body">
          <input type="hidden" id="modal-class-date" name="log_date">
          <input type="hidden" id="modal-department-id" name="department_id">
          <input type="hidden" id="modal-year-id" name="year_id">
          <input type="hidden" id="modal-section-id" name="section_id">
          <input type="hidden" id="modal-session-number" name="session_number">
          <input type="hidden" id="modal-start-time" name="start_time">
          <input type="hidden" id="modal-end-time" name="end_time">
          <input type="hidden" id="modal-faculty-name" name="faculty_name">

          <div class="form-group">
            <label class="form-label" for="subject_name">Subject Name *</label>
            <input type="text" id="subject_name" name="subject_name" class="form-control w-100" placeholder="e.g. Python Programming / Advanced Accounting / DBMS" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="topic_covered">Topic Covered Today *</label>
            <textarea id="topic_covered" name="topic_covered" rows="3" class="form-control w-100" placeholder="Detail the specific concepts, chapters, or syllabus points taught in this session..." required></textarea>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label" for="session_type">Session Nature</label>
              <select id="session_type" name="session_type" class="form-select w-100">
                <option value="theory">Regular Theory Class</option>
                <option value="lab">Computer / Practical Lab Session</option>
                <option value="special_event">Seminar / Student Workshop</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Class Merging</label>
              <div style="margin-top: 8px;">
                <label style="display: flex; align-items: center; gap: 8px; font-size: 0.88rem; cursor: pointer;">
                  <input type="checkbox" id="is_merged" name="is_merged" value="1">
                  <span>Combined / Merged Class</span>
                </label>
              </div>
            </div>
          </div>

          <!-- If merged is checked, pick second section -->
          <div id="merge-section-wrapper" class="form-group" style="display: none; background: #faf5ff; padding: 12px; border-radius: 8px; border: 1px dashed #c084fc;">
            <label class="form-label" for="merged_with_section_id" style="color: #6b21a8;">Combine with Other Section:</label>
            <select id="merged_with_section_id" name="merged_with_section_id" class="form-select w-100"></select>
            <p class="text-sm mt-1" style="color: #7e22ce;">
              ℹ️ Taking two sections combined will update both sections in the master sheet and credit exactly 1.0 hour to your total workload.
            </p>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('class-log-modal')">Cancel</button>
          <button type="submit" class="btn btn-primary glow-btn">Save & Update Class Log</button>
        </div>
      </form>
    </div>
  </div>

  <?php include __DIR__ . '/includes/footer.php'; ?>
  <script src="assets/js/faculty.js?v=<?php echo time(); ?>"></script>
</body>
</html>
