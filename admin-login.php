<?php
/**
 * Dedicated Admin Command Center Login
 */
require_once __DIR__ . '/config/auth.php';

if (is_logged_in() && $_SESSION['user_role'] === 'admin') {
    header("Location: admin-dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal Login - Faculty Workload & Class Tracking</title>
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
      padding: 9px 12px;
      font-size: 0.9rem;
      font-weight: 700;
      border: none;
      background: transparent;
      color: #64748b;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .auth-tab-btn.active {
      background: #ffffff;
      color: #dc2626;
      box-shadow: 0 2px 8px rgba(220, 38, 38, 0.12);
    }
  </style>
</head>
<body class="auth-page">

  <div class="auth-container" style="max-width: 440px; margin: 50px auto; padding: 0 16px;">
    <div class="auth-card glass-panel" style="border-top: 4px solid #ef4444; padding: 30px 28px; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.12);">
      
      <div class="brand-header text-center" style="margin-bottom: 22px;">
        <div class="brand-icon" style="background: linear-gradient(135deg, #ef4444, #f59e0b); width: 56px; height: 56px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 12px; box-shadow: 0 8px 20px rgba(239, 68, 68, 0.25);">
          🛡️
        </div>
        <h2 class="title-gradient" id="admin-portal-title" style="background: linear-gradient(135deg, #1e293b, #ef4444); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.45rem; font-weight: 800;">Admin Command Center</h2>
        <p class="subtitle" id="admin-portal-subtitle" style="color: #64748b; font-size: 0.86rem; margin-top: 4px;">Management, Workload Calculations & Audit Reports</p>
      </div>

      <!-- Auth Switcher Tabs -->
      <div class="auth-tabs">
        <button type="button" class="auth-tab-btn active" id="tab-btn-login" onclick="switchAuthTab('login')">
          🔑 Sign In
        </button>
        <button type="button" class="auth-tab-btn" id="tab-btn-register" onclick="switchAuthTab('register')">
          ✨ Register
        </button>
      </div>

      <!-- 1. LOGIN FORM -->
      <form id="admin-login-form">
        <input type="hidden" name="portal" value="admin">

        <div class="form-group mb-3">
          <label class="form-label" for="admin-email">Administrator Email</label>
          <input type="email" id="admin-email" name="email" class="form-control w-100" placeholder="malli123@gmail.com" required autocomplete="email">
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="admin-password">Admin Security Password</label>
          <input type="password" id="admin-password" name="password" class="form-control w-100" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <button type="submit" id="btn-admin-submit" class="btn btn-lg w-100 mt-2" style="background: #dc2626; color: white; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);">
          Authenticate Admin &rarr;
        </button>

        <div class="text-center mt-3 text-sm" style="color: #64748b;">
          New administrator? <a href="javascript:void(0)" onclick="switchAuthTab('register')" style="color: #dc2626; font-weight: 700;">Register Admin Account &rarr;</a>
        </div>
      </form>

      <!-- 2. REGISTRATION FORM -->
      <form id="admin-register-form" style="display: none;">
        <input type="hidden" name="portal" value="admin">

        <div class="form-group mb-3">
          <label class="form-label" for="reg-admin-name">Administrator Name *</label>
          <input type="text" id="reg-admin-name" name="name" class="form-control w-100" placeholder="e.g. Admin Mallikarjun" required>
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="reg-admin-email">Administrator Email Address *</label>
          <input type="email" id="reg-admin-email" name="email" class="form-control w-100" placeholder="e.g. admin@college.edu" required autocomplete="email">
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="reg-admin-password">Create Security Password *</label>
          <input type="password" id="reg-admin-password" name="password" class="form-control w-100" placeholder="Min 4 characters" required autocomplete="new-password">
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="reg-admin-confirm-password">Confirm Password *</label>
          <input type="password" id="reg-admin-confirm-password" name="confirm_password" class="form-control w-100" placeholder="Re-type password" required autocomplete="new-password">
        </div>

        <div class="form-group mb-3">
          <label class="form-label" for="reg-admin-dept">Department Oversight Scope</label>
          <select id="reg-admin-dept" name="department" class="form-select w-100">
            <option value="BOTH" selected>🌐 BOTH (All Departments / College-wide)</option>
            <option value="BCA">💻 BCA (Computer Applications)</option>
            <option value="BCOM">📊 BCOM (Commerce)</option>
          </select>
        </div>

        <button type="submit" id="btn-admin-register-submit" class="btn btn-lg w-100 mt-2" style="background: linear-gradient(135deg, #dc2626, #b91c1c); color: white; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35);">
          Register Administrator Account &rarr;
        </button>

        <div class="text-center mt-3 text-sm" style="color: #64748b;">
          Already registered? <a href="javascript:void(0)" onclick="switchAuthTab('login')" style="color: #dc2626; font-weight: 700;">Sign In here &rarr;</a>
        </div>
      </form>

    </div>
  </div>

  <div id="toast-container"></div>
  <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
  <script>
    function switchAuthTab(type) {
      const loginForm = document.getElementById('admin-login-form');
      const regForm = document.getElementById('admin-register-form');
      const btnLogin = document.getElementById('tab-btn-login');
      const btnReg = document.getElementById('tab-btn-register');
      const title = document.getElementById('admin-portal-title');
      const subtitle = document.getElementById('admin-portal-subtitle');

      if (type === 'register') {
        loginForm.style.display = 'none';
        regForm.style.display = 'block';
        btnLogin.classList.remove('active');
        btnReg.classList.add('active');
        title.innerText = 'Admin Registration';
        subtitle.innerText = 'Create a new administrator account';
        document.getElementById('reg-admin-name').focus();
      } else {
        loginForm.style.display = 'block';
        regForm.style.display = 'none';
        btnReg.classList.remove('active');
        btnLogin.classList.add('active');
        title.innerText = 'Admin Command Center';
        subtitle.innerText = 'Management, Workload Calculations & Audit Reports';
        document.getElementById('admin-email').focus();
      }
    }

    // Admin Login Form Submission
    document.getElementById('admin-login-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      const submitBtn = document.getElementById('btn-admin-submit');

      submitBtn.disabled = true;
      submitBtn.innerText = 'Verifying Admin Privileges...';

      try {
        const res = await fetch('api/auth.php?action=login&portal=admin', {
          method: 'POST',
          body: formData
        });
        const json = await res.json();

        if (json.success) {
          showToast(json.message, 'success');
          setTimeout(() => {
            window.location.href = 'admin-dashboard.php';
          }, 400);
        } else {
          showToast(json.message || 'Authentication failed.', 'error');
          submitBtn.disabled = false;
          submitBtn.innerText = 'Authenticate Admin →';
        }
      } catch (err) {
        showToast('Connection error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerText = 'Authenticate Admin →';
      }
    });

    // Admin Registration Form Submission
    document.getElementById('admin-register-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = e.target;
      const pwd = document.getElementById('reg-admin-password').value;
      const cpwd = document.getElementById('reg-admin-confirm-password').value;

      if (pwd !== cpwd) {
        showToast('Passwords do not match! Please verify.', 'error');
        document.getElementById('reg-admin-confirm-password').focus();
        return;
      }

      if (pwd.length < 4) {
        showToast('Password must be at least 4 characters long.', 'error');
        document.getElementById('reg-admin-password').focus();
        return;
      }

      const formData = new FormData(form);
      const submitBtn = document.getElementById('btn-admin-register-submit');

      submitBtn.disabled = true;
      submitBtn.innerText = 'Registering Administrator...';

      try {
        const res = await fetch('api/auth.php?action=register&portal=admin', {
          method: 'POST',
          body: formData
        });
        const json = await res.json();

        if (json.success) {
          const registeredEmail = document.getElementById('reg-admin-email').value;
          showToast(json.message || 'Administrator account registered successfully! Please sign in.', 'success');
          form.reset();
          submitBtn.disabled = false;
          submitBtn.innerText = 'Register Administrator Account →';

          // Switch automatically to Sign In tab
          switchAuthTab('login');
          if (registeredEmail) {
            document.getElementById('admin-email').value = registeredEmail;
          }
          document.getElementById('admin-password').focus();
        } else {
          showToast(json.message || 'Registration failed.', 'error');
          submitBtn.disabled = false;
          submitBtn.innerText = 'Register Administrator Account →';
        }
      } catch (err) {
        showToast('Connection error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerText = 'Register Administrator Account →';
      }
    });

    // Handle URL parameters (tab switch & security notices)
    window.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('action') === 'register' || urlParams.get('tab') === 'register') {
        switchAuthTab('register');
      }
      if (urlParams.get('error') === 'admin_required' || urlParams.get('error') === 'unauthorized') {
        showToast('Access Denied: Please sign in with your registered administrator account.', 'warning');
      }
    });
  </script>
</body>
</html>
