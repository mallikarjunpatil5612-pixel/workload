/**
 * Admin Control Portal Interactive Logic
 * Live Matrix, 5B1 Search Drilldown, Schedule Planner, Workload Reports, Image Exporter
 */

document.addEventListener('DOMContentLoaded', () => {
  initAdminPortal();
});

let allFacultyList = [];
let allSectionsList = [];
let allTimeSlotsList = [];

// Helper to convert slot time to total minutes from midnight (12h/24h aware)
function getSlotMinutes(timeStr) {
  if (!timeStr) return 0;
  const parts = timeStr.trim().split(':');
  let h = parseInt(parts[0] || '0', 10);
  const m = parseInt(parts[1] || '0', 10);
  if (h >= 1 && h <= 6) h += 12;
  return h * 60 + m;
}

// Convert 24-hour time string ("13:25:00", "13:25", "9:00") to 12-hour format ("01:25 PM", "09:00 AM")
function formatTime12(timeStr) {
  if (!timeStr) return '-';
  const clean = String(timeStr).trim();
  if (/am|pm/i.test(clean)) {
    return clean;
  }
  const parts = clean.split(':');
  if (parts.length < 2) return clean;
  let h = parseInt(parts[0], 10);
  const m = String(parts[1]).padStart(2, '0').substring(0, 2);
  if (isNaN(h)) return clean;

  let ampm = 'AM';
  let displayH = h;

  if (h >= 13 && h <= 23) {
    ampm = 'PM';
    displayH = h - 12;
  } else if (h === 12) {
    ampm = 'PM';
    displayH = 12;
  } else if (h >= 1 && h <= 6) {
    ampm = 'PM';
    displayH = h;
  } else if (h === 0) {
    ampm = 'AM';
    displayH = 12;
  } else {
    ampm = 'AM';
    displayH = h;
  }

  const hStr = displayH < 10 ? '0' + displayH : '' + displayH;
  return `${hStr}:${m} ${ampm}`;
}

function formatTimeRange12(start, end) {
  if (!start && !end) return '-';
  if (!start) return formatTime12(end);
  if (!end) return formatTime12(start);
  return `${formatTime12(start)} - ${formatTime12(end)}`;
}

// Ensure teacher name is always properly formatted as Teacher / Professor and never Admin
function formatTeacherName(name) {
  if (!name) return 'Assigned Teacher';
  let clean = String(name).trim();
  clean = clean.replace(/Admin\s*Mallikarjun/gi, 'Prof. Mallikarjun Patil');
  clean = clean.replace(/^Admin\s+/gi, 'Prof. ');
  return clean;
}

function formatSessionLabel(label) {
  if (!label) return '';
  return String(label).replace(/\(?(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})(\s*[AP]M)?\)?/gi, (match, t1, t2) => {
    return `(${formatTime12(t1)} - ${formatTime12(t2)})`;
  });
}

function initAdminPortal() {
  const today = new Date().toISOString().split('T')[0];
  const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

  if (document.getElementById('admin-matrix-date')) {
    document.getElementById('admin-matrix-date').value = today;
  }
  if (document.getElementById('planner-schedule-date')) {
    document.getElementById('planner-schedule-date').value = tomorrow;
  }

  // Event Listeners for Matrix Filters
  document.getElementById('admin-matrix-date')?.addEventListener('change', loadMasterMatrix);
  document.getElementById('admin-dept-filter')?.addEventListener('change', loadMasterMatrix);
  document.getElementById('admin-year-filter')?.addEventListener('change', loadMasterMatrix);

  // Section Drilldown Search (e.g. 5B1 Search)
  document.getElementById('btn-search-section')?.addEventListener('click', executeSectionSearch);
  document.getElementById('search-section-input')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') executeSectionSearch();
  });

  // Teacher Attendance & Daily Workload Listeners
  document.getElementById('attendance-date')?.addEventListener('change', loadTeacherAttendance);
  document.getElementById('attendance-dept-filter')?.addEventListener('change', loadTeacherAttendance);
  document.getElementById('btn-refresh-attendance')?.addEventListener('click', loadTeacherAttendance);

  // Workload Report Filter
  document.getElementById('btn-load-workload')?.addEventListener('click', loadWorkloadReport);

  // Timetable Planner Form
  document.getElementById('schedule-slot-form')?.addEventListener('submit', handleSaveScheduleSlot);

  // Special Event Form
  document.getElementById('special-event-form')?.addEventListener('submit', handleSaveSpecialEvent);

  // Faculty Management Form
  document.getElementById('faculty-form')?.addEventListener('submit', handleSaveFaculty);

  // Section & Semester Management Form
  document.getElementById('section-form')?.addEventListener('submit', handleSaveSection);

  // Time Slots Form
  document.getElementById('time-slot-form')?.addEventListener('submit', handleSaveTimeSlot);

  // Shareable Image Download Button
  document.getElementById('btn-download-image')?.addEventListener('click', exportDailyReportAsImage);

  // Initial Loads
  loadDashboardStats();
  loadMasterMatrix();
  loadTeacherAttendance();
  loadFacultiesData();
  loadSectionsData();
  loadTimeSlotsData();
  loadWorkloadReport();
}

// 1. Dashboard Top Stats
async function loadDashboardStats() {
  try {
    const res = await fetch('api/admin.php?action=get_dashboard_stats');
    const json = await res.json();
    if (json.success) {
      document.getElementById('stat-total-faculty').innerText = json.stats.total_faculty;
      document.getElementById('stat-today-classes').innerText = json.stats.today_classes;
      document.getElementById('stat-bca-today').innerText = json.stats.bca_today;
      document.getElementById('stat-bcom-today').innerText = json.stats.bcom_today;
    }
  } catch (err) {
    console.error(err);
  }
}

// 2. Load Master Daily Matrix
async function loadMasterMatrix() {
  const date = document.getElementById('admin-matrix-date')?.value || new Date().toISOString().split('T')[0];
  const dept = document.getElementById('admin-dept-filter')?.value || 'ALL';
  const year = document.getElementById('admin-year-filter')?.value || 0;

  const container = document.getElementById('matrix-table-container');
  if (!container) return;

  container.innerHTML = `<div class="text-center text-muted p-4"><div class="pulse">⏳ Loading Master College Class Matrix...</div></div>`;

  try {
    const res = await fetch(`api/admin.php?action=get_master_matrix&date=${date}&department=${dept}&year=${year}`);
    const json = await res.json();

    if (!json.success) {
      if (res.status === 401 || res.status === 403 || (json.message && json.message.toLowerCase().includes('session'))) {
        container.innerHTML = `
          <div class="alert alert-warning" style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a;">
            <div>
              <strong style="color: #92400e;">⚠️ Admin Session Expired</strong>
              <div style="font-size: 0.85rem; margin-top: 4px; color: #78350f;">Your login session has timed out. Please log in again to view the live class matrix.</div>
            </div>
            <a href="admin-login.php" class="btn btn-primary btn-sm" style="text-decoration: none; font-weight: 700; white-space: nowrap; margin-left: 15px;">🔑 Log In Again</a>
          </div>
        `;
        return;
      }
      container.innerHTML = `<div class="alert alert-error">⚠️ Failed to load matrix: ${json.message || 'Unknown error'}</div>`;
      return;
    }

    renderMasterMatrix(json);
  } catch (err) {
    console.error('Matrix error:', err);
    container.innerHTML = `<div class="alert alert-error">⚠️ Error connecting to server: ${err.message || 'Network error'}</div>`;
  }
}

function renderMasterMatrix(data) {
  const container = document.getElementById('matrix-table-container');
  const sections = data.sections || [];
  const timeSlots = data.time_slots || [];
  const logsMap = data.logs || {};
  const schedMap = data.schedules || {};
  const events = data.events || [];

  // Sort time slots chronologically by start time so breaks are placed centrally session-wise
  timeSlots.sort((a, b) => {
    const minA = getSlotMinutes(a.start_time);
    const minB = getSlotMinutes(b.start_time);
    if (minA === minB) {
      return (a.session_number || 0) - (b.session_number || 0);
    }
    return minA - minB;
  });

  let html = '';

  // Event announcements
  if (events.length > 0) {
    events.forEach(ev => {
      html += `
        <div class="alert alert-warning mb-3">
          <strong>📢 College Notice [${ev.event_type.toUpperCase()}]: ${ev.title}</strong>
          <p class="text-sm mt-1">${ev.description || 'Special session / activity scheduled.'}</p>
        </div>
      `;
    });
  }

  if (sections.length === 0) {
    container.innerHTML = `<div class="text-center text-muted p-4">No sections found for the selected filter.</div>`;
    return;
  }

  html += `
    <div class="mobile-swipe-hint">
      <span>👉</span> Swipe table horizontally to see all sessions &amp; break <span>👈</span>
    </div>
    <div id="capture-matrix-area">
      <div class="table-responsive">
        <table class="matrix-table">
          <thead>
            <tr>
              <th style="min-width: 140px;">Section / Dept</th>
              ${timeSlots.map(slot => {
                const isBreak = slot.is_break == 1 || slot.session_number >= 100 || slot.slot_type === 'tea_break' || slot.slot_type === 'lunch_break';
                const labelClean = slot.label && slot.label.includes('(') ? slot.label.split('(')[0].trim() : (slot.label || `Session ${slot.session_number}`);
                const startStr = formatTime12(slot.start_time);
                const endStr = formatTime12(slot.end_time);

                if (isBreak) {
                  return `
                    <th class="break-th">
                      <div class="break-title">
                        <span>⏸️</span>
                        <span>BREAK</span>
                      </div>
                      <div class="break-time">${startStr} - ${endStr}</div>
                    </th>
                  `;
                }

                return `
                  <th>
                    <div>${labelClean}</div>
                    <div style="font-size: 0.72rem; font-weight: 500; color: var(--text-muted);">${startStr} - ${endStr}</div>
                  </th>
                `;
              }).join('')}
            </tr>
          </thead>
          <tbody>
  `;

  sections.forEach(sec => {
    html += `
      <tr>
        <td>
          <div class="d-flex align-center">
            <span class="badge ${sec.dept_code === 'BCA' ? 'badge-bca' : 'badge-bcom'}">${sec.dept_code}</span>
          </div>
          <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem; margin-top: 4px;">${sec.display_code}</div>
          <div class="text-muted text-sm">${sec.section_name} (${sec.year_name})</div>
        </td>
    `;

    timeSlots.forEach(slot => {
      const isBreak = slot.is_break == 1 || slot.session_number >= 100 || slot.slot_type === 'tea_break' || slot.slot_type === 'lunch_break';
      const key = `${sec.id}_${slot.session_number}`;
      const log = logsMap[key];
      const sched = schedMap[key];

      if (isBreak) {
        const startStr = formatTime12(slot.start_time);
        const endStr = formatTime12(slot.end_time);
        html += `
          <td class="session-cell break-cell" title="BREAK (${startStr} - ${endStr})">
            <div class="break-box">
              <span class="break-icon">⏸️</span>
              <span class="break-label">BREAK</span>
            </div>
          </td>
        `;
        return;
      }

      html += `<td class="session-cell">`;

      if (log) {
        const typeBadge = log.session_type === 'lab' 
          ? '<span class="badge badge-lab">🔬 Lab</span>' 
          : (log.is_merged == 1 ? '<span class="badge badge-merged">👥 Merged</span>' : '<span class="badge badge-theory">📖 Theory</span>');

        html += `
          <div class="slot-card filled ${log.session_type === 'lab' ? 'lab' : ''} ${log.is_merged == 1 ? 'merged' : ''}">
            <div class="slot-header">
              <span>Session ${slot.session_number}</span>
              ${typeBadge}
            </div>
            <div class="slot-subject">${log.subject_name}</div>
            <div class="slot-topic" title="${log.topic_covered}">${log.topic_covered}</div>
            <div class="slot-faculty">
              <span>👤 ${formatTeacherName(log.faculty_name)}</span>
            </div>
            <div class="mt-1 d-flex justify-between align-center">
              <span class="text-muted" style="font-size: 0.68rem;">${formatTime12(log.start_time)} - ${formatTime12(log.end_time)}</span>
              <button class="btn btn-sm btn-secondary" style="padding: 2px 6px; font-size: 0.7rem;" onclick="adminDeleteLog(${log.id})">🗑️</button>
            </div>
          </div>
        `;
      } else if (sched && sched.is_no_class == 1) {
        html += `
          <div class="slot-card" style="background: #fff1f2; border-color: #fecdd3;">
            <div class="slot-header text-danger font-bold">🚫 No Class</div>
            <div class="text-sm text-muted mt-1">${sched.special_note || 'Session Suspended'}</div>
          </div>
        `;
      } else if (sched && sched.expected_subject) {
        html += `
          <div class="slot-card" style="background: #f0fdf4; border-color: #bbf7d0;">
            <div class="slot-header" style="color: #16a34a;">📅 Scheduled</div>
            <div class="slot-subject">${sched.expected_subject}</div>
            <div class="slot-faculty">👤 ${formatTeacherName(sched.assigned_faculty_name || 'Assigned Teacher')}</div>
            <div class="text-muted text-sm" style="font-size: 0.72rem;">Pending Faculty Log</div>
          </div>
        `;
      } else {
        html += `
          <div class="slot-card" style="opacity: 0.85; border-style: dashed; cursor: pointer; transition: all 0.2s ease;" onclick="openPlannerModal(${sec.id}, ${slot.session_number})" title="Click to Assign Faculty / Plan for ${sec.display_code} Session ${slot.session_number}">
            <div class="slot-header">
              <span style="font-weight: 600;">Session ${slot.session_number}</span>
              <span class="badge" style="background: #f1f5f9; color: #64748b;">Available</span>
            </div>
            <div class="slot-empty-btn" style="color: #6366f1; font-weight: 700; font-size: 11px; margin-top: 4px;">
              <span>➕ Assign Class</span>
            </div>
          </div>
        `;
      }

      html += `</td>`;
    });

    html += `</tr>`;
  });

  html += `
          </tbody>
        </table>
      </div>
    </div>
  `;

  container.innerHTML = html;
}

window.openPlannerModal = function(secId = null, sessNum = null) {
  const currentDate = document.getElementById('admin-matrix-date')?.value || new Date().toISOString().split('T')[0];
  const dateInput = document.getElementById('planner-schedule-date');
  if (dateInput) dateInput.value = currentDate;

  if (secId) {
    const secSelect = document.getElementById('planner-section-id');
    if (secSelect) secSelect.value = secId;
  }
  if (sessNum) {
    const sessSelect = document.getElementById('planner-session-number');
    if (sessSelect) sessSelect.value = sessNum;
  }
  openModal('planner-modal');
};

window.openSpecialEventModal = function() {
  const currentDate = document.getElementById('admin-matrix-date')?.value || new Date().toISOString().split('T')[0];
  const dateInput = document.getElementById('event-date');
  if (dateInput) dateInput.value = currentDate;
  openModal('special-event-modal');
};

// 3. Section Drilldown Search (e.g. Who took classes for 5B1?)
async function executeSectionSearch() {
  const query = document.getElementById('search-section-input')?.value.trim() || '5B1';
  const date  = document.getElementById('search-section-date')?.value || '';

  const resultsContainer = document.getElementById('section-search-results');
  if (!resultsContainer) return;

  resultsContainer.innerHTML = `<div class="text-center text-muted p-3"><div class="pulse">🔍 Searching classes for ${query}...</div></div>`;

  try {
    const res = await fetch(`api/admin.php?action=query_section&section_query=${encodeURIComponent(query)}&date=${date}`);
    const json = await res.json();

    if (!json.success || json.results.length === 0) {
      resultsContainer.innerHTML = `
        <div class="alert alert-info">
          No class records found for Section <strong>${query}</strong> ${date ? `on ${date}` : ''}.
        </div>
      `;
      return;
    }

    let html = `
      <div class="mb-2 font-bold text-sm text-muted">
        Found ${json.count} class sessions recorded for <strong>"${json.query}"</strong>:
      </div>
      <div class="table-responsive">
        <table class="matrix-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Section / Code</th>
              <th>Session</th>
              <th>Faculty Name</th>
              <th>Subject</th>
              <th>Topic Covered</th>
              <th>Type</th>
            </tr>
          </thead>
          <tbody>
    `;

    json.results.forEach(r => {
      html += `
        <tr>
          <td><strong>${formatDate(r.log_date)}</strong></td>
          <td><span class="badge ${r.dept_code === 'BCA' ? 'badge-bca' : 'badge-bcom'}">${r.section_code}</span></td>
          <td>Session ${r.session_number} (${formatTime12(r.start_time)} - ${formatTime12(r.end_time)})</td>
          <td><strong style="color: var(--primary);">👤 ${formatTeacherName(r.faculty_name)}</strong> (${r.employee_id})</td>
          <td><strong>${r.subject_name}</strong></td>
          <td class="text-muted">${r.topic_covered}</td>
          <td>
            <span class="badge ${r.session_type === 'lab' ? 'badge-lab' : (r.is_merged == 1 ? 'badge-merged' : 'badge-theory')}">
              ${r.session_type.toUpperCase()}
            </span>
          </td>
        </tr>
      `;
    });

    html += `
          </tbody>
        </table>
      </div>
    `;

    resultsContainer.innerHTML = html;
  } catch (err) {
    resultsContainer.innerHTML = `<div class="alert alert-error">Error retrieving section drilldown.</div>`;
  }
}

// 3.5. Daily Teacher Attendance & Real-Time Workload
async function loadTeacherAttendance() {
  const date = document.getElementById('attendance-date')?.value || new Date().toISOString().split('T')[0];
  const dept = document.getElementById('attendance-dept-filter')?.value || 'ALL';

  const tbody = document.getElementById('attendance-table-body');
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted p-4"><div class="pulse">⏳ Loading Teacher Attendance & Live Workload...</div></td></tr>`;

  try {
    const res = await fetch(`api/admin.php?action=get_daily_teacher_attendance&date=${date}&department=${dept}`);
    const json = await res.json();

    if (!json.success) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger p-4">Error loading attendance: ${json.message}</td></tr>`;
      return;
    }

    // Update Top Metric Cards
    if (document.getElementById('att-stat-total')) document.getElementById('att-stat-total').innerText = json.total_faculty;
    if (document.getElementById('att-stat-present')) document.getElementById('att-stat-present').innerText = json.total_present;
    if (document.getElementById('att-stat-pending')) document.getElementById('att-stat-pending').innerText = json.total_absent;
    if (document.getElementById('att-stat-hours')) document.getElementById('att-stat-hours').innerText = parseFloat(json.total_daily_hours).toFixed(1) + ' hrs';

    renderTeacherAttendance(json.data);
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger p-4">Network error loading attendance data.</td></tr>`;
  }
}

function renderTeacherAttendance(data) {
  const tbody = document.getElementById('attendance-table-body');
  if (!tbody) return;

  if (data.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted p-4">No faculty records found.</td></tr>`;
    return;
  }

  tbody.innerHTML = data.map(item => {
    const f = item.faculty;
    const isAttended = item.is_attended;
    const sessionCount = item.session_count;
    const dailyHours = parseFloat(item.daily_workload).toFixed(1);

    // Render interactive session chips
    let sessionsHtml = '';
    if (item.sessions && item.sessions.length > 0) {
      sessionsHtml = `
        <div style="display: flex; flex-direction: column; gap: 6px;">
          ${item.sessions.map(s => `
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px; font-size: 0.82rem;">
              <div class="d-flex justify-between align-center">
                <strong>Session ${s.session_number} (${formatTime12(s.start_time)} - ${formatTime12(s.end_time)})</strong>
                <span class="badge ${s.session_type === 'lab' ? 'badge-lab' : (s.is_merged == 1 ? 'badge-merged' : 'badge-theory')}">
                  ${s.session_type.toUpperCase()}
                </span>
              </div>
              <div style="color: var(--primary); font-weight: 600; margin-top: 2px;">
                🏫 ${s.section_code}: ${s.subject_name}
              </div>
              <div class="text-muted text-sm" style="font-size: 0.76rem; margin-top: 2px;">
                📝 ${s.topic_covered}
              </div>
            </div>
          `).join('')}
        </div>
      `;
    } else {
      sessionsHtml = `<span class="text-muted text-sm">No classes logged for this date yet.</span>`;
    }

    return `
      <tr>
        <td>
          <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">👤 ${f.name}</div>
          <div class="text-muted text-sm">${f.employee_id} • ${f.designation}</div>
          <div class="text-muted text-sm" style="font-size: 0.75rem;">📧 ${f.email}</div>
        </td>
        <td>
          <span class="badge ${f.department === 'BCA' ? 'badge-bca' : (f.department === 'BCOM' ? 'badge-bcom' : 'badge-admin')}">
            ${f.department}
          </span>
        </td>
        <td>
          ${isAttended 
            ? `<span class="badge badge-success" style="font-size: 0.82rem; padding: 6px 10px;">✅ Attended (${sessionCount} ${sessionCount === 1 ? 'class' : 'classes'})</span>` 
            : `<span class="badge badge-inactive" style="font-size: 0.82rem; padding: 6px 10px;">⏳ Pending / No Log</span>`
          }
        </td>
        <td>
          <div style="font-size: 1.1rem; font-weight: 800; color: #1e293b;">${sessionCount}</div>
          <div class="text-muted text-sm" style="font-size: 0.72rem;">${item.theory_count} Theory • ${item.lab_count} Lab</div>
        </td>
        <td>
          <div style="font-size: 1.2rem; font-weight: 800; color: var(--primary);">${dailyHours} hrs</div>
          <div class="text-muted text-sm" style="font-size: 0.72rem;">Credited Workload</div>
        </td>
        <td style="min-width: 280px; max-width: 400px;">
          ${sessionsHtml}
        </td>
      </tr>
    `;
  }).join('');
}

window.setAttendanceToday = function() {
  const today = new Date().toISOString().split('T')[0];
  const dateInput = document.getElementById('attendance-date');
  if (dateInput) {
    dateInput.value = today;
    loadTeacherAttendance();
  }
};

window.setWorkloadPreset = function(type) {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  const todayStr = `${y}-${m}-${d}`;

  const startInput = document.getElementById('report-start-date');
  const endInput = document.getElementById('report-end-date');

  if (type === 'today') {
    if (startInput) startInput.value = todayStr;
    if (endInput) endInput.value = todayStr;
  } else if (type === 'week') {
    const day = now.getDay() || 7;
    const mon = new Date(now);
    mon.setDate(now.getDate() - day + 1);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);

    const monStr = mon.toISOString().split('T')[0];
    const sunStr = sun.toISOString().split('T')[0];

    if (startInput) startInput.value = monStr;
    if (endInput) endInput.value = sunStr;
  } else if (type === 'month') {
    const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
    if (startInput) startInput.value = `${y}-${m}-01`;
    if (endInput) endInput.value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
  }

  loadWorkloadReport();
};

// 4. Monthly & Daily Faculty Workload Report Calculator
async function loadWorkloadReport() {
  const dept      = document.getElementById('report-dept-filter')?.value || 'ALL';
  const startDate = document.getElementById('report-start-date')?.value || '';
  const endDate   = document.getElementById('report-end-date')?.value || '';

  const tableBody = document.getElementById('workload-report-body');
  if (!tableBody) return;

  tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted p-4"><div class="pulse">⏳ Calculating Faculty Workloads...</div></td></tr>`;

  try {
    const res = await fetch(`api/admin.php?action=get_workload_report&department=${dept}&start_date=${startDate}&end_date=${endDate}`);
    const json = await res.json();

    if (!json.success || json.data.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted p-4">No workload data recorded for this duration.</td></tr>`;
      return;
    }

    tableBody.innerHTML = json.data.map((fac, idx) => `
      <tr>
        <td><strong>#${idx + 1}</strong></td>
        <td>
          <div style="font-weight: 700; color: #1e293b;">${formatTeacherName(fac.faculty_name)}</div>
          <div class="text-muted text-sm">${fac.employee_id} • ${fac.designation}</div>
        </td>
        <td><span class="badge ${fac.department === 'BCA' ? 'badge-bca' : (fac.department === 'BCOM' ? 'badge-bcom' : 'badge-admin')}">${fac.department}</span></td>
        <td>${fac.theory_hours} hrs</td>
        <td>${fac.lab_hours} hrs</td>
        <td>${fac.merged_hours} hrs <span class="text-muted text-sm">(1 hr/each)</span></td>
        <td>
          <span style="font-size: 1.15rem; font-weight: 800; color: var(--primary);">
            ${fac.total_workload_hours} hrs
          </span>
        </td>
      </tr>
    `).join('');
  } catch (err) {
    tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger p-4">Failed to load workload calculation.</td></tr>`;
  }
}

// 5. Schedule / Timetable Designer (Tomorrow's or Future Schedule)
async function handleSaveScheduleSlot(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;

  try {
    const res = await fetch('api/admin.php?action=save_daily_schedule_slot', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();

    if (json.success) {
      showToast(json.message, 'success');
      closeModal('planner-modal');
      loadMasterMatrix();
    } else {
      showToast(json.message || 'Failed to save schedule slot', 'error');
    }
  } catch (err) {
    showToast('Network error while saving schedule slot', 'error');
  } finally {
    btn.disabled = false;
  }
}

// 6. Special Event Announcement Handler
async function handleSaveSpecialEvent(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);

  try {
    const res = await fetch('api/admin.php?action=save_special_event', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();

    if (json.success) {
      showToast(json.message, 'success');
      closeModal('special-event-modal');
      form.reset();
      loadMasterMatrix();
    } else {
      showToast(json.message || 'Error saving event', 'error');
    }
  } catch (err) {
    showToast('Network error while saving event', 'error');
  }
}

// 7. Faculty Manager CRUD
async function loadFacultiesData() {
  try {
    const res = await fetch('api/admin.php?action=get_faculties');
    const json = await res.json();
    if (json.success) {
      allFacultyList = json.faculties;
      renderFacultyTable(json.faculties);
      populateFacultyDropdowns(json.faculties);
    }
  } catch (err) {
    console.error(err);
  }
}

function renderFacultyTable(faculties) {
  const tbody = document.getElementById('faculty-table-body');
  if (!tbody) return;

  tbody.innerHTML = faculties.map(f => `
    <tr>
      <td><strong>${f.employee_id}</strong></td>
      <td><strong>${f.name}</strong></td>
      <td>${f.email}</td>
      <td><span class="badge ${f.department === 'BCA' ? 'badge-bca' : 'badge-bcom'}">${f.department}</span></td>
      <td>${f.designation}</td>
      <td>
        <span class="badge ${f.status === 'active' ? 'badge-theory' : 'badge-admin'}">
          ${f.status.toUpperCase()}
        </span>
      </td>
      <td>
        <div style="display: flex; gap: 6px; align-items: center;">
          <button class="btn btn-sm btn-secondary" onclick="openEditFacultyModal(${JSON.stringify(f).replace(/"/g, '&quot;')})">Edit</button>
          <button class="btn btn-sm btn-outline-danger" title="Delete Faculty Member" onclick="deleteFacultyDirect(${f.id}, '${f.name.replace(/'/g, "\\'")}')">🗑️</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function populateFacultyDropdowns(faculties) {
  const select = document.getElementById('planner-assigned-faculty');
  if (!select) return;
  select.innerHTML = '<option value="">-- Select Faculty --</option>';
  faculties.forEach(f => {
    select.innerHTML += `<option value="${f.id}">${f.name} (${f.department} - ${f.employee_id})</option>`;
  });
}

async function handleSaveFaculty(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);

  try {
    const res = await fetch('api/admin.php?action=save_faculty', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      closeModal('faculty-modal');
      form.reset();
      loadFacultiesData();
      loadDashboardStats();
    } else {
      showToast(json.message || 'Error', 'error');
    }
  } catch (err) {
    showToast('Network error saving faculty', 'error');
  }
}

window.openEditFacultyModal = function(faculty) {
  document.getElementById('faculty-id').value = faculty.id;
  document.getElementById('faculty-emp-id').value = faculty.employee_id;
  document.getElementById('faculty-name-input').value = faculty.name;
  document.getElementById('faculty-email-input').value = faculty.email;
  document.getElementById('faculty-dept-input').value = faculty.department;
  if (document.getElementById('faculty-desig-input')) {
    document.getElementById('faculty-desig-input').value = faculty.designation || 'Assistant Professor';
  }
  if (document.getElementById('faculty-password-input')) {
    document.getElementById('faculty-password-input').value = '';
  }
  if (document.getElementById('faculty-phone-input')) {
    document.getElementById('faculty-phone-input').value = faculty.phone || '';
  }
  
  // Show Delete button in modal footer for existing faculty
  const delBtn = document.getElementById('btn-delete-faculty');
  if (delBtn) {
    delBtn.style.display = 'inline-flex';
  }

  document.getElementById('faculty-modal-title').innerText = 'Edit Faculty Profile';
  openModal('faculty-modal');
};

window.handleDeleteFaculty = async function() {
  const id = document.getElementById('faculty-id').value;
  const name = document.getElementById('faculty-name-input').value || 'this faculty member';

  if (!id || id === '0') {
    showToast('Cannot delete unsaved faculty member.', 'error');
    return;
  }

  if (!confirm(`Are you sure you want to permanently delete faculty member "${name}"?\n\nThis will remove their profile and unassign scheduled sessions.`)) {
    return;
  }

  try {
    const formData = new FormData();
    formData.append('id', id);

    const res = await fetch('api/admin.php?action=delete_faculty', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message || `Faculty member ${name} deleted successfully.`, 'success');
      closeModal('faculty-modal');
      document.getElementById('faculty-form')?.reset();
      loadFacultiesData();
      if (typeof loadDashboardStats === 'function') {
        loadDashboardStats();
      }
    } else {
      showToast(json.message || 'Error deleting faculty member', 'error');
    }
  } catch (err) {
    showToast('Network error while deleting faculty profile', 'error');
  }
};

window.deleteFacultyDirect = async function(id, name) {
  if (!confirm(`Are you sure you want to permanently delete faculty member "${name}"?\n\nThis will remove their profile and unassign scheduled sessions.`)) {
    return;
  }

  try {
    const formData = new FormData();
    formData.append('id', id);

    const res = await fetch('api/admin.php?action=delete_faculty', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message || `Faculty member ${name} deleted successfully.`, 'success');
      loadFacultiesData();
      if (typeof loadDashboardStats === 'function') {
        loadDashboardStats();
      }
    } else {
      showToast(json.message || 'Error deleting faculty member', 'error');
    }
  } catch (err) {
    showToast('Network error while deleting faculty profile', 'error');
  }
};

// 8. Sections & Semester Management (Assigned Room Removed)
async function loadSectionsData() {
  try {
    const res = await fetch('api/admin.php?action=get_sections');
    const json = await res.json();
    if (json.success) {
      allSectionsList = json.sections;
      renderSectionsTable(json.sections);
      populateSectionDropdowns(json.sections);
    }
  } catch (err) {
    console.error(err);
  }
}

function renderSectionsTable(sections) {
  const tbody = document.getElementById('sections-table-body');
  if (!tbody) return;

  tbody.innerHTML = sections.map(s => `
    <tr>
      <td><strong>${s.section_name}</strong></td>
      <td><span class="badge ${s.dept_code === 'BCA' ? 'badge-bca' : 'badge-bcom'}">${s.dept_code}</span></td>
      <td>${s.year_name}</td>
      <td>Sem ${s.semester}</td>
      <td>
        <button class="btn btn-sm btn-secondary" onclick="openEditSectionModal(${JSON.stringify(s).replace(/"/g, '&quot;')})">Edit</button>
      </td>
    </tr>
  `).join('');
}

function buildGroupedSectionOptionsHtml(sections, defaultOptionText = '-- Select Section --', defaultOptionVal = '') {
  let html = defaultOptionText ? `<option value="${defaultOptionVal}">${defaultOptionText}</option>` : '';

  // Group sections by Department and Academic Year (e.g. BCA 1st Year, BCA 2nd Year, BCA 3rd Year)
  const grouped = {};
  sections.forEach(s => {
    const yr = s.year_name || (s.year_number ? (s.year_number === 1 ? '1st Year' : (s.year_number === 2 ? '2nd Year' : '3rd Year')) : 'Academic Year');
    const sem = s.semester ? ` (Sem ${s.semester})` : '';
    const groupKey = `${s.dept_code} - ${yr}${sem}`;
    if (!grouped[groupKey]) {
      grouped[groupKey] = [];
    }
    grouped[groupKey].push(s);
  });

  for (const [groupName, list] of Object.entries(grouped)) {
    html += `<optgroup label="📚 ${groupName}">`;
    list.forEach(s => {
      const yrShort = s.year_name ? s.year_name.replace(' Year', 'Yr') : '';
      html += `<option value="${s.id}" data-dept="${s.department_id}" data-year="${s.year_id}">🎯 [${yrShort}] ${s.display_code} (${s.section_name})</option>`;
    });
    html += `</optgroup>`;
  }

  return html;
}

function populateSectionDropdowns(sections) {
  const plannerSec = document.getElementById('planner-section-id');
  const plannerMerge = document.getElementById('planner-merged-section-id');
  const pdfSec = document.getElementById('pdf-section-select');
  const slotsFilterSec = document.getElementById('slots-section-filter');

  if (plannerSec) plannerSec.innerHTML = buildGroupedSectionOptionsHtml(sections, '-- Select Section --', '');
  if (plannerMerge) plannerMerge.innerHTML = buildGroupedSectionOptionsHtml(sections, '-- Combine With Section --', '');
  if (pdfSec) pdfSec.innerHTML = buildGroupedSectionOptionsHtml(sections, '', '');
  if (slotsFilterSec) slotsFilterSec.innerHTML = buildGroupedSectionOptionsHtml(sections, '🌐 All Sections (College Default)', '');

  filterTimeSlotModalSections();
}

window.filterSlotsSectionDropdown = function() {
  const deptVal = document.getElementById('slots-dept-filter')?.value || 'ALL';
  const secSelect = document.getElementById('slots-section-filter');
  if (!secSelect) return;

  const currentSec = secSelect.value;
  const filtered = allSectionsList.filter(s => deptVal === 'ALL' || String(s.department_id) === String(deptVal));
  secSelect.innerHTML = buildGroupedSectionOptionsHtml(filtered, '🌐 All Sections (College Default)', '');

  // Preserve previous selection if still available
  secSelect.value = currentSec;
  loadTimeSlotsData();
};

window.filterTimeSlotModalSections = function(selectedSecId = null) {
  const secSelect = document.getElementById('time-slot-section-id');
  if (!secSelect) return;

  secSelect.innerHTML = buildGroupedSectionOptionsHtml(allSectionsList, '🌐 College-Wide Default (All Sections)', '');

  if (selectedSecId) {
    secSelect.value = selectedSecId;
  }
};

async function handleSaveSection(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);

  try {
    const res = await fetch('api/admin.php?action=save_section', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      closeModal('section-modal');
      form.reset();
      loadSectionsData();
    } else {
      showToast(json.message || 'Error', 'error');
    }
  } catch (err) {
    showToast('Network error saving section', 'error');
  }
}

window.openEditSectionModal = function(sec) {
  document.getElementById('section-id').value = sec.id;
  document.getElementById('section-dept-id').value = sec.department_id;
  document.getElementById('section-year-id').value = sec.year_id;
  document.getElementById('section-semester').value = sec.semester;
  document.getElementById('section-name-input').value = sec.section_name;
  if (document.getElementById('section-code-input')) {
    document.getElementById('section-code-input').value = sec.display_code || sec.section_name;
  }
  document.getElementById('section-modal-title').innerText = 'Edit Section & Semester';
  openModal('section-modal');
};

// 9. Admin delete log action
window.adminDeleteLog = async function(logId) {
  if (!confirm('Are you sure you want to remove this class log?')) return;
  try {
    const fd = new FormData();
    fd.append('log_id', logId);
    const res = await fetch('api/admin.php?action=delete_log', { 
      method: 'POST', 
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: fd 
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      loadMasterMatrix();
      loadDashboardStats();
    } else {
      showToast(json.message || 'Error deleting log', 'error');
    }
  } catch (err) {
    showToast('Network error deleting log', 'error');
  }
};

// 10. Session & Time Slot Management (Year-Wise Configuration)
async function loadTimeSlotsData() {
  const tbody = document.getElementById('time-slots-table-body');
  const yearFilter = document.getElementById('slots-year-filter')?.value || '';

  try {
    const res = await fetch(`api/admin.php?action=get_time_slots&target_scope=${yearFilter}`);
    const json = await res.json();
    if (json.success) {
      allTimeSlotsList = json.time_slots || [];
      renderTimeSlotsTable(allTimeSlotsList);
      populatePlannerSessionDropdown(allTimeSlotsList);
    }
  } catch (err) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger p-3">Error loading session time slots.</td></tr>`;
  }
}

window.handleSessionTypePreset = function(val) {
  const startInput = document.getElementById('time-slot-start-time');
  const endInput = document.getElementById('time-slot-end-time');
  if (!startInput || !endInput) return;

  if (val === '101') {
    // Short Tea Break / Morning Interval preset (11:00 - 11:15)
    startInput.value = '11:00';
    endInput.value = '11:15';
  } else if (val === '102') {
    // Lunch Break Hour preset (13:15 - 14:00)
    startInput.value = '13:15';
    endInput.value = '14:00';
  }
};

function renderTimeSlotsTable(slots) {
  const tbody = document.getElementById('time-slots-table-body');
  if (!tbody) return;

  if (slots.length === 0) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted p-4">No session time slots found for the selected Academic Year. Click "Add New Session Slot" above.</td></tr>`;
    return;
  }

  tbody.innerHTML = slots.map(slot => {
    const startStr = formatTime12(slot.start_time);
    const endStr = formatTime12(slot.end_time);
    const isActive = slot.is_active == 1;
    const isBreak = slot.is_break == 1 || slot.session_number == 101 || slot.session_number == 102;

    // Accurate duration calculation handling 12hr/24hr format crossings
    let durationText = '-';
    if (slot.start_time && slot.end_time) {
      let minA = getSlotMinutes(slot.start_time);
      let minB = getSlotMinutes(slot.end_time);
      let diff = minB - minA;
      if (diff < 0) diff += 24 * 60;
      durationText = `${diff} mins`;
    }

    // Target Scope badge (Year-Wise)
    let scopeBadge = '';
    if (slot.dept_code && slot.year_name) {
      scopeBadge = `<span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">🎓 ${slot.dept_code} - ${slot.year_name} (All ${slot.year_name.replace(' Year', 'Yr')} Sections)</span>`;
    } else if (slot.dept_code) {
      scopeBadge = `<span class="badge" style="background: #fef3c7; color: #92400e; font-weight: 700;">🏛️ ${slot.dept_code} Department</span>`;
    } else {
      scopeBadge = `<span class="badge" style="background: #f1f5f9; color: #475569; font-weight: 600;">🌐 All Classes (College Default)</span>`;
    }

    const slotNumLabel = isBreak ? `<span class="badge" style="background: #fff7ed; color: #c2410c; font-weight: 800;">⏸️ BREAK</span>` : `<strong style="color: var(--primary);">#${slot.session_number}</strong>`;
    const labelDisplay = isBreak 
      ? `<div style="font-weight: 700; color: #ea580c;">${formatSessionLabel(slot.label)}</div>`
      : `<div style="font-weight: 600; color: #1e293b;">${formatSessionLabel(slot.label)}</div>`;

    return `
      <tr style="${isBreak ? 'background: #fffbeb;' : ''}">
        <td>${slotNumLabel}</td>
        <td>${scopeBadge}</td>
        <td>${labelDisplay}</td>
        <td><span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 600;">${startStr}</span></td>
        <td><span class="badge" style="background: #e0e7ff; color: #3730a3; font-weight: 600;">${endStr}</span></td>
        <td><span class="text-muted text-sm font-semibold">${durationText}</span></td>
        <td>
          <span class="badge ${isActive ? 'badge-success' : 'badge-inactive'}">
            ${isActive ? 'Active' : 'Inactive'}
          </span>
        </td>
        <td>
          <div class="d-flex gap-1">
            <button class="btn btn-secondary btn-sm" onclick="openEditTimeSlotModal(${JSON.stringify(slot).replace(/"/g, '&quot;')})">
              ✏️ Edit
            </button>
            <button class="btn btn-danger btn-sm" onclick="adminDeleteTimeSlot(${slot.id})">
              🗑️
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

function populatePlannerSessionDropdown(slots) {
  const select = document.getElementById('planner-session-number');
  if (!select) return;
  
  // Filter out break slots: classes cannot be scheduled during break hours!
  const teachingSlots = slots.filter(s => s.is_active == 1 && s.is_break != 1 && s.session_number < 100 && s.slot_type !== 'tea_break' && s.slot_type !== 'lunch_break');
  if (teachingSlots.length === 0) return;

  teachingSlots.sort((a, b) => getSlotMinutes(a.start_time) - getSlotMinutes(b.start_time));

  select.innerHTML = teachingSlots.map(s => {
    const startStr = formatTime12(s.start_time);
    const endStr = formatTime12(s.end_time);
    const cleanLabel = s.label && s.label.includes('(') ? s.label.split('(')[0].trim() : (s.label || `Session ${s.session_number}`);
    const scope = s.dept_code && s.year_name ? ` [${s.dept_code} ${s.year_name}]` : '';
    return `<option value="${s.session_number}">${cleanLabel} (${startStr} - ${endStr})${scope}</option>`;
  }).join('');
}

window.openEditTimeSlotModal = function(slot) {
  document.getElementById('time-slot-id').value = slot.id;
  document.getElementById('time-slot-session-number').value = slot.session_number;
  document.getElementById('time-slot-start-time').value = (slot.start_time || '').substring(0, 5);
  document.getElementById('time-slot-end-time').value = (slot.end_time || '').substring(0, 5);
  
  const targetVal = (slot.department_id && slot.year_id) ? `${slot.department_id}_${slot.year_id}` : '';
  const targetSelect = document.getElementById('time-slot-target-scope');
  if (targetSelect) {
    targetSelect.value = targetVal;
  }

  document.getElementById('time-slot-is-active').checked = slot.is_active == 1;
  document.getElementById('time-slot-modal-title').innerText = 'Edit Session Time Slot #' + slot.session_number;
  openModal('time-slot-modal');
};

async function handleSaveTimeSlot(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  formData.set('is_active', document.getElementById('time-slot-is-active').checked ? '1' : '0');

  try {
    const res = await fetch('api/admin.php?action=save_time_slot', { 
      method: 'POST', 
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: formData 
    });
    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      closeModal('time-slot-modal');
      loadTimeSlotsData();
      loadMasterMatrix();
    } else {
      showToast(json.message || 'Error saving session time slot', 'error');
    }
  } catch (err) {
    showToast('Network error saving session time slot', 'error');
  }
}

window.adminDeleteTimeSlot = async function(id) {
  if (!confirm('Are you sure you want to delete this session time slot?')) return;
  try {
    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch('api/admin.php?action=delete_time_slot', { 
      method: 'POST', 
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: fd 
    });
    
    if (res.status === 401 || res.status === 403) {
      showToast('⚠️ Admin session expired. Please re-login to the Admin portal.', 'error');
      setTimeout(() => { window.location.href = 'admin-login.php'; }, 1500);
      return;
    }

    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      loadTimeSlotsData();
      loadMasterMatrix();
    } else {
      showToast(json.message || 'Error deleting time slot', 'error');
    }
  } catch (err) {
    showToast('Network error deleting time slot. Please refresh or check connection.', 'error');
  }
};

// 10. PDF Export Engine (Single Section or Full Master Sheet)
window.openExportPdfModal = function(defaultSecId = null) {
  const matrixDate = document.getElementById('admin-matrix-date')?.value || new Date().toISOString().split('T')[0];
  const pdfDateInput = document.getElementById('pdf-report-date');
  if (pdfDateInput) pdfDateInput.value = matrixDate;

  if (defaultSecId) {
    document.getElementById('pdf-scope-single').checked = true;
    const secSelect = document.getElementById('pdf-section-select');
    if (secSelect) secSelect.value = defaultSecId;
  }
  togglePdfScope();
  openModal('export-pdf-modal');
};

window.togglePdfScope = function() {
  const isSingle = document.getElementById('pdf-scope-single')?.checked;
  const isDept = document.getElementById('pdf-scope-dept')?.checked;

  const secWrap = document.getElementById('pdf-section-wrapper');
  const deptWrap = document.getElementById('pdf-dept-wrapper');

  if (secWrap) secWrap.style.display = isSingle ? 'block' : 'none';
  if (deptWrap) deptWrap.style.display = isDept ? 'block' : 'none';
};

window.downloadSingleSectionPdf = function(secId, displayCode, secName) {
  window.openExportPdfModal(secId);
};

window.generateAndPrintPdf = async function() {
  const date = document.getElementById('pdf-report-date')?.value || new Date().toISOString().split('T')[0];
  const isSingle = document.getElementById('pdf-scope-single')?.checked;
  const isDept = document.getElementById('pdf-scope-dept')?.checked;
  const secSelect = document.getElementById('pdf-section-select');
  const deptSelect = document.getElementById('pdf-dept-select');

  const selectedSecId = secSelect?.value;
  const selectedDept = isDept ? (deptSelect?.value || 'BCA') : 'ALL';

  closeModal('export-pdf-modal');
  showToast('📄 Preparing Print / PDF Document...', 'info');

  try {
    // Fetch fresh matrix data for this date
    const res = await fetch(`api/admin.php?action=get_master_matrix&date=${date}&department=ALL&year=0`);
    const json = await res.json();

    if (!json.success) {
      showToast('Error loading report data for PDF', 'error');
      return;
    }

    const allSections = json.sections || [];
    const timeSlots = json.time_slots || [];
    const logsMap = json.logs || {};
    const schedMap = json.schedules || {};

    let targetSections = allSections;
    let reportTitle = "FULL COLLEGE DAILY CLASS & WORKLOAD REPORT";
    let subHeader = `All Departments • Date: ${date}`;

    if (isSingle) {
      targetSections = allSections.filter(s => s.id == selectedSecId);
      if (targetSections.length > 0) {
        const targetSec = targetSections[0];
        reportTitle = `SECTION DAILY CLASS LOG & ATTENDANCE REPORT`;
        subHeader = `Section: ${targetSec.display_code} (${targetSec.section_name}) • ${targetSec.dept_code} (${targetSec.year_name}) • Date: ${date}`;
      }
    } else if (isDept) {
      targetSections = allSections.filter(s => s.dept_code === selectedDept);
      reportTitle = `${selectedDept} DEPARTMENT DAILY CLASS LOG REPORT`;
      subHeader = `Department: ${selectedDept} • Date: ${date}`;
    }

    if (targetSections.length === 0) {
      showToast('No section data found for selected criteria.', 'error');
      return;
    }

    // Build Print Window HTML
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      showToast('Pop-up blocked! Please allow pop-ups for this site to print PDF.', 'error');
      return;
    }

    // Sort time slots chronologically so breaks are central session-wise
    timeSlots.sort((a, b) => {
      const minA = getSlotMinutes(a.start_time);
      const minB = getSlotMinutes(b.start_time);
      if (minA === minB) return (a.session_number || 0) - (b.session_number || 0);
      return minA - minB;
    });

    let tableContentHtml = '';

    if (isSingle && targetSections.length === 1) {
      // Specialized Single Section Detailed Report
      const sec = targetSections[0];
      tableContentHtml = `
        <div class="sec-meta-box">
          <div class="meta-item"><strong>Department:</strong> ${sec.dept_code}</div>
          <div class="meta-item"><strong>Academic Year:</strong> ${sec.year_name}</div>
          <div class="meta-item"><strong>Semester:</strong> Sem ${sec.semester}</div>
          <div class="meta-item"><strong>Section Code:</strong> ${sec.display_code}</div>
          <div class="meta-item"><strong>Section Name:</strong> ${sec.section_name}</div>
          <div class="meta-item"><strong>Date:</strong> ${date}</div>
        </div>

        <table class="print-table">
          <thead>
            <tr>
              <th style="width: 14%;">Period / Slot</th>
              <th style="width: 14%;">Time Range</th>
              <th style="width: 22%;">Subject Name</th>
              <th style="width: 20%;">Faculty Name & Emp ID</th>
              <th style="width: 18%;">Topic Covered</th>
              <th style="width: 12%;">Class Nature</th>
            </tr>
          </thead>
          <tbody>
            ${timeSlots.map(slot => {
              const isBreak = slot.is_break == 1 || slot.session_number >= 100 || slot.slot_type === 'tea_break' || slot.slot_type === 'lunch_break';
              const startStr = formatTime12(slot.start_time);
              const endStr = formatTime12(slot.end_time);
              const labelClean = slot.label && slot.label.includes('(') ? slot.label.split('(')[0].trim() : (slot.label || `Session ${slot.session_number}`);

              if (isBreak) {
                const isLunch = slot.slot_type === 'lunch_break' || labelClean.toLowerCase().includes('lunch');
                const icon = isLunch ? '🍱' : '⏸️';
                const bLabel = isLunch ? 'Lunch Break' : 'Break';
                return `
                  <tr style="background: #fffdf5;">
                    <td class="font-bold" style="color: #b45309;">${icon} ${bLabel}</td>
                    <td style="color: #d97706; font-weight: 600;">${startStr} - ${endStr}</td>
                    <td colspan="4" style="color: #92400e; font-weight: bold; background: #fffbeb;">${icon} Interval / Break Period (Classes are not conducted)</td>
                  </tr>
                `;
              }

              const key = `${sec.id}_${slot.session_number}`;
              const log = logsMap[key];
              const sched = schedMap[key];

              if (log) {
                const nature = log.session_type === 'lab' ? '🔬 Lab' : (log.is_merged == 1 ? '👥 Merged' : '📖 Theory');
                return `
                  <tr>
                    <td class="font-bold">${labelClean}</td>
                    <td>${startStr} - ${endStr}</td>
                    <td class="font-bold" style="color: #1e1b4b;">${log.subject_name}</td>
                    <td><strong>${formatTeacherName(log.faculty_name)}</strong><br><small style="color:#64748b;">${log.employee_id || ''}</small></td>
                    <td>${log.topic_covered}</td>
                    <td><span class="badge-tag">${nature}</span></td>
                  </tr>
                `;
              } else if (sched && sched.is_no_class == 1) {
                return `
                  <tr style="background: #f8fafc;">
                    <td class="font-bold">${labelClean}</td>
                    <td>${startStr} - ${endStr}</td>
                    <td colspan="4" style="color: #dc2626; font-weight: bold;">🚫 No Class / Suspended Session (${sched.special_note || 'Official'})</td>
                  </tr>
                `;
              } else {
                return `
                  <tr style="background: #ffffff;">
                    <td class="font-bold">${labelClean}</td>
                    <td>${startStr} - ${endStr}</td>
                    <td colspan="4" style="color: #94a3b8; font-style: italic;">Slot Not Logged</td>
                  </tr>
                `;
              }
            }).join('')}
          </tbody>
        </table>
      `;
    } else {
      // Full Master Matrix / Department Sheet Layout
      tableContentHtml = `
        <table class="print-table">
          <thead>
            <tr>
              <th style="width: 140px;">Section / Dept</th>
              ${timeSlots.map(slot => {
                const isBreak = slot.is_break == 1 || slot.session_number >= 100 || slot.slot_type === 'tea_break' || slot.slot_type === 'lunch_break';
                const labelClean = slot.label && slot.label.includes('(') ? slot.label.split('(')[0].trim() : (slot.label || `Session ${slot.session_number}`);
                const startStr = formatTime12(slot.start_time);
                const endStr = formatTime12(slot.end_time);

                if (isBreak) {
                  const isLunch = slot.slot_type === 'lunch_break' || labelClean.toLowerCase().includes('lunch');
                  const icon = isLunch ? '🍱' : '⏸️';
                  const title = isLunch ? 'LUNCH BREAK' : 'BREAK';
                  return `
                    <th style="background: #fef3c7; color: #92400e; text-align: center;">
                      <div>${icon} ${title}</div>
                      <div style="font-size: 10px; font-weight: 600; color: #b45309;">${startStr} - ${endStr}</div>
                    </th>
                  `;
                }

                return `
                  <th>
                    <div>${labelClean}</div>
                    <div style="font-size: 11px; font-weight: 500; color: #475569;">${startStr} - ${endStr}</div>
                  </th>
                `;
              }).join('')}
            </tr>
          </thead>
          <tbody>
            ${targetSections.map(sec => `
              <tr>
                <td style="background: #f8fafc;">
                  <strong style="font-size: 13px; color: #1e1b4b;">${sec.display_code}</strong>
                  <div style="font-size: 11px; color: #475569;">${sec.section_name} (${sec.dept_code})</div>
                </td>
                ${timeSlots.map(slot => {
                  const isBreak = slot.is_break == 1 || slot.session_number >= 100 || slot.slot_type === 'tea_break' || slot.slot_type === 'lunch_break';
                  if (isBreak) {
                    const isLunch = slot.slot_type === 'lunch_break' || (slot.label && slot.label.toLowerCase().includes('lunch'));
                    const icon = isLunch ? '🍱' : '⏸️';
                    const title = isLunch ? 'LUNCH BREAK' : 'BREAK';
                    return `<td style="background: #fffbeb; color: #b45309; font-size: 11px; font-weight: bold; text-align: center; border-left: 1px dashed #fcd34d; border-right: 1px dashed #fcd34d;">${icon} ${title}<br><small style="color:#d97706; font-size:9px;">No Class</small></td>`;
                  }

                  const key = `${sec.id}_${slot.session_number}`;
                  const log = logsMap[key];
                  const sched = schedMap[key];

                  if (log) {
                    const nature = log.session_type === 'lab' ? '[Lab]' : (log.is_merged == 1 ? '[Merged]' : '');
                    return `
                      <td>
                        <div style="font-weight: bold; color: #1e1b4b; font-size: 12px;">${log.subject_name} ${nature}</div>
                        <div style="font-size: 11px; color: #2563eb; font-weight: 600;">👤 ${formatTeacherName(log.faculty_name)}</div>
                        <div style="font-size: 10px; color: #64748b; margin-top: 2px;">${log.topic_covered}</div>
                      </td>
                    `;
                  } else if (sched && sched.is_no_class == 1) {
                    return `<td style="background: #fef2f2; color: #dc2626; font-size: 11px; font-weight: bold;">🚫 No Class</td>`;
                  } else {
                    return `<td style="color: #cbd5e1; font-size: 11px; text-align: center;">-</td>`;
                  }
                }).join('')}
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    }

    const printHtml = `
      <!DOCTYPE html>
      <html>
      <head>
        <title>${reportTitle} - ${date}</title>
        <meta charset="utf-8">
        <style>
          @page {
            size: ${isSingle ? 'A4 portrait' : 'A4 landscape'};
            margin: 12mm 10mm 15mm 10mm;
          }
          body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 10px;
            color: #0f172a;
            background: #ffffff;
            font-size: 12px;
          }
          .header {
            text-align: center;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 10px;
            margin-bottom: 14px;
          }
          .header h1 {
            margin: 0 0 4px 0;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #0f172a;
            text-transform: uppercase;
          }
          .header p {
            margin: 0;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
          }
          .sec-meta-box {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 12px;
          }
          .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
          }
          .print-table th, .print-table td {
            border: 1px solid #94a3b8;
            padding: 7px 9px;
            vertical-align: top;
            text-align: left;
          }
          .print-table th {
            background-color: #f1f5f9;
            font-weight: 700;
            color: #0f172a;
            font-size: 12px;
          }
          .font-bold { font-weight: 700; }
          .badge-tag {
            display: inline-block;
            padding: 2px 6px;
            background: #e2e8f0;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
          }
          .footer-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
          }
          .sign-block {
            text-align: center;
            border-top: 1px dashed #475569;
            width: 180px;
            padding-top: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
          }
          @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
          }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>${reportTitle}</h1>
          <p>${subHeader}</p>
        </div>

        ${tableContentHtml}

        <div class="footer-signatures">
          <div class="sign-block">Faculty / Incharge Signature</div>
          <div class="sign-block">Verified by HOD</div>
          <div class="sign-block">Principal / Academic Dean</div>
        </div>

        <div class="no-print" style="margin-top: 20px; text-align: center;">
          <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; font-weight: bold; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">
            🖨️ Click to Print / Save as PDF
          </button>
        </div>
      </body>
      </html>
    `;

    printWindow.document.open();
    printWindow.document.write(printHtml);
    printWindow.document.close();

    // Auto-trigger print dialog
    setTimeout(() => {
      printWindow.focus();
      printWindow.print();
    }, 500);

    showToast('✅ Print / PDF dialog ready!', 'success');
  } catch (err) {
    showToast('Failed to generate PDF document.', 'error');
  }
};
