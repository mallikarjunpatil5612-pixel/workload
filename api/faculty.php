<?php
/**
 * Faculty Operations API Endpoint
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_faculty();
$user = get_logged_in_user();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = get_db();

    switch ($action) {
        // 1. Get dynamic day table for selected department & year
        case 'get_day_table':
            $date = $_GET['date'] ?? date('Y-m-d');
            $reqDept = $_GET['department_id'] ?? '';
            if ($reqDept === '' || $reqDept === null) {
                $reqDept = $user['department'] ?? 'BOTH';
            }

            if ($reqDept === 'BOTH' || $reqDept === 'ALL' || $reqDept === '0' || $reqDept === 0) {
                $department_id = 'BOTH';
            } elseif ($reqDept == '1' || strtoupper($reqDept) === 'BCA') {
                $department_id = 1;
            } elseif ($reqDept == '2' || strtoupper($reqDept) === 'BCOM') {
                $department_id = 2;
            } else {
                $department_id = 'BOTH';
            }

            $year_id = intval($_GET['year_id'] ?? 1); // 1 = 1st, 2 = 2nd, 3 = 3rd Year

            $tableData = get_dynamic_day_table($date, $department_id, $year_id);
            
            // Also fetch sections for merge dropdown
            $stmtAllSec = $db->prepare("SELECT s.id, s.display_code, s.section_name, d.code as dept_code, ay.name as year_name 
                                        FROM sections s 
                                        JOIN departments d ON s.department_id = d.id 
                                        JOIN academic_years ay ON s.year_id = ay.id 
                                        WHERE s.status = 'active'
                                        ORDER BY d.code ASC, ay.year_number ASC, s.section_name ASC");
            $stmtAllSec->execute();
            $allSections = $stmtAllSec->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => $tableData,
                'all_sections' => $allSections,
                'current_faculty_id' => $user['id'],
                'current_faculty_name' => (stripos($user['name'] ?? '', 'Admin') !== false) ? 'Prof. Mallikarjun Patil' : ($user['name'] ?? 'Faculty Member')
            ]);
            break;

        // 2. Submit or update class log
        case 'save_log':
            $logDate       = $_POST['log_date'] ?? date('Y-m-d');
            $departmentId  = intval($_POST['department_id'] ?? 0);
            $yearId        = intval($_POST['year_id'] ?? 0);
            $sectionId     = intval($_POST['section_id'] ?? 0);
            $sessionNumber = intval($_POST['session_number'] ?? 0);
            $subjectName   = trim($_POST['subject_name'] ?? '');
            $topicCovered  = trim($_POST['topic_covered'] ?? '');
            $startTime     = $_POST['start_time'] ?? '09:00:00';
            $endTime       = $_POST['end_time'] ?? '10:00:00';
            $sessionType   = $_POST['session_type'] ?? 'theory';
            $isMerged      = !empty($_POST['is_merged']) ? 1 : 0;
            $mergedSectionId = !empty($_POST['merged_with_section_id']) ? intval($_POST['merged_with_section_id']) : null;
            $remarks       = trim($_POST['remarks'] ?? '');

            // Strict department isolation: ensure teachers can only log for their own department
            $teacherDept = $user['department'] ?? '';
            if ($teacherDept === 'BCA') {
                $departmentId = 1;
            } elseif ($teacherDept === 'BCOM') {
                $departmentId = 2;
            }

            // Verify that the target section actually belongs to the teacher's department
            if ($sectionId > 0 && ($teacherDept === 'BCA' || $teacherDept === 'BCOM')) {
                $stmtSecCheck = $db->prepare("SELECT d.code FROM sections s JOIN departments d ON s.department_id = d.id WHERE s.id = :sec_id");
                $stmtSecCheck->execute(['sec_id' => $sectionId]);
                $secDeptCode = $stmtSecCheck->fetchColumn();
                if ($secDeptCode && $secDeptCode !== $teacherDept) {
                    echo json_encode(['success' => false, 'message' => "Access Denied: {$teacherDept} teachers can only access and log {$teacherDept} sessions."]);
                    exit;
                }
            }

            // Verify merged section also belongs to the same department
            if ($isMerged && $mergedSectionId && ($teacherDept === 'BCA' || $teacherDept === 'BCOM')) {
                $stmtMergeCheck = $db->prepare("SELECT d.code FROM sections s JOIN departments d ON s.department_id = d.id WHERE s.id = :msec_id");
                $stmtMergeCheck->execute(['msec_id' => $mergedSectionId]);
                $mDeptCode = $stmtMergeCheck->fetchColumn();
                if ($mDeptCode && $mDeptCode !== $teacherDept) {
                    echo json_encode(['success' => false, 'message' => "Access Denied: You cannot combine classes with the {$mDeptCode} department."]);
                    exit;
                }
            }

            if (!$departmentId || !$yearId || !$sectionId || !$sessionNumber) {
                echo json_encode(['success' => false, 'message' => 'Missing required class or session details.']);
                exit;
            }

            // Crucial Rule: Break time cannot be attended or logged as a class session
            if ($sessionNumber >= 100) {
                echo json_encode(['success' => false, 'message' => 'Break time cannot be attended or logged as a class session.']);
                exit;
            }

            $stmtCheckBreak = $db->prepare("SELECT is_break, slot_type, label FROM time_slots 
                                            WHERE session_number = :sess 
                                              AND ((department_id = :dept AND year_id = :yr) OR (department_id IS NULL AND year_id IS NULL))
                                            ORDER BY department_id DESC, year_id DESC LIMIT 1");
            $stmtCheckBreak->execute(['sess' => $sessionNumber, 'dept' => $departmentId, 'yr' => $yearId]);
            $breakSlot = $stmtCheckBreak->fetch();
            if ($breakSlot && ($breakSlot['is_break'] == 1 || in_array($breakSlot['slot_type'], ['tea_break', 'lunch_break']))) {
                echo json_encode(['success' => false, 'message' => 'Break time cannot be attended or logged as a class session (' . $breakSlot['label'] . ').']);
                exit;
            }

            if (empty($subjectName) || empty($topicCovered)) {
                echo json_encode(['success' => false, 'message' => 'Please enter both Subject and Topic Covered.']);
                exit;
            }

            $teacherLogName = (stripos($user['name'] ?? '', 'Admin') !== false) ? 'Prof. Mallikarjun Patil' : $user['name'];
            record_class_log([
                'log_date'               => $logDate,
                'department_id'          => $departmentId,
                'year_id'                => $yearId,
                'section_id'             => $sectionId,
                'session_number'         => $sessionNumber,
                'subject_name'           => $subjectName,
                'topic_covered'          => $topicCovered,
                'start_time'             => $startTime,
                'end_time'               => $endTime,
                'session_type'           => $sessionType,
                'is_merged'              => $isMerged,
                'merged_with_section_id' => $mergedSectionId,
                'remarks'                => $remarks
            ], $user['id'], $teacherLogName);

            echo json_encode([
                'success' => true,
                'message' => 'Class log recorded successfully for ' . $teacherLogName . '!'
            ]);
            break;

        // 3. Get personal workload statistics (Weekly, Monthly, Overall)
        case 'get_my_workload':
            $facultyId = $user['id'];
            $today = date('Y-m-d');
            
            // This week (Monday to Sunday)
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd = date('Y-m-d', strtotime('sunday this week'));
            
            // This month
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            
            // Custom Range if provided
            $customStart = $_GET['start_date'] ?? $monthStart;
            $customEnd = $_GET['end_date'] ?? $monthEnd;

            $todayStats = get_faculty_workload_summary($facultyId, $today, $today);
            $weekStats  = get_faculty_workload_summary($facultyId, $weekStart, $weekEnd);
            $monthStats = get_faculty_workload_summary($facultyId, $monthStart, $monthEnd);
            $customStats = get_faculty_workload_summary($facultyId, $customStart, $customEnd);

            // Recent 10 logs
            $stmtRecent = $db->prepare("SELECT cl.*, d.code as dept_code, ay.name as year_name, s.display_code as section_code 
                                        FROM class_logs cl
                                        JOIN departments d ON cl.department_id = d.id
                                        JOIN academic_years ay ON cl.year_id = ay.id
                                        JOIN sections s ON cl.section_id = s.id
                                        WHERE cl.faculty_id = :fid 
                                        ORDER BY cl.log_date DESC, cl.session_number ASC 
                                        LIMIT 15");
            $stmtRecent->execute(['fid' => $facultyId]);
            $recentLogs = $stmtRecent->fetchAll();

            // Range-specific logs for detailed workload calculation breakdown
            $stmtRange = $db->prepare("SELECT cl.*, d.code as dept_code, ay.name as year_name, s.display_code as section_code, s2.display_code as merged_section_code 
                                       FROM class_logs cl
                                       JOIN departments d ON cl.department_id = d.id
                                       JOIN academic_years ay ON cl.year_id = ay.id
                                       JOIN sections s ON cl.section_id = s.id
                                       LEFT JOIN sections s2 ON cl.merged_with_section_id = s2.id
                                       WHERE cl.faculty_id = :fid AND cl.log_date BETWEEN :sdate AND :edate
                                       ORDER BY cl.log_date DESC, cl.session_number ASC");
            $stmtRange->execute(['fid' => $facultyId, 'sdate' => $customStart, 'edate' => $customEnd]);
            $rangeLogs = $stmtRange->fetchAll();

            $teacherDisplayName = (stripos($user['name'] ?? '', 'Admin') !== false) ? 'Prof. Mallikarjun Patil' : ($user['name'] ?? 'Faculty Member');

            echo json_encode([
                'success' => true,
                'faculty' => [
                    'name' => $teacherDisplayName,
                    'employee_id' => $user['employee_id'],
                    'department' => (!empty($user['department']) && $user['department'] !== 'BOTH') ? $user['department'] : 'General Faculty',
                    'designation' => ($user['designation'] === 'Academic Administrator') ? 'Faculty Teacher' : $user['designation']
                ],
                'stats' => [
                    'today_hours'  => $todayStats['total_hours'],
                    'weekly_hours' => $weekStats['total_hours'],
                    'monthly_hours'=> $monthStats['total_hours'],
                    'custom_stats' => $customStats
                ],
                'range_logs'  => $rangeLogs,
                'recent_logs' => $recentLogs
            ]);
            break;

        // 4. Get faculty history with filters
        case 'get_history':
            $facultyId = $user['id'];
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');

            $stmt = $db->prepare("SELECT cl.*, d.code as dept_code, ay.name as year_name, s.display_code as section_code, s2.display_code as merged_section_code
                                  FROM class_logs cl
                                  JOIN departments d ON cl.department_id = d.id
                                  JOIN academic_years ay ON cl.year_id = ay.id
                                  JOIN sections s ON cl.section_id = s.id
                                  LEFT JOIN sections s2 ON cl.merged_with_section_id = s2.id
                                  WHERE cl.faculty_id = :fid AND cl.log_date BETWEEN :sdate AND :edate
                                  ORDER BY cl.log_date DESC, cl.session_number ASC");
            $stmt->execute(['fid' => $facultyId, 'sdate' => $startDate, 'edate' => $endDate]);
            $logs = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown faculty action.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Faculty API Error: ' . $e->getMessage()]);
}
