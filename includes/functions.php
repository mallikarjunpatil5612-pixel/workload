<?php
/**
 * Core Application Functions & Business Logic
 * Compatible across MySQL and SQLite PDO
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize strings for safe output and input
 */
function clean($data) {
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper to convert slot time to total minutes from midnight (12h/24h aware)
 */
function get_slot_time_minutes($timeStr) {
    if (empty($timeStr)) return 0;
    $parts = explode(':', trim($timeStr));
    $h = intval($parts[0] ?? 0);
    $m = intval($parts[1] ?? 0);
    // In college timetables, hours 1 to 6 represent afternoon (1:00 PM to 6:00 PM)
    if ($h >= 1 && $h <= 6) {
        $h += 12;
    }
    return $h * 60 + $m;
}

/**
 * Format 24-hour time string into user-friendly 12-hour AM/PM format (e.g. 01:25 PM, 09:00 AM)
 */
function format_time_12hr($timeStr) {
    if (empty($timeStr)) return '-';
    $clean = trim($timeStr);
    if (stripos($clean, 'AM') !== false || stripos($clean, 'PM') !== false) {
        return $clean;
    }
    $parts = explode(':', $clean);
    if (count($parts) < 2) return $clean;
    $h = intval($parts[0] ?? 0);
    $m = sprintf('%02d', intval($parts[1] ?? 0));
    $ampm = 'AM';
    $displayH = $h;
    if ($h >= 13 && $h <= 23) {
        $ampm = 'PM';
        $displayH = $h - 12;
    } elseif ($h === 12) {
        $ampm = 'PM';
        $displayH = 12;
    } elseif ($h >= 1 && $h <= 6) {
        $ampm = 'PM';
        $displayH = $h;
    } elseif ($h === 0) {
        $ampm = 'AM';
        $displayH = 12;
    } else {
        $ampm = 'AM';
        $displayH = $h;
    }
    return sprintf('%02d:%s %s', $displayH, $m, $ampm);
}


/**
 * Calculate faculty workload for a given date range
 * Crucial Rule: A merged class taken by a faculty across 2 sections counts as ONE single hour (1.0 hr).
 */
function get_faculty_workload_summary($faculty_id, $start_date, $end_date) {
    $db = get_db();
    
    // We sum workload_credits where:
    // 1. Regular class = 1.0 hr
    // 2. Merged primary class = 1.0 hr
    // 3. Merged secondary class = 0.0 hr (linked to parent to prevent double-counting)
    $sql = "SELECT 
                COUNT(CASE WHEN workload_credits > 0 OR merged_log_parent_id IS NULL THEN 1 END) as total_entries,
                COALESCE(SUM(workload_credits), 0) as total_hours,
                SUM(CASE WHEN session_type = 'lab' AND (workload_credits > 0 OR merged_log_parent_id IS NULL) THEN 1 ELSE 0 END) as lab_sessions,
                SUM(CASE WHEN is_merged = 1 AND (workload_credits > 0 OR merged_log_parent_id IS NULL) THEN 1 ELSE 0 END) as merged_sessions,
                SUM(CASE WHEN session_type = 'theory' AND is_merged = 0 THEN 1 ELSE 0 END) as regular_sessions
            FROM class_logs 
            WHERE faculty_id = :faculty_id 
              AND log_date BETWEEN :start_date AND :end_date";
              
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'faculty_id' => $faculty_id,
        'start_date' => $start_date,
        'end_date'   => $end_date
    ]);
    
    $result = $stmt->fetch();
    return [
        'total_hours'      => floatval($result['total_hours']),
        'total_entries'    => intval($result['total_entries']),
        'lab_sessions'     => intval($result['lab_sessions']),
        'merged_sessions'  => intval($result['merged_sessions']),
        'regular_sessions' => intval($result['regular_sessions'])
    ];
}

/**
 * Get all faculties workload summary for monthly reports / calculations
 */
function get_all_faculties_workload($department = null, $start_date = null, $end_date = null) {
    $db = get_db();
    
    if (!$start_date) {
        $start_date = date('Y-m-01'); // 1st of current month
    }
    if (!$end_date) {
        $end_date = date('Y-m-t'); // End of current month
    }
    
    $whereDept = "";
    $params = [
        'start_date' => $start_date,
        'end_date'   => $end_date
    ];
    
    if ($department && $department !== 'ALL') {
        $whereDept = "AND (u.department = :dept OR u.department = 'BOTH')";
        $params['dept'] = $department;
    }
    
    $sql = "SELECT 
                u.id as faculty_id,
                u.employee_id,
                u.name as faculty_name,
                u.department,
                u.designation,
                u.status,
                COALESCE(SUM(cl.workload_credits), 0) as total_workload_hours,
                COUNT(cl.id) as total_logs_count,
                SUM(CASE WHEN cl.session_type = 'lab' THEN 1 ELSE 0 END) as lab_hours,
                SUM(CASE WHEN cl.is_merged = 1 AND cl.workload_credits > 0 THEN 1 ELSE 0 END) as merged_hours,
                SUM(CASE WHEN cl.session_type = 'theory' AND cl.is_merged = 0 THEN 1 ELSE 0 END) as theory_hours
            FROM users u
            LEFT JOIN class_logs cl ON u.id = cl.faculty_id AND cl.log_date BETWEEN :start_date AND :end_date
            WHERE u.role = 'faculty' $whereDept
            GROUP BY u.id, u.employee_id, u.name, u.department, u.designation, u.status
            ORDER BY total_workload_hours DESC, u.name ASC";
            
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get dynamic day class table for a given date, department, and academic year
 */
function get_dynamic_day_table($date, $department_id, $year_id) {
    $db = get_db();
    $isBoth = ($department_id === 0 || $department_id === '0' || $department_id === 'BOTH' || $department_id === 'ALL');
    
    // 1. Get all sections for this department & year
    if ($isBoth) {
        $stmtSec = $db->prepare("SELECT s.*, d.code as dept_code FROM sections s 
                                 JOIN departments d ON s.department_id = d.id 
                                 WHERE s.year_id = :year_id AND s.status = 'active' 
                                 ORDER BY d.code ASC, s.section_name ASC");
        $stmtSec->execute(['year_id' => $year_id]);
    } else {
        $stmtSec = $db->prepare("SELECT s.*, d.code as dept_code FROM sections s 
                                 JOIN departments d ON s.department_id = d.id 
                                 WHERE s.department_id = :dept_id AND s.year_id = :year_id AND s.status = 'active' 
                                 ORDER BY s.section_name ASC");
        $stmtSec->execute(['dept_id' => $department_id, 'year_id' => $year_id]);
    }
    $sections = $stmtSec->fetchAll();
    
    // 2. Get year-specific time slots or default college slots
    if ($isBoth) {
        $stmtSlots = $db->prepare("SELECT * FROM time_slots 
                                   WHERE is_active = 1 
                                     AND (year_id = :year_id OR year_id IS NULL)
                                   ORDER BY session_number ASC");
        $stmtSlots->execute(['year_id' => $year_id]);
    } else {
        $stmtSlots = $db->prepare("SELECT * FROM time_slots 
                                   WHERE is_active = 1 
                                     AND ((department_id = :dept_id AND year_id = :year_id) 
                                          OR (department_id IS NULL AND year_id IS NULL))
                                   ORDER BY department_id DESC, year_id DESC, session_number ASC");
        $stmtSlots->execute(['dept_id' => $department_id, 'year_id' => $year_id]);
    }
    $allCandidateSlots = $stmtSlots->fetchAll();
    
    $time_slots = [];
    $seenSessions = [];
    foreach ($allCandidateSlots as $slot) {
        $sessNum = $slot['session_number'];
        if (!isset($seenSessions[$sessNum])) {
            $time_slots[] = $slot;
            $seenSessions[$sessNum] = true;
        }
    }
    usort($time_slots, function($a, $b) {
        $minA = get_slot_time_minutes($a['start_time']);
        $minB = get_slot_time_minutes($b['start_time']);
        if ($minA === $minB) {
            return $a['session_number'] <=> $b['session_number'];
        }
        return $minA <=> $minB;
    });
    
    // 3. Get pre-designed schedule if any for this day
    if ($isBoth) {
        $stmtSched = $db->prepare("SELECT ds.*, u.name as assigned_faculty_name, s2.display_code as merged_section_code 
                                   FROM daily_schedules ds
                                   LEFT JOIN users u ON ds.assigned_faculty_id = u.id
                                   LEFT JOIN sections s2 ON ds.merged_with_section_id = s2.id
                                   WHERE ds.schedule_date = :sdate AND ds.year_id = :year_id");
        $stmtSched->execute(['sdate' => $date, 'year_id' => $year_id]);
    } else {
        $stmtSched = $db->prepare("SELECT ds.*, u.name as assigned_faculty_name, s2.display_code as merged_section_code 
                                   FROM daily_schedules ds
                                   LEFT JOIN users u ON ds.assigned_faculty_id = u.id
                                   LEFT JOIN sections s2 ON ds.merged_with_section_id = s2.id
                                   WHERE ds.schedule_date = :sdate AND ds.department_id = :dept_id AND ds.year_id = :year_id");
        $stmtSched->execute(['sdate' => $date, 'dept_id' => $department_id, 'year_id' => $year_id]);
    }
    $schedules = $stmtSched->fetchAll();
    
    $scheduleMap = [];
    foreach ($schedules as $sch) {
        $key = $sch['section_id'] . '_' . $sch['session_number'];
        $scheduleMap[$key] = $sch;
    }
    
    // 4. Get actual class logs submitted by faculty for this day
    if ($isBoth) {
        $stmtLogs = $db->prepare("SELECT cl.*, COALESCE(NULLIF(cl.faculty_name, ''), u.name) as logged_faculty_name, u.employee_id, s2.display_code as merged_section_code
                                  FROM class_logs cl
                                  LEFT JOIN users u ON cl.faculty_id = u.id
                                  LEFT JOIN sections s2 ON cl.merged_with_section_id = s2.id
                                  WHERE cl.log_date = :ldate AND cl.year_id = :year_id");
        $stmtLogs->execute(['ldate' => $date, 'year_id' => $year_id]);
    } else {
        $stmtLogs = $db->prepare("SELECT cl.*, COALESCE(NULLIF(cl.faculty_name, ''), u.name) as logged_faculty_name, u.employee_id, s2.display_code as merged_section_code
                                  FROM class_logs cl
                                  LEFT JOIN users u ON cl.faculty_id = u.id
                                  LEFT JOIN sections s2 ON cl.merged_with_section_id = s2.id
                                  WHERE cl.log_date = :ldate AND cl.department_id = :dept_id AND cl.year_id = :year_id");
        $stmtLogs->execute(['ldate' => $date, 'dept_id' => $department_id, 'year_id' => $year_id]);
    }
    $logs = $stmtLogs->fetchAll();
    
    $logsMap = [];
    foreach ($logs as $l) {
        if (!empty($l['faculty_name'])) {
            $l['faculty_name'] = preg_replace('/^Admin\s*Mallikarjun/i', 'Prof. Mallikarjun Patil', $l['faculty_name']);
            $l['faculty_name'] = preg_replace('/^Admin\s+/i', 'Prof. ', $l['faculty_name']);
        }
        if (!empty($l['logged_faculty_name'])) {
            $l['logged_faculty_name'] = preg_replace('/^Admin\s*Mallikarjun/i', 'Prof. Mallikarjun Patil', $l['logged_faculty_name']);
            $l['logged_faculty_name'] = preg_replace('/^Admin\s+/i', 'Prof. ', $l['logged_faculty_name']);
        }
        $key = $l['section_id'] . '_' . $l['session_number'];
        $logsMap[$key] = $l;
    }
    
    // 5. Check if there is any special event / no-class day notice
    if ($isBoth) {
        $stmtEvent = $db->prepare("SELECT * FROM special_events 
                                   WHERE event_date = :edate 
                                     AND (year_id IS NULL OR year_id = :year_id)");
        $stmtEvent->execute(['edate' => $date, 'year_id' => $year_id]);
    } else {
        $stmtEvent = $db->prepare("SELECT * FROM special_events 
                                   WHERE event_date = :edate 
                                     AND (department_id IS NULL OR department_id = :dept_id)
                                     AND (year_id IS NULL OR year_id = :year_id)");
        $stmtEvent->execute(['edate' => $date, 'dept_id' => $department_id, 'year_id' => $year_id]);
    }
    $special_event = $stmtEvent->fetch();
    
    return [
        'date'          => $date,
        'department_id' => $department_id,
        'year_id'       => $year_id,
        'sections'      => $sections,
        'time_slots'    => $time_slots,
        'schedules'     => $scheduleMap,
        'logs'          => $logsMap,
        'special_event' => $special_event
    ];
}

/**
 * Get section drilldown logs (e.g. "Who all took classes for Section 5B1 on date X or random day?")
 */
function get_section_logs_drilldown($section_query, $date = null) {
    $db = get_db();
    
    $where = [];
    $params = [];
    
    $where[] = "(s.section_name LIKE :sec OR s.display_code LIKE :sec)";
    $params['sec'] = '%' . $section_query . '%';
    
    if (!empty($date)) {
        $where[] = "cl.log_date = :ldate";
        $params['ldate'] = $date;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT 
                cl.id,
                cl.log_date,
                cl.session_number,
                cl.subject_name,
                cl.topic_covered,
                cl.start_time,
                cl.end_time,
                cl.session_type,
                cl.is_merged,
                cl.remarks,
                u.name as faculty_name,
                u.employee_id,
                u.department as faculty_dept,
                d.code as dept_code,
                d.name as dept_name,
                ay.name as year_name,
                s.display_code as section_code,
                s.section_name,
                s.room_no,
                s2.display_code as merged_section_code
            FROM class_logs cl
            JOIN users u ON cl.faculty_id = u.id
            JOIN sections s ON cl.section_id = s.id
            JOIN departments d ON cl.department_id = d.id
            JOIN academic_years ay ON cl.year_id = ay.id
            LEFT JOIN sections s2 ON cl.merged_with_section_id = s2.id
            WHERE $whereClause
            ORDER BY cl.log_date DESC, cl.session_number ASC";
            
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Helper to log class session with automatic merged class dual-logging
 */
function record_class_log($data, $faculty_id, $faculty_name) {
    $db = get_db();
    
    $log_date         = $data['log_date'];
    $department_id    = intval($data['department_id']);
    $year_id          = intval($data['year_id']);
    $section_id       = intval($data['section_id']);
    $session_number   = intval($data['session_number']);
    $subject_name     = trim($data['subject_name']);
    $topic_covered    = trim($data['topic_covered']);
    $start_time       = $data['start_time'];
    $end_time         = $data['end_time'];
    $session_type     = $data['session_type'] ?? 'theory';
    $is_merged        = !empty($data['is_merged']) ? 1 : 0;
    $merged_section_id= $is_merged && !empty($data['merged_with_section_id']) ? intval($data['merged_with_section_id']) : null;
    $remarks          = $data['remarks'] ?? null;
    
    if ($session_number < 1 || $session_number > 5) {
        throw new Exception("Invalid session number. Must be 1 to 5.");
    }
    
    // Check if a log already exists for this date, section, and session
    $stmtCheck = $db->prepare("SELECT id FROM class_logs WHERE log_date = :d AND section_id = :s AND session_number = :n");
    $stmtCheck->execute(['d' => $log_date, 's' => $section_id, 'n' => $session_number]);
    $existingLogId = $stmtCheck->fetchColumn();

    if ($existingLogId) {
        // Update primary log
        $stmtUpdate = $db->prepare("UPDATE class_logs SET 
            faculty_id = :faculty_id,
            faculty_name = :faculty_name,
            subject_name = :subj,
            topic_covered = :topic,
            start_time = :start_t,
            end_time = :end_t,
            session_type = :sess_type,
            is_merged = :is_merged,
            merged_with_section_id = :merged_sec,
            workload_credits = 1.0,
            remarks = :remarks
            WHERE id = :id");
        $stmtUpdate->execute([
            'faculty_id'   => $faculty_id,
            'faculty_name' => $faculty_name,
            'subj'         => $subject_name,
            'topic'        => $topic_covered,
            'start_t'      => $start_time,
            'end_t'        => $end_time,
            'sess_type'    => $session_type,
            'is_merged'    => $is_merged,
            'merged_sec'   => $merged_section_id,
            'remarks'      => $remarks,
            'id'           => $existingLogId
        ]);
        $primaryLogId = $existingLogId;
    } else {
        // Insert primary log
        $stmtInsert = $db->prepare("INSERT INTO class_logs 
            (log_date, department_id, year_id, section_id, session_number, faculty_id, faculty_name, subject_name, topic_covered, start_time, end_time, session_type, is_merged, merged_with_section_id, workload_credits, remarks)
            VALUES 
            (:log_date, :dept_id, :year_id, :sec_id, :session_no, :faculty_id, :faculty_name, :subj, :topic, :start_t, :end_t, :sess_type, :is_merged, :merged_sec, 1.0, :remarks)");
        $stmtInsert->execute([
            'log_date'     => $log_date,
            'dept_id'      => $department_id,
            'year_id'      => $year_id,
            'sec_id'       => $section_id,
            'session_no'   => $session_number,
            'faculty_id'   => $faculty_id,
            'faculty_name' => $faculty_name,
            'subj'         => $subject_name,
            'topic'        => $topic_covered,
            'start_t'      => $start_time,
            'end_t'        => $end_time,
            'sess_type'    => $session_type,
            'is_merged'    => $is_merged,
            'merged_sec'   => $merged_section_id,
            'remarks'      => $remarks
        ]);
        $primaryLogId = $db->lastInsertId();
    }
    
    // 2. If merged class, also register in the secondary section with workload_credits = 0.0 (Linked to primary to avoid double counting)
    if ($is_merged && $merged_section_id && $merged_section_id !== $section_id) {
        $secStmt = $db->prepare("SELECT department_id, year_id FROM sections WHERE id = :id");
        $secStmt->execute(['id' => $merged_section_id]);
        $secInfo = $secStmt->fetch();
        
        if ($secInfo) {
            $stmtCheckSec = $db->prepare("SELECT id FROM class_logs WHERE log_date = :d AND section_id = :s AND session_number = :n");
            $stmtCheckSec->execute(['d' => $log_date, 's' => $merged_section_id, 'n' => $session_number]);
            $existingSecLogId = $stmtCheckSec->fetchColumn();

            if ($existingSecLogId) {
                $stmtUpdateSec = $db->prepare("UPDATE class_logs SET
                    faculty_id = :faculty_id,
                    faculty_name = :faculty_name,
                    subject_name = :subj,
                    topic_covered = :topic,
                    start_time = :start_t,
                    end_time = :end_t,
                    session_type = 'merged',
                    is_merged = 1,
                    merged_with_section_id = :parent_sec,
                    workload_credits = 0.0,
                    merged_log_parent_id = :parent_id,
                    remarks = :remarks
                    WHERE id = :id");
                $stmtUpdateSec->execute([
                    'faculty_id'   => $faculty_id,
                    'faculty_name' => $faculty_name,
                    'subj'         => $subject_name,
                    'topic'        => "[Combined with Section] " . $topic_covered,
                    'start_t'      => $start_time,
                    'end_t'        => $end_time,
                    'parent_sec'   => $section_id,
                    'parent_id'    => $primaryLogId,
                    'remarks'      => "Combined Session",
                    'id'           => $existingSecLogId
                ]);
            } else {
                $stmtInsertSec = $db->prepare("INSERT INTO class_logs 
                    (log_date, department_id, year_id, section_id, session_number, faculty_id, faculty_name, subject_name, topic_covered, start_time, end_time, session_type, is_merged, merged_with_section_id, workload_credits, merged_log_parent_id, remarks)
                    VALUES 
                    (:log_date, :dept_id, :year_id, :sec_id, :session_no, :faculty_id, :faculty_name, :subj, :topic, :start_t, :end_t, 'merged', 1, :parent_sec, 0.0, :parent_id, :remarks)");
                $stmtInsertSec->execute([
                    'log_date'     => $log_date,
                    'dept_id'      => $secInfo['department_id'],
                    'year_id'      => $secInfo['year_id'],
                    'sec_id'       => $merged_section_id,
                    'session_no'   => $session_number,
                    'faculty_id'   => $faculty_id,
                    'faculty_name' => $faculty_name,
                    'subj'         => $subject_name,
                    'topic'        => "[Combined with Section] " . $topic_covered,
                    'start_t'      => $start_time,
                    'end_t'        => $end_time,
                    'parent_sec'   => $section_id,
                    'parent_id'    => $primaryLogId,
                    'remarks'      => "Combined Session"
                ]);
            }
        }
    }
    
    return true;
}
