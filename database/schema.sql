-- College Faculty Daily Class & Workload Management System (EduLog)
-- Database Schema & Initial Data

CREATE DATABASE IF NOT EXISTS `faculty_workload_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `faculty_workload_db`;

-- 1. Users Table (Admin and Faculty Members)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'faculty') NOT NULL DEFAULT 'faculty',
    `department` ENUM('BCA', 'BCOM', 'BOTH') NOT NULL DEFAULT 'BCA',
    `designation` VARCHAR(100) DEFAULT 'Assistant Professor',
    `phone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `total_years` INT DEFAULT 3,
    `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB;

-- 3. Academic Years Table
CREATE TABLE IF NOT EXISTS `academic_years` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `year_number` INT NOT NULL, -- 1, 2, 3
    `name` VARCHAR(50) NOT NULL -- 1st Year, 2nd Year, 3rd Year
) ENGINE=InnoDB;

-- 4. Sections / Classes Table (e.g. BCA 1st Year Sec A, B, C, D or Sem 5 Sec 5B1)
CREATE TABLE IF NOT EXISTS `sections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `year_id` INT NOT NULL,
    `semester` INT DEFAULT 1, -- 1 to 6
    `section_name` VARCHAR(50) NOT NULL, -- A, B, C, D or 1B1, 5B1
    `display_code` VARCHAR(50) NOT NULL, -- e.g. BCA-1A, BCOM-3B, 5B1
    `room_no` VARCHAR(30) DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Standard Session Time Slots (1 to 5 sessions per day)
CREATE TABLE IF NOT EXISTS `time_slots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_number` INT NOT NULL, -- 1, 2, 3, 4, 5
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `label` VARCHAR(50) NOT NULL, -- 'Session 1 (09:00 - 10:00 AM)', etc.
    `department_id` INT DEFAULT NULL,
    `year_id` INT DEFAULT NULL,
    `section_id` INT DEFAULT NULL,
    `is_break` TINYINT(1) DEFAULT 0,
    `slot_type` VARCHAR(30) DEFAULT 'class', -- 'class', 'lunch_break', 'tea_break'
    `is_active` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Daily Pre-Designed Schedule / Slot Plan by Admin
CREATE TABLE IF NOT EXISTS `daily_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `schedule_date` DATE NOT NULL,
    `department_id` INT NOT NULL,
    `year_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `session_number` INT NOT NULL, -- 1 to 5
    `expected_subject` VARCHAR(150) DEFAULT NULL,
    `assigned_faculty_id` INT DEFAULT NULL,
    `is_merged` TINYINT(1) DEFAULT 0,
    `merged_with_section_id` INT DEFAULT NULL,
    `is_lab` TINYINT(1) DEFAULT 0,
    `is_no_class` TINYINT(1) DEFAULT 0,
    `special_note` VARCHAR(255) DEFAULT NULL,
    `created_by_admin` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_faculty_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_daily_slot` (`schedule_date`, `section_id`, `session_number`)
) ENGINE=InnoDB;

-- 7. Daily Class Logs (Filled by Faculty)
CREATE TABLE IF NOT EXISTS `class_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `log_date` DATE NOT NULL,
    `department_id` INT NOT NULL,
    `year_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `session_number` INT NOT NULL, -- 1 to 5
    `faculty_id` INT NOT NULL,
    `faculty_name` VARCHAR(120) NOT NULL,
    `subject_name` VARCHAR(150) NOT NULL,
    `topic_covered` TEXT NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `session_type` ENUM('theory', 'lab', 'merged', 'special_event', 'substitute') NOT NULL DEFAULT 'theory',
    `is_merged` TINYINT(1) DEFAULT 0,
    `merged_with_section_id` INT DEFAULT NULL,
    `workload_credits` DECIMAL(3,1) DEFAULT 1.0, -- Default 1.0 hr, for merged second section log it's 0.0 or linked
    `merged_log_parent_id` INT DEFAULT NULL, -- Links secondary log of merged class to avoid double-counting faculty workload
    `remarks` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('submitted', 'approved', 'flagged') DEFAULT 'submitted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_class_log` (`log_date`, `section_id`, `session_number`)
) ENGINE=InnoDB;

-- 8. Special Events / No Class Days announcements
CREATE TABLE IF NOT EXISTS `special_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_date` DATE NOT NULL,
    `department_id` INT DEFAULT NULL, -- NULL means all departments
    `year_id` INT DEFAULT NULL,
    `section_id` INT DEFAULT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `event_type` ENUM('holiday', 'seminar', 'student_session', 'exam', 'workshop', 'sports') DEFAULT 'student_session',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed Data Setup
INSERT INTO `departments` (`id`, `code`, `name`, `total_years`) VALUES
(1, 'BCA', 'Bachelor of Computer Applications', 3),
(2, 'BCOM', 'Bachelor of Commerce', 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `academic_years` (`id`, `year_number`, `name`) VALUES
(1, 1, '1st Year'),
(2, 2, '2nd Year'),
(3, 3, '3rd Year')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Default Time Slots (4-5 sessions per day with Break Hour)
INSERT INTO `time_slots` (`id`, `session_number`, `start_time`, `end_time`, `label`, `is_break`, `slot_type`) VALUES
(1, 1, '08:30:00', '09:25:00', 'Session 1', 0, 'class'),
(2, 2, '09:25:00', '10:20:00', 'Session 2', 0, 'class'),
(6, 101, '10:20:00', '10:30:00', 'Break (Interval)', 1, 'tea_break'),
(3, 3, '10:30:00', '11:25:00', 'Session 3', 0, 'class'),
(4, 4, '11:25:00', '12:20:00', 'Session 4', 0, 'class'),
(5, 5, '14:00:00', '15:00:00', 'Session 5 (02:00 - 03:00 PM)', 0, 'class')
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`);

-- Seed Sections: 4 sections per year for BCA and BCOM (total 24 sections)
INSERT INTO `sections` (`id`, `department_id`, `year_id`, `semester`, `section_name`, `display_code`, `room_no`) VALUES
-- BCA 1st Year (Sem 1)
(1, 1, 1, 1, 'Section A', 'BCA-1A', 'Room 101'),
(2, 1, 1, 1, 'Section B', 'BCA-1B', 'Room 102'),
(3, 1, 1, 1, 'Section C', 'BCA-1C', 'Room 103'),
(4, 1, 1, 1, 'Section D', 'BCA-1D', 'Room 104'),
-- BCA 2nd Year (Sem 3)
(5, 1, 2, 3, 'Section A', 'BCA-2A', 'Room 201'),
(6, 1, 2, 3, 'Section B', 'BCA-2B', 'Room 202'),
(7, 1, 2, 3, 'Section C', 'BCA-2C', 'Room 203'),
(8, 1, 2, 3, 'Section D', 'BCA-2D', 'Room 204'),
-- BCA 3rd Year (Sem 5) - e.g. 5B1, 5B2, 5B3, 5B4
(9, 1, 3, 5, '5B1', 'BCA-5B1', 'Room 301'),
(10, 1, 3, 5, '5B2', 'BCA-5B2', 'Room 302'),
(11, 1, 3, 5, '5B3', 'BCA-5B3', 'Room 303'),
(12, 1, 3, 5, '5B4', 'BCA-5B4', 'Room 304'),

-- BCOM 1st Year
(13, 2, 1, 1, 'Section A', 'BCOM-1A', 'Commerce Block 101'),
(14, 2, 1, 1, 'Section B', 'BCOM-1B', 'Commerce Block 102'),
(15, 2, 1, 1, 'Section C', 'BCOM-1C', 'Commerce Block 103'),
(16, 2, 1, 1, 'Section D', 'BCOM-1D', 'Commerce Block 104'),
-- BCOM 2nd Year
(17, 2, 2, 3, 'Section A', 'BCOM-2A', 'Commerce Block 201'),
(18, 2, 2, 3, 'Section B', 'BCOM-2B', 'Commerce Block 202'),
(19, 2, 2, 3, 'Section C', 'BCOM-2C', 'Commerce Block 203'),
(20, 2, 2, 3, 'Section D', 'BCOM-2D', 'Commerce Block 204'),
-- BCOM 3rd Year
(21, 2, 3, 5, 'Section A', 'BCOM-3A', 'Commerce Block 301'),
(22, 2, 3, 5, 'Section B', 'BCOM-3B', 'Commerce Block 302'),
(23, 2, 3, 5, 'Section C', 'BCOM-3C', 'Commerce Block 303'),
(24, 2, 3, 5, 'Section D', 'BCOM-3D', 'Commerce Block 304')
ON DUPLICATE KEY UPDATE `display_code`=VALUES(`display_code`);
