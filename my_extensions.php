<?php
/**
 * CommitTrack - my_extensions.php (Student)
 * View all deadline extension requests submitted by the student, along with
 * status (Pending, Approved, Rejected) and teacher feedback notes.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_student();

$studentId  = (int)$_SESSION['user_id'];
$userName   = $_SESSION['user_name'] ?? 'Student';
$userEmail  = $_SESSION['user_email'] ?? '';
$userRole   = 'student';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Fetch all extension requests for this student or group requests in their classrooms
$stmt = $pdo->prepare('
    SELECT er.*, t.title AS task_title, t.priority AS task_priority,
           c.name AS classroom_name, c.section AS classroom_section,
           u.name AS teacher_name
    FROM extension_requests er
    JOIN tasks t ON t.id = er.task_id
    JOIN classrooms c ON c.id = er.classroom_id
    JOIN users u ON u.id = c.admin_id
    WHERE er.student_id = ?
       OR (er.classroom_id IN (SELECT classroom_id FROM classroom_members WHERE student_id = ?) AND er.request_type = "group")
    ORDER BY er.created_at DESC
');
$stmt->execute([$studentId, $studentId]);
$requests = $stmt->fetchAll();

$pendingCount  = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
$rejectedCount = count(array_filter($requests, fn($r) => $r['status'] === 'rejected'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Extension Requests – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap { max-width: 1040px; margin: 0 auto; padding: 30px 24px 60px; }
.page-title { font-size: 1.5rem; color: var(--primary); margin-bottom: 4px; }
.page-sub { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px; }
.summary-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 26px; }
.sum-card { background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px 18px; }
.sum-label { font-size: 0.74rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
.sum-val { font-size: 1.45rem; font-weight: 800; color: var(--primary); }

.req-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; background: #fff; border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-color); }
.req-table th { text-align: left; padding: 11px 14px; color: var(--text-muted); font-weight: 600; border-bottom: 2px solid var(--border-color); font-size: 0.76rem; text-transform: uppercase; background: #f9faf8; }
.req-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f3; vertical-align: top; }
.req-table tr:last-child td { border-bottom: none; }

.status-badge { font-size: 0.72rem; padding: 3px 9px; border-radius: 99px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
.status-pending { background: #fef3c7; color: #b45309; }
.status-approved { background: #dcfce7; color: #15803d; }
.status-rejected { background: #fee2e2; color: #dc2626; }

.type-pill { font-size: 0.7rem; padding: 2px 7px; border-radius: 4px; font-weight: 600; background: #f0f3f1; color: var(--text-main); }
.teacher-note { margin-top: 6px; padding: 8px 10px; background: #fbfcfb; border-left: 3px solid var(--primary); border-radius: 0 6px 6px 0; font-size: 0.8rem; color: var(--text-main); }
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
    <h1 class="page-title">Deadline Extension Requests</h1>
    <a href="my_tasks.php" class="btn btn-secondary btn-sm">← Back to My Tasks</a>
  </div>
  <p class="page-sub">Track individual and classroom deadline extension requests and instructor decisions.</p>

  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:18px">✓ <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger" style="margin-bottom:18px">✕ <?= e($err) ?></div><?php endif; ?>

  <div class="summary-bar">
    <div class="sum-card">
      <div class="sum-label">Pending Review</div>
      <div class="sum-val" style="color:#b45309"><?= $pendingCount ?></div>
    </div>
    <div class="sum-card">
      <div class="sum-label">Approved</div>
      <div class="sum-val" style="color:#16a34a"><?= $approvedCount ?></div>
    </div>
    <div class="sum-card">
      <div class="sum-label">Rejected</div>
      <div class="sum-val" style="color:#dc2626"><?= $rejectedCount ?></div>
    </div>
  </div>

  <?php if (empty($requests)): ?>
    <div style="text-align:center;padding:56px 20px;background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);color:var(--text-muted)">
      <div style="font-size:2.8rem;margin-bottom:12px">⏱️</div>
      <h3 style="color:var(--primary);margin-bottom:6px">No Extension Requests</h3>
      <p style="font-size:0.9rem">You haven't requested any deadline extensions. You can request extensions from your tasks list.</p>
      <a href="my_tasks.php" class="btn btn-primary btn-sm" style="margin-top:16px">View My Tasks →</a>
    </div>
  <?php else: ?>
    <table class="req-table">
      <thead>
        <tr>
          <th>Task & Classroom</th>
          <th>Type</th>
          <th>Original Due</th>
          <th>Requested Due</th>
          <th>Status & Feedback</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
          <tr>
            <td>
              <strong style="color:var(--primary)"><?= e($r['task_title']) ?></strong>
              <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px">
                🏫 <?= e($r['classroom_name']) ?><?= $r['classroom_section'] ? ' (' . e($r['classroom_section']) . ')' : '' ?>
                · Teacher: <?= e($r['teacher_name']) ?>
              </div>
              <div style="font-size:0.8rem;color:#475569;margin-top:6px;line-height:1.4">
                <strong>Reason:</strong> <?= nl2br(e($r['reason'])) ?>
              </div>
              <div style="font-size:0.72rem;color:var(--text-light);margin-top:4px">
                Submitted <?= date('M d, Y · h:i A', strtotime($r['created_at'])) ?>
              </div>
            </td>
            <td>
              <span class="type-pill">
                <?= $r['request_type'] === 'group' ? '👥 Group' : '👤 Individual' ?>
              </span>
            </td>
            <td style="color:var(--text-muted);font-size:0.84rem">
              <?= $r['original_deadline'] ? date('M d, Y', strtotime($r['original_deadline'])) : '—' ?>
            </td>
            <td>
              <strong style="color:var(--primary);font-size:0.88rem"><?= date('M d, Y', strtotime($r['requested_deadline'])) ?></strong>
            </td>
            <td>
              <span class="status-badge status-<?= $r['status'] ?>">
                <?php if ($r['status'] === 'approved'): ?>✅ Approved<?php elseif ($r['status'] === 'rejected'): ?>❌ Rejected<?php else: ?>⏳ Pending Review<?php endif; ?>
              </span>
              <?php if (!empty($r['teacher_comment'])): ?>
                <div class="teacher-note">
                  <strong style="color:var(--primary);font-size:0.75rem;display:block;margin-bottom:2px">Instructor Note:</strong>
                  <?= nl2br(e($r['teacher_comment'])) ?>
                </div>
              <?php endif; ?>
              <?php if ($r['reviewed_at']): ?>
                <div style="font-size:0.7rem;color:var(--text-light);margin-top:4px">
                  Reviewed <?= date('M d, Y', strtotime($r['reviewed_at'])) ?>
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
<script src="script.js"></script>
</body>
</html>
