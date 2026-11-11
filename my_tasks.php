<?php
/**
 * CommitTrack - my_tasks.php (Student)
 * View all assigned tasks; update status to Pending/In Progress/Completed.
 * Supports requesting individual & classroom deadline extensions, sorted by priority & urgency.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_student();

$studentId = (int)$_SESSION['user_id'];
$userName  = $_SESSION['user_name'] ?? 'Student';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole  = 'student';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
$today     = date('Y-m-d');

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

/* ---- Handle status update ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    verify_csrf();
    $assignId  = (int)($_POST['assignment_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, ['pending','in_progress','completed'], true) && $assignId > 0) {
        $chk = $pdo->prepare('SELECT id FROM task_assignments WHERE id=? AND student_id=?');
        $chk->execute([$assignId, $studentId]);
        if ($chk->fetch()) {
            $pdo->prepare('UPDATE task_assignments SET status=? WHERE id=?')->execute([$newStatus, $assignId]);
            $msg = 'Task status updated successfully!';
        }
    }
}

/* ---- Fetch tasks sorted by priority & urgency (Phase 5 requirement) ---- */
// 1. Overdue high-priority
// 2. Upcoming high-priority
// 3. Medium-priority
// 4. Low-priority
// (within each, nearest deadline first)
$sql = "
    SELECT ta.id AS assignment_id, ta.status, ta.custom_deadline,
           t.id AS task_id, t.title, t.description, t.deadline, t.original_deadline, t.priority,
           t.classroom_id,
           c.name AS classroom_name, c.section AS classroom_section,
           COALESCE(ta.custom_deadline, t.deadline) AS effective_deadline,
           (
             SELECT er.status FROM extension_requests er
             WHERE er.task_id = t.id
               AND (er.student_id = ? OR (er.classroom_id = t.classroom_id AND er.request_type = 'group'))
               AND er.status = 'pending'
             LIMIT 1
           ) AS pending_ext_status
    FROM task_assignments ta
    JOIN tasks t ON t.id = ta.task_id
    JOIN classrooms c ON c.id = t.classroom_id
    WHERE ta.student_id = ?
    ORDER BY
        CASE
            WHEN (COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND t.priority = 'high' AND ta.status != 'completed') THEN 1
            WHEN (t.priority = 'high' AND ta.status != 'completed') THEN 2
            WHEN (t.priority = 'medium') THEN 3
            ELSE 4
        END ASC,
        COALESCE(ta.custom_deadline, t.deadline) ASC,
        t.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId, $studentId]);
$taskList = $stmt->fetchAll();

/* ---- Summary counts ---- */
$total       = count($taskList);
$completed   = count(array_filter($taskList, fn($t) => $t['status'] === 'completed'));
$inProgress  = count(array_filter($taskList, fn($t) => $t['status'] === 'in_progress'));
$pending     = count(array_filter($taskList, fn($t) => $t['status'] === 'pending'));
$pct         = $total > 0 ? round($completed / $total * 100) : 0;
$highPending = count(array_filter($taskList, fn($t) => $t['priority'] === 'high' && $t['status'] !== 'completed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Tasks – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap{max-width:1040px;margin:0 auto;padding:30px 24px 60px}
.page-title{font-size:1.5rem;color:var(--primary);margin-bottom:4px}
.page-sub{color:var(--text-muted);font-size:.9rem;margin-bottom:24px}
.summary-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.sum-card{background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:16px 18px}
.sum-label{font-size:.74rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px}
.sum-val{font-size:1.45rem;font-weight:800;color:var(--primary)}

.task-card{background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:20px 22px;margin-bottom:16px;display:flex;flex-direction:column;gap:12px;transition:var(--transition)}
.task-card:hover{box-shadow:0 6px 18px rgba(0,0,0,0.04)}
.task-card.high-priority-urgent{border-left:4px solid #dc2626}
.task-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.task-title{font-size:1.02rem;font-weight:700;color:var(--primary)}
.task-meta{font-size:.78rem;color:var(--text-muted);display:flex;gap:12px;flex-wrap:wrap;align-items:center}

.prio-badge{font-size:.7rem;padding:3px 8px;border-radius:4px;font-weight:700;text-transform:uppercase}
.prio-high{background:#fee2e2;color:#dc2626}
.prio-medium{background:#fef3c7;color:#b45309}
.prio-low{background:#dcfce7;color:#16a34a}

.status-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.status-select{font-size:.82rem;padding:5px 10px;border-radius:8px;border:1px solid var(--border-color);background:#f9faf8;color:var(--primary);font-weight:600;cursor:pointer}
.status-select:focus{outline:none;border-color:var(--primary)}
.update-btn{background:var(--primary);color:#fff;border:none;padding:5px 12px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer}
.update-btn:hover{background:var(--primary-hover)}

.overdue-badge{font-size:.7rem;background:#fee2e2;color:#dc2626;padding:3px 8px;border-radius:4px;font-weight:700}
.extended-badge{font-size:.7rem;background:#dbeafe;color:#1d4ed8;padding:3px 8px;border-radius:4px;font-weight:700}
.pending-ext-badge{font-size:.7rem;background:#fef3c7;color:#b45309;padding:3px 8px;border-radius:4px;font-weight:700}

.pill-pending{background:#fef3c7;color:#b45309}
.pill-in_progress{background:#e0f2fe;color:#0369a1}
.pill-completed{background:#dcfce7;color:#15803d}

/* Modal styles for requesting extension */
.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal-box{background:#fff;border-radius:var(--radius-lg);padding:28px 30px;max-width:480px;width:92%;box-shadow:0 20px 45px rgba(0,0,0,0.18)}
.modal-title{font-size:1.18rem;font-weight:700;color:var(--primary);margin-bottom:6px}
.modal-sub{font-size:.84rem;color:var(--text-muted);margin-bottom:18px}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;flex-wrap:wrap;gap:10px">
    <div>
      <h1 class="page-title">My Tasks</h1>
      <p class="page-sub">Track coursework priorities, submit status updates, and request deadline extensions.</p>
    </div>
    <a href="my_extensions.php" class="btn btn-secondary btn-sm">Extension History →</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:18px">✓ <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"  style="margin-bottom:18px">✕ <?= e($err) ?></div><?php endif; ?>

  <!-- Summary Bar -->
  <div class="summary-bar">
    <div class="sum-card">
      <div class="sum-label">Total Tasks</div>
      <div class="sum-val"><?= $total ?></div>
    </div>
    <div class="sum-card">
      <div class="sum-label">Completed</div>
      <div class="sum-val" style="color:#16a34a"><?= $completed ?></div>
    </div>
    <div class="sum-card">
      <div class="sum-label">In Progress</div>
      <div class="sum-val" style="color:#0369a1"><?= $inProgress ?></div>
    </div>
    <div class="sum-card">
      <div class="sum-label">High Priority Pending</div>
      <div class="sum-val" style="color:<?= $highPending > 0 ? '#dc2626' : 'var(--primary)' ?>"><?= $highPending ?></div>
    </div>
  </div>

  <!-- Progress Bar -->
  <div style="background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:16px 20px;margin-bottom:26px;display:flex;align-items:center;gap:20px">
    <div>
      <div style="font-size:.78rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px">Overall Completion</div>
      <div style="font-size:1.4rem;font-weight:800;color:var(--primary)"><?= $pct ?>%</div>
    </div>
    <div style="flex:1;height:10px;background:#eef2f0;border-radius:99px;overflow:hidden">
      <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:99px;transition:width .3s"></div>
    </div>
    <div style="font-size:.82rem;color:var(--text-muted)"><?= $completed ?> / <?= $total ?> tasks completed</div>
  </div>

  <!-- Task Cards -->
  <?php if (empty($taskList)): ?>
    <div style="text-align:center;padding:56px 20px;background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);color:var(--text-muted)">
      <div style="font-size:2.8rem;margin-bottom:12px">📭</div>
      <h3 style="color:var(--primary);margin-bottom:6px">No Tasks Assigned Yet</h3>
      <p style="font-size:.88rem">Join a classroom using your instructor's invite link to receive assignments.</p>
      <a href="my_classrooms.php" class="btn btn-primary btn-sm" style="margin-top:16px">Browse Classrooms →</a>
    </div>
  <?php else: ?>
    <?php foreach ($taskList as $t):
      $effectiveDue = $t['effective_deadline'];
      $isOverdue = $effectiveDue && $effectiveDue < $today && $t['status'] !== 'completed';
      $isExtended = !empty($t['custom_deadline']);
      $isHighUrgent = ($t['priority'] === 'high' && $t['status'] !== 'completed');
    ?>
    <div class="task-card <?= $isHighUrgent ? 'high-priority-urgent' : '' ?>">
      <div class="task-header">
        <div style="flex:1;min-width:0">
          <div class="task-title">
            <?= e($t['title']) ?>
            <?php if ($isOverdue): ?>
              <span class="overdue-badge">⚠️ Overdue</span>
            <?php endif; ?>
            <?php if ($isExtended): ?>
              <span class="extended-badge">✓ Extended</span>
            <?php endif; ?>
            <?php if ($t['pending_ext_status']): ?>
              <span class="pending-ext-badge">⏳ Extension Pending</span>
            <?php endif; ?>
          </div>
          <div class="task-meta" style="margin-top:6px">
            <span>🏫 <?= e($t['classroom_name']) ?><?= $t['classroom_section'] ? ' (' . e($t['classroom_section']) . ')' : '' ?></span>
            <?php if ($effectiveDue): ?>
              <span>📅 <strong>Due:</strong> <?= date('M d, Y', strtotime($effectiveDue)) ?></span>
            <?php endif; ?>
            <?php if ($t['original_deadline'] && $t['original_deadline'] !== $effectiveDue): ?>
              <span style="text-decoration:line-through;color:var(--text-light);font-size:.74rem">Orig: <?= date('M d', strtotime($t['original_deadline'])) ?></span>
            <?php endif; ?>
            <span class="prio-badge prio-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?> Priority</span>
          </div>
          <?php if (!empty($t['description'])): ?>
            <div style="font-size:.84rem;color:var(--text-main);margin-top:8px;line-height:1.45;background:#f9fbf9;padding:8px 12px;border-radius:6px">
              <?= nl2br(e($t['description'])) ?>
            </div>
          <?php endif; ?>
        </div>
        <span class="status-pill pill-<?= $t['status'] ?>" style="white-space:nowrap;flex-shrink:0">
          <?= $t['status'] === 'in_progress' ? 'In Progress' : ucfirst($t['status']) ?>
        </span>
      </div>

      <!-- Action row: status update & request extension -->
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;border-top:1px solid #f1f5f3;padding-top:12px;margin-top:4px">
        <!-- Status Update -->
        <form method="POST" class="status-form">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="assignment_id" value="<?= $t['assignment_id'] ?>">
          <label style="font-size:.78rem;color:var(--text-muted);font-weight:600">Update Status:</label>
          <select name="status" class="status-select">
            <option value="pending"     <?= $t['status']==='pending'     ?'selected':'' ?>>⏳ Pending</option>
            <option value="in_progress" <?= $t['status']==='in_progress' ?'selected':'' ?>>🔄 In Progress</option>
            <option value="completed"   <?= $t['status']==='completed'   ?'selected':'' ?>>✅ Completed</option>
          </select>
          <button type="submit" class="update-btn">Save</button>
        </form>

        <!-- Request Extension Button (Available for incomplete tasks without active pending request) -->
        <?php if ($t['status'] !== 'completed'): ?>
          <?php if ($t['pending_ext_status']): ?>
            <a href="my_extensions.php" class="btn btn-outline btn-sm" style="font-size:.76rem;border-color:#f59e0b;color:#b45309">
              ⏳ Extension Request Under Review →
            </a>
          <?php else: ?>
            <button type="button" class="btn btn-outline btn-sm" style="font-size:.76rem" onclick="openExtensionModal(<?= $t['task_id'] ?>, '<?= e(addslashes($t['title'])) ?>', '<?= $effectiveDue ?>')">
              ⏱️ Request Deadline Extension
            </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</div>
</div>

<!-- Request Extension Modal -->
<div class="modal-overlay" id="extensionModal">
  <div class="modal-box">
    <div class="modal-title">Request Deadline Extension</div>
    <div class="modal-sub" id="modalTaskName">Task: Assignment</div>
    <form method="POST" action="request_extension.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="task_id" id="modalTaskId" value="">

      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Request Type *</label>
        <select name="request_type" class="form-control" style="padding-left:12px" required>
          <option value="individual">Individual (Just for me)</option>
          <option value="group">Classroom / Group Request (Entire class)</option>
        </select>
      </div>

      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Requested New Deadline *</label>
        <input type="date" name="requested_deadline" id="modalRequestedDate" class="form-control" style="padding-left:12px" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
      </div>

      <div class="form-group" style="margin-bottom:18px">
        <label class="form-label">Reason for Request *</label>
        <textarea name="reason" class="form-control" style="padding:10px 12px;min-height:80px" placeholder="Explain why you or the class require additional time (illness, project complexity, conflicting schedule)..." required></textarea>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeExtensionModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Submit Request →</button>
      </div>
    </form>
  </div>
</div>

<script>
function openExtensionModal(taskId, taskTitle, currentDue) {
  document.getElementById('modalTaskId').value = taskId;
  document.getElementById('modalTaskName').textContent = 'Task: ' + taskTitle + (currentDue ? ' (Current Due: ' + currentDue + ')' : '');
  const minDate = currentDue ? currentDue : '<?= date('Y-m-d') ?>';
  document.getElementById('modalRequestedDate').min = minDate;
  document.getElementById('extensionModal').classList.add('active');
}
function closeExtensionModal() {
  document.getElementById('extensionModal').classList.remove('active');
}
// Close on click outside
window.addEventListener('click', (e) => {
  const modal = document.getElementById('extensionModal');
  if (e.target === modal) closeExtensionModal();
});
</script>
<script src="script.js"></script>
</body>
</html>
