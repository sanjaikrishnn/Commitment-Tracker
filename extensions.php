<?php
/**
 * CommitTrack - extensions.php (Admin / Teacher)
 * Review, approve, and reject deadline extension requests.
 * Uses atomic database transactions to ensure consistency between request status and task deadlines.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_admin();

$adminId     = (int)$_SESSION['user_id'];
$userName    = $_SESSION['user_name'] ?? 'Admin';
$userEmail   = $_SESSION['user_email'] ?? '';
$userRole    = 'admin';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

$msg = '';
$err = '';

// Handle POST actions: Approve or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action    = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $comment   = trim($_POST['teacher_comment'] ?? '');

    // Verify that this request belongs to a classroom owned by this admin
    $chkStmt = $pdo->prepare('
        SELECT er.*, t.title AS task_title, t.classroom_id, t.deadline AS task_current_deadline,
               c.admin_id
        FROM extension_requests er
        JOIN classrooms c ON c.id = er.classroom_id
        JOIN tasks t ON t.id = er.task_id
        WHERE er.id = ? AND c.admin_id = ?
    ');
    $chkStmt->execute([$requestId, $adminId]);
    $req = $chkStmt->fetch();

    if (!$req) {
        $err = 'Extension request not found or access denied.';
    } elseif ($req['status'] !== 'pending') {
        $err = 'This request has already been reviewed.';
    } else {
        if ($action === 'approve') {
            try {
                $pdo->beginTransaction();

                // 1. Mark request as approved with feedback
                $updReq = $pdo->prepare('
                    UPDATE extension_requests
                    SET status = "approved", teacher_comment = ?, reviewed_at = NOW()
                    WHERE id = ?
                ');
                $updReq->execute([$comment ?: 'Approved by instructor.', $requestId]);

                // 2. Update deadlines based on individual or group scope
                if ($req['request_type'] === 'individual') {
                    // Update only this student's assignment custom_deadline
                    $updAssign = $pdo->prepare('
                        UPDATE task_assignments
                        SET custom_deadline = ?
                        WHERE task_id = ? AND student_id = ?
                    ');
                    $updAssign->execute([$req['requested_deadline'], $req['task_id'], $req['student_id']]);
                } else {
                    // Group extension: update the classroom task deadline while preserving original_deadline
                    $updTask = $pdo->prepare('
                        UPDATE tasks
                        SET original_deadline = COALESCE(original_deadline, deadline), deadline = ?
                        WHERE id = ? AND classroom_id = ?
                    ');
                    $updTask->execute([$req['requested_deadline'], $req['task_id'], $req['classroom_id']]);
                }

                $pdo->commit();
                $msg = 'Extension request for "' . e($req['task_title']) . '" approved successfully. Deadline updated.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Extension Approval Error: ' . $e->getMessage());
                $err = 'Failed to approve extension request. Please try again.';
            }
        } elseif ($action === 'reject') {
            try {
                // Rejection: deadlines remain strictly unchanged!
                $updReq = $pdo->prepare('
                    UPDATE extension_requests
                    SET status = "rejected", teacher_comment = ?, reviewed_at = NOW()
                    WHERE id = ?
                ');
                $updReq->execute([$comment ?: 'Extension request could not be accommodated.', $requestId]);
                $msg = 'Extension request for "' . e($req['task_title']) . '" rejected. Deadline remains unchanged.';
            } catch (Exception $e) {
                error_log('Extension Rejection Error: ' . $e->getMessage());
                $err = 'Failed to reject extension request.';
            }
        }
    }
}

// Filter handling
$filter = $_GET['filter'] ?? 'all';
$whereFilter = '';
if ($filter === 'pending')  $whereFilter = 'AND er.status = "pending"';
if ($filter === 'approved') $whereFilter = 'AND er.status = "approved"';
if ($filter === 'rejected') $whereFilter = 'AND er.status = "rejected"';

// Fetch all extension requests for classrooms owned by this admin
$query = "
    SELECT er.*, t.title AS task_title, t.priority AS task_priority,
           c.name AS classroom_name, c.section AS classroom_section,
           u.name AS student_name, u.email AS student_email
    FROM extension_requests er
    JOIN classrooms c ON c.id = er.classroom_id
    JOIN tasks t ON t.id = er.task_id
    JOIN users u ON u.id = er.student_id
    WHERE c.admin_id = ? $whereFilter
    ORDER BY 
        CASE er.status WHEN 'pending' THEN 1 ELSE 2 END,
        er.created_at DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$adminId]);
$requests = $stmt->fetchAll();

// Get counts for tabs
$cntStmt = $pdo->prepare('
    SELECT 
        COUNT(*) AS total,
        SUM(er.status = "pending") AS pending_count,
        SUM(er.status = "approved") AS approved_count,
        SUM(er.status = "rejected") AS rejected_count
    FROM extension_requests er
    JOIN classrooms c ON c.id = er.classroom_id
    WHERE c.admin_id = ?
');
$cntStmt->execute([$adminId]);
$counts = $cntStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Extension Requests – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap { max-width: 1080px; margin: 0 auto; padding: 30px 24px 60px; }
.page-title { font-size: 1.5rem; color: var(--primary); margin-bottom: 4px; }
.page-sub { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }

.filter-tabs { display: flex; gap: 8px; margin-bottom: 22px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; }
.tab-link { padding: 6px 14px; border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 6px; }
.tab-link:hover { color: var(--primary); background: #f0f4f1; }
.tab-link.active { background: var(--primary-light); color: var(--primary); }
.tab-badge { background: #e2ece6; color: var(--primary); font-size: 0.72rem; padding: 2px 6px; border-radius: 99px; }
.tab-badge.badge-urgent { background: #fee2e2; color: #dc2626; }

.req-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; background: #fff; border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-color); }
.req-table th { text-align: left; padding: 11px 14px; color: var(--text-muted); font-weight: 600; border-bottom: 2px solid var(--border-color); font-size: 0.76rem; text-transform: uppercase; background: #f9faf8; }
.req-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f3; vertical-align: top; }
.req-table tr:last-child td { border-bottom: none; }

.status-badge { font-size: 0.72rem; padding: 3px 9px; border-radius: 99px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.status-pending { background: #fef3c7; color: #b45309; }
.status-approved { background: #dcfce7; color: #15803d; }
.status-rejected { background: #fee2e2; color: #dc2626; }

.action-btns { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
.btn-approve { background: #16a34a; color: #fff; border: none; padding: 5px 11px; border-radius: 6px; font-size: 0.76rem; font-weight: 600; cursor: pointer; }
.btn-approve:hover { background: #15803d; }
.btn-reject { background: #fee2e2; color: #dc2626; border: none; padding: 5px 11px; border-radius: 6px; font-size: 0.76rem; font-weight: 600; cursor: pointer; }
.btn-reject:hover { background: #fecaca; }

/* Modal styles */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.45); z-index: 9999; align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal-box { background: #fff; border-radius: var(--radius-lg); padding: 26px 28px; max-width: 440px; width: 92%; box-shadow: 0 20px 45px rgba(0,0,0,0.18); }
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title">Deadline Extension Requests</h1>
  <p class="page-sub">Review student requests for additional time, view submitted reasons, and approve or decline extensions.</p>

  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:18px">✓ <?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"  style="margin-bottom:18px">✕ <?= e($err) ?></div><?php endif; ?>

  <!-- Tabs -->
  <div class="filter-tabs">
    <a href="extensions.php?filter=all" class="tab-link <?= $filter === 'all' ? 'active' : '' ?>">
      All Requests <span class="tab-badge"><?= (int)$counts['total'] ?></span>
    </a>
    <a href="extensions.php?filter=pending" class="tab-link <?= $filter === 'pending' ? 'active' : '' ?>">
      Pending Review <span class="tab-badge <?= $counts['pending_count'] > 0 ? 'badge-urgent' : '' ?>"><?= (int)$counts['pending_count'] ?></span>
    </a>
    <a href="extensions.php?filter=approved" class="tab-link <?= $filter === 'approved' ? 'active' : '' ?>">
      Approved <span class="tab-badge"><?= (int)$counts['approved_count'] ?></span>
    </a>
    <a href="extensions.php?filter=rejected" class="tab-link <?= $filter === 'rejected' ? 'active' : '' ?>">
      Rejected <span class="tab-badge"><?= (int)$counts['rejected_count'] ?></span>
    </a>
  </div>

  <?php if (empty($requests)): ?>
    <div style="text-align:center;padding:56px 20px;background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);color:var(--text-muted)">
      <div style="font-size:2.8rem;margin-bottom:12px">⏱️</div>
      <h3 style="color:var(--primary);margin-bottom:6px">No Extension Requests Found</h3>
      <p style="font-size:0.9rem">There are no deadline extension requests matching this filter.</p>
    </div>
  <?php else: ?>
    <table class="req-table">
      <thead>
        <tr>
          <th>Student & Classroom</th>
          <th>Task & Reason</th>
          <th>Scope</th>
          <th>Original Due</th>
          <th>Requested Due</th>
          <th>Status & Decision</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
          <tr>
            <td>
              <strong style="color:var(--primary)"><?= e($r['student_name']) ?></strong>
              <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px"><?= e($r['student_email']) ?></div>
              <div style="font-size:0.78rem;color:var(--text-main);margin-top:4px">
                🏫 <?= e($r['classroom_name']) ?><?= $r['classroom_section'] ? ' (' . e($r['classroom_section']) . ')' : '' ?>
              </div>
              <div style="font-size:0.7rem;color:var(--text-light);margin-top:4px">
                Submitted <?= date('M d, Y · h:i A', strtotime($r['created_at'])) ?>
              </div>
            </td>
            <td>
              <strong style="color:var(--primary)"><?= e($r['task_title']) ?></strong>
              <div style="font-size:0.8rem;color:#475569;margin-top:6px;line-height:1.45;background:#f9fbf9;padding:8px 10px;border-radius:6px">
                <strong>Reason:</strong> <?= nl2br(e($r['reason'])) ?>
              </div>
              <?php if (!empty($r['teacher_comment'])): ?>
                <div style="font-size:0.78rem;color:var(--text-main);margin-top:6px;padding:6px 8px;background:#f0f7f3;border-left:3px solid var(--primary);border-radius:0 4px 4px 0">
                  <strong>Decision Note:</strong> <?= nl2br(e($r['teacher_comment'])) ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size:0.72rem;padding:3px 7px;border-radius:4px;font-weight:600;background:<?= $r['request_type'] === 'group' ? '#ede9fe' : '#e0f2fe' ?>;color:<?= $r['request_type'] === 'group' ? '#6d28d9' : '#0369a1' ?>">
                <?= $r['request_type'] === 'group' ? '👥 Entire Class' : '👤 Individual' ?>
              </span>
            </td>
            <td style="color:var(--text-muted);font-size:0.84rem">
              <?= $r['original_deadline'] ? date('M d, Y', strtotime($r['original_deadline'])) : '—' ?>
            </td>
            <td>
              <strong style="color:var(--primary);font-size:0.88rem"><?= date('M d, Y', strtotime($r['requested_deadline'])) ?></strong>
            </td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <span class="status-badge status-pending" style="margin-bottom:8px">⏳ Pending Review</span>
                <div class="action-btns">
                  <button type="button" class="btn-approve" onclick="openDecisionModal('approve', <?= $r['id'] ?>, '<?= e(addslashes($r['student_name'])) ?>', '<?= e(addslashes($r['task_title'])) ?>', '<?= $r['requested_deadline'] ?>')">
                    ✓ Approve
                  </button>
                  <button type="button" class="btn-reject" onclick="openDecisionModal('reject', <?= $r['id'] ?>, '<?= e(addslashes($r['student_name'])) ?>', '<?= e(addslashes($r['task_title'])) ?>', '<?= $r['requested_deadline'] ?>')">
                    ✕ Reject
                  </button>
                </div>
              <?php elseif ($r['status'] === 'approved'): ?>
                <span class="status-badge status-approved">✅ Approved</span>
                <div style="font-size:0.7rem;color:var(--text-light);margin-top:4px">
                  <?= $r['reviewed_at'] ? date('M d, Y', strtotime($r['reviewed_at'])) : '' ?>
                </div>
              <?php else: ?>
                <span class="status-badge status-rejected">❌ Rejected</span>
                <div style="font-size:0.7rem;color:var(--text-light);margin-top:4px">
                  <?= $r['reviewed_at'] ? date('M d, Y', strtotime($r['reviewed_at'])) : '' ?>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</div>
</div>

<!-- Decision Confirmation Modal -->
<div class="modal-overlay" id="decisionModal">
  <div class="modal-box">
    <div class="modal-title" id="decisionTitle">Approve Extension</div>
    <div class="modal-sub" id="decisionSub">Confirm your decision</div>
    <form method="POST" action="extensions.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" id="decisionAction" value="approve">
      <input type="hidden" name="request_id" id="decisionRequestId" value="0">

      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Note for Student (Optional):</label>
        <textarea name="teacher_comment" id="decisionComment" class="form-control" style="padding:10px 12px;min-height:70px" placeholder="Add an optional feedback or instructions note for the student..."></textarea>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn btn-outline btn-sm" onclick="closeDecisionModal()">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm" id="decisionSubmitBtn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDecisionModal(action, reqId, studentName, taskTitle, requestedDate) {
  document.getElementById('decisionAction').value = action;
  document.getElementById('decisionRequestId').value = reqId;
  const isApprove = (action === 'approve');
  document.getElementById('decisionTitle').textContent = isApprove ? 'Approve Deadline Extension' : 'Reject Deadline Extension';
  document.getElementById('decisionSub').textContent = (isApprove ? 'Extending deadline to ' + requestedDate : 'Keeping original deadline') + ' for ' + studentName + ' (' + taskTitle + ')';
  document.getElementById('decisionSubmitBtn').textContent = isApprove ? '✓ Approve & Update Deadline' : '✕ Reject Request';
  document.getElementById('decisionSubmitBtn').style.background = isApprove ? '#16a34a' : '#dc2626';
  document.getElementById('decisionModal').classList.add('active');
}
function closeDecisionModal() {
  document.getElementById('decisionModal').classList.remove('active');
}
window.addEventListener('click', (e) => {
  const modal = document.getElementById('decisionModal');
  if (e.target === modal) closeDecisionModal();
});
</script>
<script src="script.js"></script>
</body>
</html>
