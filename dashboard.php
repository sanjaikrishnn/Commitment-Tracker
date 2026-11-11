<?php
/**
 * CommitTrack - dashboard.php
 * Real-data dashboards for Admin and Student, pulled live from MySQL.
 * Includes complete teacher analytics, classroom progress breakdowns, and priority sorting.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
require_auth();

$userId      = (int)$_SESSION['user_id'];
$userName    = $_SESSION['user_name'] ?? 'User';
$userEmail   = $_SESSION['user_email'] ?? '';
$userRole    = $_SESSION['user_role'] ?? 'student';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
$today       = date('Y-m-d');

/* ========== ADMIN DATA ========== */
if ($userRole === 'admin') {
    // 1. Total classrooms
    $r = $pdo->prepare('SELECT COUNT(*) FROM classrooms WHERE admin_id = ?');
    $r->execute([$userId]);
    $classroomCount = (int)$r->fetchColumn();

    // 2. Total students across admin's classrooms
    $r = $pdo->prepare('
        SELECT COUNT(DISTINCT cm.student_id) 
        FROM classroom_members cm 
        JOIN classrooms c ON c.id = cm.classroom_id 
        WHERE c.admin_id = ?
    ');
    $r->execute([$userId]);
    $studentCount = (int)$r->fetchColumn();

    // 3. Task counts & assignment statuses
    $r = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE admin_id = ?');
    $r->execute([$userId]);
    $taskCount = (int)$r->fetchColumn();

    $r = $pdo->prepare("
        SELECT 
            COUNT(ta.id) AS total_assigned,
            SUM(ta.status = 'completed') AS completed_count,
            SUM(ta.status = 'in_progress') AS in_progress_count,
            SUM(ta.status = 'pending') AS pending_count,
            SUM(COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND ta.status != 'completed') AS overdue_count,
            SUM(t.priority = 'high' AND ta.status != 'completed') AS high_pending_count
        FROM tasks t
        LEFT JOIN task_assignments ta ON ta.task_id = t.id
        WHERE t.admin_id = ?
    ");
    $r->execute([$userId]);
    $taskStats = $r->fetch();

    $totalAssigned   = (int)($taskStats['total_assigned'] ?? 0);
    $completedCount  = (int)($taskStats['completed_count'] ?? 0);
    $inProgressCount = (int)($taskStats['in_progress_count'] ?? 0);
    $pendingCount    = (int)($taskStats['pending_count'] ?? 0);
    $overdueCount    = (int)($taskStats['overdue_count'] ?? 0);
    $highPendingCount= (int)($taskStats['high_pending_count'] ?? 0);
    $overallPct      = $totalAssigned > 0 ? round(($completedCount / $totalAssigned) * 100) : 0;

    // 4. Pending extension requests count
    $r = $pdo->prepare('
        SELECT COUNT(*) FROM extension_requests er
        JOIN classrooms c ON c.id = er.classroom_id
        WHERE c.admin_id = ? AND er.status = "pending"
    ');
    $r->execute([$userId]);
    $pendingExtCount = (int)$r->fetchColumn();

    // 5. Classroom-wise completion percentages & counts
    $r = $pdo->prepare("
        SELECT c.*,
               COUNT(DISTINCT cm.student_id) AS member_count,
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
        ORDER BY c.created_at DESC
    ");
    $r->execute([$userId]);
    $classroomStats = $r->fetchAll();

    // 6. High-priority pending tasks (derived query avoids aggregate alias reference issues)
    $r = $pdo->prepare("
        SELECT * FROM (
            SELECT t.id, t.title, t.deadline, t.priority, c.name AS cls_name,
                   COUNT(ta.id) AS assigned,
                   COALESCE(SUM(ta.status = 'completed'), 0) AS done,
                   (t.deadline < CURDATE()) AS is_overdue
            FROM tasks t
            JOIN classrooms c ON c.id = t.classroom_id
            LEFT JOIN task_assignments ta ON ta.task_id = t.id
            WHERE t.admin_id = ? AND t.priority = 'high'
            GROUP BY t.id
        ) AS high_prio_sub
        WHERE assigned = 0 OR done < assigned
        ORDER BY deadline ASC
        LIMIT 5
    ");
    $r->execute([$userId]);
    $highPriorityTasks = $r->fetchAll();

    // 7. Students with delayed or incomplete tasks (derived query avoids aggregate alias reference issues)
    $r = $pdo->prepare("
        SELECT * FROM (
            SELECT u.id, u.name, u.email, c.name AS classroom_name,
                   COUNT(ta.id) AS total_tasks,
                   COALESCE(SUM(ta.status = 'completed'), 0) AS done_tasks,
                   COALESCE(SUM(ta.status != 'completed'), 0) AS incomplete_tasks,
                   COALESCE(SUM(COALESCE(ta.custom_deadline, t.deadline) < CURDATE() AND ta.status != 'completed'), 0) AS overdue_tasks
            FROM users u
            JOIN classroom_members cm ON cm.student_id = u.id
            JOIN classrooms c ON c.id = cm.classroom_id
            JOIN tasks t ON t.classroom_id = c.id
            JOIN task_assignments ta ON ta.task_id = t.id AND ta.student_id = u.id
            WHERE c.admin_id = ?
            GROUP BY u.id, c.id
        ) AS delayed_sub
        WHERE overdue_tasks > 0 OR incomplete_tasks > 0
        ORDER BY overdue_tasks DESC, incomplete_tasks DESC
        LIMIT 6
    ");
    $r->execute([$userId]);
    $delayedStudents = $r->fetchAll();
}

/* ========== STUDENT DATA ========== */
if ($userRole === 'student') {
    // 1. Classroom count
    $r = $pdo->prepare('SELECT COUNT(*) FROM classroom_members WHERE student_id = ?');
    $r->execute([$userId]);
    $classroomCount = (int)$r->fetchColumn();

    // 2. Task assignment stats
    $r = $pdo->prepare('SELECT COUNT(*) FROM task_assignments WHERE student_id = ?');
    $r->execute([$userId]);
    $totalTasks = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM task_assignments WHERE student_id = ? AND status = 'completed'");
    $r->execute([$userId]);
    $completedTasks = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM task_assignments WHERE student_id = ? AND status = 'in_progress'");
    $r->execute([$userId]);
    $inProgressTasks = (int)$r->fetchColumn();

    $r = $pdo->prepare("SELECT COUNT(*) FROM task_assignments WHERE student_id = ? AND status = 'pending'");
    $r->execute([$userId]);
    $pendingTasks = (int)$r->fetchColumn();

    $pct = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

    // 3. Pending extension requests count for student
    $r = $pdo->prepare('SELECT COUNT(*) FROM extension_requests WHERE student_id = ? AND status = "pending"');
    $r->execute([$userId]);
    $myPendingExtCount = (int)$r->fetchColumn();

    // 4. My tasks sorted by Phase 5 rules:
    // 1. Overdue high-priority
    // 2. Upcoming high-priority
    // 3. Medium-priority
    // 4. Low-priority
    // (within each, nearest deadline first)
    $r = $pdo->prepare("
        SELECT ta.id AS assignment_id, ta.status, ta.custom_deadline,
               t.title, t.deadline, t.priority, c.name AS cls_name,
               COALESCE(ta.custom_deadline, t.deadline) AS effective_deadline
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
        LIMIT 6
    ");
    $r->execute([$userId]);
    $myTasks = $r->fetchAll();

    // 5. Upcoming deadlines (non-completed, within 14 days)
    $r = $pdo->prepare("
        SELECT t.title, COALESCE(ta.custom_deadline, t.deadline) AS deadline, c.name AS cls_name, t.priority
        FROM task_assignments ta
        JOIN tasks t ON t.id = ta.task_id
        JOIN classrooms c ON c.id = t.classroom_id
        WHERE ta.student_id = ? AND ta.status != 'completed'
          AND COALESCE(ta.custom_deadline, t.deadline) IS NOT NULL
          AND COALESCE(ta.custom_deadline, t.deadline) >= ?
          AND COALESCE(ta.custom_deadline, t.deadline) <= DATE_ADD(?, INTERVAL 14 DAY)
        ORDER BY COALESCE(ta.custom_deadline, t.deadline) ASC
        LIMIT 5
    ");
    $r->execute([$userId, $today, $today]);
    $upcomingDeadlines = $r->fetchAll();

    // 6. My classrooms
    $r = $pdo->prepare('
        SELECT c.id, c.name, c.section, u.name AS admin_name,
               COUNT(DISTINCT ta.task_id) AS total_tasks,
               SUM(ta.status = "completed") AS completed_tasks
        FROM classroom_members cm
        JOIN classrooms c ON c.id = cm.classroom_id
        JOIN users u ON u.id = c.admin_id
        LEFT JOIN task_assignments ta ON ta.student_id = ? AND ta.task_id IN (SELECT id FROM tasks WHERE classroom_id = c.id)
        WHERE cm.student_id = ?
        GROUP BY c.id
        ORDER BY c.name
        LIMIT 4
    ');
    $r->execute([$userId, $userId]);
    $myClassrooms = $r->fetchAll();
}

$welcomeMsg = isset($_GET['welcome']) ? 'Welcome to CommitTrack, ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . '!' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= e(ucfirst($userRole)) ?> Dashboard – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.dash-content { padding: 28px 32px 56px; overflow-y: auto; }
.stat-cards-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 18px 20px; display: flex; align-items: center; gap: 14px; }
.stat-icon { width: 42px; height: 42px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-title { font-size: 0.74rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
.stat-value { font-size: 1.45rem; font-weight: 800; color: var(--primary); line-height: 1; }

.dashboard-grid-2col { display: grid; grid-template-columns: 1.6fr 1fr; gap: 24px; }
.card-widget { background: #fff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 22px 24px; margin-bottom: 24px; }
.widget-title { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 16px; }
.widget-link { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-decoration: none; }
.widget-link:hover { color: var(--primary); }

.task-row { display: flex; align-items: center; justify-content: space-between; padding: 11px 0; border-bottom: 1px solid #f1f5f3; gap: 10px; }
.task-row:last-child { border-bottom: none; padding-bottom: 0; }
.task-dot { width: 9px; height: 9px; border-radius: 99px; flex-shrink: 0; }
.dot-pending { background: #f59e0b; } .dot-in_progress { background: #3b82f6; } .dot-completed { background: #10b981; }

.pill-s { font-size: 0.7rem; padding: 3px 9px; border-radius: 99px; font-weight: 600; white-space: nowrap; }
.pill-pending { background: #fef3c7; color: #b45309; }
.pill-in_progress { background: #e0f2fe; color: #0369a1; }
.pill-completed { background: #dcfce7; color: #15803d; }

.prio-badge { font-size: 0.68rem; padding: 2px 7px; border-radius: 4px; font-weight: 700; text-transform: uppercase; }
.prio-high { background: #fee2e2; color: #dc2626; }
.prio-medium { background: #fef3c7; color: #b45309; }
.prio-low { background: #dcfce7; color: #16a34a; }

.prog-bar-bg { width: 80px; height: 7px; background: #eef2f0; border-radius: 99px; overflow: hidden; display: inline-block; }
.prog-bar-fill { height: 100%; background: var(--primary); border-radius: 99px; }
.overdue-tag { font-size: 0.68rem; background: #fee2e2; color: #dc2626; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: 700; }

.alert-banner { background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }

@media(max-width:960px){.stat-cards-grid{grid-template-columns:1fr 1fr}.dashboard-grid-2col{grid-template-columns:1fr}}
@media(max-width:600px){.stat-cards-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<main class="dash-content">

<?php if ($welcomeMsg): ?>
  <div class="alert alert-success" style="margin-bottom:18px">🎉 <?= e($welcomeMsg) ?></div>
<?php endif; ?>

<?php if ($userRole === 'admin'): /* ================= ADMIN DASHBOARD ================= */ ?>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:1.55rem;margin-bottom:3px">Instructor Dashboard 👋</h1>
      <p style="color:var(--text-muted);font-size:.9rem">Academic analytics, classroom performance, and task tracking.</p>
    </div>
    <div style="display:flex;gap:10px">
      <a href="reports.php" class="btn btn-outline btn-sm">📊 Generate Reports</a>
      <a href="classrooms.php" class="btn btn-primary btn-sm">+ Create Classroom</a>
    </div>
  </div>

  <?php if ($pendingExtCount > 0): ?>
    <div class="alert-banner">
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:20px">⏱️</span>
        <div>
          <strong style="color:#b91c1c;font-size:0.9rem">Action Required: <?= $pendingExtCount ?> Deadline Extension Request<?= $pendingExtCount > 1 ? 's' : '' ?></strong>
          <div style="font-size:0.78rem;color:#7f1d1d">Students have submitted requests for additional time on coursework.</div>
        </div>
      </div>
      <a href="extensions.php" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none">Review Requests →</a>
    </div>
  <?php endif; ?>

  <!-- Admin Analytics Cards -->
  <div class="stat-cards-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">🏫</div>
      <div>
        <div class="stat-title">Classrooms</div>
        <div class="stat-value"><?= $classroomCount ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#dcfce7;color:#16a34a">👩‍🎓</div>
      <div>
        <div class="stat-title">Enrolled Students</div>
        <div class="stat-value"><?= $studentCount ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;color:#7c3aed">📋</div>
      <div>
        <div class="stat-title">Total Tasks</div>
        <div class="stat-value"><?= $taskCount ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#b45309">🎯</div>
      <div>
        <div class="stat-title">Completion Rate</div>
        <div class="stat-value"><?= $overallPct ?>%</div>
      </div>
    </div>
  </div>

  <!-- Secondary Row Stats -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
    <div style="background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:14px 16px">
      <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase">Completed Tasks</div>
      <div style="font-size:1.3rem;font-weight:800;color:#16a34a;margin-top:2px"><?= $completedCount ?></div>
    </div>
    <div style="background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:14px 16px">
      <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase">In-Progress</div>
      <div style="font-size:1.3rem;font-weight:800;color:#0369a1;margin-top:2px"><?= $inProgressCount ?></div>
    </div>
    <div style="background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:14px 16px">
      <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase">Pending</div>
      <div style="font-size:1.3rem;font-weight:800;color:#b45309;margin-top:2px"><?= $pendingCount ?></div>
    </div>
    <div style="background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:14px 16px">
      <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase">Overdue Tasks</div>
      <div style="font-size:1.3rem;font-weight:800;color:<?= $overdueCount > 0 ? '#dc2626' : 'var(--primary)' ?>;margin-top:2px"><?= $overdueCount ?></div>
    </div>
  </div>

  <!-- 2-Col Grid -->
  <div class="dashboard-grid-2col">
    <!-- Left Col: Classroom Progress & High Priority Tasks -->
    <div>
      <!-- Classroom Completion Rates -->
      <div class="card-widget">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <div class="widget-title" style="margin:0">Classroom Completion Rates</div>
          <a href="classrooms.php" class="widget-link">Manage Classrooms →</a>
        </div>
        <?php if (empty($classroomStats)): ?>
          <p style="color:var(--text-muted);font-size:.88rem;text-align:center;padding:20px 0">No classrooms yet. <a href="classrooms.php">Create one →</a></p>
        <?php else: ?>
          <?php foreach ($classroomStats as $c):
            $clsPct = $c['assigned_count'] > 0 ? round(($c['completed_count'] / $c['assigned_count']) * 100) : 0;
          ?>
            <div style="padding:10px 0;border-bottom:1px solid #f1f5f3">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <div>
                  <strong style="color:var(--primary);font-size:0.88rem"><?= e($c['name']) ?></strong>
                  <span style="font-size:0.75rem;color:var(--text-muted)"><?= $c['section'] ? ' · ' . e($c['section']) : '' ?> (<?= $c['member_count'] ?> students)</span>
                </div>
                <div style="font-size:0.82rem;font-weight:700;color:var(--primary)"><?= $clsPct ?>%</div>
              </div>
              <div style="display:flex;align-items:center;gap:12px">
                <div style="flex:1;height:7px;background:#eef2f0;border-radius:99px;overflow:hidden">
                  <div style="width:<?= $clsPct ?>%;height:100%;background:var(--primary);border-radius:99px"></div>
                </div>
                <span style="font-size:0.74rem;color:var(--text-muted);white-space:nowrap"><?= $c['completed_count'] ?>/<?= $c['assigned_count'] ?> completed</span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- High Priority Incomplete Tasks -->
      <div class="card-widget">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <div class="widget-title" style="margin:0">High-Priority Incomplete Tasks</div>
          <a href="tasks.php" class="widget-link">View All Tasks →</a>
        </div>
        <?php if (empty($highPriorityTasks)): ?>
          <p style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:14px 0">🎉 All high-priority tasks are completed or up-to-date!</p>
        <?php else: ?>
          <?php foreach ($highPriorityTasks as $t): ?>
            <div class="task-row">
              <div>
                <div style="font-size:.88rem;font-weight:600;color:var(--primary)">
                  <?= e($t['title']) ?>
                  <?php if ($t['is_overdue']): ?><span class="overdue-tag">Overdue</span><?php endif; ?>
                </div>
                <div style="font-size:.74rem;color:var(--text-muted)">
                  <?= e($t['cls_name']) ?> · Due: <?= $t['deadline'] ? date('M d, Y', strtotime($t['deadline'])) : 'No deadline' ?>
                </div>
              </div>
              <span class="prio-badge prio-high">High Priority</span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Col: Delayed Students & Quick Links -->
    <div>
      <!-- Students Requiring Attention -->
      <div class="card-widget">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <div class="widget-title" style="margin:0">Students Needing Attention</div>
          <a href="students.php" class="widget-link">All Students →</a>
        </div>
        <?php if (empty($delayedStudents)): ?>
          <p style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:14px 0">No students are currently behind schedule.</p>
        <?php else: ?>
          <?php foreach ($delayedStudents as $s): ?>
            <div class="task-row">
              <div>
                <div style="font-size:.88rem;font-weight:600"><?= e($s['name']) ?></div>
                <div style="font-size:.74rem;color:var(--text-muted)"><?= e($s['classroom_name']) ?></div>
              </div>
              <div style="text-align:right">
                <?php if ($s['overdue_tasks'] > 0): ?>
                  <span style="font-size:.74rem;color:#dc2626;font-weight:700"><?= $s['overdue_tasks'] ?> Overdue</span>
                <?php else: ?>
                  <span style="font-size:.74rem;color:#b45309;font-weight:600"><?= $s['incomplete_tasks'] ?> Pending</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Quick Action Cards -->
      <div class="card-widget" style="padding:18px 20px">
        <div class="widget-title" style="margin-bottom:12px">Quick Actions</div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <a href="reports.php" class="btn btn-primary btn-sm" style="text-align:center">📊 One-Click Academic Reports</a>
          <a href="extensions.php" class="btn btn-secondary btn-sm" style="text-align:center">⏱️ Deadline Extension Requests (<?= $pendingExtCount ?>)</a>
          <a href="tasks.php" class="btn btn-outline btn-sm" style="text-align:center">+ Create & Assign Task</a>
        </div>
      </div>
    </div>
  </div>

<?php else: /* ================= STUDENT DASHBOARD ================= */ ?>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:16px">
    <div>
      <h1 style="font-size:1.55rem;margin-bottom:3px">Hello, <?= e($userName) ?>! 👋</h1>
      <p style="color:var(--text-muted);font-size:.92rem">Stay consistent, stay ahead with your coursework commitments.</p>
    </div>
    <!-- Overall progress -->
    <div style="background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-md);padding:12px 20px;display:flex;align-items:center;gap:16px">
      <div>
        <div style="font-size:.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase">Overall Progress</div>
        <div style="font-size:1.4rem;font-weight:800;color:var(--primary)"><?= $pct ?>%</div>
        <div style="width:100px;height:8px;background:#eef2f0;border-radius:99px;overflow:hidden;margin-top:4px">
          <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:99px"></div>
        </div>
      </div>
      <div style="font-size:1.8rem">🎯</div>
    </div>
  </div>

  <!-- Student Stats -->
  <div class="stat-cards-grid">
    <div class="stat-card">
      <div class="stat-icon" style="background:#e0f2fe;color:#0284c7">🏫</div>
      <div><div class="stat-title">My Classrooms</div><div class="stat-value"><?= $classroomCount ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;color:#6d28d9">📋</div>
      <div><div class="stat-title">Total Tasks</div><div class="stat-value"><?= $totalTasks ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#dcfce7;color:#16a34a">✅</div>
      <div><div class="stat-title">Completed</div><div class="stat-value" style="color:#16a34a"><?= $completedTasks ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fef3c7;color:#b45309">⏳</div>
      <div><div class="stat-title">Pending / In-Progress</div><div class="stat-value" style="color:#b45309"><?= $pendingTasks + $inProgressTasks ?></div></div>
    </div>
  </div>

  <!-- Student 2-Col -->
  <div class="dashboard-grid-2col">
    <!-- Tasks by Priority -->
    <div class="card-widget">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div class="widget-title" style="margin:0">Active Tasks (Sorted by Priority & Urgency)</div>
        <a href="my_tasks.php" class="widget-link">View All →</a>
      </div>
      <?php if (empty($myTasks)): ?>
        <p style="color:var(--text-muted);font-size:.88rem;text-align:center;padding:20px 0">No tasks assigned yet. <a href="my_classrooms.php">Join a classroom →</a></p>
      <?php else: ?>
        <?php foreach ($myTasks as $t):
          $effectiveDue = $t['effective_deadline'];
          $isOverdue = $effectiveDue && $effectiveDue < $today && $t['status'] !== 'completed';
        ?>
          <div class="task-row">
            <div style="display:flex;align-items:center;gap:10px;min-width:0">
              <div class="task-dot dot-<?= $t['status'] ?>"></div>
              <div style="min-width:0">
                <div style="font-size:.88rem;font-weight:600;color:var(--primary)">
                  <?= e($t['title']) ?>
                  <?php if ($isOverdue): ?><span class="overdue-tag">Overdue</span><?php endif; ?>
                  <?php if ($t['custom_deadline']): ?><span style="font-size:0.68rem;background:#dbeafe;color:#1d4ed8;padding:2px 5px;border-radius:4px;margin-left:4px">Extended</span><?php endif; ?>
                </div>
                <div style="font-size:.74rem;color:var(--text-muted)">
                  <?= e($t['cls_name']) ?> · Due: <?= $effectiveDue ? date('M d', strtotime($effectiveDue)) : 'No deadline' ?>
                  · <span class="prio-badge prio-<?= $t['priority'] ?>"><?= ucfirst($t['priority']) ?></span>
                </div>
              </div>
            </div>
            <span class="pill-s pill-<?= $t['status'] ?>"><?= $t['status'] === 'in_progress' ? 'In Progress' : ucfirst($t['status']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Deadlines & Classrooms -->
    <div style="display:flex;flex-direction:column;gap:20px">
      <!-- Upcoming Deadlines -->
      <div class="card-widget">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
          <div class="widget-title" style="margin:0">Upcoming Deadlines (14 Days)</div>
          <a href="my_tasks.php" class="widget-link">View All →</a>
        </div>
        <?php if (empty($upcomingDeadlines)): ?>
          <p style="color:var(--text-muted);font-size:.84rem;text-align:center;padding:10px 0">🎉 No pressing deadlines in the next 14 days!</p>
        <?php else: ?>
          <?php foreach ($upcomingDeadlines as $d): ?>
            <div class="task-row" style="padding:8px 0">
              <div>
                <div style="font-size:.86rem;font-weight:600"><?= e($d['title']) ?></div>
                <div style="font-size:.74rem;color:var(--text-muted)"><?= e($d['cls_name']) ?></div>
              </div>
              <div style="text-align:right">
                <span style="font-size:.78rem;color:#b45309;font-weight:700"><?= date('M d', strtotime($d['deadline'])) ?></span>
                <span class="prio-badge prio-<?= $d['priority'] ?>" style="display:block;margin-top:2px"><?= ucfirst($d['priority']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- My Classrooms Summary -->
      <?php if (!empty($myClassrooms)): ?>
      <div class="card-widget" style="padding:18px 20px">
        <div class="widget-title" style="margin-bottom:12px">My Classrooms</div>
        <?php foreach ($myClassrooms as $mc):
          $mPct = $mc['total_tasks'] > 0 ? round(($mc['completed_tasks'] / $mc['total_tasks']) * 100) : 0;
        ?>
          <div class="task-row" style="padding:8px 0">
            <div>
              <div style="font-size:.86rem;font-weight:600"><?= e($mc['name']) ?></div>
              <div style="font-size:.72rem;color:var(--text-muted)">Instructor: <?= e($mc['admin_name']) ?></div>
            </div>
            <div style="text-align:right">
              <span style="font-size:0.78rem;font-weight:700;color:var(--primary)"><?= $mPct ?>%</span>
              <span style="font-size:0.7rem;color:var(--text-muted);display:block"><?= (int)$mc['completed_tasks'] ?>/<?= $mc['total_tasks'] ?> tasks</span>
            </div>
          </div>
        <?php endforeach; ?>
        <a href="my_classrooms.php" class="widget-link" style="display:block;margin-top:10px">Manage Classrooms →</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>
</main>
</div>
</div>
<script src="script.js"></script>
</body>
</html>
