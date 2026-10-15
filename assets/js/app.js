/**
 * Common App Utilities, Toast Notifications & Modal Dialogs
 */

// Toast Notifications
window.showToast = function(message, type = 'success') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  
  const icon = type === 'success' ? '✓' : (type === 'error' ? '✕' : 'ℹ');
  toast.innerHTML = `
    <span style="font-weight: bold;">${icon}</span>
    <span>${message}</span>
  `;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
};

// Modal Open/Close Helpers
window.openModal = function(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
};

window.closeModal = function(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = '';
  }
};

// Global Logout Handler
window.handleLogout = async function() {
  handlePortalLogout('faculty');
};

window.handlePortalLogout = async function(role = 'faculty') {
  if (!confirm('Are you sure you want to log out?')) return;
  const portal = role === 'admin' ? 'admin' : 'faculty';
  try {
    const res = await fetch(`api/auth.php?action=logout&portal=${portal}`);
    const data = await res.json();
    if (data.success) {
      window.location.href = data.redirect || (portal === 'admin' ? 'admin-login.php' : 'faculty-login.php');
    }
  } catch (err) {
    window.location.href = portal === 'admin' ? 'admin-login.php' : 'faculty-login.php';
  }
};

// Format Date YYYY-MM-DD
window.formatDate = function(dateString) {
  if (!dateString) return '';
  const d = new Date(dateString);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

// Convert 24-hour time string ("13:25:00", "13:25", "9:00") to 12-hour format ("01:25 PM", "09:00 AM")
window.formatTime12 = function(timeStr) {
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
};

// Convert time range to 12-hour format ("08:30 AM - 09:25 AM")
window.formatTimeRange12 = function(start, end) {
  if (!start && !end) return '-';
  if (!start) return window.formatTime12(end);
  if (!end) return window.formatTime12(start);
  return `${window.formatTime12(start)} - ${window.formatTime12(end)}`;
};

// Format any embedded time ranges inside session labels into 12-hour AM/PM
window.formatSessionLabel = function(label) {
  if (!label) return '';
  return String(label).replace(/\(?(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})(\s*[AP]M)?\)?/gi, (match, t1, t2) => {
    return `(${window.formatTime12(t1)} - ${window.formatTime12(t2)})`;
  });
};

