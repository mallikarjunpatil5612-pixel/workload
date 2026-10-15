<?php
/**
 * College Gateway - Separate Portals Entry Hub
 */
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    $user = get_logged_in_user();
    if ($user['role'] === 'admin') {
        header("Location: admin-dashboard.php");
    } else {
        header("Location: faculty-dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>College Class & Workload Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .portal-selection-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-top: 2rem;
    }
    .portal-card {
      background: white;
      border: 2px solid var(--border-color);
      border-radius: var(--radius-lg);
      padding: 2rem;
      text-align: center;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .portal-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 30px -10px rgba(0,0,0,0.1);
    }
    .portal-card.faculty-portal:hover {
      border-color: var(--primary);
    }
    .portal-card.admin-portal:hover {
      border-color: #dc2626;
    }
    .portal-card-icon {
      width: 72px;
      height: 72px;
      border-radius: 20px;
      margin: 0 auto 1.25rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
    }
    .faculty-icon-bg {
      background: linear-gradient(135deg, #6366f1, #0ea5e9);
      color: white;
      box-shadow: 0 10px 20px rgba(99, 102, 241, 0.25);
    }
    .admin-icon-bg {
      background: linear-gradient(135deg, #ef4444, #f59e0b);
      color: white;
      box-shadow: 0 10px 20px rgba(239, 68, 68, 0.25);
    }
    @media (max-width: 680px) {
      .portal-selection-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="auth-page">

  <div class="auth-container" style="max-width: 760px;">
    <div class="glass-panel" style="padding: 2.5rem 2rem;">
      
      <div class="brand-header text-center">
        <div class="brand-icon pulse">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
          </svg>
        </div>
        <h2 class="title-gradient" style="font-size: 1.85rem;">College Class & Workload ERP</h2>
        <p class="subtitle" style="margin-bottom: 0.5rem;">Secure Portal Gateway • Restricted Access</p>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 6px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; margin-top: 6px;">
          <span>🔒</span> Strictly Restricted: Only Registered Faculty & Administrators Can Access
        </div>
      </div>

      <!-- 2 Distinct Portal Cards -->
      <div class="portal-selection-grid">
        
        <!-- 1. Faculty / Teachers Portal -->
        <div class="portal-card faculty-portal">
          <div>
            <div class="portal-card-icon faculty-icon-bg">
              👨‍🏫
            </div>
            <h3 style="font-size: 1.35rem; color: #1e1b4b; margin-bottom: 0.4rem;">Teacher / Faculty Portal</h3>
            <p class="text-muted text-sm mb-3">
              For BCA & BCOM Teaching Faculty to log daily classes, select sessions, record covered topics, and view personal workload.
            </p>
            <ul style="text-align: left; font-size: 0.82rem; color: var(--text-muted); list-style: none; padding: 0; margin-bottom: 1.5rem;">
              <li style="margin-bottom: 6px;">✔ Dynamic Day Class Sheet</li>
              <li style="margin-bottom: 6px;">✔ 1-Click Session Logger (Auto-Profile)</li>
              <li style="margin-bottom: 6px;">✔ Combined / Merged Class Support (1 hr)</li>
              <li>✔ Daily & Monthly Workload Tracker</li>
            </ul>
          </div>
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <a href="faculty-login.php" class="btn btn-primary btn-lg w-100 glow-btn" style="text-align: center; text-decoration: none;">
              Teacher Sign In &rarr;
            </a>
            <a href="faculty-login.php?action=register" class="btn btn-secondary btn-sm w-100" style="text-align: center; text-decoration: none; font-size: 0.85rem;">
              ✨ Register New Faculty Account
            </a>
          </div>
        </div>

        <!-- 2. Admin & Management Portal -->
        <div class="portal-card admin-portal">
          <div>
            <div class="portal-card-icon admin-icon-bg">
              🛡️
            </div>
            <h3 style="font-size: 1.35rem; color: #1e1b4b; margin-bottom: 0.4rem;">Admin Command Center</h3>
            <p class="text-muted text-sm mb-3">
              For Principal, Academic Deans & HODs to monitor master class matrix, calculate total faculty workloads & export reports.
            </p>
            <ul style="text-align: left; font-size: 0.82rem; color: var(--text-muted); list-style: none; padding: 0; margin-bottom: 1.5rem;">
              <li style="margin-bottom: 6px;">✔ Master College Daily Class Matrix</li>
              <li style="margin-bottom: 6px;">✔ Section Drilldown Audit (e.g. 5B1)</li>
              <li style="margin-bottom: 6px;">✔ Faculty Workload Calculation Engine</li>
              <li>✔ 📸 WhatsApp / Group Image Generator</li>
            </ul>
          </div>
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <a href="admin-login.php" class="btn btn-lg w-100" style="background: #dc2626; color: white; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.3); text-align: center; text-decoration: none;">
              Admin Sign In &rarr;
            </a>
            <a href="admin-login.php?action=register" class="btn btn-secondary btn-sm w-100" style="text-align: center; text-decoration: none; font-size: 0.85rem;">
              ✨ Register Administrator Account
            </a>
          </div>
        </div>

      </div>

      <div class="text-center mt-4">
        <a href="install.php" class="text-muted text-sm">🛠️ System Setup / Database Initializer</a>
      </div>

    </div>
  </div>

  <div id="toast-container"></div>
  <script src="assets/js/app.js"></script>
</body>
</html>
