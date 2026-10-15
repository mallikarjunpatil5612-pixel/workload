<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

echo "Testing Database Connection...\n";
$db = get_db();
echo "Connected successfully to DB driver: " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";

// Test login query for admin
$email = 'malli123@gmail.com';
$password = '12345';
$loginIdent = strtolower(trim($email));

$stmt = $db->prepare("SELECT * FROM users WHERE (LOWER(email) = :email_ident OR LOWER(employee_id) = :emp_ident) AND status = 'active'");
$stmt->execute(['email_ident' => $loginIdent, 'emp_ident' => $loginIdent]);
$user = $stmt->fetch();

if ($user) {
    echo "Found user: " . $user['name'] . " (" . $user['email'] . ", " . $user['role'] . ")\n";
    if (password_verify($password, $user['password'])) {
        echo "Password verification: SUCCESS!\n";
    } else {
        echo "Password verification: FAILED (hash mismatch)\n";
    }
} else {
    echo "User not found!\n";
}

// Test login query for faculty
$facEmail = 'ananya.bca@college.edu';
$facPass = 'Faculty@123';
$stmtFac = $db->prepare("SELECT * FROM users WHERE (LOWER(email) = :email_ident OR LOWER(employee_id) = :emp_ident) AND status = 'active'");
$stmtFac->execute(['email_ident' => strtolower($facEmail), 'emp_ident' => strtolower($facEmail)]);
$facUser = $stmtFac->fetch();

if ($facUser) {
    echo "Found faculty: " . $facUser['name'] . " (" . $facUser['email'] . ")\n";
    if (password_verify($facPass, $facUser['password'])) {
        echo "Faculty password verification: SUCCESS!\n";
    } else {
        echo "Faculty password verification: FAILED\n";
    }
} else {
    echo "Faculty not found!\n";
}

echo "All tests passed successfully!\n";
