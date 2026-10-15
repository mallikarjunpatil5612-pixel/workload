/**
 * Faculty Portal Interactive Logic
 * Dynamic Daily Class Table, Auto-Populated Logging, Workload Metrics
 */

document.addEventListener('DOMContentLoaded', () => {
  initFacultyPortal();
});

let currentFacultyData = {};
let allAvailableSections = [];

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

function initFacultyPortal() {
  const today = new Date().toISOString().split('T')[0];
  const dateInput = document.getElementById('faculty-table-date');
  if (dateInput) {
    dateInput.value = today;
  }

  // Event Listeners for Filters
  document.getElementById('faculty-dept-select')?.addEventListener('change', loadDynamicDayTable);
  document.getElementById('faculty-year-select')?.addEventListener('change', loadDynamicDayTable);
  document.getElementById('faculty-table-date')?.addEventListener('change', loadDynamicDayTable);

  // Class Log Form Submission
  document.getElementById('class-log-form')?.addEventListener('submit', handleClassLogSubmit);

  // Merged checkbox toggle
  document.getElementById('is_merged')?.addEventListener('change', function() {
    const mergeSectionGroup = document.getElementById('merge-section-wrapper');
    if (mergeSectionGroup) {
      mergeSectionGroup.style.display = this.checked ? 'block' : 'none';
    }
  });

  // Workload range filter
  document.getElementById('btn-filter-workload')?.addEventListener('click', loadWorkloadStats);

  // Initial Data Loads
  loadDynamicDayTable();
  loadWorkloadStats();
}

// 1. Fetch & Render Dynamic Daily Class Table
async function loadDynamicDayTable() {
  const deptId = document.getElementById('faculty-dept-select')?.value || 'BOTH';
  const yearId = document.getElementById('faculty-year-select')?.value || 1;
  const date   = document.getElementById('faculty-table-date')?.value || new Date().toISOString().split('T')[0];

  const tableContainer = document.getElementById('dynamic-table-content');
  if (!tableContainer) return;

  tableContainer.innerHTML = `
    <div style="text-align: center; padding: 2.5rem;" class="text-muted">
      <div class="pulse">⏳ Loading Class Schedule & Logs...</div>
    </div>
  `;

  try {
    const res = await fetch(`api/faculty.php?action=get_day_table&department_id=${deptId}&year_id=${yearId}&date=${date}`);
    const json = await res.json();

    if (!json.success) {
      tableContainer.innerHTML = `<div class="alert alert-error">Failed to load table: ${json.message}</div>`;
      return;
    }

    currentFacultyData = {
      id: json.current_faculty_id,
      name: json.current_faculty_name
    };
    allAvailableSections = json.all_sections || [];

    renderDayTableMatrix(json.data, json.all_sections);
  } catch (err) {
    tableContainer.innerHTML = `<div class="alert alert-error">Error connecting to server.</div>`;
  }
}

// Render Table Matrix (Cleaned of room display)
function renderDayTableMatrix(data, allSections) {
  const tableContainer = document.getElementById('dynamic-table-content');
  const sections = data.sections || [];
  const timeSlots = data.time_slots || [];
  const logsMap = data.logs || {};
  const schedMap = data.schedules || {};
  const specialEvent = data.special_event;

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

  // Special Notice / No Class Day alert banner if any
  if (specialEvent) {
    html += `
      <div class="alert alert-warning mb-3">
        <strong>📢 Announcement: ${specialEvent.title}</strong>
        <p class="text-sm mt-1">${specialEvent.description || 'Special student session / event scheduled for today.'}</p>
      </div>
    `;
  }

  if (sections.length === 0) {
    tableContainer.innerHTML = `
      <div class="text-center text-muted p-4">
        No active sections found for this selected Department & Year.
      </div>
    `;
    return;
  }

  html += `
    <div class="mobile-swipe-hint">
      <span>👉</span> Swipe table horizontally to see all sessions &amp; break <span>👈</span>
    </div>
    <div class="table-responsive">
      <table class="matrix-table">
        <thead>
          <tr>
            <th style="min-width: 140px;">Section / Class</th>
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
          <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">${sec.display_code}</div>
          <div class="text-muted text-sm">${sec.section_name}</div>
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
        // Class already taken & logged
        const isMine = log.faculty_id == currentFacultyData.id;
        const typeBadge = log.session_type === 'lab' 
          ? '<span class="badge badge-lab">🔬 Lab</span>' 
          : (log.is_merged == 1 ? '<span class="badge badge-merged">👥 Merged</span>' : '<span class="badge badge-theory">📖 Theory</span>');

        html += `
          <div class="slot-card filled ${log.session_type === 'lab' ? 'lab' : ''} ${log.is_merged == 1 ? 'merged' : ''}"
               onclick="openEditLogModal(${JSON.stringify(log).replace(/"/g, '&quot;')}, ${sec.id}, ${slot.session_number}, '${slot.start_time}', '${slot.end_time}', '${sec.display_code}')">
            <div class="slot-header">
              <span>Session ${slot.session_number}</span>
              ${typeBadge}
            </div>
            <div class="slot-subject">${log.subject_name}</div>
            <div class="slot-topic" title="${log.topic_covered}">${log.topic_covered}</div>
            <div class="slot-faculty">
              <span>👤 ${(log.faculty_name || '').replace(/Admin\s*Mallikarjun/gi, 'Prof. Mallikarjun Patil').replace(/^Admin\s+/gi, 'Prof. ')} ${isMine ? '(You)' : ''}</span>
            </div>
          </div>
        `;
      } else if (sched && sched.is_no_class == 1) {
        html += `
          <div class="slot-card" style="background: #fff1f2; border-color: #fecdd3; cursor: default;">
            <div class="slot-header text-danger font-bold">🚫 No Class</div>
            <div class="text-sm text-muted mt-1">${sched.special_note || 'Session Suspended'}</div>
          </div>
        `;
      } else {
        // Blank slot -> Faculty can click to enter what they did!
        const preAssigned = sched && sched.expected_subject ? `<div style="font-size: 0.72rem; color: #4f46e5; font-weight: 600;">Plan: ${sched.expected_subject}</div>` : '';

        html += `
          <div class="slot-card" onclick="openClassLogModal(${sec.id}, '${sec.display_code}', ${slot.session_number}, '${slot.start_time}', '${slot.end_time}')">
            <div class="slot-header">
              <span>Session ${slot.session_number}</span>
              <span class="badge" style="background: #f1f5f9; color: #64748b;">Available</span>
            </div>
            ${preAssigned}
            <div class="slot-empty-btn">
              <span>➕ Log Class</span>
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
  `;

  tableContainer.innerHTML = html;
}

// 2. Open Logging Modal
window.openClassLogModal = function(sectionId, sectionCode, sessionNo, startTime, endTime) {
  // Prevent logging class during break periods
  if (sessionNo >= 100) {
    showToast('Break time cannot be attended or logged as a class session.', 'warning');
    return;
  }
  const deptId = document.getElementById('faculty-dept-select').value;
  const yearId = document.getElementById('faculty-year-select').value;
  const date   = document.getElementById('faculty-table-date').value;

  const facInput = document.getElementById('modal-faculty-name');
  if (facInput) facInput.value = currentFacultyData.name || 'Faculty Member';
  document.getElementById('modal-class-date').value = date;
  document.getElementById('modal-department-id').value = deptId;
  document.getElementById('modal-year-id').value = yearId;
  document.getElementById('modal-section-id').value = sectionId;
  document.getElementById('modal-session-number').value = sessionNo;
  
  const secDisp = document.getElementById('modal-section-display');
  if (secDisp) secDisp.innerText = `${sectionCode} - Session ${sessionNo} (${formatTime12(startTime)} - ${formatTime12(endTime)})`;
  
  const modalTitle = document.getElementById('class-log-modal-title');
  if (modalTitle) modalTitle.innerText = `Record Class: ${sectionCode} - Session ${sessionNo}`;

  document.getElementById('modal-start-time').value = startTime;
  document.getElementById('modal-end-time').value = endTime;

  // Reset fields
  document.getElementById('subject_name').value = '';
  document.getElementById('topic_covered').value = '';
  document.getElementById('session_type').value = 'theory';
  document.getElementById('is_merged').checked = false;
  document.getElementById('merge-section-wrapper').style.display = 'none';

  // Populate merge dropdown
  populateMergeDropdown(sectionId);

  openModal('class-log-modal');
};

// Edit / View existing log
window.openEditLogModal = function(log, sectionId, sessionNo, startTime, endTime, sectionCode) {
  openClassLogModal(sectionId, sectionCode, sessionNo, startTime, endTime);

  const modalTitle = document.getElementById('class-log-modal-title');
  if (modalTitle) modalTitle.innerText = `Edit Class Log: ${sectionCode} - Session ${sessionNo}`;

  document.getElementById('subject_name').value = log.subject_name;
  document.getElementById('topic_covered').value = log.topic_covered;
  document.getElementById('session_type').value = log.session_type || 'theory';
  document.getElementById('is_merged').checked = log.is_merged == 1;

  if (log.is_merged == 1) {
    document.getElementById('merge-section-wrapper').style.display = 'block';
    document.getElementById('merged_with_section_id').value = log.merged_with_section_id || '';
  }
};

function populateMergeDropdown(currentSecId) {
  const select = document.getElementById('merged_with_section_id');
  if (!select) return;

  select.innerHTML = '<option value="">-- Select Section to Combine With --</option>';
  allAvailableSections.forEach(sec => {
    if (sec.id != currentSecId) {
      select.innerHTML += `<option value="${sec.id}">${sec.dept_code} (${sec.year_name}) - ${sec.display_code}</option>`;
    }
  });
}

// 3. Handle Submit
async function handleClassLogSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);

  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.innerText = 'Saving...';

  try {
    const res = await fetch('api/faculty.php?action=save_log', {
      method: 'POST',
      body: formData
    });
    const json = await res.json();

    if (json.success) {
      showToast(json.message, 'success');
      closeModal('class-log-modal');
      loadDynamicDayTable();
      loadWorkloadStats();
    } else {
      showToast(json.message || 'Failed to save log', 'error');
    }
  } catch (err) {
    showToast('Network error while saving class log', 'error');
  } finally {
    submitBtn.disabled = false;
    submitBtn.innerText = 'Save & Update Class Log';
  }
}

function formatDateSafe(dateStr) {
  if (!dateStr) return '-';
  if (typeof window.formatDate === 'function') {
    return window.formatDate(dateStr);
  }
  try {
    const parts = dateStr.split('-');
    if (parts.length === 3) {
      const d = new Date(parts[0], parts[1] - 1, parts[2]);
      return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
  } catch (e) {}
  return dateStr;
}

// 4. Tab Switching for Faculty Dashboard
window.switchFacultyTab = function(tabId, el) {
  document.querySelectorAll('.faculty-tab-content').forEach(tab => {
    tab.style.display = 'none';
  });
  const target = document.getElementById(tabId);
  if (target) {
    target.style.display = 'block';
  }

  document.querySelectorAll('.tab-pills .tab-pill').forEach(btn => {
    btn.classList.remove('active');
  });
  if (el) {
    el.classList.add('active');
  }

  // Trigger appropriate refresh if entering workload tab
  if (tabId === 'tab-workload-calc') {
    loadWorkloadStats();
  }
};

window.openWorkloadTab = function() {
  const pill = document.getElementById('pill-workload-calc');
  window.switchFacultyTab('tab-workload-calc', pill);
  document.getElementById('tab-workload-calc')?.scrollIntoView({ behavior: 'smooth' });
};

// 5. Workload Date Range Presets
window.setFacultyWorkloadPreset = function(type) {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');

  const startInput = document.getElementById('workload-start-date');
  const endInput = document.getElementById('workload-end-date');

  if (type === 'today') {
    const todayStr = `${y}-${m}-${d}`;
    if (startInput) startInput.value = todayStr;
    if (endInput) endInput.value = todayStr;
  } else if (type === 'week') {
    const day = now.getDay();
    const diffToMon = now.getDate() - day + (day === 0 ? -6 : 1);
    const mon = new Date(now.getFullYear(), now.getMonth(), diffToMon);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);

    const monStr = `${mon.getFullYear()}-${String(mon.getMonth()+1).padStart(2,'0')}-${String(mon.getDate()).padStart(2,'0')}`;
    const sunStr = `${sun.getFullYear()}-${String(sun.getMonth()+1).padStart(2,'0')}-${String(sun.getDate()).padStart(2,'0')}`;

    if (startInput) startInput.value = monStr;
    if (endInput) endInput.value = sunStr;
  } else if (type === 'month') {
    const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
    if (startInput) startInput.value = `${y}-${m}-01`;
    if (endInput) endInput.value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
  } else if (type === 'last_month') {
    const prevMonthLastDay = new Date(y, now.getMonth(), 0);
    const prevY = prevMonthLastDay.getFullYear();
    const prevM = String(prevMonthLastDay.getMonth() + 1).padStart(2, '0');
    const prevLastDate = String(prevMonthLastDay.getDate()).padStart(2, '0');

    if (startInput) startInput.value = `${prevY}-${prevM}-01`;
    if (endInput) endInput.value = `${prevY}-${prevM}-${prevLastDate}`;
  }

  loadWorkloadStats();
};

let lastWorkloadPayload = null;

// 6. Calculate Personal Workload Statistics & Detailed Itemized Logs
async function loadWorkloadStats() {
  const startDate = document.getElementById('workload-start-date')?.value || '';
  const endDate   = document.getElementById('workload-end-date')?.value || '';

  // Update Range Label display
  const rangeLabel = document.getElementById('stat-calc-range-label');
  if (rangeLabel && startDate && endDate) {
    rangeLabel.innerText = `${formatDateSafe(startDate)} to ${formatDateSafe(endDate)}`;
  }

  const statementBody = document.getElementById('workload-statement-body');
  if (statementBody) {
    statementBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted p-4"><div class="pulse">⏳ Calculating Workload & Retrieving Logs...</div></td></tr>`;
  }

  const calcBtn = document.getElementById('btn-filter-workload');
  if (calcBtn) {
    calcBtn.disabled = true;
    calcBtn.innerHTML = '<span>⏳</span> Calculating...';
  }

  try {
    const res = await fetch(`api/faculty.php?action=get_my_workload&start_date=${startDate}&end_date=${endDate}`);
    const json = await res.json();

    if (json.success) {
      lastWorkloadPayload = json;

      // Top Welcome Overview Cards
      if (document.getElementById('stat-today-hours')) {
        document.getElementById('stat-today-hours').innerText = `${json.stats.today_hours} hrs`;
      }
      if (document.getElementById('stat-weekly-hours')) {
        document.getElementById('stat-weekly-hours').innerText = `${json.stats.weekly_hours} hrs`;
      }
      if (document.getElementById('stat-monthly-hours')) {
        document.getElementById('stat-monthly-hours').innerText = `${json.stats.monthly_hours} hrs`;
      }

      // Workload Calculator KPI Breakdown Cards
      const c = json.stats.custom_stats || {};
      const totalHours = c.total_hours !== undefined ? c.total_hours : 0;
      const totalEntries = c.total_entries !== undefined ? c.total_entries : 0;
      const theoryHours = c.regular_sessions !== undefined ? c.regular_sessions : 0;
      const labHours = c.lab_sessions !== undefined ? c.lab_sessions : 0;
      const mergedHours = c.merged_sessions !== undefined ? c.merged_sessions : 0;

      if (document.getElementById('stat-custom-hours')) {
        document.getElementById('stat-custom-hours').innerText = `${totalHours} hrs`;
      }
      if (document.getElementById('stat-custom-entries')) {
        document.getElementById('stat-custom-entries').innerText = `(${totalEntries} classes)`;
      }

      if (document.getElementById('stat-calc-total-hours')) {
        document.getElementById('stat-calc-total-hours').innerText = `${totalHours} hrs`;
      }
      if (document.getElementById('stat-calc-theory-hours')) {
        document.getElementById('stat-calc-theory-hours').innerText = `${theoryHours} hrs`;
      }
      if (document.getElementById('stat-calc-lab-hours')) {
        document.getElementById('stat-calc-lab-hours').innerText = `${labHours} hrs`;
      }
      if (document.getElementById('stat-calc-merged-hours')) {
        document.getElementById('stat-calc-merged-hours').innerText = `${mergedHours} hrs`;
      }
      if (document.getElementById('stat-calc-total-entries')) {
        document.getElementById('stat-calc-total-entries').innerText = `${totalEntries}`;
      }

      // Render Itemized Logs for Calculated Period
      renderWorkloadStatementTable(json.range_logs || []);

      // Render General Recent Logs
      renderRecentLogs(json.recent_logs || []);
    } else {
      if (statementBody) {
        statementBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger p-4">Failed to calculate workload: ${json.message || 'Server error'}</td></tr>`;
      }
    }
  } catch (err) {
    console.error(err);
    if (statementBody) {
      statementBody.innerHTML = `<tr><td colspan="7" class="text-center text-danger p-4">Error connecting to server.</td></tr>`;
    }
  } finally {
    if (calcBtn) {
      calcBtn.disabled = false;
      calcBtn.innerHTML = '<span>⚡</span> Calculate Workload';
    }
  }
}

function renderWorkloadStatementTable(logs) {
  const container = document.getElementById('workload-statement-body');
  if (!container) return;

  if (!logs || logs.length === 0) {
    container.innerHTML = `
      <tr>
        <td colspan="7" class="text-center text-muted p-4">
          <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">📂</div>
          <strong>No teaching sessions recorded for this selected range.</strong>
          <p class="text-xs text-muted mt-1">Try selecting a different date range or choose "This Month" / "This Week".</p>
        </td>
      </tr>
    `;
    return;
  }

  container.innerHTML = logs.map(l => {
    const credits = parseFloat(l.workload_credits || 0);
    const creditBadge = credits > 0 
      ? `<strong style="color: #16a34a; font-size: 0.95rem;">+${credits.toFixed(1)} hr</strong>`
      : `<span class="text-muted text-xs" title="Secondary section of combined class (primary section credited 1.0 hr)">0.0 hr (combined)</span>`;

    let badgeClass = 'badge-theory';
    let badgeText = (l.session_type || 'theory').toUpperCase();
    if (l.session_type === 'lab') {
      badgeClass = 'badge-lab';
    } else if (l.is_merged == 1) {
      badgeClass = 'badge-merged';
      badgeText = 'COMBINED';
    }

    const sectionDisplay = l.merged_section_code 
      ? `${l.section_code} + ${l.merged_section_code}` 
      : l.section_code;

    return `
      <tr>
        <td><strong>${formatDateSafe(l.log_date)}</strong></td>
        <td>
          <span class="badge ${l.dept_code === 'BCA' ? 'badge-bca' : 'badge-bcom'}">${sectionDisplay}</span>
          <div class="text-xs text-muted">${l.dept_code || ''} • ${l.year_name || ''}</div>
        </td>
        <td>
          <div style="font-weight: 600;">Session ${l.session_number}</div>
          <div class="text-xs text-muted">${formatTime12(l.start_time)} - ${formatTime12(l.end_time)}</div>
        </td>
        <td><strong>${l.subject_name || '-'}</strong></td>
        <td style="max-width: 280px; word-break: break-word;">${l.topic_covered || '-'}</td>
        <td>
          <span class="badge ${badgeClass}">${badgeText}</span>
        </td>
        <td style="text-align: right; padding-right: 1.5rem;">
          ${creditBadge}
        </td>
      </tr>
    `;
  }).join('');
}

function renderRecentLogs(logs) {
  const container = document.getElementById('faculty-recent-logs');
  if (!container) return;

  if (!logs || logs.length === 0) {
    container.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No recent class logs found.</td></tr>';
    return;
  }

  container.innerHTML = logs.map(l => `
    <tr>
      <td><strong>${formatDateSafe(l.log_date)}</strong></td>
      <td><span class="badge ${l.dept_code === 'BCA' ? 'badge-bca' : 'badge-bcom'}">${l.section_code}</span></td>
      <td>Session ${l.session_number} (${formatTime12(l.start_time)} - ${formatTime12(l.end_time)})</td>
      <td><strong>${l.subject_name}</strong></td>
      <td class="text-muted">${l.topic_covered}</td>
      <td>
        <span class="badge ${l.session_type === 'lab' ? 'badge-lab' : (l.is_merged == 1 ? 'badge-merged' : 'badge-theory')}">
          ${(l.session_type || 'theory').toUpperCase()}
        </span>
      </td>
    </tr>
  `).join('');
}

// 7. Print Official Faculty Workload Statement
window.printFacultyWorkloadStatement = function() {
  if (!lastWorkloadPayload || !lastWorkloadPayload.stats) {
    window.print();
    return;
  }

  const fac = lastWorkloadPayload.faculty || {};
  const stats = lastWorkloadPayload.stats.custom_stats || {};
  const logs = lastWorkloadPayload.range_logs || [];
  const startDate = document.getElementById('workload-start-date')?.value || '';
  const endDate = document.getElementById('workload-end-date')?.value || '';

  const printWin = window.open('', '_blank', 'width=920,height=750');
  if (!printWin) {
    window.print();
    return;
  }

  const rowsHtml = logs.map((l, idx) => {
    const credits = parseFloat(l.workload_credits || 0);
    const sectionDisplay = l.merged_section_code ? `${l.section_code} + ${l.merged_section_code}` : l.section_code;
    return `
      <tr>
        <td style="text-align: center;">${idx + 1}</td>
        <td>${formatDateSafe(l.log_date)}</td>
        <td>${sectionDisplay} (${l.dept_code || ''})</td>
        <td>Session ${l.session_number} (${formatTime12(l.start_time)} - ${formatTime12(l.end_time)})</td>
        <td><strong>${l.subject_name || '-'}</strong></td>
        <td>${l.topic_covered || '-'}</td>
        <td style="text-transform: capitalize;">${l.is_merged == 1 ? 'Combined' : (l.session_type || 'Theory')}</td>
        <td style="text-align: right; font-weight: bold;">${credits.toFixed(1)} hr</td>
      </tr>
    `;
  }).join('');

  printWin.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Faculty Workload Statement - ${fac.name || 'Faculty'}</title>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #1e293b; padding: 25px; margin: 0; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .header p { margin: 4px 0 0; font-size: 13px; color: #475569; }
        .title-badge { font-weight: bold; font-size: 15px; margin-top: 8px; color: #4338ca; text-decoration: underline; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 18px; font-size: 13px; background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .summary-box { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; text-align: center; }
        .sum-card { border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px; background: #ffffff; }
        .sum-card h4 { margin: 0; font-size: 18px; color: #4338ca; }
        .sum-card p { margin: 4px 0 0; font-size: 11px; color: #64748b; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; font-size: 11.5px; margin-bottom: 25px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-weight: 700; }
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 15px; font-size: 13px; }
        .sig-line { width: 220px; border-top: 1px dashed #475569; text-align: center; padding-top: 6px; font-weight: 600; }
        @media print {
          body { padding: 0; }
          button { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <h1>COLLEGE OF COMPUTER APPLICATIONS & COMMERCE</h1>
        <p>Affiliated to State University • Recognized by Government of Karnataka</p>
        <div class="title-badge">OFFICIAL FACULTY WORKLOAD STATEMENT & LOG REPORT</div>
      </div>

      <div class="meta-grid">
        <div>
          <strong>Faculty Name:</strong> ${fac.name || 'Faculty Member'}<br>
          <strong>Employee ID:</strong> ${fac.employee_id || '-'}<br>
          <strong>Designation:</strong> ${fac.designation || 'Faculty Teacher'}
        </div>
        <div>
          <strong>Department:</strong> ${fac.department || 'BCA / BCOM'}<br>
          <strong>Statement Period:</strong> ${formatDateSafe(startDate)} to ${formatDateSafe(endDate)}<br>
          <strong>Date Generated:</strong> ${new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
        </div>
      </div>

      <div class="summary-box">
        <div class="sum-card">
          <h4>${stats.total_hours || 0} hrs</h4>
          <p>Total Workload</p>
        </div>
        <div class="sum-card">
          <h4>${stats.regular_sessions || 0} hrs</h4>
          <p>Theory Sessions</p>
        </div>
        <div class="sum-card">
          <h4>${stats.lab_sessions || 0} hrs</h4>
          <p>Practical Labs</p>
        </div>
        <div class="sum-card">
          <h4>${stats.merged_sessions || 0} hrs</h4>
          <p>Combined Classes (1 hr each)</p>
        </div>
      </div>

      <table>
        <thead>
          <tr>
            <th style="width: 25px;">#</th>
            <th>Date</th>
            <th>Section</th>
            <th>Timing</th>
            <th>Subject</th>
            <th>Topic Covered</th>
            <th>Type</th>
            <th style="text-align: right;">Credits</th>
          </tr>
        </thead>
        <tbody>
          ${rowsHtml || '<tr><td colspan="8" style="text-align: center;">No class logs found in this period.</td></tr>'}
        </tbody>
      </table>

      <div class="signatures">
        <div class="sig-line">Faculty Signature</div>
        <div class="sig-line">HOD / Department Coordinator</div>
        <div class="sig-line">Principal / Academic Dean</div>
      </div>

      <script>
        window.onload = function() {
          window.print();
        };
      <\/script>
    </body>
    </html>
  `);
  printWin.document.close();
};
