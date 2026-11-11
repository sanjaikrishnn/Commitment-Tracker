<?php
/**
 * CommitTrack - join.php
 * Students join a classroom via unique invite code in the URL.
 * Prevents duplicates, assigns classroom tasks, and handles invalid codes gracefully.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_auth();

$code   = trim($_GET['code'] ?? '');
$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? '';

if ($role !== 'student') {
    header('Location: classrooms.php');
    exit;
}

$userName    = $_SESSION['user_name'] ?? 'Student';
$userEmail   = $_SESSION['user_email'] ?? '';
$userRole    = 'student';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

$classroom = null;
$invalidCode = false;

if ($code === '') {
    $invalidCode = true;
} else {
    // Fetch classroom by code
    $stmt = $pdo->prepare('SELECT c.*, u.name AS teacher_name FROM classrooms c JOIN users u ON u.id = c.admin_id WHERE c.invite_code = ?');
    $stmt->execute([$code]);
    $classroom = $stmt->fetch();
    if (!$classroom) {
        $invalidCode = true;
    }
}

if ($invalidCode) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width,initial-scale=1.0">
      <title>Invalid Invitation – CommitTrack</title>
      <link rel="stylesheet" href="style.css">
    </head>
    <body class="auth-page">
      <div class="auth-container" style="text-align:center;max-width:440px">
        <div style="font-size:3rem;margin-bottom:12px">⚠️</div>
        <h2 style="color:var(--primary);margin-bottom:8px">Invalid Invitation Link</h2>
        <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:24px">
          This classroom invitation code is invalid, mistyped, or has been removed by the instructor.
        </p>
        <a href="dashboard.php" class="btn btn-primary btn-block">← Return to Dashboard</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$classroomId = (int)$classroom['id'];
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Prevent duplicate membership
    $check = $pdo->prepare('SELECT id FROM classroom_members WHERE classroom_id=? AND student_id=?');
    $check->execute([$classroomId, $userId]);
    if ($check->fetch()) {
        $err = 'You are already an enrolled member of this classroom.';
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Enroll student
            $pdo->prepare('INSERT INTO classroom_members (classroom_id, student_id) VALUES (?,?)')
                ->execute([$classroomId, $userId]);

            // 2. Automatically assign existing classroom tasks to the new member
            $assignTasks = $pdo->prepare('
                INSERT IGNORE INTO task_assignments (task_id, student_id, status)
                SELECT id, ?, "pending" FROM tasks WHERE classroom_id = ?
            ');
            $assignTasks->execute([$userId, $classroomId]);

            $pdo->commit();
            header('Location: my_classrooms.php?joined=' . $classroomId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = 'Failed to join classroom. Please try again.';
        }
    }
}

// Check if already member
$already = $pdo->prepare('SELECT id FROM classroom_members WHERE classroom_id=? AND student_id=?');
$already->execute([$classroomId, $userId]);
$isMember = (bool)$already->fetch();

// Member count
$cnt = $pdo->prepare('SELECT COUNT(*) FROM classroom_members WHERE classroom_id=?');
$cnt->execute([$classroomId]);
$memberCount = (int)$cnt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Join <?= e($classroom['name']) ?> – CommitTrack</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
  <svg class="botanical-decor decor-top-left" viewBox="0 0 200 200" fill="none"><path d="M-20 -20 Q80 40 40 140 Q10 200 -20 200 Z" fill="#d8ebe1"/></svg>
  <svg class="botanical-decor decor-bottom-right" viewBox="0 0 240 240" fill="none"><path d="M260 260 Q140 180 180 80 Q210 20 260 20 Z" fill="#dcefe5"/><circle cx="210" cy="90" r="14" fill="#a7d5be" opacity="0.6"/></svg>

  <div class="auth-container" style="max-width:460px;text-align:center">
    <div class="auth-header">
      <a href="dashboard.php" class="brand-logo">
        <span class="brand-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
        CommitTrack
      </a>
    </div>

    <div style="font-size:2.8rem;margin-bottom:10px">🏫</div>
    <h1 class="auth-title"><?= e($classroom['name']) ?></h1>
    <?php if ($classroom['section']): ?>
      <p class="auth-subtitle"><?= e($classroom['section']) ?></p>
    <?php endif; ?>
    <p style="font-size:0.82rem;color:var(--text-muted);margin:6px 0 16px">
      Instructor: <strong><?= e($classroom['teacher_name']) ?></strong> · <?= $memberCount ?> student<?= $memberCount != 1 ? 's' : '' ?> enrolled
    </p>

    <?php if ($err): ?>
      <div class="alert alert-danger" style="margin-bottom:16px">✕ <?= e($err) ?></div>
    <?php endif; ?>

    <?php if ($isMember): ?>
      <div class="alert alert-success">✓ You are already an enrolled member of this classroom.</div>
      <a href="my_classrooms.php" class="btn btn-primary btn-block" style="margin-top:16px">Go to My Classrooms →</a>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:18px">
          You are enrolling as <strong><?= e($userName) ?></strong> (<?= e($userEmail) ?>).
        </p>
        <button type="submit" class="btn btn-primary btn-block">Confirm & Enroll in Classroom →</button>
      </form>
      <div class="auth-footer" style="margin-top:16px"><a href="dashboard.php">← Back to Dashboard</a></div>
    <?php endif; ?>
  </div>
</body>
</html>
