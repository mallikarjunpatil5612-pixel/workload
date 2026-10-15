<?php
/**
 * Authentication and Session Management for Separate Portals
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

/**
 * Check if a registered admin user is logged in
 */
function is_admin_logged_in() {
    if (!empty($_SESSION['admin_user_id']) && ($_SESSION['admin_user_role'] ?? '') === 'admin') {
        return true;
    }
    if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin') {
        $_SESSION['admin_user_id'] = $_SESSION['user_id'];
        $_SESSION['admin_employee_id'] = $_SESSION['employee_id'] ?? '';
        $_SESSION['admin_user_name'] = $_SESSION['user_name'] ?? '';
        $_SESSION['admin_user_email'] = $_SESSION['user_email'] ?? '';
        $_SESSION['admin_user_role'] = 'admin';
        return true;
    }
    return false;
}

/**
 * Check if a registered faculty user is logged in
 */
function is_faculty_logged_in() {
    if (!empty($_SESSION['faculty_user_id']) && ($_SESSION['faculty_user_role'] ?? '') === 'faculty') {
        return true;
    }
    if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'faculty') {
        $_SESSION['faculty_user_id'] = $_SESSION['user_id'];
        $_SESSION['faculty_employee_id'] = $_SESSION['employee_id'] ?? '';
        $_SESSION['faculty_user_name'] = $_SESSION['user_name'] ?? '';
        $_SESSION['faculty_user_email'] = $_SESSION['user_email'] ?? '';
        $_SESSION['faculty_user_role'] = 'faculty';
        $_SESSION['faculty_user_department'] = $_SESSION['user_department'] ?? 'BCA';
        $_SESSION['faculty_user_designation'] = $_SESSION['user_designation'] ?? 'Faculty Member';
        return true;
    }
    return false;
}

/**
 * Check if a user is logged in (Portal-aware)
 */
function is_logged_in($portal = null) {
    if ($portal === 'admin') {
        return is_admin_logged_in();
    }
    if ($portal === 'faculty') {
        return is_faculty_logged_in();
    }
    return is_admin_logged_in() || is_faculty_logged_in();
}

/**
 * Get current logged in user details (Portal-aware)
 */
function get_logged_in_user($forcePortal = null) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = basename($_SERVER['PHP_SELF'] ?? '');
    $isAdminContext = ($forcePortal === 'admin') || 
                      (strpos($uri, 'admin') !== false) || 
                      (strpos($script, 'admin') !== false);

    if ($isAdminContext && is_admin_logged_in()) {
        return [
            'id'            => $_SESSION['admin_user_id'],
            'employee_id'   => $_SESSION['admin_employee_id'] ?? 'ADM001',
            'name'          => $_SESSION['admin_user_name'] ?? 'Administrator',
            'email'         => $_SESSION['admin_user_email'] ?? '',
            'role'          => 'admin',
            'department'    => 'BOTH',
            'designation'   => 'Academic Administrator',
        ];
    }

    if (is_faculty_logged_in()) {
        return [
            'id'            => $_SESSION['faculty_user_id'],
            'employee_id'   => $_SESSION['faculty_employee_id'] ?? '',
            'name'          => $_SESSION['faculty_user_name'] ?? '',
            'email'         => $_SESSION['faculty_user_email'] ?? '',
            'role'          => $_SESSION['faculty_user_role'] ?? 'faculty',
            'department'    => $_SESSION['faculty_user_department'] ?? 'BCA',
            'designation'   => $_SESSION['faculty_user_designation'] ?? 'Faculty Member',
        ];
    }

    return null;
}

/**
 * Require Login middleware
 */
function require_login($targetPortal = 'faculty') {
    if (!is_logged_in($targetPortal)) {
        if (is_ajax_request()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Please sign in or register to access this resource.']);
            exit;
        }
        $redirectUrl = ($targetPortal === 'admin') ? 'admin-login.php' : 'faculty-login.php';
        header("Location: " . $redirectUrl . "?error=unauthorized");
        exit;
    }
}

/**
 * Require Admin role middleware (Strictly dedicated for Admin Portal)
 */
function require_admin() {
    if (!is_admin_logged_in()) {
        if (is_ajax_request()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access Denied: Only registered administrators can access this portal. Please log in.']);
            exit;
        }
        header("Location: admin-login.php?error=admin_required");
        exit;
    }
}

/**
 * Require Faculty role middleware (Strictly dedicated for Faculty Portal)
 */
function require_faculty() {
    if (!is_faculty_logged_in()) {
        if (is_ajax_request()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Access Denied: Only registered faculty members can access this portal. Please log in or register.']);
            exit;
        }
        header("Location: faculty-login.php?error=faculty_required");
        exit;
    }
}

/**
 * Helper to check if request is AJAX / API call
 */
function is_ajax_request() {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
           (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false);
}

/**
 * CSRF Protection Helpers
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
