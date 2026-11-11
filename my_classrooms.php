<?php
/**
 * CommitTrack - my_classrooms.php (Student)
 * View enrolled classrooms and tasks within each.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_student();

$studentId  = (int)$_SESSION['user_id'];
$joinedMsg  = isset($_GET['joined']) ? 'You successfully joined the classroom!' : '';

// Enrolled classrooms with admin name and task counts
$rows = $pdo->prepare("
    SELECT c.*, u.name AS admin_name,
           COUNT(DISTINCT ta.task_id) AS my_tasks,
           SUM(ta.status = 'completed') AS my_completed
    FROM classroom_members cm
    JOIN classrooms c ON c.id = cm.classroom_id
    JOIN users u ON u.id = c.admin_id
    LEFT JOIN task_assignments ta ON ta.task_id IN (
        SELECT id FROM tasks WHERE classroom_id = c.id
    ) AND ta.student_id = ?
    WHERE cm.student_id = ?
    GROUP BY c.id
    ORDER BY c.name
");
$rows->execute([$studentId, $studentId]);
$classrooms = $rows->fetchAll();

$userName   = $_SESSION['user_name'] ?? '';
$userEmail  = $_SESSION['user_email'] ?? '';
$userRole   = 'student';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Classrooms – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap{max-width:980px;margin:0 auto;padding:30px 24px 60px}
.page-title{font-size:1.5rem;color:var(--primary);margin-bottom:4px}
.page-sub{color:var(--text-muted);font-size:.9rem;margin-bottom:26px}
.cls-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:20px}
.cls-card{background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:22px 22px;display:flex;flex-direction:column;gap:10px}
.cls-name{font-size:1.05rem;font-weight:700;color:var(--primary)}
.cls-meta{font-size:.8rem;color:var(--text-muted)}
.cls-stats{display:flex;gap:16px;font-size:.82rem;color:var(--text-muted)}
.cls-stat strong{color:var(--primary)}
.prog-bar-bg{width:100%;height:7px;background:#eef2f0;border-radius:99px;overflow:hidden;margin-top:4px}
.prog-bar-fill{height:100%;background:var(--primary);border-radius:99px}
.join-form{display:flex;gap:10px;margin-bottom:28px;background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:16px 18px}
.join-form input{flex:1;padding:9px 14px;border:1px solid var(--border-color);border-radius:var(--radius-md);font-size:.9rem}
.join-form input:focus{outline:none;border-color:var(--primary)}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title">My Classrooms</h1>
  <p class="page-sub">Classrooms you are enrolled in. Paste an invite link to join a new one.</p>

  <?php if ($joinedMsg): ?><div class="alert alert-success" style="margin-bottom:16px">✓ <?= e($joinedMsg) ?></div><?php endif; ?>

  <!-- Quick-join by code -->
  <form class="join-form" action="" method="GET" onsubmit="joinByCode(event)">
    <input type="text" id="inviteCodeInput" placeholder="Paste an invite link or code here…">
    <button type="submit" class="btn btn-primary btn-sm">Join →</button>
  </form>

  <?php if (empty($classrooms)): ?>
    <div style="text-align:center;padding:48px 0;color:var(--text-muted)">
      <div style="font-size:2.8rem;margin-bottom:12px">🏫</div>
      <p>You haven't joined any classrooms yet.</p>
      <p style="font-size:.85rem;margin-top:8px">Ask your instructor for an invite link.</p>
    </div>
  <?php else: ?>
  <div class="cls-grid">
    <?php foreach ($classrooms as $c):
      $pct = $c['my_tasks'] > 0 ? round($c['my_completed'] / $c['my_tasks'] * 100) : 0;
    ?>
    <div class="cls-card">
      <div>
        <div class="cls-name"><?= e($c['name']) ?></div>
        <div class="cls-meta">
          <?= $c['section'] ? e($c['section']) . ' · ' : '' ?>
          Instructor: <?= e($c['admin_name']) ?>
        </div>
      </div>
      <div class="cls-stats">
        <span><strong><?= $c['my_tasks'] ?></strong> Tasks</span>
        <span><strong><?= (int)$c['my_completed'] ?></strong> Completed</span>
        <span><strong><?= $pct ?>%</strong> Done</span>
      </div>
      <div>
        <div class="prog-bar-bg"><div class="prog-bar-fill" style="width:<?= $pct ?>%"></div></div>
      </div>
      <div style="margin-top:4px">
        <a href="my_tasks.php" class="btn btn-secondary btn-sm">View My Tasks</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div>
</div>
<script>
function joinByCode(e) {
  e.preventDefault();
  let val = document.getElementById('inviteCodeInput').value.trim();
  if (!val) return;
  // If it's a full URL, extract code param; otherwise treat as raw code
  let code = val;
  try {
    const url = new URL(val);
    code = url.searchParams.get('code') || val;
  } catch(_) {}
  if (code) window.location.href = 'join.php?code=' + encodeURIComponent(code);
}
</script>
<script src="script.js"></script>
</body>
</html>
