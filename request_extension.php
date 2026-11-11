<?php
/**
 * CommitTrack - request_extension.php
 * Handles student deadline extension submissions for individual or group requests.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_student();

$studentId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my_tasks.php');
    exit;
}

verify_csrf();

$taskId            = (int)($_POST['task_id'] ?? 0);
$requestType       = $_POST['request_type'] ?? 'individual';
$requestedDeadline = trim($_POST['requested_deadline'] ?? '');
$reason            = trim($_POST['reason'] ?? '');

if (!in_array($requestType, ['individual', 'group'], true)) {
    $requestType = 'individual';
}

// 1. Verify task assignment and enrollment
$stmt = $pdo->prepare('
    SELECT ta.id AS assignment_id, ta.status, ta.custom_deadline,
           t.id AS task_id, t.classroom_id, t.deadline, t.title
    FROM task_assignments ta
    JOIN tasks t ON t.id = ta.task_id
    JOIN classroom_members cm ON cm.classroom_id = t.classroom_id AND cm.student_id = ta.student_id
    WHERE ta.task_id = ? AND ta.student_id = ?
');
$stmt->execute([$taskId, $studentId]);
$task = $stmt->fetch();

if (!$task) {
    header('Location: my_tasks.php?err=' . urlencode('Task not found or access denied.'));
    exit;
}

// 2. Prevent requests for completed tasks
if ($task['status'] === 'completed') {
    header('Location: my_tasks.php?err=' . urlencode('Cannot request an extension for an already completed task.'));
    exit;
}

// 3. Validate deadline format and must be in future
$currentDeadline = $task['custom_deadline'] ?: $task['deadline'];
$today = date('Y-m-d');

if ($requestedDeadline === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDeadline)) {
    header('Location: my_tasks.php?err=' . urlencode('Please provide a valid requested deadline date.'));
    exit;
}

if ($requestedDeadline <= $today) {
    header('Location: my_tasks.php?err=' . urlencode('Requested deadline must be a future date after today.'));
    exit;
}

if ($currentDeadline && $requestedDeadline <= $currentDeadline) {
    header('Location: my_tasks.php?err=' . urlencode('Requested deadline must be later than the current deadline (' . date('M d, Y', strtotime($currentDeadline)) . ').'));
    exit;
}

// 4. Validate reason
if (mb_strlen($reason) < 10) {
    header('Location: my_tasks.php?err=' . urlencode('Please provide a detailed reason for the extension (at least 10 characters).'));
    exit;
}

// 5. Prevent duplicate active (pending) requests
if ($requestType === 'individual') {
    $dup = $pdo->prepare('SELECT id FROM extension_requests WHERE task_id = ? AND student_id = ? AND status = "pending"');
    $dup->execute([$taskId, $studentId]);
} else {
    // Group request: check if this student or anyone in the classroom already submitted a pending group request
    $dup = $pdo->prepare('SELECT id FROM extension_requests WHERE task_id = ? AND classroom_id = ? AND request_type = "group" AND status = "pending"');
    $dup->execute([$taskId, $task['classroom_id']]);
}

if ($dup->fetch()) {
    header('Location: my_tasks.php?err=' . urlencode('An extension request is already pending review for this task.'));
    exit;
}

// 6. Insert extension request record
$ins = $pdo->prepare('
    INSERT INTO extension_requests (task_id, classroom_id, student_id, request_type, original_deadline, requested_deadline, reason, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, "pending")
');
$ins->execute([
    $taskId,
    $task['classroom_id'],
    $studentId,
    $requestType,
    $currentDeadline,
    $requestedDeadline,
    $reason
]);

header('Location: my_extensions.php?msg=' . urlencode('Extension request submitted successfully! Your teacher will review it.'));
exit;
