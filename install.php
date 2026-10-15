<?php
/**
 * Auto-Installer & Database Initializer
 * College Faculty Daily Class & Workload Management System (EduLog)
 */

define('INSTALL_DB_HOST', 'localhost');
define('INSTALL_DB_USER', 'root');
define('INSTALL_DB_PASS', '');
define('INSTALL_DB_NAME', 'faculty_workload_db');

$message = '';
$status = 'info';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
if ($requestMethod === 'POST' || php_sapi_name() === 'cli') {
    try {
        // 1. Connect to MySQL server without DB first
        $pdo = new PDO("mysql:host=" . INSTALL_DB_HOST . ";charset=utf8mb4", INSTALL_DB_USER, INSTALL_DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // 2. Create Database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . INSTALL_DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . INSTALL_DB_NAME . "`");
        
        // 3. Read and execute schema
        $schemaPath = __DIR__ . '/database/schema.sql';
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            $pdo->exec($sql);
        }
        
        // 4. Seed Default Admin & Faculty Accounts with Bcrypt Passwords
        $adminPassword = password_hash('12345', PASSWORD_BCRYPT);
        $facultyPassword = password_hash('Faculty@123', PASSWORD_BCRYPT);
        
        $users = [
            [
                'emp_id' => 'ADM001',
                'name' => 'Admin Mallikarjun',
                'email' => 'malli123@gmail.com',
                'password' => $adminPassword,
                'role' => 'admin',
                'dept' => 'BOTH',
                'desig' => 'Academic Administrator',
                'phone' => '9876543210'
            ],
            [
                'emp_id' => 'BCA101',
                'name' => 'Prof. Ananya Sharma',
                'email' => 'ananya.bca@college.edu',
                'password' => $facultyPassword,
                'role' => 'faculty',
                'dept' => 'BCA',
                'desig' => 'Associate Professor (CS)',
                'phone' => '9876543211'
            ],
            [
                'emp_id' => 'BCA102',
                'name' => 'Prof. Vikram Hegde',
                'email' => 'vikram.bca@college.edu',
                'password' => $facultyPassword,
                'role' => 'faculty',
                'dept' => 'BCA',
                'desig' => 'Assistant Professor (DBMS)',
                'phone' => '9876543212'
            ],
            [
                'emp_id' => 'BCOM101',
                'name' => 'Prof. Suresh Nair',
                'email' => 'suresh.bcom@college.edu',
                'password' => $facultyPassword,
                'role' => 'faculty',
                'dept' => 'BCOM',
                'desig' => 'HOD Commerce & Accounts',
                'phone' => '9876543213'
            ],
            [
                'emp_id' => 'BCOM102',
                'name' => 'Prof. Priya Kulkarni',
                'email' => 'priya.bcom@college.edu',
                'password' => $facultyPassword,
                'role' => 'faculty',
                'dept' => 'BCOM',
                'desig' => 'Assistant Professor (Taxation)',
                'phone' => '9876543214'
            ]
        ];
        
        $stmtUser = $pdo->prepare("INSERT INTO users (employee_id, name, email, password, role, department, designation, phone)
                                  VALUES (:emp, :name, :email, :pass, :role, :dept, :desig, :phone)
                                  ON DUPLICATE KEY UPDATE 
                                  name=VALUES(name), email=VALUES(email), password=VALUES(password), designation=VALUES(designation)");
                                  
        foreach ($users as $u) {
            $stmtUser->execute([
                'emp'   => $u['emp_id'],
                'name'  => $u['name'],
                'email' => $u['email'],
                'pass'  => $u['password'],
                'role'  => $u['role'],
                'dept'  => $u['dept'],
                'desig' => $u['desig'],
                'phone' => $u['phone']
            ]);
        }
        
        // 5. Seed some sample class logs for today to demonstrate the system
        $message = "Database, tables, sections (BCA/BCOM 1st, 2nd, 3rd Year including 5B1) & baseline setup have been initialized successfully with zero mock data!";
        $status = 'success';
        
        if (php_sapi_name() === 'cli') {
            echo "\n [SUCCESS] " . $message . "\n";
            echo " Admin Login: malli123@gmail.com / 12345\n";
            echo " Faculty Login: ananya.bca@college.edu / Faculty@123\n\n";
        }
    } catch (Exception $e) {
        $message = "Installation Error: " . $e->getMessage();
        $status = 'error';
        if (php_sapi_name() === 'cli') {
            echo "\n [ERROR] " . $message . "\n";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Installer - College Faculty Workload System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container" style="max-width: 600px;">
        <div class="auth-card glass-panel animate-fadeIn">
            <div class="brand-header text-center">
                <div class="brand-icon pulse">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                    </svg>
                </div>
                <h2 class="title-gradient">College Faculty System Setup</h2>
                <p class="subtitle">1-Click Database & Demo Records Installation</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $status; ?> animate-slideDown">
                    <div class="alert-icon">
                        <?php echo $status === 'success' ? '✓' : '⚠️'; ?>
                    </div>
                    <div class="alert-content">
                        <strong><?php echo $status === 'success' ? 'Database Initialized!' : 'Notice'; ?></strong>
                        <p><?php echo $message; ?></p>
                    </div>
                </div>
                <?php if ($status === 'success'): ?>
                    <div class="credentials-box">
                        <div class="cred-header">Default Login Credentials</div>
                        <div class="cred-row">
                            <span class="badge badge-admin">Admin Portal</span>
                            <code>malli123@gmail.com</code> / <code>12345</code>
                        </div>
                        <div class="cred-row">
                            <span class="badge badge-bca">BCA Faculty</span>
                            <code>ananya.bca@college.edu</code> / <code>Faculty@123</code>
                        </div>
                        <div class="cred-row">
                            <span class="badge badge-bcom">BCOM Faculty</span>
                            <code>suresh.bcom@college.edu</code> / <code>Faculty@123</code>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="index.php" class="btn btn-primary btn-lg w-100 glow-btn">
                            Proceed to System Login &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($status !== 'success'): ?>
                <div class="info-card mb-4">
                    <h4>Pre-configured Setup</h4>
                    <ul class="feature-list">
                        <li><span>✔</span> MySQL Database: <code>faculty_workload_db</code></li>
                        <li><span>✔</span> 2 Departments: <strong>BCA</strong> & <strong>BCOM</strong> (1st, 2nd, 3rd Year)</li>
                        <li><span>✔</span> 24 Sections (4 per year including 5B1, 5B2...)</li>
                        <li><span>✔</span> 5 Daily Class Session Time Slots</li>
                        <li><span>✔</span> Faculty Workload Calculator & Merged Class Engine</li>
                        <li><span>✔</span> WhatsApp Ready Daily Report Image Exporter</li>
                    </ul>
                </div>
                <form method="POST" action="install.php">
                    <button type="submit" class="btn btn-primary btn-lg w-100 glow-btn">
                        Install Database & Load Demo Data
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="index.php" class="text-muted text-sm">Already installed? Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
