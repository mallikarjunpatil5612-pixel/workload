<?php
/**
 * Dedicated Teacher & Faculty Portal Authentication
 * Supports: Sign In (Email / Employee ID + Password) and Faculty Registration
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

if (is_faculty_logged_in()) {
    header("Location: faculty-dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Portal - Sign In & Faculty Registration</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .auth-tabs {
      display: flex;
      background: #f1f5f9;
      padding: 4px;
      border-radius: 12px;
      margin-bottom: 22px;
      gap: 4px;
    }
    .auth-tab-btn {
      flex: 1;
      padding: 10px 12px;
      font-size: 0.92rem;
      font-weight: 700;
      border: none;
      background: transparent;
      color: #64748b;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .auth-tab-btn.active {
      background: #ffffff;
      color: #4f46e5;
      box-shadow: 0 2px 8px rgba(79, 70, 229, 0.15);
    }
    .dept-selector-group {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 6px;
    }
    @media (max-width: 480px) {
      .dept-selector-group {
        grid-template-columns: 1fr;
      }
    }
    .dept-radio-label {
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      padding: 10px;
      text-align: center;
      cursor: pointer;
      background: #ffffff;
      transition: all 0.2s;
      font-weight: 600;
      font-size: 0.88rem;
      color: #334155;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 2px;
    }
    .dept-radio-label input {
      display: none;
    }
    .dept-radio-label.selected,
    .dept-radio-label:hover {
      border-color: #6366f1;
      background: #eef2ff;
      color: #4338ca;
    }
    .dept-radio-label.selected {
      box-shadow: 0 0 0 2px #6366f1;
    }
    .password-toggle-wrapper {
      position: relative;
    }
    .password-toggle-wrapper .toggle-pwd-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      color: #94a3b8;
      padding: 0;
    }
  </style>
</head>
<body class="auth-page">

  <div class="auth-container" style="max-width: 480px; margin: 40px auto; padding: 0 16px;">
    <div class="auth-card glass-panel" style="border-top: 4px solid #4f46e5; padding: 32px 28px; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.12);">
      
      <!-- Brand Header -->
      <div class="brand-header text-center" style="margin-bottom: 22px;">
        <div class="brand-icon" style="background: linear-gradient(135deg, #4f46e5, #06b6d4); width: 58px; height: 58px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 12px; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);">
          👨‍🏫
        </div>
        <h2 class="title-gradient" id="teacher-portal-title" style="background: linear-gradient(135deg, #1e1b4b, #4f46e5); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.55rem; font-weight: 800;">Teacher Portal</h2>
        <p class="subtitle" id="teacher-portal-subtitle" style="color: #64748b; font-size: 0.88rem; margin-top: 4px;">Daily Class Logging, Time Matrix & Workload Management</p>
      </div>

      <!-- Auth Switcher Tabs -->
      <div class="auth-tabs">
        <button type="button" class="auth-tab-btn active" id="tab-btn-login" onclick="switchAuthTab('login')">
          🔑 Sign In
        </button>
        <button type="button" class="auth-tab-btn" id="tab-btn-register" onclick="switchAuthTab('register')">
          ✨ Register Faculty
        </button>
      </div>

      <!-- 1. CLEAN SIGN IN FORM -->
      <form id="faculty-login-form">
        <input type="hidden" name="portal" value="faculty">

        <div class="form-group mb-3">
          <label class="form-label font-bold" for="login-ident">Email Address or Employee ID</label>
          <input type="text" id="login-ident" name="email" class="form-control w-100" placeholder="e.g. chethu@12gmail.com or FAC110" required autocomplete="username">
        </div>

        <div class="form-group mb-3">
          <label class="form-label font-bold" for="login-password">Password</label>
          <div class="password-toggle-wrapper">
            <input type="password" id="login-password" name="password" class="form-control w-100" placeholder="••••••••" required autocomplete="current-password">
            <button type="button" class="toggle-pwd-btn" onclick="togglePasswordVisibility('login-password')">👁️</button>
          </div>
        </div>

        <button type="submit" id="btn-login-submit" class="btn btn-primary btn-lg w-100 mt-2 glow-btn" style="font-size: 1rem; padding: 12px; font-weight: 700;">
          Sign In to Teacher Portal &rarr;
        </button>

        <div class="text-center mt-3 text-sm" style="color: #64748b;">
          New faculty member? <a href="javascript:void(0)" onclick="switchAuthTab('register')" style="color: #4f46e5; font-weight: 700;">Register your Faculty Account &rarr;</a>
        </div>
      </form>

      <!-- 2. CLEAN FACULTY REGISTRATION FORM (Employee ID & Designation Removed) -->
      <form id="faculty-register-form" style="display: none;">
        <input type="hidden" name="portal" value="faculty">

        <div class="form-group mb-3">
          <label class="form-label font-bold" for="reg-faculty-name">Full Name *</label>
          <input type="text" id="reg-faculty-name" name="name" class="form-control w-100" placeholder="e.g. Prof. Rajesh Sharma" required>
        </div>

        <div class="form-group mb-3">
          <label class="form-label font-bold">Select Department *</label>
          <div class="dept-selector-group">
            <label class="dept-radio-label selected" id="lbl-dept-bca">
              <input type="radio" name="department" value="BCA" checked onchange="handleDeptRadioChange(this.value)">
              <span style="font-size: 1.2rem;">💻</span>
              <span>BCA</span>
              <span class="text-xs text-muted">Computer Apps</span>
            </label>
            <label class="dept-radio-label" id="lbl-dept-bcom">
              <input type="radio" name="department" value="BCOM" onchange="handleDeptRadioChange(this.value)">
              <span style="font-size: 1.2rem;">📊</span>
              <span>BCOM</span>
              <span class="text-xs text-muted">Commerce</span>
            </label>
            <label class="dept-radio-label" id="lbl-dept-both">
              <input type="radio" name="department" value="BOTH" onchange="handleDeptRadioChange(this.value)">
              <span style="font-size: 1.2rem;">🌐</span>
              <span>BOTH / ALL</span>
              <span class="text-xs text-muted">All Both Depts</span>
            </label>
          </div>
        </div>

        <div class="form-group mb-3">
          <label class="form-label font-bold" for="reg-faculty-email">Email Address *</label>
          <input type="email" id="reg-faculty-email" name="email" class="form-control w-100" placeholder="faculty@college.edu" required autocomplete="email">
        </div>

        <div class="form-grid-2 mb-3">
          <div class="form-group">
            <label class="form-label font-bold" for="reg-faculty-password">Password *</label>
            <input type="password" id="reg-faculty-password" name="password" class="form-control w-100" placeholder="Min 4 chars" required autocomplete="new-password">
          </div>

          <div class="form-group">
            <label class="form-label font-bold" for="reg-faculty-confirm">Confirm Password *</label>
            <input type="password" id="reg-faculty-confirm" name="confirm_password" class="form-control w-100" placeholder="Re-enter password" required autocomplete="new-password">
          </div>
        </div>

        <button type="submit" id="btn-reg-submit" class="btn btn-primary btn-lg w-100 mt-2 glow-btn" style="background: linear-gradient(135deg, #4f46e5, #4338ca); font-size: 1rem; padding: 12px; font-weight: 700;">
          Complete Registration & Enter Portal &rarr;
        </button>

        <div class="text-center mt-3 text-sm" style="color: #64748b;">
          Already registered? <a href="javascript:void(0)" onclick="switchAuthTab('login')" style="color: #4f46e5; font-weight: 700;">Sign in to your account &rarr;</a>
        </div>
      </form>
    </div>
  </div>

  <div id="toast-container"></div>
  <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
  <script>
    function switchAuthTab(type) {
      const loginForm = document.getElementById('faculty-login-form');
      const regForm = document.getElementById('faculty-register-form');
      const btnLogin = document.getElementById('tab-btn-login');
      const btnReg = document.getElementById('tab-btn-register');
      const title = document.getElementById('teacher-portal-title');
      const subtitle = document.getElementById('teacher-portal-subtitle');

      if (type === 'register') {
        loginForm.style.display = 'none';
        regForm.style.display = 'block';
        btnLogin.classList.remove('active');
        btnReg.classList.add('active');
        title.innerText = 'Teacher Registration';
        subtitle.innerText = 'Create your official teacher profile to log daily classes';
        document.getElementById('reg-faculty-name').focus();
      } else {
        loginForm.style.display = 'block';
        regForm.style.display = 'none';
        btnLogin.classList.add('active');
        btnReg.classList.remove('active');
        title.innerText = 'Teacher Portal';
        subtitle.innerText = 'Daily Class Logging, Time Matrix & Workload Management';
        document.getElementById('login-ident').focus();
      }
    }

    function handleDeptRadioChange(val) {
      document.getElementById('lbl-dept-bca').classList.toggle('selected', val === 'BCA');
      document.getElementById('lbl-dept-bcom').classList.toggle('selected', val === 'BCOM');
      const lblBoth = document.getElementById('lbl-dept-both');
      if (lblBoth) lblBoth.classList.toggle('selected', val === 'BOTH');
    }

    function togglePasswordVisibility(fieldId) {
      const field = document.getElementById(fieldId);
      if (!field) return;
      field.type = (field.type === 'password') ? 'text' : 'password';
    }

    // 1. Teacher Login Submit
    document.getElementById('faculty-login-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('btn-login-submit');
      const originalText = btn.innerHTML;

      btn.disabled = true;
      btn.innerText = 'Signing in...';

      const formData = new FormData(form);

      try {
        const res = await fetch('api/auth.php?action=login', {
          method: 'POST',
          body: formData
        });
        const json = await res.json();

        if (json.success) {
          showToast(json.message || 'Login successful!', 'success');
          setTimeout(() => {
            window.location.href = json.redirect || 'faculty-dashboard.php';
          }, 350);
        } else {
          showToast(json.message || 'Invalid email/employee ID or password.', 'error');
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      } catch (err) {
        showToast('Connection error. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    });

    // 2. Teacher Registration Submit
    document.getElementById('faculty-register-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = e.target;
      const pwd = document.getElementById('reg-faculty-password').value;
      const confirmPwd = document.getElementById('reg-faculty-confirm').value;

      if (pwd !== confirmPwd) {
        showToast('Passwords do not match. Please verify your password.', 'error');
        return;
      }

      const btn = document.getElementById('btn-reg-submit');
      const originalText = btn.innerHTML;

      btn.disabled = true;
      btn.innerText = 'Registering Account...';

      const formData = new FormData(form);

      try {
        const res = await fetch('api/auth.php?action=register', {
          method: 'POST',
          body: formData
        });
        const json = await res.json();

        if (json.success) {
          showToast(json.message || 'Registration successful! Welcome!', 'success');
          setTimeout(() => {
            window.location.href = json.redirect || 'faculty-dashboard.php';
          }, 500);
        } else {
          showToast(json.message || 'Registration failed', 'error');
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      } catch (err) {
        showToast('Connection error during registration. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    });

    // Handle URL parameters (tab switch & security notices)
    window.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('action') === 'register' || urlParams.get('tab') === 'register') {
        switchAuthTab('register');
      }
      if (urlParams.get('error') === 'faculty_required' || urlParams.get('error') === 'unauthorized') {
        showToast('Access Denied: Please sign in with your registered faculty account or register below.', 'warning');
      }
    });
  </script>
</body>
</html>
