<?php
/**
 * CommitTrack - Classroom Management (Admin)
 * Create classrooms, generate invite codes, view members, delete.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_admin();

$adminId = (int)$_SESSION['user_id'];
$userName    = $_SESSION['user_name'] ?? 'Instructor';
$userEmail   = $_SESSION['user_email'] ?? '';
$userRole    = 'admin';
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
$msg = $err = '';

/* ---- Handle POST actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name    = trim($_POST['name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $desc    = trim($_POST['description'] ?? '');

        if ($name === '') {
            $err = 'Classroom name is required.';
        } else {
            // Generate unique 12-char alphanumeric invite code
            do {
                $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
                $ck = $pdo->prepare('SELECT id FROM classrooms WHERE invite_code = ?');
                $ck->execute([$code]);
            } while ($ck->fetch());

            $ins = $pdo->prepare('INSERT INTO classrooms (admin_id, name, section, description, invite_code) VALUES (?,?,?,?,?)');
            $ins->execute([$adminId, $name, $section ?: null, $desc ?: null, $code]);
            $msg = 'Classroom "' . e($name) . '" created successfully!';
        }
    } elseif ($action === 'delete') {
        $cid = (int)($_POST['classroom_id'] ?? 0);
        // Verify ownership
        $own = $pdo->prepare('SELECT id FROM classrooms WHERE id=? AND admin_id=?');
        $own->execute([$cid, $adminId]);
        if ($own->fetch()) {
            $pdo->prepare('DELETE FROM classrooms WHERE id=?')->execute([$cid]);
            $msg = 'Classroom deleted.';
        } else {
            $err = 'Classroom not found or permission denied.';
        }
    }
}

/* ---- Fetch admin's classrooms with member + task counts ---- */
$rows = $pdo->prepare('
    SELECT c.*,
           COUNT(DISTINCT cm.student_id) AS member_count,
           COUNT(DISTINCT t.id)          AS task_count
    FROM   classrooms c
    LEFT JOIN classroom_members cm ON cm.classroom_id = c.id
    LEFT JOIN tasks              t  ON t.classroom_id  = c.id
    WHERE  c.admin_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
');
$rows->execute([$adminId]);
$classrooms = $rows->fetchAll();

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Classrooms – CommitTrack</title>
<link rel="stylesheet" href="style.css">
<style>
.page-wrap{max-width:980px;margin:0 auto;padding:32px 24px 60px}
.page-title{font-size:1.5rem;color:var(--primary);margin-bottom:4px}
.page-sub{color:var(--text-muted);font-size:.9rem;margin-bottom:28px}
.card-form{background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:26px 28px;margin-bottom:34px}
.card-form h2{font-size:1.1rem;margin-bottom:18px;color:var(--primary)}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.cls-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px}
.cls-card{background:#fff;border:1px solid var(--border-color);border-radius:var(--radius-lg);padding:20px 22px;display:flex;flex-direction:column;gap:10px}
.cls-name{font-size:1.08rem;font-weight:700;color:var(--primary)}
.cls-section{font-size:.82rem;color:var(--text-muted)}
.cls-stats{display:flex;gap:18px;font-size:.82rem;color:var(--text-muted)}
.cls-stat strong{color:var(--primary)}
.invite-row{display:flex;align-items:center;gap:8px;background:#f3f8f5;border:1px solid #d3e5dc;border-radius:8px;padding:7px 10px}
.invite-code{font-family:monospace;font-size:.82rem;color:var(--primary);flex:1;word-break:break-all}
.copy-btn{background:var(--primary);color:#fff;border:none;padding:5px 11px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;white-space:nowrap}
.copy-btn:hover{background:var(--primary-hover)}
.cls-actions{display:flex;gap:10px;margin-top:4px}
.danger-btn{background:#fee2e2;color:#dc2626;border:none;padding:6px 13px;border-radius:6px;font-size:.8rem;font-weight:600;cursor:pointer}
.danger-btn:hover{background:#fecaca}
.empty-state{text-align:center;padding:48px 20px;color:var(--text-muted)}
</style>
</head>
<body>
<div class="dashboard-layout">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="dash-main">
<?php include __DIR__ . '/partials/topbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title">Classrooms</h1>
  <p class="page-sub">Create and manage your classrooms. Share the invite link so students can join.</p>

  <?php if ($msg): ?><div class="alert alert-success" style="margin-bottom:18px">✓ <?= $msg ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"  style="margin-bottom:18px">✕ <?= e($err) ?></div><?php endif; ?>

  <!-- Create Classroom Form -->
  <div class="card-form">
    <h2>+ Create New Classroom</h2>
    <form method="POST" action="classrooms.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-row" style="margin-bottom:14px">
        <div class="form-group" style="margin:0">
          <label class="form-label">Classroom Name *</label>
          <div class="input-wrapper"><input name="name" class="form-control" style="padding-left:14px" placeholder="e.g. CSE - Section A" required></div>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Section / Batch</label>
          <div class="input-wrapper"><input name="section" class="form-control" style="padding-left:14px" placeholder="e.g. Batch 2025-B"></div>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:18px">
        <label class="form-label">Description (optional)</label>
        <textarea name="description" class="form-control" style="padding-left:14px;padding-top:10px;resize:vertical;min-height:60px" placeholder="Short description of this classroom…"></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Create Classroom →</button>
    </form>
  </div>

  <!-- Classrooms List -->
  <?php if (empty($classrooms)): ?>
    <div class="empty-state">
      <div style="font-size:2.5rem;margin-bottom:12px">🏫</div>
      <p>No classrooms yet. Create your first classroom above!</p>
    </div>
  <?php else: ?>
  <div class="cls-grid">
    <?php foreach ($classrooms as $c): 
      $inviteUrl = $baseUrl . 'join.php?code=' . urlencode($c['invite_code']);
    ?>
    <div class="cls-card">
      <div>
        <div class="cls-name"><?= e($c['name']) ?></div>
        <?php if ($c['section']): ?><div class="cls-section"><?= e($c['section']) ?></div><?php endif; ?>
      </div>
      <div class="cls-stats">
        <span><strong><?= $c['member_count'] ?></strong> Students</span>
        <span><strong><?= $c['task_count'] ?></strong> Tasks</span>
        <span style="margin-left:auto;font-size:.75rem"><?= date('M d, Y', strtotime($c['created_at'])) ?></span>
      </div>
      <!-- Invite Link -->
      <div class="invite-row">
        <span class="invite-code" id="iurl-<?= $c['id'] ?>"><?= e($inviteUrl) ?></span>
        <button class="copy-btn" onclick="copyInvite('iurl-<?= $c['id'] ?>',this)">Copy Link</button>
      </div>
      <div class="cls-actions">
        <a href="classroom_detail.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">View Members & Tasks</a>
        <form method="POST" onsubmit="return confirm('Delete this classroom and all its data?')">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="classroom_id" value="<?= $c['id'] ?>">
          <button class="danger-btn" type="submit">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</div>
</div>

<script>
function copyInvite(id, btn) {
  const text = document.getElementById(id).textContent;
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.textContent;
    btn.textContent = 'Copied!';
    btn.style.background = '#16a34a';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 2000);
  }).catch(() => {
    prompt('Copy this link:', text);
  });
}
</script>
<script src="script.js"></script>
</body>
</html>
