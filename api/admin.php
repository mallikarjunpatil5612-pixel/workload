<?php
/**
 * Admin Operations & Control API Endpoint
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = get_db();

    switch ($action) {
        // 1. Dashboard Top Summary Stats
        case 'get_dashboard_stats':
            $today = date('Y-m-d');

            // Total active faculties
            $totalFaculty = $db->query("SELECT COUNT(*) FROM users WHERE role = 'faculty' AND status = 'active'")->fetchColumn();
            
            // Classes logged today
            $stmtToday = $db->prepare("SELECT COUNT(*) as total_logs, 
                                              SUM(CASE WHEN department_id = 1 THEN 1 ELSE 0 END) as bca_logs,
                                              SUM(CASE WHEN department_id = 2 THEN 1 ELSE 0 END) as bcom_logs,
                                              SUM(CASE WHEN session_type = 'lab' THEN 1 ELSE 0 END) as lab_logs,
                                              SUM(CASE WHEN is_merged = 1 THEN 1 ELSE 0 END) as merged_logs
                                       FROM class_logs WHERE log_date = :t");
            $stmtToday->execute(['t' => $today]);
            $todayData = $stmtToday->fetch();

            // Total sections count
            $totalSections = $db->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn();

            // Recent system activities
            $stmtRecent = $db->query("SELECT cl.*, COALESCE(NULLIF(cl.faculty_name, ''), u.name) as faculty_name, s.display_code as section_code 
                                      FROM class_logs cl
                                      LEFT JOIN users u ON cl.faculty_id = u.id
                                      JOIN sections s ON cl.section_id = s.id
                                      ORDER BY cl.created_at DESC LIMIT 8");
            $recentActivities = $stmtRecent->fetchAll();
            foreach ($recentActivities as &$ra) {
                if (!empty($ra['faculty_name'])) {
                    $ra['faculty_name'] = preg_replace('/^Admin\s*Mallikarjun/i', 'Prof. Mallikarjun Patil', $ra['faculty_name']);
                    $ra['faculty_name'] = preg_replace('/^Admin\s+/i', 'Prof. ', $ra['faculty_name']);
                }
            }
            unset($ra);

            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_faculty'  => intval($totalFaculty),
                    'total_sections' => intval($totalSections),
                    'today_classes'  => intval($todayData['total_logs'] ?? 0),
                    'bca_today'      => intval($todayData['bca_logs'] ?? 0),
                    'bcom_today'     => intval($todayData['bcom_logs'] ?? 0),
                    'labs_today'     => intval($todayData['lab_logs'] ?? 0),
                    'merged_today'   => intval($todayData['merged_logs'] ?? 0)
                ],
                'recent_activities' => $recentActivities
            ]);
            break;

        // 2. Master Daily Class Matrix / Day-wise Report Data
        case 'get_master_matrix':
            $date = $_GET['date'] ?? date('Y-m-d');
            $deptCode = $_GET['department'] ?? 'ALL'; // ALL, BCA, BCOM
            $year = intval($_GET['year'] ?? 0); // 0 = all years, 1, 2, 3

            $whereSec = ["s.status = 'active'"];
            $params = [];

            if ($deptCode !== 'ALL') {
                $whereSec[] = "d.code = :dept";
                $params['dept'] = $deptCode;
            }
            if ($year > 0) {
                $whereSec[] = "ay.year_number = :yr";
                $params['yr'] = $year;
            }

            $whereSql = implode(' AND ', $whereSec);

            // Fetch sections
            $stmtSec = $db->prepare("SELECT s.*, d.code as dept_code, d.name as dept_name, ay.year_number, ay.name as year_name 
                                     FROM sections s
                                     JOIN departments d ON s.department_id = d.id
                                     JOIN academic_years ay ON s.year_id = ay.id
                                     WHERE $whereSql
                                     ORDER BY d.code ASC, ay.year_number ASC, s.section_name ASC");
            $stmtSec->execute($params);
            $sections = $stmtSec->fetchAll();

            // Fetch time slots (de-duplicated by session number and ordered chronologically by start time)
            $dept = $deptCode;
            if ($deptCode !== 'ALL' || $year > 0) {
                $deptIdVal = null;
                if ($deptCode !== 'ALL') {
                    $stD = $db->prepare("SELECT id FROM departments WHERE code = :code");
                    $stD->execute(['code' => $deptCode]);
                    $deptIdVal = $stD->fetchColumn();
                }
                $slotConds = [];
                $slotParams = [];
                if ($deptIdVal && $year > 0) {
                    $slotConds[] = "(department_id = :dval AND year_id = :yval)";
                    $slotParams['dval'] = $deptIdVal;
                    $slotParams['yval'] = $year;
                } else if ($deptIdVal) {
                    $slotConds[] = "(department_id = :dval)";
                    $slotParams['dval'] = $deptIdVal;
                } else if ($year > 0) {
                    $slotConds[] = "(year_id = :yval)";
                    $slotParams['yval'] = $year;
                }
                $slotConds[] = "(department_id IS NULL AND year_id IS NULL)";
                $whereSlots = "WHERE is_active = 1 AND (" . implode(" OR ", $slotConds) . ")";
                
                $stmtSlots = $db->prepare("SELECT * FROM time_slots $whereSlots ORDER BY department_id DESC, year_id DESC, session_number ASC");
                $stmtSlots->execute($slotParams);
                $rawSlots = $stmtSlots->fetchAll();
            } else {
                $stmtSlots = $db->query("SELECT * FROM time_slots WHERE is_active = 1 ORDER BY department_id DESC, year_id DESC, session_number ASC");
                $rawSlots = $stmtSlots ? $stmtSlots->fetchAll() : [];
            }

            $timeSlots = [];
            $seenSessions = [];
            foreach ($rawSlots as $slot) {
                $sessNum = $slot['session_number'];
                if (!isset($seenSessions[$sessNum])) {
                    $timeSlots[] = $slot;
                    $seenSessions[$sessNum] = true;
                }
            }

            if (empty($timeSlots)) {
                $timeSlots = [
                    ['session_number' => 1, 'label' => 'Session 1 (08:30 - 09:25)', 'start_time' => '08:30:00', 'end_time' => '09:25:00', 'is_break' => 0, 'slot_type' => 'class'],
                    ['session_number' => 2, 'label' => 'Session 2 (09:25 - 10:20)', 'start_time' => '09:25:00', 'end_time' => '10:20:00', 'is_break' => 0, 'slot_type' => 'class'],
                    ['session_number' => 101, 'label' => 'Break (Interval)', 'start_time' => '10:20:00', 'end_time' => '10:30:00', 'is_break' => 1, 'slot_type' => 'tea_break'],
                    ['session_number' => 3, 'label' => 'Session 3 (10:30 - 11:25)', 'start_time' => '10:30:00', 'end_time' => '11:25:00', 'is_break' => 0, 'slot_type' => 'class'],
                    ['session_number' => 4, 'label' => 'Session 4 (11:25 - 12:20)', 'start_time' => '11:25:00', 'end_time' => '12:20:00', 'is_break' => 0, 'slot_type' => 'class'],
                    ['session_number' => 5, 'label' => 'Session 5 (02:00 - 03:00 PM)', 'start_time' => '14:00:00', 'end_time' => '15:00:00', 'is_break' => 0, 'slot_type' => 'class']
                ];
            }

            // CRITICAL: Sort time slots chronologically by start time so breaks are placed centrally session-wise
            usort($timeSlots, function($a, $b) {
                $minA = get_slot_time_minutes($a['start_time']);
                $minB = get_slot_time_minutes($b['start_time']);
                if ($minA === $minB) {
                    return $a['session_number'] <=> $b['session_number'];
                }
                return $minA <=> $minB;
            });

            // Fetch all logs for this date
            $stmtLogs = $db->prepare("SELECT cl.*, COALESCE(NULLIF(cl.faculty_name, ''), u.name) as faculty_name, u.employee_id, s2.display_code as merged_section_code
                                      FROM class_logs cl
                                      LEFT JOIN users u ON cl.faculty_id = u.id
                                      LEFT JOIN sections s2 ON cl.merged_with_section_id = s2.id
                                      WHERE cl.log_date = :ldate");
            $stmtLogs->execute(['ldate' => $date]);
            $logs = $stmtLogs->fetchAll();

            $logsMap = [];
            foreach ($logs as $l) {
                if (!empty($l['faculty_name'])) {
                    $l['faculty_name'] = preg_replace('/^Admin\s*Mallikarjun/i', 'Prof. Mallikarjun Patil', $l['faculty_name']);
                    $l['faculty_name'] = preg_replace('/^Admin\s+/i', 'Prof. ', $l['faculty_name']);
                }
                $key = $l['section_id'] . '_' . $l['session_number'];
                $logsMap[$key] = $l;
            }

            // Fetch pre-schedules
            $stmtSched = $db->prepare("SELECT ds.*, u.name as assigned_faculty_name, s2.display_code as merged_section_code
                                       FROM daily_schedules ds
                                       LEFT JOIN users u ON ds.assigned_faculty_id = u.id
                                       LEFT JOIN sections s2 ON ds.merged_with_section_id = s2.id
                                       WHERE ds.schedule_date = :sdate");
            $stmtSched->execute(['sdate' => $date]);
            $schedules = $stmtSched->fetchAll();

            $schedMap = [];
            foreach ($schedules as $sc) {
                if (!empty($sc['assigned_faculty_name'])) {
                    $sc['assigned_faculty_name'] = preg_replace('/^Admin\s*Mallikarjun/i', 'Prof. Mallikarjun Patil', $sc['assigned_faculty_name']);
                    $sc['assigned_faculty_name'] = preg_replace('/^Admin\s+/i', 'Prof. ', $sc['assigned_faculty_name']);
                }
                $key = $sc['section_id'] . '_' . $sc['session_number'];
                $schedMap[$key] = $sc;
            }

            // Special Events
            $stmtEvents = $db->prepare("SELECT * FROM special_events WHERE event_date = :edate");
            $stmtEvents->execute(['edate' => $date]);
            $events = $stmtEvents->fetchAll();

            echo json_encode([
                'success'    => true,
                'date'       => $date,
                'sections'   => $sections,
                'time_slots' => $timeSlots,
                'logs'       => $logsMap,
                'schedules'  => $schedMap,
                'events'     => $events
            ]);
            break;

        // 3. Section Search Drilldown (e.g., "Who took classes for Section 5B1 on date X?")
        case 'query_section':
            $sectionQuery = trim($_GET['section_query'] ?? '5B1');
            $date = !empty($_GET['date']) ? trim($_GET['date']) : null;

            $results = get_section_logs_drilldown($sectionQuery, $date);
            echo json_encode([
                'success' => true,
                'query'   => $sectionQuery,
                'date'    => $date,
                'count'   => count($results),
                'results' => $results
            ]);
            break;

        // 4. Monthly Faculty Workload Calculation & Report
        case 'get_workload_report':
            $dept = $_GET['department'] ?? 'ALL';
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');

            $reportData = get_all_faculties_workload($dept, $startDate, $endDate);
            echo json_encode([
                'success'    => true,
                'department' => $dept,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'data'       => $reportData
            ]);
            break;

        // 5. Faculty Management
        case 'get_faculties':
            $stmt = $db->query("SELECT id, employee_id, name, email, role, department, designation, phone, status, created_at 
                                FROM users WHERE role = 'faculty' ORDER BY department ASC, name ASC");
            echo json_encode(['success' => true, 'faculties' => $stmt->fetchAll()]);
            break;

        case 'save_faculty':
            $id          = intval($_POST['id'] ?? 0);
            $employeeId  = trim($_POST['employee_id'] ?? '');
            $name        = trim($_POST['name'] ?? '');
            $email       = trim($_POST['email'] ?? '');
            $password    = trim($_POST['password'] ?? '');
            $department  = $_POST['department'] ?? 'BCA';
            $designation = trim($_POST['designation'] ?? 'Assistant Professor');
            $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;

            if (empty($employeeId) || empty($name) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Employee ID, Name and Email are required.']);
                exit;
            }

            if ($id > 0) {
                // Keep existing phone if phone field not present in request
                if ($phone === null) {
                    $currPhone = $db->query("SELECT phone FROM users WHERE id = " . intval($id))->fetchColumn();
                    $phone = $currPhone ?: null;
                }

                // Update
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("UPDATE users SET employee_id = :emp, name = :name, email = :email, password = :pwd, department = :dept, designation = :desig, phone = :phone WHERE id = :id");
                    $stmt->execute(['emp' => $employeeId, 'name' => $name, 'email' => $email, 'pwd' => $hash, 'dept' => $department, 'desig' => $designation, 'phone' => $phone, 'id' => $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET employee_id = :emp, name = :name, email = :email, department = :dept, designation = :desig, phone = :phone WHERE id = :id");
                    $stmt->execute(['emp' => $employeeId, 'name' => $name, 'email' => $email, 'dept' => $department, 'desig' => $designation, 'phone' => $phone, 'id' => $id]);
                }
                echo json_encode(['success' => true, 'message' => 'Faculty details updated successfully.']);
            } else {
                // Insert
                if (empty($password)) {
                    $password = 'Faculty@123';
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (employee_id, name, email, password, role, department, designation, phone)
                                      VALUES (:emp, :name, :email, :pwd, 'faculty', :dept, :desig, :phone)");
                $stmt->execute(['emp' => $employeeId, 'name' => $name, 'email' => $email, 'pwd' => $hash, 'dept' => $department, 'desig' => $designation, 'phone' => $phone]);
                echo json_encode(['success' => true, 'message' => 'New faculty added successfully. Default password is ' . $password]);
            }
            break;

        case 'toggle_faculty_status':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE users SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = :id AND role = 'faculty'");
            $stmt->execute(['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Faculty status updated.']);
            break;

        case 'delete_faculty':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid faculty ID.']);
                exit;
            }

            // Check faculty role and prevent deleting admin accounts
            $chk = $db->prepare("SELECT role, name FROM users WHERE id = :id");
            $chk->execute(['id' => $id]);
            $userToDelete = $chk->fetch();

            if (!$userToDelete) {
                echo json_encode(['success' => false, 'message' => 'Faculty member not found.']);
                exit;
            }

            if ($userToDelete['role'] === 'admin') {
                echo json_encode(['success' => false, 'message' => 'Administrator accounts cannot be deleted.']);
                exit;
            }

            // Clean up related records
            $db->prepare("DELETE FROM class_logs WHERE faculty_id = :id")->execute(['id' => $id]);
            $db->prepare("UPDATE daily_schedules SET assigned_faculty_id = NULL WHERE assigned_faculty_id = :id")->execute(['id' => $id]);

            // Delete faculty user
            $stmtDel = $db->prepare("DELETE FROM users WHERE id = :id AND role = 'faculty'");
            $stmtDel->execute(['id' => $id]);

            echo json_encode([
                'success' => true,
                'message' => 'Faculty member "' . $userToDelete['name'] . '" deleted successfully.'
            ]);
            break;

        // 6. Section & Semester Management
        case 'get_sections':
            $stmt = $db->query("SELECT s.*, d.code as dept_code, d.name as dept_name, ay.year_number, ay.name as year_name 
                                FROM sections s
                                JOIN departments d ON s.department_id = d.id
                                JOIN academic_years ay ON s.year_id = ay.id
                                ORDER BY d.code ASC, ay.year_number ASC, s.section_name ASC");
            echo json_encode(['success' => true, 'sections' => $stmt->fetchAll()]);
            break;

        case 'save_section':
            $id           = intval($_POST['id'] ?? 0);
            $deptId       = intval($_POST['department_id'] ?? 1);
            $yearId       = intval($_POST['year_id'] ?? 1);
            $semester     = intval($_POST['semester'] ?? 1);
            $sectionName  = trim($_POST['section_name'] ?? '');
            $displayCode  = trim($_POST['display_code'] ?? '');
            $roomNo       = trim($_POST['room_no'] ?? '');

            if (empty($sectionName)) {
                echo json_encode(['success' => false, 'message' => 'Section name is required.']);
                exit;
            }

            if (empty($displayCode)) {
                $deptCode = $db->query("SELECT code FROM departments WHERE id = $deptId")->fetchColumn() ?: 'SEC';
                $displayCode = $deptCode . '-' . $sectionName;
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE sections SET department_id = :dept, year_id = :yr, semester = :sem, section_name = :sec, display_code = :dcode, room_no = :room WHERE id = :id");
                $stmt->execute(['dept' => $deptId, 'yr' => $yearId, 'sem' => $semester, 'sec' => $sectionName, 'dcode' => $displayCode, 'room' => $roomNo, 'id' => $id]);
                echo json_encode(['success' => true, 'message' => 'Section details updated successfully.']);
            } else {
                $stmt = $db->prepare("INSERT INTO sections (department_id, year_id, semester, section_name, display_code, room_no) VALUES (:dept, :yr, :sem, :sec, :dcode, :room)");
                $stmt->execute(['dept' => $deptId, 'yr' => $yearId, 'sem' => $semester, 'sec' => $sectionName, 'dcode' => $displayCode, 'room' => $roomNo]);
                echo json_encode(['success' => true, 'message' => 'New section created.']);
            }
            break;

        // 7. Schedule / Timetable Designer & Publisher
        case 'save_daily_schedule_slot':
            $scheduleDate   = $_POST['schedule_date'] ?? date('Y-m-d');
            $departmentId   = intval($_POST['department_id'] ?? 1);
            $yearId         = intval($_POST['year_id'] ?? 1);
            $sectionId      = intval($_POST['section_id'] ?? 0);
            $sessionNumber  = intval($_POST['session_number'] ?? 0);
            $expectedSubj   = trim($_POST['expected_subject'] ?? '');
            $assignedFacId  = !empty($_POST['assigned_faculty_id']) ? intval($_POST['assigned_faculty_id']) : null;
            $isMerged       = !empty($_POST['is_merged']) ? 1 : 0;
            $mergedSecId    = !empty($_POST['merged_with_section_id']) ? intval($_POST['merged_with_section_id']) : null;
            $isLab          = !empty($_POST['is_lab']) ? 1 : 0;
            $isNoClass      = !empty($_POST['is_no_class']) ? 1 : 0;
            $specialNote    = trim($_POST['special_note'] ?? '');

            // Reject scheduling regular teaching classes during Break Hour / Interval
            if ($sessionNumber >= 100) {
                echo json_encode(['success' => false, 'message' => 'Classes cannot be scheduled or assigned during Break Hour / Interval.']);
                exit;
            }

            // Check if slot exists
            $stmtCheck = $db->prepare("SELECT id FROM daily_schedules WHERE schedule_date = :sd AND section_id = :sec AND session_number = :sess");
            $stmtCheck->execute(['sd' => $scheduleDate, 'sec' => $sectionId, 'sess' => $sessionNumber]);
            $existingSlotId = $stmtCheck->fetchColumn();

            if ($existingSlotId) {
                $stmtUpdate = $db->prepare("UPDATE daily_schedules SET 
                    expected_subject = :subj,
                    assigned_faculty_id = :fac,
                    is_merged = :merged,
                    merged_with_section_id = :msec,
                    is_lab = :lab,
                    is_no_class = :noclass,
                    special_note = :note,
                    created_by_admin = :admin
                    WHERE id = :id");
                $stmtUpdate->execute([
                    'subj'    => $expectedSubj,
                    'fac'     => $assignedFacId,
                    'merged'  => $isMerged,
                    'msec'    => $mergedSecId,
                    'lab'     => $isLab,
                    'noclass' => $isNoClass,
                    'note'    => $specialNote,
                    'admin'   => $_SESSION['user_id'] ?? 1,
                    'id'      => $existingSlotId
                ]);
            } else {
                $stmtInsert = $db->prepare("INSERT INTO daily_schedules 
                    (schedule_date, department_id, year_id, section_id, session_number, expected_subject, assigned_faculty_id, is_merged, merged_with_section_id, is_lab, is_no_class, special_note, created_by_admin)
                    VALUES 
                    (:sdate, :dept, :yr, :sec, :sess, :subj, :fac, :merged, :msec, :lab, :noclass, :note, :admin)");
                $stmtInsert->execute([
                    'sdate'   => $scheduleDate,
                    'dept'    => $departmentId,
                    'yr'      => $yearId,
                    'sec'     => $sectionId,
                    'sess'    => $sessionNumber,
                    'subj'    => $expectedSubj,
                    'fac'     => $assignedFacId,
                    'merged'  => $isMerged,
                    'msec'    => $mergedSecId,
                    'lab'     => $isLab,
                    'noclass' => $isNoClass,
                    'note'    => $specialNote,
                    'admin'   => $_SESSION['user_id'] ?? 1
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Daily schedule slot published successfully.']);
            break;

        // 8. Special Event / Student Session / No Class announcement
        case 'save_special_event':
            $eventDate  = $_POST['event_date'] ?? date('Y-m-d');
            $deptId     = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
            $yearId     = !empty($_POST['year_id']) ? intval($_POST['year_id']) : null;
            $title      = trim($_POST['title'] ?? '');
            $desc       = trim($_POST['description'] ?? '');
            $eventType  = $_POST['event_type'] ?? 'student_session';

            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Event title is required.']);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO special_events (event_date, department_id, year_id, title, description, event_type, created_by)
                                  VALUES (:edate, :dept, :yr, :title, :desc, :etype, :admin)");
            $stmt->execute([
                'edate' => $eventDate,
                'dept'  => $deptId,
                'yr'    => $yearId,
                'title' => $title,
                'desc'  => $desc,
                'etype' => $eventType,
                'admin' => $_SESSION['user_id'] ?? 1
            ]);

            echo json_encode(['success' => true, 'message' => 'Special event notice published.']);
            break;

        // 9. Admin delete class log
        case 'delete_log':
            $logId = intval($_POST['log_id'] ?? 0);
            if ($logId > 0) {
                $stmt = $db->prepare("DELETE FROM class_logs WHERE id = :id OR merged_log_parent_id = :parent_id");
                $stmt->execute(['id' => $logId, 'parent_id' => $logId]);
                echo json_encode(['success' => true, 'message' => 'Class log removed successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid log ID.']);
            }
            break;

        // 10. Time Slots & Session Management (Year-Wise Timing Arrangement)
        case 'get_time_slots':
            $targetScope = $_GET['target_scope'] ?? '';
            $deptId = null;
            $yearId = null;

            if (!empty($targetScope)) {
                $parts = explode('_', $targetScope);
                if (count($parts) == 2) {
                    $deptId = intval($parts[0]);
                    $yearId = intval($parts[1]);
                }
            } else {
                if (isset($_GET['department_id']) && $_GET['department_id'] !== 'ALL' && $_GET['department_id'] !== '') {
                    $deptId = intval($_GET['department_id']);
                }
                if (!empty($_GET['year_id'])) {
                    $yearId = intval($_GET['year_id']);
                }
            }

            if ($deptId && $yearId) {
                // Fetch slots specifically for this Dept + Year
                $stmt = $db->prepare("SELECT ts.*, d.code as dept_code, d.name as dept_name, ay.name as year_name, ay.year_number 
                                      FROM time_slots ts
                                      LEFT JOIN departments d ON ts.department_id = d.id
                                      LEFT JOIN academic_years ay ON ts.year_id = ay.id
                                      WHERE ts.department_id = :dept AND ts.year_id = :yr
                                      ORDER BY ts.start_time ASC, ts.session_number ASC");
                $stmt->execute(['dept' => $deptId, 'yr' => $yearId]);
                $timeSlots = $stmt->fetchAll();

                // If no year-specific slots exist yet, fallback to college-wide defaults
                if (empty($timeSlots)) {
                    $stmt = $db->query("SELECT ts.*, d.code as dept_code, d.name as dept_name, ay.name as year_name, ay.year_number 
                                        FROM time_slots ts
                                        LEFT JOIN departments d ON ts.department_id = d.id
                                        LEFT JOIN academic_years ay ON ts.year_id = ay.id
                                        WHERE ts.department_id IS NULL AND ts.year_id IS NULL
                                        ORDER BY ts.start_time ASC, ts.session_number ASC");
                    $timeSlots = $stmt->fetchAll();
                }
            } else if ($deptId) {
                $stmt = $db->prepare("SELECT ts.*, d.code as dept_code, d.name as dept_name, ay.name as year_name, ay.year_number 
                                      FROM time_slots ts
                                      LEFT JOIN departments d ON ts.department_id = d.id
                                      LEFT JOIN academic_years ay ON ts.year_id = ay.id
                                      WHERE ts.department_id = :dept OR (ts.department_id IS NULL AND ts.year_id IS NULL)
                                      ORDER BY ts.start_time ASC, ts.session_number ASC");
                $stmt->execute(['dept' => $deptId]);
                $timeSlots = $stmt->fetchAll();
            } else {
                $stmt = $db->query("SELECT ts.*, d.code as dept_code, d.name as dept_name, ay.name as year_name, ay.year_number 
                                    FROM time_slots ts
                                    LEFT JOIN departments d ON ts.department_id = d.id
                                    LEFT JOIN academic_years ay ON ts.year_id = ay.id
                                    ORDER BY ts.department_id ASC, ts.year_id ASC, ts.start_time ASC, ts.session_number ASC");
                $timeSlots = $stmt->fetchAll();
            }

            usort($timeSlots, function($a, $b) {
                $scopeA = ($a['department_id'] ?? 0) * 10 + ($a['year_id'] ?? 0);
                $scopeB = ($b['department_id'] ?? 0) * 10 + ($b['year_id'] ?? 0);
                if ($scopeA !== $scopeB) {
                    return $scopeA <=> $scopeB;
                }
                $minA = get_slot_time_minutes($a['start_time']);
                $minB = get_slot_time_minutes($b['start_time']);
                if ($minA === $minB) {
                    return $a['session_number'] <=> $b['session_number'];
                }
                return $minA <=> $minB;
            });

            echo json_encode(['success' => true, 'time_slots' => $timeSlots]);
            break;

        case 'save_time_slot':
            $id            = intval($_POST['id'] ?? 0);
            $sessionNumber = intval($_POST['session_number'] ?? 1);
            $label         = trim($_POST['label'] ?? '');
            $startTime     = trim($_POST['start_time'] ?? '');
            $endTime       = trim($_POST['end_time'] ?? '');
            $isActive      = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $isBreak       = !empty($_POST['is_break']) ? 1 : 0;
            $slotType      = trim($_POST['slot_type'] ?? 'class');

            // Handle break hours automatically based on selection
            if ($sessionNumber == 101 || $slotType === 'tea_break') {
                $isBreak = 1;
                $slotType = 'tea_break';
                $label = 'Break (Interval)';
            } else if ($sessionNumber == 102 || $slotType === 'lunch_break') {
                $isBreak = 1;
                $slotType = 'lunch_break';
                $label = '🍱 Lunch Break';
            } else if ($isBreak == 1) {
                if (empty($label)) $label = 'Break';
            } else {
                if (empty($label)) {
                    $label = 'Session ' . $sessionNumber;
                }
            }

            $deptId = null;
            $yearId = null;
            $targetScope = $_POST['target_scope'] ?? ($_POST['target_year'] ?? '');
            if (!empty($targetScope)) {
                $parts = explode('_', $targetScope);
                if (count($parts) == 2) {
                    $deptId = intval($parts[0]);
                    $yearId = intval($parts[1]);
                }
            } else {
                $deptId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
                $yearId = !empty($_POST['year_id']) ? intval($_POST['year_id']) : null;
            }

            if (empty($startTime) || empty($endTime)) {
                echo json_encode(['success' => false, 'message' => 'Start time and End time are required.']);
                exit;
            }

            // Ensure start and end times are in HH:MM:SS format
            if (strlen($startTime) == 5) $startTime .= ':00';
            if (strlen($endTime) == 5) $endTime .= ':00';

            // Ensure 24-hour normalization for afternoon hours (1:00 PM to 6:00 PM)
            $sp = explode(':', $startTime);
            $sh = intval($sp[0] ?? 0);
            if ($sh >= 1 && $sh <= 6) {
                $startTime = sprintf('%02d:%02d:%02d', $sh + 12, intval($sp[1] ?? 0), intval($sp[2] ?? 0));
            }
            $ep = explode(':', $endTime);
            $eh = intval($ep[0] ?? 0);
            if ($eh >= 1 && $eh <= 6) {
                $endTime = sprintf('%02d:%02d:%02d', $eh + 12, intval($ep[1] ?? 0), intval($ep[2] ?? 0));
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE time_slots SET session_number = :sess, label = :lbl, start_time = :st, end_time = :et, department_id = :dept, year_id = :yr, is_break = :brk, slot_type = :stype, is_active = :active WHERE id = :id");
                $stmt->execute([
                    'sess'   => $sessionNumber,
                    'lbl'    => $label,
                    'st'     => $startTime,
                    'et'     => $endTime,
                    'dept'   => $deptId,
                    'yr'     => $yearId,
                    'brk'    => $isBreak,
                    'stype'  => $slotType,
                    'active' => $isActive,
                    'id'     => $id
                ]);
                echo json_encode(['success' => true, 'message' => $isBreak ? 'Break hour timings updated successfully.' : 'Session time slot updated successfully.']);
            } else {
                $stmt = $db->prepare("INSERT INTO time_slots (session_number, label, start_time, end_time, department_id, year_id, is_break, slot_type, is_active) VALUES (:sess, :lbl, :st, :et, :dept, :yr, :brk, :stype, :active)");
                $stmt->execute([
                    'sess'   => $sessionNumber,
                    'lbl'    => $label,
                    'st'     => $startTime,
                    'et'     => $endTime,
                    'dept'   => $deptId,
                    'yr'     => $yearId,
                    'brk'    => $isBreak,
                    'stype'  => $slotType,
                    'active' => $isActive
                ]);
                echo json_encode(['success' => true, 'message' => $isBreak ? 'New break hour added successfully.' : 'New session time slot created successfully.']);
            }
            break;

        case 'delete_time_slot':
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare("DELETE FROM time_slots WHERE id = :id");
                $stmt->execute(['id' => $id]);
                echo json_encode(['success' => true, 'message' => 'Session time slot deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid time slot ID.']);
            }
            break;

        // 11. Daily Teacher Attendance & Real-Time Workload
        case 'get_daily_teacher_attendance':
            $date = $_GET['date'] ?? date('Y-m-d');
            $dept = $_GET['department'] ?? 'ALL';

            $whereDept = "";
            $params = ['ldate' => $date];
            if ($dept !== 'ALL') {
                $whereDept = "AND (u.department = :dept OR u.department = 'BOTH')";
                $params['dept'] = $dept;
            }

            // Fetch all faculties
            $sqlFac = "SELECT u.id, u.employee_id, u.name, u.email, u.department, u.designation, u.phone 
                       FROM users u 
                       WHERE u.role = 'faculty' AND u.status = 'active' $whereDept 
                       ORDER BY u.department ASC, u.name ASC";
            $stmtFac = $db->prepare($sqlFac);
            if ($dept !== 'ALL') {
                $stmtFac->execute(['dept' => $dept]);
            } else {
                $stmtFac->execute();
            }
            $faculties = $stmtFac->fetchAll();

            // Fetch all logs for this date
            $sqlLogs = "SELECT cl.*, s.display_code as section_code, s.section_name, s2.display_code as merged_section_code
                        FROM class_logs cl
                        JOIN sections s ON cl.section_id = s.id
                        LEFT JOIN sections s2 ON cl.merged_with_section_id = s2.id
                        WHERE cl.log_date = :ldate
                        ORDER BY cl.session_number ASC";
            $stmtLogs = $db->prepare($sqlLogs);
            $stmtLogs->execute(['ldate' => $date]);
            $allLogs = $stmtLogs->fetchAll();

            $facultyLogsMap = [];
            foreach ($allLogs as $lg) {
                $facultyLogsMap[$lg['faculty_id']][] = $lg;
            }

            $attendanceData = [];
            $totalPresent = 0;
            $totalAbsent = 0;
            $totalDailyHours = 0;

            foreach ($faculties as $f) {
                $fLogs = $facultyLogsMap[$f['id']] ?? [];
                $dailyCredits = 0;
                $labCount = 0;
                $theoryCount = 0;
                $mergedCount = 0;

                foreach ($fLogs as $fl) {
                    $dailyCredits += floatval($fl['workload_credits'] ?? 1.0);
                    if ($fl['session_type'] === 'lab') $labCount++;
                    elseif ($fl['is_merged'] == 1) $mergedCount++;
                    else $theoryCount++;
                }

                $isPresent = count($fLogs) > 0;
                if ($isPresent) {
                    $totalPresent++;
                    $totalDailyHours += $dailyCredits;
                } else {
                    $totalAbsent++;
                }

                $attendanceData[] = [
                    'faculty'        => $f,
                    'is_attended'    => $isPresent,
                    'session_count'  => count($fLogs),
                    'daily_workload' => $dailyCredits,
                    'theory_count'   => $theoryCount,
                    'lab_count'      => $labCount,
                    'merged_count'   => $mergedCount,
                    'sessions'       => $fLogs
                ];
            }

            echo json_encode([
                'success'           => true,
                'date'              => $date,
                'department'        => $dept,
                'total_faculty'     => count($faculties),
                'total_present'     => $totalPresent,
                'total_absent'      => $totalAbsent,
                'total_daily_hours' => $totalDailyHours,
                'data'              => $attendanceData
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown admin action.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Admin API Error: ' . $e->getMessage()]);
}
