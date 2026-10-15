<?php
/**
 * Global Header Component with Portal-Specific Badges
 */
require_once __DIR__ . '/../config/auth.php';
$user = get_logged_in_user();

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isFacultyPortal = ($currentPage === 'faculty-dashboard.php' || strpos($currentPage, 'faculty') !== false);
$isAdminPortal = ($currentPage === 'admin-dashboard.php' || strpos($currentPage, 'admin') !== false);

$portalTitle = $isFacultyPortal ? 'Teacher Portal' : 'Admin Command Center';
$portalIcon  = $isFacultyPortal ? '👨‍🏫' : '🛡️';
$portalBrandGradient = $isFacultyPortal ? 'background: linear-gradient(135deg, #6366f1, #0ea5e9);' : 'background: linear-gradient(135deg, #ef4444, #f59e0b);';
$avatarStyle = $isFacultyPortal ? 'background: rgba(99, 102, 241, 0.15); color: #4f46e5;' : 'background: rgba(239, 68, 68, 0.15); color: #dc2626;';

$userDisplayName = $user['name'] ?? 'User';
if ($isFacultyPortal && stripos($userDisplayName, 'Admin ') === 0) {
    $userDisplayName = 'Teacher ' . substr($userDisplayName, 6);
}

$deptName = $user['department'] ?? '';
if ($isFacultyPortal) {
    if ($deptName === 'BOTH') {
        $roleBadgeText = 'TEACHER • BCA & BCOM';
    } elseif (!empty($deptName) && !in_array($deptName, ['FACULTY', 'ALL'])) {
        $roleBadgeText = 'TEACHER • ' . htmlspecialchars($deptName);
    } else {
        $roleBadgeText = 'TEACHER';
    }
} else {
    $roleBadgeText = 'ADMINISTRATOR';
}
$roleBadgeClass = $isFacultyPortal ? 'badge-bca' : 'badge-admin';
$brandLink = $isFacultyPortal ? 'faculty-dashboard.php' : 'admin-dashboard.php';
?>
<header class="navbar">
  <div class="nav-container">
    <a href="<?php echo $brandLink; ?>" class="nav-brand">
      <div class="brand-badge" style="<?php echo $portalBrandGradient; ?>">
        <?php echo $portalIcon; ?>
      </div>
      <div class="brand-text">
        <h1><?php echo $portalTitle; ?></h1>
        <span>College Class & Workload System</span>
      </div>
    </a>

    <div class="nav-actions">

      <div class="user-profile-chip">
        <div class="user-avatar" style="<?php echo $avatarStyle; ?>">
          <?php echo strtoupper(substr($userDisplayName, 0, 1)); ?>
        </div>
        <div class="user-meta">
          <span class="user-name"><?php echo htmlspecialchars($userDisplayName); ?></span>
          <span class="user-role-badge">
            <span class="badge <?php echo $roleBadgeClass; ?>" style="font-size: 0.65rem; padding: 2px 8px;">
              <?php echo $roleBadgeText; ?>
            </span>
          </span>
        </div>
      </div>

      <button onclick="handlePortalLogout('<?php echo $isFacultyPortal ? 'faculty' : 'admin'; ?>')" class="btn btn-sm btn-secondary" title="Logout">
        <span>Logout</span> ⏻
      </button>
    </div>
  </div>
</header>
