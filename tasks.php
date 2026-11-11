<?php
/**
 * CommitTrack - tasks.php  (Admin)
 * Create, edit, and delete tasks. Assign to whole classroom or individual students.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_admin();

$adminId  = (int)$_SESSION['user_id'];
$msg = $err = '';

// Pre-select classroom if given via GET
$preClassId = (int)($_GET['classroom_id'] ?? 0);
$editId     = (int)($_GET['edit'] ?? 0);
$editTask   = null;

/* ---- HANDLE POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $title       = trim($_POST['title'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $deadline    = $_POST['deadline'] ?? '';
        $priority    = $_POST['priority'] ?? 'medium';
        $classroomId = (int)($_POST['classroom_id'] ?? 0);
        $assignTo    = $_POST['assign_to'] ?? 'all'; // 'all' or student id

        if ($title === '') {
            $err = 'Task title is required.';
        } elseif ($classroomId === 0) {
            $err = 'Please select a classroom.';
        } else {
            // Verify classroom belongs to admin
            $own = $pdo->prepare('SELECT id FROM classrooms WHERE id=? AND admin_id=?');
            $own->execute([$classroomId, $adminId]);
            if (!$own->fetch()) {
                $err = 'Classroom not found.';
            } else {
                $deadline = ($deadline !== '') ? $deadline : null;
                if (!in_array($priority, ['low','medium','high'])) $priority = 'medium';

                $ins = $pdo->prepare('INSERT INTO tasks (admin_id, classroom_id, title, description, deadline, priority) VALUES (?,?,?,?,?,?)');
                $ins->execute([$adminId, $classroomId, $title, $desc ?: null, $deadline, $priority]);
                $taskId = (int)$pdo->lastInsertId();

                // Determine whom to assign
                if ($assignTo === 'all') {
                    $studs = $pdo->prepare('SELECT student_id FROM classroom_members WHERE classroom_id=?');
                    $studs->execute([$classroomId]);
                    $studentIds = array_column($studs->fetchAll(), 'student_id');
                } else {
                    $sid = (int)$assignTo;
                    // Verify student is in the classroom
                    $chk = $pdo->prepare('SELECT student_id FROM classroom_members WHERE classroom_id=? AND student_id=?');
                    $chk->execute([$classroomId, $sid]);
                    $studentIds = $chk->fetch() ? [$sid] : [];
                }

                $insA = $pdo->prepare('INSERT IGNORE INTO task_assignments (task_id, student_id) VALUES (?,?)');
                foreach ($studentIds as $sid) {
                    $insA->execute([$taskId, $sid]);
                }

                $msg = 'Task "' . e($title) . '" created and assigned to ' . count($studentIds) . ' student(s).';
            }
        }
    } elseif ($action === 'edit_task') {
        $tid         = (int)($_POST['task_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $desc        = trim($_POST['description'] ?? '');
        $deadline    = $_POST['deadline'] ?? '';
        $priority    = $_POST['priority'] ?? 'medium';
        $classroomId = (int)($_POST['classroom_id'] ?? 0);
        $assignTo    = $_POST['assign_to'] ?? 'keep';

        // Verify task ownership
        $own = $pdo->prepare('SELECT id FROM tasks WHERE id=? AND admin_id=?');
        $own->execute([$tid, $adminId]);
        if (!$own->fetch()) {
            $err = 'Task not found or permission denied.';
        } elseif ($title === '') {
            $err = 'Task title cannot be empty.';
        } elseif ($classroomId === 0) {
            $err = 'Please select a classroom.';
        } else {
            $deadline = ($deadline !== '') ? $deadline : null;
            if (!in_array($priority, ['low','medium','high'])) $priority = 'medium';

            $upd = $pdo->prepare('UPDATE tasks SET title=?, description=?, deadline=?, priority=?, classroom_id=? WHERE id=? AND admin_id=?');
            $upd->execute([$title, $desc ?: null, $deadline, $priority, $classroomId, $tid, $adminId]);

            // Optional re-assign or additional assignment
            if ($assignTo === 'all') {
                $studs = $pdo->prepare('SELECT student_id FROM classroom_members WHERE classroom_id=?');
                $studs->execute([$classroomId]);
                $studentIds = array_column($studs->fetchAll(), 'student_id');
                $insA = $pdo->prepare('INSERT IGNORE INTO task_assignments (task_id, student_id) VALUES (?,?)');
                foreach ($studentIds as $sid) { $insA->execute([$tid, $sid]); }
            } elseif (is_numeric($assignTo) && (int)$assignTo > 0) {
                $sid = (int)$assignTo;
                $insA = $pdo->prepare('INSERT IGNORE INTO task_assignments (task_id, student_id) VALUES (?,?)');
                $insA->execute([$tid, $sid]);
            }

            $msg = 'Task "' . e($title) . '" updated successfully!';
            $editId = 0; // Return to standard view
        }
    } elseif ($action === 'delete_task') {
        $tid = (int)($_POST['task_id'] ?? 0);
        $own = $pdo->prepare('SELECT id FROM tasks WHERE id=? AND admin_id=?');
        $own->execute([$tid, $adminId]);
        if ($own->fetch()) {
            $pdo->prepare('DELETE FROM tasks WHERE id=?')->execute([$tid]);
            $msg = 'Task deleted successfully.';
        } else {
            $err = 'Task not found or access denied.';
        }
    }
}

/* ---- Fetch task for editing if requested ---- */
if ($editId > 0) {
    $eStmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND admin_id = ?');
    $eStmt->execute([$editId, $adminId]);
    $editTask = $eStmt->fetch();
    if ($editTask) {
        $preClassId = (int)$editTask['classroom_id'];
    }
}

/* ---- Fetch admin classrooms for dropdown ---- */
$clsRows = $pdo->prepare('SELECT id, name, section FROM classrooms WHERE admin_id=? ORDER BY name');
$clsRows->execute([$adminId]);
$classroomList = $clsRows->fetchAll();

/* ---- Fetch all tasks with progress summary (Parameterized) ---- */
$sql = "
    SELECT t.*, c.name AS cls_name,
           COUNT(ta.id) AS assigned_count,
           SUM(ta.status='completed') AS completed_count,
           SUM(ta.status='in_progress') AS progress_count,
           SUM(ta.status='pending') AS pending_count,
           (t.deadline < CURDATE() AND SUM(ta.status='completed') < COUNT(ta.id)) AS is_overdue
    FROM tasks t
    JOIN classrooms c ON c.id = t.classroom_id
    LEFT JOIN task_assignments ta ON ta.task_id = t.id
    WHERE t.admin_id = ? " . ($preClassId && !$editTask ? "AND t.classroom_id = ?" : "") . "
    GROUP BY t.id
    ORDER BY
        CASE
            WHEN (t.deadline < CURDATE() AND t.priority = 'high' AND (COUNT(ta.id) = 0 OR SUM(ta.status='completed') < COUNT(ta.id))) THEN 1
            WHEN (t.priority = 'high') THEN 2
            WHEN (t.priority = 'medium') THEN 3
            ELSE 4
        END ASC,
        t.deadline ASC,
        t.created_at DESC
";
$stmt = $pdo->prepare($sql);
$params = [$adminId];
if ($preClassId && !$editTask) {
    $params[] = $preClassId;
}
$stmt->execute($params);
$allTasks = $stmt->fetchAll();

/* ---- Fetch members per classroom for assign_to dropdown (JS-driven) ---- */
$memStmt = $pdo->prepare("
    SELECT cm.classroom_id, u.id AS student_id, u.name AS student_name
    FROM classroom_members cm JOIN users u ON u.id = cm.student_id
    WHERE cm.classroom_id IN (SELECT id FROM classrooms WHERE admin_id = ?)
    ORDER BY u.name
");
$memStmt->execute([$adminId]);
$membersAll = $memStmt->fetchAll();
$membersByClass = [];
foreach ($membersAll as $m) {
    $membersByClass[$m['classroom_id']][] = $m;
}

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
<title><?= $editTask ? 'Edit Task' : 'Tasks' ?> – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap{max-width:1040px;margin:0 auto;padding:30px 24px 60px}
.page-title{font-size:1.5rem;color:var(--primary);margin-bottom:4px}
.page-sub{color:var(--text-muted);font-size:.9rem;margin-bottom:26px}
.card-form{background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:24px 26px;margin-bottom:30px}
.card-form h2{font-size:1.05rem;color:var(--primary);margin-bottom:16px}
.form-row3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;margin-bottom:14px}
.form-row2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.task-table{width:100%;border-collapse:collapse;font-size:.86rem;background:#fff;border-radius:var(--radius-lg);overflow:hidden;border:1px solid var(--border-color)}
.task-table th{text-align:left;padding:10px 14px;color:var(--text-muted);font-weight:600;border-bottom:2px solid var(--border-color);font-size:.76rem;text-transform:uppercase;background:#f9faf8}
.task-table td{padding:11px 14px;border-bottom:1px solid #f1f5f3;vertical-align:middle}
.task-table tr:last-child td{border-bottom:none}
.prio-badge{font-size:.7rem;padding:3px 8px;border-radius:4px;font-weight:600}
.prio-high{background:#fee2e2;color:#dc2626}
.prio-medium{background:#fef3c7;color:#b45309}
.prio-low{background:#dcfce7;color:#16a34a}
.progress-cell{display:flex;align-items:center;gap:8px}
.prog-bar-bg{width:70px;height:6px;background:#eef2f0;border-radius:99px;overflow:hidden}
.prog-bar-fill{height:100%;background:var(--primary);border-radius:99px}
.danger-btn{background:#fee2e2;color:#dc2626;border:none;padding:5px 11px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer}
.danger-btn:hover{background:#fecaca}
.overdue-tag{font-size:.68rem;background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:4px;margin-left:6px}
select.form-control{padding-left:12px}
.actions-cell{display:flex;align-items:center;gap:6px}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title"><?= $editTask ? 'Edit Task' : 'Tasks' ?></h1>
  <p class="page-sub"><?= $editTask ? 'Update task details, deadline, and assignments.' : 'Create, assign, edit and manage tasks across classrooms.' ?></p>

  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:16px">✓ <?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"  style="margin-bottom:16px">✕ <?= e($err) ?></div><?php endif; ?>

  <!-- Task Form (Create or Edit) -->
  <div class="card-form">
    <h2><?= $editTask ? '✏️ Edit Task: ' . e($editTask['title']) : '+ Create New Task' ?></h2>
    <?php if (empty($classroomList)): ?>
      <p style="color:var(--text-muted);font-size:.9rem">You need to <a href="classrooms.php">create a classroom</a> first before adding tasks.</p>
    <?php else: ?>
    <form method="POST" action="tasks.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= $editTask ? 'edit_task' : 'create_task' ?>">
      <?php if ($editTask): ?>
        <input type="hidden" name="task_id" value="<?= (int)$editTask['id'] ?>">
      <?php endif; ?>

      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Task Title *</label>
        <div class="input-wrapper">
          <input name="title" class="form-control" style="padding-left:14px" placeholder="e.g. Chapter 3 Assignment" value="<?= e($editTask['title'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-row3">
        <div class="form-group" style="margin:0">
          <label class="form-label">Classroom *</label>
          <select name="classroom_id" id="classroomSelect" class="form-control" required onchange="updateAssignTo(this.value)">
            <option value="">— Select Classroom —</option>
            <?php foreach ($classroomList as $cl): ?>
              <?php
                $isSelected = $editTask ? ($editTask['classroom_id'] == $cl['id']) : ($preClassId == $cl['id']);
              ?>
              <option value="<?= $cl['id'] ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= e($cl['name']) ?><?= $cl['section'] ? ' (' . e($cl['section']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Priority</label>
          <select name="priority" class="form-control">
            <?php $currPrio = $editTask['priority'] ?? 'medium'; ?>
            <option value="low" <?= $currPrio === 'low' ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= $currPrio === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="high" <?= $currPrio === 'high' ? 'selected' : '' ?>>High</option>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Deadline</label>
          <div class="input-wrapper">
            <input type="date" name="deadline" class="form-control" style="padding-left:14px" value="<?= e($editTask['deadline'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="form-row2">
        <div class="form-group" style="margin:0">
          <label class="form-label">Assign To</label>
          <select name="assign_to" id="assignToSelect" class="form-control">
            <?php if ($editTask): ?>
              <option value="keep">— Keep Current Assignments —</option>
              <option value="all">— Add All Students in Classroom —</option>
            <?php else: ?>
              <option value="all">— All Students in Classroom —</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Description (optional)</label>
          <textarea name="description" class="form-control" style="padding-left:14px;padding-top:8px;resize:vertical;min-height:44px" placeholder="Additional instructions or criteria…"><?= e($editTask['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:12px;margin-top:14px">
        <button type="submit" class="btn btn-primary">
          <?= $editTask ? 'Save Task Changes →' : 'Create & Assign Task →' ?>
        </button>
        <?php if ($editTask): ?>
          <a href="tasks.php" class="btn btn-outline btn-sm">Cancel Edit</a>
        <?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- Tasks Table -->
  <?php if (empty($allTasks)): ?>
    <div style="text-align:center;padding:40px 0;color:var(--text-muted)">
      <div style="font-size:2.5rem;margin-bottom:10px">📋</div>
      <p>No tasks yet. Create your first task above!</p>
    </div>
  <?php else: ?>
  <table class="task-table">
    <thead>
      <tr>
        <th>Task</th><th>Classroom</th><th>Priority</th><th>Deadline</th><th>Progress</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($allTasks as $t):
      $pct = $t['assigned_count'] > 0 ? round($t['completed_count'] / $t['assigned_count'] * 100) : 0;
    ?>
      <tr>
        <td>
          <strong><?= e($t['title']) ?></strong>
          <?php if ($t['is_overdue']): ?><span class="overdue-tag">Overdue</span><?php endif; ?>
          <?php if ($t['description']): ?><div style="font-size:.76rem;color:var(--text-muted);margin-top:2px"><?= e(mb_substr($t['description'], 0, 60)) ?>…</div><?php endif; ?>
        </td>
        <td style="font-size:.82rem;color:var(--text-muted)"><?= e($t['cls_name']) ?></td>
        <td><span class="prio-badge prio-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span></td>
        <td style="font-size:.82rem;color:var(--text-muted)"><?= $t['deadline'] ? date('M d, Y', strtotime($t['deadline'])) : '—' ?></td>
        <td>
          <div class="progress-cell">
            <div class="prog-bar-bg"><div class="prog-bar-fill" style="width:<?= $pct ?>%"></div></div>
            <span style="font-size:.76rem;color:var(--text-muted)"><?= $t['completed_count'] ?>/<?= $t['assigned_count'] ?></span>
          </div>
        </td>
        <td>
          <div class="actions-cell">
            <a href="tasks.php?edit=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 9px;font-size:.78rem">Edit</a>
            <form method="POST" onsubmit="return confirm('Delete this task and all student assignments?')" style="margin:0">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_task">
              <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
              <button class="danger-btn" type="submit">Delete</button>
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

<script>
// Members data embedded for JS-driven assign_to dropdown
const membersByClass = <?= json_encode($membersByClass) ?>;
const isEditMode = <?= $editTask ? 'true' : 'false' ?>;

function updateAssignTo(classroomId) {
  const sel = document.getElementById('assignToSelect');
  sel.innerHTML = isEditMode 
    ? '<option value="keep">— Keep Current Assignments —</option><option value="all">— Add All Students in Classroom —</option>'
    : '<option value="all">— All Students in Classroom —</option>';
  const members = membersByClass[classroomId] || [];
  members.forEach(m => {
    const opt = document.createElement('option');
    opt.value = m.student_id;
    opt.textContent = (isEditMode ? 'Assign to: ' : '') + m.student_name;
    sel.appendChild(opt);
  });
}
// Init on load if classroom pre-selected
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('classroomSelect');
  if (sel && sel.value) updateAssignTo(sel.value);
});
</script>
<script src="script.js"></script>
</body>
</html>
