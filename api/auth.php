<?php
/**
 * Authentication API Endpoint with Separate Portal Support
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$portal = $_GET['portal'] ?? $_POST['portal'] ?? 'faculty'; // 'admin' or 'faculty'

try {
    $db = get_db();

    switch ($action) {
        case 'login':
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Please provide both email and password.']);
                exit;
            }

            // Guarantee Admin Mallikarjun exists with password 12345
            if (strtolower($email) === 'malli123@gmail.com' && $password === '12345') {
                $chkAdmin = $db->query("SELECT id FROM users WHERE email = 'malli123@gmail.com'")->fetch();
                $admHash = password_hash('12345', PASSWORD_BCRYPT);
                if (!$chkAdmin) {
                    try {
                        $db->prepare("INSERT INTO users (employee_id, name, email, password, role, department, designation, phone, status)
                                      VALUES ('ADM001', 'Admin Mallikarjun', 'malli123@gmail.com', :pwd, 'admin', 'BOTH', 'Academic Administrator', '9876543210', 'active')")
                           ->execute(['pwd' => $admHash]);
                    } catch (Exception $e) {
                        $db->prepare("UPDATE users SET email = 'malli123@gmail.com', password = :pwd, role = 'admin', status = 'active' WHERE employee_id = 'ADM001' OR role = 'admin'")
                           ->execute(['pwd' => $admHash]);
                    }
                } else {
                    $db->prepare("UPDATE users SET password = :pwd, status = 'active' WHERE email = 'malli123@gmail.com'")
                       ->execute(['pwd' => $admHash]);
                }
            }

            $loginIdent = strtolower(trim($email));
            $stmt = $db->prepare("SELECT * FROM users WHERE (LOWER(email) = :email_ident OR LOWER(employee_id) = :emp_ident) AND status = 'active'");
            $stmt->execute(['email_ident' => $loginIdent, 'emp_ident' => $loginIdent]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // If attempting to login to Admin portal, must have admin role
                if ($portal === 'admin' && $user['role'] !== 'admin') {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Access Denied: This account does not have Administrator privileges. Please use the Faculty Portal.'
                    ]);
                    exit;
                }

                // Set portal-specific session keys so admin and faculty never clash
                if ($portal === 'admin' || $user['role'] === 'admin') {
                    $_SESSION['admin_user_id'] = $user['id'];
                    $_SESSION['admin_employee_id'] = $user['employee_id'];
                    $_SESSION['admin_user_name'] = $user['name'];
                    $_SESSION['admin_user_email'] = $user['email'];
                    $_SESSION['admin_user_role'] = 'admin';
                }

                if ($portal === 'faculty' || $user['role'] === 'faculty') {
                    $_SESSION['faculty_user_id'] = $user['id'];
                    $_SESSION['faculty_employee_id'] = $user['employee_id'];
                    $_SESSION['faculty_user_name'] = $user['name'];
                    $_SESSION['faculty_user_email'] = $user['email'];
                    $_SESSION['faculty_user_role'] = $user['role'];
                    $_SESSION['faculty_user_department'] = $user['department'];
                    $_SESSION['faculty_user_designation'] = $user['designation'];
                }

                // General fallback session keys
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_department'] = $user['department'];
                $_SESSION['user_designation'] = $user['designation'];

                $redirect = ($portal === 'faculty') ? 'faculty-dashboard.php' : (($user['role'] === 'admin') ? 'admin-dashboard.php' : 'faculty-dashboard.php');

                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful! Welcome, ' . $user['name'],
                    'redirect' => $redirect,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'role' => $user['role'],
                        'department' => $user['department'],
                        'employee_id' => $user['employee_id']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
            }
            break;

        case 'register':
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Please provide both email and password to register.']);
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
                exit;
            }

            if (strlen($password) < 4) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 4 characters long.']);
                exit;
            }

            if (!empty($confirmPassword) && $password !== $confirmPassword) {
                echo json_encode(['success' => false, 'message' => 'Passwords do not match. Please verify your password.']);
                exit;
            }

            // Check if email already exists
            $checkEmail = $db->prepare("SELECT id FROM users WHERE email = :email");
            $checkEmail->execute(['email' => $email]);
            if ($checkEmail->fetch()) {
                echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
                exit;
            }

            $role = ($portal === 'admin') ? 'admin' : 'faculty';

            // Derive or generate fields
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) {
                $emailUser = explode('@', $email)[0];
                $parts = preg_split('/[\._\-]/', $emailUser);
                $cleanName = ucwords($parts[0] ?? 'User');
                $name = ($role === 'admin') ? ("Admin " . $cleanName) : ("Prof. " . $cleanName);
            }

            $department = trim($_POST['department'] ?? '');
            if (empty($department) || !in_array($department, ['BCA', 'BCOM', 'BOTH'])) {
                $department = ($role === 'admin') ? 'BOTH' : ((strpos($email, 'bcom') !== false) ? 'BCOM' : 'BCA');
            }

            $empId = strtoupper(trim($_POST['employee_id'] ?? ''));
            if (empty($empId)) {
                // Auto generate unique Employee ID with department or ADM prefix
                $deptPrefix = ($role === 'admin') ? 'ADM' : (($department === 'BOTH') ? 'FAC' : $department);
                $baseNum = 100;
                $maxId = $db->query("SELECT MAX(id) FROM users")->fetchColumn();
                $generated = $deptPrefix . ($baseNum + intval($maxId) + 1);
                
                // Ensure uniqueness
                $chkEmp = $db->prepare("SELECT id FROM users WHERE employee_id = :eid");
                $chkEmp->execute(['eid' => $generated]);
                if ($chkEmp->fetch()) {
                    $generated = $deptPrefix . mt_rand(200, 999);
                }
                $empId = $generated;
            }

            $defaultDesig = ($role === 'admin') ? 'Academic Administrator' : 'Assistant Professor';
            $designation = trim($_POST['designation'] ?? $defaultDesig) ?: $defaultDesig;
            $phone = trim($_POST['phone'] ?? '');

            // Hash password and insert
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (employee_id, name, email, password, role, department, designation, phone, status)
                                  VALUES (:emp_id, :name, :email, :password, :role, :dept, :desig, :phone, 'active')");
            $stmt->execute([
                'emp_id'   => $empId,
                'name'     => $name,
                'email'    => $email,
                'password' => $hashedPassword,
                'role'     => $role,
                'dept'     => $department,
                'desig'    => $designation,
                'phone'    => $phone ?: null
            ]);

            $newUserId = $db->lastInsertId();

            // Auto-login for faculty portal
            $redirect = null;
            if ($portal === 'faculty' || $role === 'faculty') {
                $_SESSION['faculty_user_id'] = $newUserId;
                $_SESSION['faculty_employee_id'] = $empId;
                $_SESSION['faculty_user_name'] = $name;
                $_SESSION['faculty_user_email'] = $email;
                $_SESSION['faculty_user_role'] = $role;
                $_SESSION['faculty_user_department'] = $department;
                $_SESSION['faculty_user_designation'] = $designation;

                $_SESSION['user_id'] = $newUserId;
                $_SESSION['employee_id'] = $empId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = $role;
                $_SESSION['user_department'] = $department;
                $_SESSION['user_designation'] = $designation;
                $redirect = 'faculty-dashboard.php';
            }

            echo json_encode([
                'success' => true,
                'message' => ($role === 'admin') 
                    ? 'Administrator account registered successfully! Please sign in with your credentials.' 
                    : 'Registration successful! Welcome to the Teacher Portal, ' . $name . '!',
                'registered_email' => $email,
                'redirect' => $redirect,
                'user' => [
                    'id' => $newUserId,
                    'name' => $name,
                    'role' => $role,
                    'department' => $department,
                    'employee_id' => $empId
                ]
            ]);
            break;

        case 'logout':
            if ($portal === 'admin') {
                unset($_SESSION['admin_user_id'], $_SESSION['admin_employee_id'], $_SESSION['admin_user_name'], $_SESSION['admin_user_email'], $_SESSION['admin_user_role']);
                if (($_SESSION['user_role'] ?? '') === 'admin') {
                    unset($_SESSION['user_id'], $_SESSION['employee_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_department'], $_SESSION['user_designation']);
                }
                $targetRedirect = 'admin-login.php';
            } else {
                unset($_SESSION['faculty_user_id'], $_SESSION['faculty_employee_id'], $_SESSION['faculty_user_name'], $_SESSION['faculty_user_email'], $_SESSION['faculty_user_role'], $_SESSION['faculty_user_department'], $_SESSION['faculty_user_designation']);
                if (($_SESSION['user_role'] ?? '') !== 'admin') {
                    unset($_SESSION['user_id'], $_SESSION['employee_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role'], $_SESSION['user_department'], $_SESSION['user_designation']);
                }
                $targetRedirect = 'teacher-login.php';
            }

            // If neither portal session remains, clean everything
            if (empty($_SESSION['admin_user_id']) && empty($_SESSION['faculty_user_id'])) {
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                session_destroy();
            }

            echo json_encode(['success' => true, 'message' => 'Logged out successfully.', 'redirect' => $targetRedirect]);
            break;

        case 'get_teachers':
            $dept = trim($_GET['dept'] ?? '');
            $where = "WHERE role = 'faculty' AND status = 'active'";
            $params = [];
            if (!empty($dept) && in_array($dept, ['BCA', 'BCOM'])) {
                $where .= " AND (department = :dept OR department = 'BOTH')";
                $params['dept'] = $dept;
            }
            $stmt = $db->prepare("SELECT id, name, department, employee_id, designation FROM users $where ORDER BY name ASC");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'teachers' => $stmt->fetchAll()]);
            break;



        case 'current_user':
            if (is_logged_in()) {
                echo json_encode(['success' => true, 'user' => get_logged_in_user()]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not logged in.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid auth action.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
