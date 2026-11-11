<?php
/**
 * CommitTrack - reports.php (Admin / Teacher One-Click Report Generator)
 *
 * Generates comprehensive reports:
 * 1. Overall Academic / Task Progress Report
 * 2. Classroom-wise Report
 * 3. Individual Student Report
 * 4. Task Completion Report
 * 5. Deadline Extension Request Report
 *
 * Formats:
 * - HTML Preview with Browser Print Button
 * - CSV Export (Native PHP with UTF-8 BOM)
 * - Excel (.xls / XML Spreadsheet formatted for Excel, LibreOffice, Sheets)
 * - PDF Export (Dompdf when available / Print-ready clean PDF view)
 *
 * Security: Strictly restricted to authenticated Admin/Teacher users.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
$pdo = getDBConnection();
require_admin();

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$adminId     = (int)$_SESSION['user_id'];
$userName    = $_SESSION['user_name'] ?? 'Instructor';
$userEmail   = $_SESSION['user_email'] ?? '';
$userRole    = 'admin';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

// Selected parameters
$reportType  = $_GET['type'] ?? 'overall';
$format      = $_GET['format'] ?? 'html';
$classroomId = (int)($_GET['classroom_id'] ?? 0);
$studentId   = (int)($_GET['student_id'] ?? 0);

// Fetch teacher's classrooms and students for dropdown selectors
$clsStmt = $pdo->prepare('SELECT id, name, section FROM classrooms WHERE admin_id = ? ORDER BY name');
$clsStmt->execute([$adminId]);
$adminClassrooms = $clsStmt->fetchAll();

$studStmt = $pdo->prepare('
    SELECT DISTINCT u.id, u.name, u.email
    FROM users u
    JOIN classroom_members cm ON cm.student_id = u.id
    JOIN classrooms c ON c.id = cm.classroom_id
    WHERE c.admin_id = ?
    ORDER BY u.name
');
$studStmt->execute([$adminId]);
$adminStudents = $studStmt->fetchAll();

$reportDate = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// Verify classroom ownership if classroomId selected
if ($classroomId > 0) {
    $vCls = $pdo->prepare('SELECT id, name, section FROM classrooms WHERE id = ? AND admin_id = ?');
    $vCls->execute([$classroomId, $adminId]);
    $selectedClassroom = $vCls->fetch();
    if (!$selectedClassroom) $classroomId = 0;
} else {
    $selectedClassroom = null;
}

// Verify student is in teacher's classroom if studentId selected
if ($studentId > 0) {
    $vStd = $pdo->prepare('
        SELECT DISTINCT u.id, u.name, u.email
        FROM users u
        JOIN classroom_members cm ON cm.student_id = u.id
        JOIN classrooms c ON c.id = cm.classroom_id
        WHERE u.id = ? AND c.admin_id = ?
    ');
    $vStd->execute([$studentId, $adminId]);
    $selectedStudent = $vStd->fetch();
    if (!$selectedStudent) $studentId = 0;
} else {
    $selectedStudent = null;
}

/* =========================================================================
   REPORT DATA BUILDER
   ========================================================================= */

$reportTitle = 'CommitTrack Report';
$reportHeaders = [];
$reportRows = [];
$summaryMetrics = [];

switch ($reportType) {
    case 'classroom':
        $reportTitle = 'Classroom-Wise Progress Report' . ($selectedClassroom ? ' - ' . $selectedClassroom['name'] : '');
        $clsFilter = $classroomId > 0 ? "AND c.id = $classroomId" : "";
        
        $stmt = $pdo->prepare("
            SELECT c.name AS classroom_name, c.section,
                   COUNT(DISTINCT cm.student_id) AS total_students,
                   COUNT(DISTINCT t.id) AS total_tasks,
                   COUNT(ta.id) AS total_assignments,
                   SUM(ta.status = 'completed') AS completed_assignments,
                   SUM(ta.status = 'in_progress') AS in_progress_assignments,
                   SUM(ta.status = 'pending') AS pending_assignments,
                   SUM(COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND ta.status != 'completed') AS overdue_assignments
            FROM classrooms c
            LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
            LEFT JOIN tasks t ON t.classroom_id = c.id
            LEFT JOIN task_assignments ta ON ta.task_id = t.id
            WHERE c.admin_id = ? $clsFilter
            GROUP BY c.id
            ORDER BY c.name
        ");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll();

        $reportHeaders = ['Classroom', 'Section', 'Students', 'Tasks', 'Assigned', 'Completed', 'In Progress', 'Pending', 'Overdue', 'Completion Rate'];
        foreach ($rows as $r) {
            $compPct = $r['total_assignments'] > 0 ? round(($r['completed_assignments'] / $r['total_assignments']) * 100) : 0;
            $reportRows[] = [
                $r['classroom_name'],
                $r['section'] ?: '—',
                (int)$r['total_students'],
                (int)$r['total_tasks'],
                (int)$r['total_assignments'],
                (int)$r['completed_assignments'],
                (int)$r['in_progress_assignments'],
                (int)$r['pending_assignments'],
                (int)$r['overdue_assignments'],
                $compPct . '%'
            ];
        }
        break;

    case 'student':
        $reportTitle = 'Individual Student Performance Report' . ($selectedStudent ? ' - ' . $selectedStudent['name'] : '');
        $stdFilter = $studentId > 0 ? "AND u.id = $studentId" : "";
        $clsFilter = $classroomId > 0 ? "AND c.id = $classroomId" : "";

        $stmt = $pdo->prepare("
            SELECT u.name AS student_name, u.email AS student_email,
                   c.name AS classroom_name,
                   t.title AS task_title, t.priority,
                   t.original_deadline, t.deadline AS task_deadline, ta.custom_deadline,
                   COALESCE(ta.custom_deadline, t.deadline) AS effective_deadline,
                   ta.status,
                   (COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND ta.status != 'completed') AS is_overdue
            FROM users u
            JOIN classroom_members cm ON cm.student_id = u.id
            JOIN classrooms c ON c.id = cm.classroom_id
            JOIN tasks t ON t.classroom_id = c.id
            JOIN task_assignments ta ON ta.task_id = t.id AND ta.student_id = u.id
            WHERE c.admin_id = ? $stdFilter $clsFilter
            ORDER BY u.name, c.name, COALESCE(ta.custom_deadline, t.deadline) ASC
        ");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll();

        $reportHeaders = ['Student Name', 'Email', 'Classroom', 'Task', 'Priority', 'Original Due', 'Effective Due', 'Status', 'Overdue?'];
        foreach ($rows as $r) {
            $reportRows[] = [
                $r['student_name'],
                $r['student_email'],
                $r['classroom_name'],
                $r['task_title'],
                ucfirst($r['priority']),
                $r['original_deadline'] ?: ($r['task_deadline'] ?: '—'),
                $r['effective_deadline'] ?: '—',
                ucfirst(str_replace('_', ' ', $r['status'])),
                $r['is_overdue'] ? 'YES' : 'No'
            ];
        }
        break;

    case 'tasks':
        $reportTitle = 'Task Completion & Deadline Report';
        $clsFilter = $classroomId > 0 ? "AND c.id = $classroomId" : "";

        $stmt = $pdo->prepare("
            SELECT t.title AS task_title, c.name AS classroom_name,
                   t.priority, t.original_deadline, t.deadline AS current_deadline,
                   COUNT(ta.id) AS total_assigned,
                   SUM(ta.status = 'completed') AS completed_count,
                   SUM(ta.status = 'in_progress') AS in_progress_count,
                   SUM(ta.status = 'pending') AS pending_count,
                   SUM(COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND ta.status != 'completed') AS overdue_count
            FROM tasks t
            JOIN classrooms c ON c.id = t.classroom_id
            LEFT JOIN task_assignments ta ON ta.task_id = t.id
            WHERE t.admin_id = ? $clsFilter
            GROUP BY t.id
            ORDER BY t.deadline ASC, t.created_at DESC
        ");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll();

        $reportHeaders = ['Task Title', 'Classroom', 'Priority', 'Original Due', 'Current Due', 'Assigned', 'Completed', 'In Progress', 'Pending', 'Overdue', 'Completion Rate'];
        foreach ($rows as $r) {
            $pct = $r['total_assigned'] > 0 ? round(($r['completed_count'] / $r['total_assigned']) * 100) : 0;
            $reportRows[] = [
                $r['task_title'],
                $r['classroom_name'],
                ucfirst($r['priority']),
                $r['original_deadline'] ?: ($r['current_deadline'] ?: '—'),
                $r['current_deadline'] ?: '—',
                (int)$r['total_assigned'],
                (int)$r['completed_count'],
                (int)$r['in_progress_count'],
                (int)$r['pending_count'],
                (int)$r['overdue_count'],
                $pct . '%'
            ];
        }
        break;

    case 'extensions':
        $reportTitle = 'Deadline Extension Requests Report';
        $clsFilter = $classroomId > 0 ? "AND c.id = $classroomId" : "";

        $stmt = $pdo->prepare("
            SELECT er.id, u.name AS student_name, u.email AS student_email,
                   c.name AS classroom_name, t.title AS task_title,
                   er.request_type, er.original_deadline, er.requested_deadline,
                   er.reason, er.status, er.teacher_comment, er.created_at, er.reviewed_at
            FROM extension_requests er
            JOIN classrooms c ON c.id = er.classroom_id
            JOIN tasks t ON t.id = er.task_id
            JOIN users u ON u.id = er.student_id
            WHERE c.admin_id = ? $clsFilter
            ORDER BY er.created_at DESC
        ");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll();

        $reportHeaders = ['Student', 'Email', 'Classroom', 'Task', 'Scope', 'Original Due', 'Requested Due', 'Status', 'Reason', 'Instructor Comment', 'Requested Date', 'Reviewed Date'];
        foreach ($rows as $r) {
            $reportRows[] = [
                $r['student_name'],
                $r['student_email'],
                $r['classroom_name'],
                $r['task_title'],
                ucfirst($r['request_type']),
                $r['original_deadline'] ?: '—',
                $r['requested_deadline'],
                ucfirst($r['status']),
                $r['reason'],
                $r['teacher_comment'] ?: '—',
                $r['created_at'],
                $r['reviewed_at'] ?: '—'
            ];
        }
        break;

    case 'overall':
    default:
        $reportType = 'overall';
        $reportTitle = 'Overall Academic Progress & Task Completion Report';

        // Overall stats
        $s1 = $pdo->prepare('SELECT COUNT(*) FROM classrooms WHERE admin_id = ?');
        $s1->execute([$adminId]); $totClass = (int)$s1->fetchColumn();

        $s2 = $pdo->prepare('SELECT COUNT(DISTINCT cm.student_id) FROM classroom_members cm JOIN classrooms c ON c.id=cm.classroom_id WHERE c.admin_id = ?');
        $s2->execute([$adminId]); $totStud = (int)$s2->fetchColumn();

        $s3 = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE admin_id = ?');
        $s3->execute([$adminId]); $totTasks = (int)$s3->fetchColumn();

        $s4 = $pdo->prepare("
            SELECT COUNT(ta.id) AS total_assigned,
                   SUM(ta.status = 'completed') AS total_completed,
                   SUM(ta.status = 'in_progress') AS total_in_progress,
                   SUM(ta.status = 'pending') AS total_pending,
                   SUM(COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND ta.status != 'completed') AS total_overdue
            FROM tasks t
            LEFT JOIN task_assignments ta ON ta.task_id = t.id
            WHERE t.admin_id = ?
        ");
        $s4->execute([$adminId]); $stats = $s4->fetch();

        $totAssigned   = (int)$stats['total_assigned'];
        $totCompleted  = (int)$stats['total_completed'];
        $totInProgress = (int)$stats['total_in_progress'];
        $totPending    = (int)$stats['total_pending'];
        $totOverdue    = (int)$stats['total_overdue'];
        $overallPct    = $totAssigned > 0 ? round(($totCompleted / $totAssigned) * 100) : 0;

        $summaryMetrics = [
            'Total Classrooms'       => $totClass,
            'Total Students'         => $totStud,
            'Total Tasks'            => $totTasks,
            'Total Assignments'      => $totAssigned,
            'Completed Assignments'  => $totCompleted,
            'In-Progress'            => $totInProgress,
            'Pending'                => $totPending,
            'Overdue Assignments'    => $totOverdue,
            'Overall Completion Rate'=> $overallPct . '%'
        ];

        // Breakdown table by classroom
        $stmt = $pdo->prepare("
            SELECT c.name AS classroom_name, c.section,
                   COUNT(DISTINCT cm.student_id) AS enrolled_students,
                   COUNT(DISTINCT t.id) AS task_count,
                   COUNT(ta.id) AS assigned_count,
                   SUM(ta.status = 'completed') AS completed_count,
                   SUM(COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND ta.status != 'completed') AS overdue_count
            FROM classrooms c
            LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
            LEFT JOIN tasks t ON t.classroom_id = c.id
            LEFT JOIN task_assignments ta ON ta.task_id = t.id
            WHERE c.admin_id = ?
            GROUP BY c.id
            ORDER BY c.name
        ");
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll();

        $reportHeaders = ['Classroom', 'Section', 'Students Enrolled', 'Total Tasks', 'Assigned Submissions', 'Completed', 'Overdue', 'Completion Rate'];
        foreach ($rows as $r) {
            $pct = $r['assigned_count'] > 0 ? round(($r['completed_count'] / $r['assigned_count']) * 100) : 0;
            $reportRows[] = [
                $r['classroom_name'],
                $r['section'] ?: '—',
                (int)$r['enrolled_students'],
                (int)$r['task_count'],
                (int)$r['assigned_count'],
                (int)$r['completed_count'],
                (int)$r['overdue_count'],
                $pct . '%'
            ];
        }
        break;
}

/* =========================================================================
   FORMAT 1: CSV EXPORT
   ========================================================================= */
if ($format === 'csv') {
    $filename = 'committrack_' . $reportType . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM so Excel opens accented/special characters cleanly
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    // Title & Metadata
    fputcsv($out, ['COMMITTRACK ACADEMIC REPORT']);
    fputcsv($out, ['Report Title', $reportTitle]);
    fputcsv($out, ['Generated On', $reportDate]);
    fputcsv($out, ['Generated By', $userName . ' (' . $userEmail . ')']);
    fputcsv($out, []);

    if (!empty($summaryMetrics)) {
        fputcsv($out, ['--- SUMMARY METRICS ---']);
        foreach ($summaryMetrics as $k => $v) {
            fputcsv($out, [$k, $v]);
        }
        fputcsv($out, []);
    }

    fputcsv($out, $reportHeaders);
    foreach ($reportRows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

/* =========================================================================
   FORMAT 2: EXCEL (.XLSX using PhpSpreadsheet / Fallback XML .xls)
   ========================================================================= */
if ($format === 'excel' || $format === 'xlsx') {
    // Check if PhpSpreadsheet is available via Composer
    if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $safeTitle = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $reportTitle), 0, 30);
        $sheet->setTitle($safeTitle ?: 'Report');

        // Document Properties
        $spreadsheet->getProperties()
            ->setCreator($userName . ' - CommitTrack')
            ->setLastModifiedBy($userName)
            ->setTitle($reportTitle)
            ->setSubject('CommitTrack Academic Progress Report');

        // Title and Metadata Block
        $sheet->setCellValue('A1', 'CommitTrack — ' . $reportTitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF173F35');

        $sheet->setCellValue('A2', 'Generated On: ' . $reportDate . ' | Instructor: ' . $userName . ' (' . $userEmail . ')');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9.5)->getColor()->setARGB('FF5E7268');

        $currentRow = 4;

        // Summary Metrics Block (if applicable)
        if (!empty($summaryMetrics)) {
            $sheet->setCellValue("A{$currentRow}", 'Summary Metric');
            $sheet->setCellValue("B{$currentRow}", 'Value');
            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$currentRow}:B{$currentRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F7F3');
            $currentRow++;

            foreach ($summaryMetrics as $k => $v) {
                $sheet->setCellValue("A{$currentRow}", $k);
                $sheet->setCellValue("B{$currentRow}", $v);
                $currentRow++;
            }
            $currentRow += 2;
        }

        // Table Headers
        $headerStartRow = $currentRow;
        $colIdx = 1;
        foreach ($reportHeaders as $h) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue("{$colLetter}{$currentRow}", $h);
            $colIdx++;
        }
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($reportHeaders));

        // Style Table Header (Brand Dark Green, White Text, Bold)
        $headerRange = "A{$headerStartRow}:{$lastColLetter}{$headerStartRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF173F35');
        $sheet->getStyle($headerRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Data Rows
        $currentRow++;
        foreach ($reportRows as $row) {
            $colIdx = 1;
            foreach ($row as $cell) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $sheet->setCellValue("{$colLetter}{$currentRow}", $cell);
                $colIdx++;
            }
            // Zebra striping
            if ($currentRow % 2 === 0) {
                $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7FAF8');
            }
            $currentRow++;
        }

        // Subtle borders on data table
        $tableRange = "A{$headerStartRow}:{$lastColLetter}" . ($currentRow - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setARGB('FFD8E4DE');

        // Auto-fit column widths
        for ($i = 1; $i <= count($reportHeaders); $i++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'committrack_' . $reportType . '_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // Graceful fallback to formatted HTML XML .xls
    $filename = 'committrack_' . $reportType . '_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\">";
    echo "<style>
      table { border-collapse: collapse; width: 100%; font-family: sans-serif; font-size: 11pt; }
      th { background-color: #173f35; color: #ffffff; padding: 10px; border: 1px solid #0f2c25; text-align: left; }
      td { padding: 8px 10px; border: 1px solid #dcdcdc; }
      tr:nth-child(even) td { background-color: #f7faf8; }
      h2 { color: #173f35; font-family: sans-serif; }
      .meta { color: #555555; font-size: 10pt; font-family: sans-serif; margin-bottom: 15px; }
      .sum-table td { background: #eef5f1; font-weight: bold; }
    </style></head><body>";
    
    echo "<h2>" . e($reportTitle) . "</h2>";
    echo "<div class=\"meta\">Generated on: " . e($reportDate) . " | Generated By: " . e($userName) . " (" . e($userEmail) . ")</div>";

    if (!empty($summaryMetrics)) {
        echo "<table class=\"sum-table\" style=\"margin-bottom: 20px; max-width: 450px;\">";
        foreach ($summaryMetrics as $k => $v) {
            echo "<tr><td style=\"width:60%\">" . e($k) . "</td><td>" . e($v) . "</td></tr>";
        }
        echo "</table>";
    }

    echo "<table><thead><tr>";
    foreach ($reportHeaders as $h) {
        echo "<th>" . e($h) . "</th>";
    }
    echo "</tr></thead><tbody>";
    foreach ($reportRows as $row) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo "<td>" . e($cell) . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table></body></html>";
    exit;
}

/* =========================================================================
   FORMAT 3: PDF (Dompdf if installed / Print-ready PDF view)
   ========================================================================= */
if ($format === 'pdf') {
    // Check if Dompdf exists via autoloader
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('\\Dompdf\\Dompdf')) {
            ob_start();
            include __DIR__ . '/partials/report_print_template.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $dompdf->stream('committrack_' . $reportType . '_' . date('Ymd') . '.pdf', ['Attachment' => 1]);
            exit;
        }
    }
    // High-fidelity print-to-PDF view with auto-trigger
    $autoPrint = true;
} else {
    $autoPrint = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($reportTitle) ?> – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap { max-width: 1160px; margin: 0 auto; padding: 30px 24px 60px; }
.page-title { font-size: 1.5rem; color: var(--primary); margin-bottom: 4px; }
.page-sub { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 22px; }

/* Control panel */
.report-controls { background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 24px; }
.control-grid { display: grid; grid-template-columns: 2fr 1.5fr 1.5fr auto; gap: 14px; align-items: flex-end; }

/* Export Buttons Bar */
.export-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.export-group { display: flex; gap: 8px; flex-wrap: wrap; }

.btn-export { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: var(--radius-sm); font-size: 0.82rem; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid var(--border-color); background: #fff; color: var(--text-main); transition: var(--transition); }
.btn-export:hover { border-color: var(--primary); color: var(--primary); background: #f0f7f3; }
.btn-export-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
.btn-export-primary:hover { background: var(--primary-hover); color: #fff; }

/* Report Document Display */
.report-doc { background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 36px 40px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
.doc-header { border-bottom: 2px solid var(--primary); padding-bottom: 18px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-start; }
.doc-brand { font-size: 1.3rem; font-weight: 800; color: var(--primary); display: flex; align-items: center; gap: 8px; }
.doc-title { font-size: 1.35rem; font-weight: 700; color: var(--primary); margin: 6px 0; }
.doc-meta { font-size: 0.8rem; color: var(--text-muted); }

/* Metrics grid for overall report */
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 28px; }
.metric-box { background: #f8faf8; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px 16px; }
.metric-title { font-size: 0.74rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 2px; }
.metric-val { font-size: 1.35rem; font-weight: 800; color: var(--primary); }

/* Table */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; margin-top: 10px; }
.data-table th { background: #f0f4f1; color: var(--primary); font-weight: 700; text-align: left; padding: 10px 12px; border-bottom: 2px solid var(--border-color); font-size: 0.75rem; text-transform: uppercase; }
.data-table td { padding: 10px 12px; border-bottom: 1px solid #f0f3f1; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fcfdfc; }

@media print {
  body { background: #fff !important; }
  .dash-sidebar, .dash-topbar, .report-controls, .export-bar { display: none !important; }
  .dashboard-layout { display: block !important; }
  .dash-main { margin: 0 !important; padding: 0 !important; }
  .page-wrap { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
  .report-doc { border: none !important; box-shadow: none !important; padding: 0 !important; }
}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title">Reports & Analytics</h1>
  <p class="page-sub">Generate comprehensive progress reports, classroom analytics, and export in 1-click.</p>

  <!-- Control form -->
  <div class="report-controls">
    <form method="GET" action="reports.php" class="control-grid">
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:0.78rem">Report Type *</label>
        <select name="type" class="form-control" style="padding-left:12px;height:38px" onchange="this.form.submit()">
          <option value="overall"    <?= $reportType === 'overall'    ? 'selected' : '' ?>>1. Overall Academic Progress</option>
          <option value="classroom"  <?= $reportType === 'classroom'  ? 'selected' : '' ?>>2. Classroom-Wise Report</option>
          <option value="student"    <?= $reportType === 'student'    ? 'selected' : '' ?>>3. Individual Student Report</option>
          <option value="tasks"      <?= $reportType === 'tasks'      ? 'selected' : '' ?>>4. Task Completion & Deadlines</option>
          <option value="extensions" <?= $reportType === 'extensions' ? 'selected' : '' ?>>5. Deadline Extension Requests</option>
        </select>
      </div>

      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:0.78rem">Filter by Classroom</label>
        <select name="classroom_id" class="form-control" style="padding-left:12px;height:38px" onchange="this.form.submit()">
          <option value="0">— All Classrooms —</option>
          <?php foreach ($adminClassrooms as $ac): ?>
            <option value="<?= $ac['id'] ?>" <?= $classroomId == $ac['id'] ? 'selected' : '' ?>>
              <?= e($ac['name']) ?><?= $ac['section'] ? ' (' . e($ac['section']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:0.78rem">Filter by Student</label>
        <select name="student_id" class="form-control" style="padding-left:12px;height:38px" onchange="this.form.submit()">
          <option value="0">— All Students —</option>
          <?php foreach ($adminStudents as $as): ?>
            <option value="<?= $as['id'] ?>" <?= $studentId == $as['id'] ? 'selected' : '' ?>>
              <?= e($as['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <button type="submit" class="btn btn-primary btn-sm" style="height:38px">Update View</button>
      </div>
    </form>
  </div>

  <!-- Export Bar -->
  <?php
    $qs = http_build_query([
      'type' => $reportType,
      'classroom_id' => $classroomId,
      'student_id' => $studentId
    ]);
  ?>
  <div class="export-bar">
    <div style="font-size:0.85rem;color:var(--text-muted)">
      Showing <strong><?= count($reportRows) ?></strong> records
    </div>
    <div class="export-group">
      <button type="button" class="btn-export btn-export-primary" onclick="window.print()">
        🖨️ Print / Save as PDF
      </button>
      <a href="reports.php?<?= $qs ?>&format=csv" class="btn-export">
        📥 Download CSV
      </a>
      <a href="reports.php?<?= $qs ?>&format=xlsx" class="btn-export">
        📊 Download Excel (.xlsx)
      </a>
      <a href="reports.php?<?= $qs ?>&format=pdf" class="btn-export">
        📄 Download PDF
      </a>
    </div>
  </div>

  <!-- Report Document Preview -->
  <div class="report-doc" id="printableReport">
    <div class="doc-header">
      <div>
        <div class="doc-brand">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          CommitTrack
        </div>
        <h2 class="doc-title"><?= e($reportTitle) ?></h2>
        <div class="doc-meta">
          Instructor: <strong><?= e($userName) ?></strong> (<?= e($userEmail) ?>)
          <?php if ($selectedClassroom): ?> · Classroom: <strong><?= e($selectedClassroom['name']) ?></strong><?php endif; ?>
          <?php if ($selectedStudent): ?> · Student: <strong><?= e($selectedStudent['name']) ?></strong><?php endif; ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:0.75rem;font-weight:600;color:var(--text-muted)">GENERATED DATE</div>
        <div style="font-size:0.85rem;font-weight:700;color:var(--primary)"><?= date('M d, Y · h:i A', strtotime($reportDate)) ?></div>
      </div>
    </div>

    <?php if (!empty($summaryMetrics)): ?>
      <div class="metrics-grid">
        <?php foreach ($summaryMetrics as $title => $val): ?>
          <div class="metric-box">
            <div class="metric-title"><?= e($title) ?></div>
            <div class="metric-val"><?= e($val) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($reportRows)): ?>
      <div style="text-align:center;padding:40px 20px;color:var(--text-muted)">
        <p>No records found for the selected criteria.</p>
      </div>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <?php foreach ($reportHeaders as $h): ?>
              <th><?= e($h) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reportRows as $row): ?>
            <tr>
              <?php foreach ($row as $idx => $cell): ?>
                <td>
                  <?php if ($cell === 'YES'): ?>
                    <span style="color:#dc2626;font-weight:700">⚠️ Yes</span>
                  <?php elseif ($cell === 'Completed' || $cell === 'Approved'): ?>
                    <span style="color:#16a34a;font-weight:600">✓ <?= e($cell) ?></span>
                  <?php elseif ($cell === 'Rejected'): ?>
                    <span style="color:#dc2626;font-weight:600">✕ <?= e($cell) ?></span>
                  <?php elseif ($cell === 'Pending' || $cell === 'Pending review'): ?>
                    <span style="color:#b45309;font-weight:600">⏳ <?= e($cell) ?></span>
                  <?php else: ?>
                    <?= e($cell) ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>
</div>
</div>
<?php if ($autoPrint): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  window.print();
});
</script>
<?php endif; ?>
<script src="script.js"></script>
</body>
</html>
