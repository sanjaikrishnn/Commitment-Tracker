<?php
/**
 * CommitTrack - students.php (Admin)
 * View all students across all classrooms.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_admin();

$adminId = (int)$_SESSION['user_id'];

$students = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.created_at,
           COUNT(DISTINCT cm.classroom_id) AS classroom_count,
           COUNT(ta.id) AS total_tasks,
           SUM(ta.status = 'completed') AS completed_tasks
    FROM users u
    JOIN classroom_members cm ON cm.student_id = u.id
        AND cm.classroom_id IN (SELECT id FROM classrooms WHERE admin_id = ?)
    LEFT JOIN task_assignments ta ON ta.student_id = u.id
        AND ta.task_id IN (SELECT id FROM tasks WHERE admin_id = ?)
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY u.name
");
$students->execute([$adminId, $adminId]);
$studentList = $students->fetchAll();

$userName   = $_SESSION['user_name'] ?? '';
$userEmail  = $_SESSION['user_email'] ?? '';
$userRole   = 'admin';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Students – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap{max-width:1000px;margin:0 auto;padding:30px 24px 60px}
.page-title{font-size:1.5rem;color:var(--primary);margin-bottom:4px}
.page-sub{color:var(--text-muted);font-size:.9rem;margin-bottom:24px}
.student-table{width:100%;border-collapse:collapse;background:#fff;border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--border-color);font-size:.86rem}
.student-table th{text-align:left;padding:10px 16px;color:var(--text-muted);font-weight:600;border-bottom:2px solid var(--border-color);font-size:.75rem;text-transform:uppercase;background:#f9faf8}
.student-table td{padding:11px 16px;border-bottom:1px solid #f1f5f3;vertical-align:middle}
.student-table tr:last-child td{border-bottom:none}
.prog-bar-bg{width:80px;height:6px;background:#eef2f0;border-radius:99px;overflow:hidden;display:inline-block;vertical-align:middle}
.prog-bar-fill{height:100%;background:var(--primary);border-radius:99px}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title">Students</h1>
  <p class="page-sub">All students enrolled in your classrooms.</p>

  <?php if (empty($studentList)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-muted)">
      <div style="font-size:2.5rem;margin-bottom:10px">👩‍🎓</div>
      <p>No students enrolled yet. Share classroom invite links to get started.</p>
    </div>
  <?php else: ?>
  <table class="student-table">
    <thead>
      <tr><th>Name</th><th>Email</th><th>Classrooms</th><th>Tasks</th><th>Progress</th><th>Joined</th></tr>
    </thead>
    <tbody>
    <?php foreach ($studentList as $s):
      $pct = $s['total_tasks'] > 0 ? round($s['completed_tasks'] / $s['total_tasks'] * 100) : 0;
    ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar-circle" style="width:32px;height:32px;font-size:.8rem;flex-shrink:0"><?= e(mb_strtoupper(mb_substr($s['name'],0,1))) ?></div>
            <strong><?= e($s['name']) ?></strong>
          </div>
        </td>
        <td style="color:var(--text-muted)"><?= e($s['email']) ?></td>
        <td><?= $s['classroom_count'] ?></td>
        <td><?= $s['completed_tasks'] ?>/<?= $s['total_tasks'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="prog-bar-bg"><div class="prog-bar-fill" style="width:<?= $pct ?>%"></div></div>
            <span style="font-size:.76rem;color:var(--text-muted)"><?= $pct ?>%</span>
          </div>
        </td>
        <td style="font-size:.78rem;color:var(--text-muted)"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
</div>
</div>
<script src="script.js"></script>
</body>
</html>
