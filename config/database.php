<?php
/**
 * Database Configuration & High-Availability Connection Handler
 * Supports MySQL with Seamless Embedded SQLite Fallback
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'faculty_workload_db');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO database connection instance.
 */
function get_db() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // 1. Try MySQL First
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        sync_db_schema($pdo, 'mysql');
        return $pdo;
    } catch (PDOException $mysqlError) {
        // If database does not exist on MySQL, try connecting to MySQL server to create it
        if ($mysqlError->getCode() == 1049 || strpos($mysqlError->getMessage(), 'Unknown database') !== false) {
            try {
                $rootPdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                // Load schema
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $pdo->exec(file_get_contents($schemaFile));
                }
                sync_db_schema($pdo, 'mysql');
                return $pdo;
            } catch (Exception $e) {
                // Fall through to SQLite
            }
        }
    }

    // 2. High-Availability SQLite Driver Fallback (Runs everywhere without server setup)
    $sqlitePath = __DIR__ . '/../database/faculty_workload.sqlite';
    $needInit = !file_exists($sqlitePath) || filesize($sqlitePath) < 1024;

    try {
        $pdo = new PDO("sqlite:" . $sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        if ($needInit) {
            init_sqlite_database($pdo);
        } else {
            sync_db_schema($pdo, 'sqlite');
        }

        return $pdo;
    } catch (Exception $sqliteError) {
        throw new Exception("Database Connection Error: " . $sqliteError->getMessage());
    }
}

/**
 * Sync migrations, ensure column consistency and sync default admin credentials
 */
function sync_db_schema($pdo, $driver = 'mysql') {
    // 1. Auto-sync admin credentials to malli123@gmail.com / 12345
    try {
        $admPwd = password_hash('12345', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET email = 'malli123@gmail.com', password = :pwd, name = 'Admin Mallikarjun' WHERE role = 'admin' OR employee_id = 'ADM001' OR email = 'admin@college.edu'");
        $stmt->execute(['pwd' => $admPwd]);
    } catch (Exception $e) {}

    // 2. Auto-migrate time_slots columns if missing
    $columns = [
        'department_id' => 'INT DEFAULT NULL',
        'year_id' => 'INT DEFAULT NULL',
        'section_id' => 'INT DEFAULT NULL',
        'is_break' => 'TINYINT(1) DEFAULT 0',
        'slot_type' => 'VARCHAR(30) DEFAULT \'class\''
    ];

    foreach ($columns as $col => $typeDef) {
        try {
            if ($driver === 'sqlite') {
                $typeDef = ($col === 'is_break') ? 'INTEGER DEFAULT 0' : (($col === 'slot_type') ? 'TEXT DEFAULT \'class\'' : 'INTEGER DEFAULT NULL');
            }
            $pdo->exec("ALTER TABLE time_slots ADD COLUMN $col $typeDef;");
        } catch (Exception $e) {
            // Column already exists or table locked - ignore
        }
    }

    // 3. Ensure baseline active time slots exist
    try {
        $slotCount = $pdo->query("SELECT COUNT(*) FROM time_slots WHERE is_active = 1")->fetchColumn();
        if ($slotCount == 0) {
            $pdo->exec("INSERT INTO time_slots (session_number, start_time, end_time, label, is_break, slot_type, is_active) VALUES
                (1, '08:30:00', '09:25:00', 'Session 1', 0, 'class', 1),
                (2, '09:25:00', '10:20:00', 'Session 2', 0, 'class', 1),
                (101, '10:20:00', '10:30:00', 'Break (Interval)', 1, 'tea_break', 1),
                (3, '10:30:00', '11:25:00', 'Session 3', 0, 'class', 1),
                (4, '11:25:00', '12:20:00', 'Session 4', 0, 'class', 1),
                (5, '14:00:00', '15:00:00', 'Session 5 (02:00 - 03:00 PM)', 0, 'class', 1);");
        }
    } catch (Exception $e) {}
}

/**
 * Initialize SQLite Schema & Seed Data
 */
function init_sqlite_database($pdo) {
    // 1. Users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'faculty',
        department TEXT NOT NULL DEFAULT 'BCA',
        designation TEXT DEFAULT 'Assistant Professor',
        phone TEXT DEFAULT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 2. Departments
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        total_years INTEGER DEFAULT 3,
        status TEXT DEFAULT 'active'
    );");

    // 3. Academic Years
    $pdo->exec("CREATE TABLE IF NOT EXISTS academic_years (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        year_number INTEGER NOT NULL,
        name TEXT NOT NULL
    );");

    // 4. Sections
    $pdo->exec("CREATE TABLE IF NOT EXISTS sections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        department_id INTEGER NOT NULL,
        year_id INTEGER NOT NULL,
        semester INTEGER DEFAULT 1,
        section_name TEXT NOT NULL,
        display_code TEXT NOT NULL,
        room_no TEXT DEFAULT NULL,
        status TEXT DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 5. Time Slots
    $pdo->exec("CREATE TABLE IF NOT EXISTS time_slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_number INTEGER NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        label TEXT NOT NULL,
        department_id INTEGER DEFAULT NULL,
        year_id INTEGER DEFAULT NULL,
        section_id INTEGER DEFAULT NULL,
        is_break INTEGER DEFAULT 0,
        slot_type TEXT DEFAULT 'class',
        is_active INTEGER DEFAULT 1
    );");

    // 6. Daily Schedules
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        schedule_date TEXT NOT NULL,
        department_id INTEGER NOT NULL,
        year_id INTEGER NOT NULL,
        section_id INTEGER NOT NULL,
        session_number INTEGER NOT NULL,
        expected_subject TEXT DEFAULT NULL,
        assigned_faculty_id INTEGER DEFAULT NULL,
        is_merged INTEGER DEFAULT 0,
        merged_with_section_id INTEGER DEFAULT NULL,
        is_lab INTEGER DEFAULT 0,
        is_no_class INTEGER DEFAULT 0,
        special_note TEXT DEFAULT NULL,
        created_by_admin INTEGER DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(schedule_date, section_id, session_number)
    );");

    // 7. Class Logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        log_date TEXT NOT NULL,
        department_id INTEGER NOT NULL,
        year_id INTEGER NOT NULL,
        section_id INTEGER NOT NULL,
        session_number INTEGER NOT NULL,
        faculty_id INTEGER NOT NULL,
        faculty_name TEXT NOT NULL,
        subject_name TEXT NOT NULL,
        topic_covered TEXT NOT NULL,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        session_type TEXT NOT NULL DEFAULT 'theory',
        is_merged INTEGER DEFAULT 0,
        merged_with_section_id INTEGER DEFAULT NULL,
        workload_credits REAL DEFAULT 1.0,
        merged_log_parent_id INTEGER DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        status TEXT DEFAULT 'submitted',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(log_date, section_id, session_number)
    );");

    // 8. Special Events
    $pdo->exec("CREATE TABLE IF NOT EXISTS special_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_date TEXT NOT NULL,
        department_id INTEGER DEFAULT NULL,
        year_id INTEGER DEFAULT NULL,
        section_id INTEGER DEFAULT NULL,
        title TEXT NOT NULL,
        description TEXT DEFAULT NULL,
        event_type TEXT DEFAULT 'student_session',
        created_by INTEGER DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // Seed Data
    $pdo->exec("INSERT OR IGNORE INTO departments (id, code, name, total_years) VALUES
        (1, 'BCA', 'Bachelor of Computer Applications', 3),
        (2, 'BCOM', 'Bachelor of Commerce', 3);");

    $pdo->exec("INSERT OR IGNORE INTO academic_years (id, year_number, name) VALUES
        (1, 1, '1st Year'),
        (2, 2, '2nd Year'),
        (3, 3, '3rd Year');");

    $pdo->exec("INSERT OR IGNORE INTO time_slots (id, session_number, start_time, end_time, label, is_break, slot_type, is_active) VALUES
        (1, 1, '08:30:00', '09:25:00', 'Session 1', 0, 'class', 1),
        (2, 2, '09:25:00', '10:20:00', 'Session 2', 0, 'class', 1),
        (6, 101, '10:20:00', '10:30:00', 'Break (Interval)', 1, 'tea_break', 1),
        (3, 3, '10:30:00', '11:25:00', 'Session 3', 0, 'class', 1),
        (4, 4, '11:25:00', '12:20:00', 'Session 4', 0, 'class', 1),
        (5, 5, '14:00:00', '15:00:00', 'Session 5 (02:00 - 03:00 PM)', 0, 'class', 1);");

    $pdo->exec("INSERT OR IGNORE INTO sections (id, department_id, year_id, semester, section_name, display_code, room_no) VALUES
        (1, 1, 1, 1, 'Section A', 'BCA-1A', 'Room 101'),
        (2, 1, 1, 1, 'Section B', 'BCA-1B', 'Room 102'),
        (3, 1, 1, 1, 'Section C', 'BCA-1C', 'Room 103'),
        (4, 1, 1, 1, 'Section D', 'BCA-1D', 'Room 104'),
        (5, 1, 2, 3, 'Section A', 'BCA-2A', 'Room 201'),
        (6, 1, 2, 3, 'Section B', 'BCA-2B', 'Room 202'),
        (7, 1, 2, 3, 'Section C', 'BCA-2C', 'Room 203'),
        (8, 1, 2, 3, 'Section D', 'BCA-2D', 'Room 204'),
        (9, 1, 3, 5, '5B1', 'BCA-5B1', 'Room 301'),
        (10, 1, 3, 5, '5B2', 'BCA-5B2', 'Room 302'),
        (11, 1, 3, 5, '5B3', 'BCA-5B3', 'Room 303'),
        (12, 1, 3, 5, '5B4', 'BCA-5B4', 'Room 304'),
        (13, 2, 1, 1, 'Section A', 'BCOM-1A', 'Commerce Block 101'),
        (14, 2, 1, 1, 'Section B', 'BCOM-1B', 'Commerce Block 102'),
        (15, 2, 1, 1, 'Section C', 'BCOM-1C', 'Commerce Block 103'),
        (16, 2, 1, 1, 'Section D', 'BCOM-1D', 'Commerce Block 104'),
        (17, 2, 2, 3, 'Section A', 'BCOM-2A', 'Commerce Block 201'),
        (18, 2, 2, 3, 'Section B', 'BCOM-2B', 'Commerce Block 202'),
        (19, 2, 2, 3, 'Section C', 'BCOM-2C', 'Commerce Block 203'),
        (20, 2, 2, 3, 'Section D', 'BCOM-2D', 'Commerce Block 204'),
        (21, 2, 3, 5, 'Section A', 'BCOM-3A', 'Commerce Block 301'),
        (22, 2, 3, 5, 'Section B', 'BCOM-3B', 'Commerce Block 302'),
        (23, 2, 3, 5, 'Section C', 'BCOM-3C', 'Commerce Block 303'),
        (24, 2, 3, 5, 'Section D', 'BCOM-3D', 'Commerce Block 304');");

    // Seed Admin Account
    $adminPassword = password_hash('12345', PASSWORD_BCRYPT);
    $stmtAdm = $pdo->prepare("INSERT OR IGNORE INTO users (employee_id, name, email, password, role, department, designation, phone)
                              VALUES ('ADM001', 'Admin Mallikarjun', 'malli123@gmail.com', ?, 'admin', 'BOTH', 'Academic Administrator', '9876543210')");
    $stmtAdm->execute([$adminPassword]);
}
