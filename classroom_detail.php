<?php
/**
 * CommitTrack - classroom_detail.php
 * Admin views members, tasks, and progress for a single classroom.
 * Allows managing classroom details, removing members, and managing classroom tasks.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_admin();

$adminId = (int)$_SESSION['user_id'];
$cid     = (int)($_GET['id'] ?? 0);
$msg = $err = '';

// Verify ownership
$cls = $pdo->prepare('SELECT * FROM classrooms WHERE id=? AND admin_id=?');
$cls->execute([$cid, $adminId]);
$classroom = $cls->fetch();
if (!$classroom) {
    header('Location: classrooms.php');
    exit;
}

// Handle POST actions (edit classroom, remove student)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_classroom') {
        $name    = trim($_POST['name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $desc    = trim($_POST['description'] ?? '');

        if ($name === '') {
            $err = 'Classroom name cannot be empty.';
        } else {
            $upd = $pdo->prepare('UPDATE classrooms SET name=?, section=?, description=? WHERE id=? AND admin_id=?');
            $upd->execute([$name, $section ?: null, $desc ?: null, $cid, $adminId]);
            $msg = 'Classroom updated successfully!';
            // Refresh
            $cls->execute([$cid, $adminId]);
            $classroom = $cls->fetch();
        }
    } elseif ($action === 'remove_member') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        if ($studentId > 0) {
            $del = $pdo->prepare('DELETE FROM classroom_members WHERE classroom_id=? AND student_id=?');
            $del->execute([$cid, $studentId]);
            $msg = 'Student removed from this classroom.';
        }
    } elseif ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM tasks WHERE id=? AND admin_id=? AND classroom_id=?');
        $del->execute([$taskId, $adminId, $cid]);
        $msg = 'Task deleted successfully.';
    }
}

// Members
$members = $pdo->prepare('
    SELECT u.id, u.name, u.email, cm.joined_at,
           COUNT(ta.id) AS total_tasks,
           SUM(ta.status="completed") AS completed_tasks
    FROM classroom_members cm
    JOIN users u ON u.id = cm.student_id
    LEFT JOIN task_assignments ta ON ta.student_id = u.id
        AND ta.task_id IN (SELECT id FROM tasks WHERE classroom_id = ?)
    WHERE cm.classroom_id = ?
    GROUP BY u.id, u.name, u.email, cm.joined_at
    ORDER BY u.name
');
$members->execute([$cid, $cid]);
$memberList = $members->fetchAll();

// Tasks for this classroom
$tasks = $pdo->prepare('
    SELECT t.*,
           COUNT(ta.id) AS assigned_count,
           SUM(ta.status="completed") AS completed_count,
           SUM(ta.status="in_progress") AS progress_count,
           SUM(ta.status="pending") AS pending_count
    FROM tasks t
    LEFT JOIN task_assignments ta ON ta.task_id = t.id
    WHERE t.classroom_id = ?
    GROUP BY t.id
    ORDER BY t.deadline ASC, t.created_at DESC
');
$tasks->execute([$cid]);
$taskList = $tasks->fetchAll();

$userName   = $_SESSION['user_name'] ?? '';
$userEmail  = $_SESSION['user_email'] ?? '';
$userRole   = 'admin';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
$inviteUrl = $baseUrl . 'join.php?code=' . urlencode($classroom['invite_code']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e($classroom['name']) ?> – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap{max-width:1060px;margin:0 auto;padding:30px 24px 60px}
.page-title{font-size:1.4rem;color:var(--primary);margin-bottom:2px}
.page-sub{color:var(--text-muted);font-size:.88rem;margin-bottom:24px}
.section-card{background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:22px 24px;margin-bottom:24px}
.section-card h2{font-size:1.05rem;color:var(--primary);margin-bottom:16px}
.member-table, .task-table{width:100%;border-collapse:collapse;font-size:.86rem}
.member-table th, .task-table th{text-align:left;padding:8px 12px;color:var(--text-muted);font-weight:600;border-bottom:2px solid var(--border-color);font-size:.78rem;text-transform:uppercase}
.member-table td, .task-table td{padding:10px 12px;border-bottom:1px solid #f1f5f3;vertical-align:middle}
.member-table tr:last-child td, .task-table tr:last-child td{border-bottom:none}
.prog-bar-bg{width:90px;height:7px;background:#eef2f0;border-radius:99px;overflow:hidden}
.prog-bar-fill{height:100%;background:var(--primary);border-radius:99px}
.prio-badge{font-size:.72rem;padding:3px 8px;border-radius:4px;font-weight:600}
.prio-high{background:#fee2e2;color:#dc2626}
.prio-medium{background:#fef3c7;color:#b45309}
.prio-low{background:#dcfce7;color:#16a34a}
.invite-row{display:flex;align-items:center;gap:8px;background:#f3f8f5;border:1px solid #d3e5dc;border-radius:8px;padding:8px 12px;margin-bottom:18px}
.invite-code{font-family:monospace;font-size:.82rem;color:var(--primary);flex:1;word-break:break-all}
.copy-btn{background:var(--primary);color:#fff;border:none;padding:5px 11px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer}
.copy-btn:hover{background:var(--primary-hover)}
.empty-note{color:var(--text-muted);font-size:.88rem;text-align:center;padding:18px 0}
.danger-btn-sm{background:#fee2e2;color:#dc2626;border:none;padding:4px 8px;border-radius:4px;font-size:.72rem;font-weight:600;cursor:pointer}
.danger-btn-sm:hover{background:#fecaca}
.edit-form-wrap{background:#fcfdfc;border:1px dashed var(--border-color);border-radius:var(--radius-md);padding:14px 16px;margin-top:12px}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
    <a href="classrooms.php" style="color:var(--text-muted);font-size:.85rem">← Back to Classrooms</a>
  </div>
  <h1 class="page-title"><?= e($classroom['name']) ?></h1>
  <p class="page-sub"><?= $classroom['section'] ? e($classroom['section']) . ' · ' : '' ?><?= count($memberList) ?> students · <?= count($taskList) ?> tasks</p>

  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:16px">✓ <?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"  style="margin-bottom:16px">✕ <?= e($err) ?></div><?php endif; ?>

  <!-- Invite link -->
  <div class="invite-row">
    <span class="invite-code" id="iurl-detail"><?= e($inviteUrl) ?></span>
    <button class="copy-btn" onclick="copyInvite('iurl-detail',this)">Copy Invite Link</button>
    <a href="tasks.php?classroom_id=<?= $cid ?>" class="btn btn-primary btn-sm" style="margin-left:auto">+ Add Task</a>
  </div>

  <!-- Classroom Details & Edit -->
  <details class="section-card" style="padding:16px 20px">
    <summary style="font-weight:700;color:var(--primary);cursor:pointer;font-size:.92rem">⚙️ Edit Classroom Info</summary>
    <form method="POST" action="classroom_detail.php?id=<?= $cid ?>" class="edit-form-wrap">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_classroom">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px">
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:.78rem">Name *</label>
          <input name="name" class="form-control" style="padding-left:10px;height:36px" value="<?= e($classroom['name']) ?>" required>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label" style="font-size:.78rem">Section / Batch</label>
          <input name="section" class="form-control" style="padding-left:10px;height:36px" value="<?= e($classroom['section'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label" style="font-size:.78rem">Description</label>
        <textarea name="description" class="form-control" style="padding:8px 10px;min-height:48px"><?= e($classroom['description'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
    </form>
  </details>

  <!-- Members -->
  <div class="section-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2 style="margin:0">Enrolled Students (<?= count($memberList) ?>)</h2>
    </div>
    <?php if (empty($memberList)): ?>
      <p class="empty-note">No students have joined yet. Share the invite link above.</p>
    <?php else: ?>
    <table class="member-table">
      <thead><tr><th>Name</th><th>Email</th><th>Joined</th><th>Progress</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($memberList as $m): 
        $pct = $m['total_tasks'] > 0 ? round($m['completed_tasks'] / $m['total_tasks'] * 100) : 0;
      ?>
        <tr>
          <td><strong><?= e($m['name']) ?></strong></td>
          <td style="color:var(--text-muted)"><?= e($m['email']) ?></td>
          <td style="color:var(--text-muted);font-size:.8rem"><?= date('M d, Y', strtotime($m['joined_at'])) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="prog-bar-bg"><div class="prog-bar-fill" style="width:<?= $pct ?>%"></div></div>
              <span style="font-size:.78rem;color:var(--text-muted)"><?= $pct ?>%</span>
            </div>
            <span style="font-size:.72rem;color:var(--text-muted)"><?= (int)$m['completed_tasks'] ?>/<?= $m['total_tasks'] ?> tasks</span>
          </td>
          <td style="text-align:right">
            <form method="POST" onsubmit="return confirm('Remove student from this classroom?')" style="margin:0">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="remove_member">
              <input type="hidden" name="student_id" value="<?= $m['id'] ?>">
              <button class="danger-btn-sm" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Tasks -->
  <div class="section-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2 style="margin:0">Tasks (<?= count($taskList) ?>)</h2>
      <a href="tasks.php?classroom_id=<?= $cid ?>" class="btn btn-secondary btn-sm">+ Add Task</a>
    </div>
    <?php if (empty($taskList)): ?>
      <p class="empty-note">No tasks assigned yet. <a href="tasks.php?classroom_id=<?= $cid ?>">Create a task →</a></p>
    <?php else: ?>
    <table class="task-table">
      <thead><tr><th>Title</th><th>Priority</th><th>Deadline</th><th>Assigned</th><th>Completed</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($taskList as $t): 
        $overdue = $t['deadline'] && $t['deadline'] < date('Y-m-d');
      ?>
        <tr>
          <td>
            <strong><?= e($t['title']) ?></strong>
            <?php if ($overdue && $t['completed_count'] < $t['assigned_count']): ?>
              <span style="font-size:.7rem;background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:4px;margin-left:6px">Overdue</span>
            <?php endif; ?>
          </td>
          <td><span class="prio-badge prio-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
          <td style="font-size:.82rem;color:var(--text-muted)"><?= $t['deadline'] ? date('M d, Y', strtotime($t['deadline'])) : '—' ?></td>
          <td><?= $t['assigned_count'] ?></td>
          <td><?= (int)$t['completed_count'] ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="tasks.php?edit=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="padding:3px 8px;font-size:.72rem">Edit</a>
              <form method="POST" onsubmit="return confirm('Delete this task?')" style="margin:0">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_task">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <button class="danger-btn-sm" type="submit">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
</div>
</div>
<script>
function copyInvite(id, btn) {
  const text = document.getElementById(id).textContent;
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.textContent; btn.textContent = 'Copied!'; btn.style.background = '#16a34a';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 2000);
  }).catch(() => { prompt('Copy link:', text); });
}
</script>
<script src="script.js"></script>
</body>
</html>
