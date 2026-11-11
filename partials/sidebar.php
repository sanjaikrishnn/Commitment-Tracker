<?php
// partials/sidebar.php — shared sidebar for all dashboard pages

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$userRole    = $userRole    ?? $_SESSION['user_role']    ?? 'student';
$userName    = $userName    ?? $_SESSION['user_name']    ?? 'User';
$userEmail   = $userEmail   ?? $_SESSION['user_email']   ?? '';
$userInitial = $userInitial ?? mb_strtoupper(mb_substr($userName, 0, 1));
$userId      = $userId      ?? (int)($_SESSION['user_id'] ?? 0);

$currentPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('navItem')) {
    function navItem($label, $href, $icon, $current, $badge = 0) {
        $active = (basename($href) === $current || basename($href, '.php') === basename($current, '.php')) ? ' active' : '';
        $badgeHtml = ($badge > 0) ? "<span class=\"nav-badge\">{$badge}</span>" : '';
        return "<a href=\"{$href}\" class=\"nav-item{$active}\">{$icon}<span style=\"flex:1\">" . e($label) . "</span>{$badgeHtml}</a>";
    }
}

// Fetch pending extensions count if database is connected
$pendingExtCount = 0;
if (isset($pdo) && $userId > 0) {
    try {
        if ($userRole === 'admin') {
            $stmtP = $pdo->prepare('
                SELECT COUNT(*) FROM extension_requests er
                JOIN classrooms c ON c.id = er.classroom_id
                WHERE c.admin_id = ? AND er.status = "pending"
            ');
            $stmtP->execute([$userId]);
            $pendingExtCount = (int)$stmtP->fetchColumn();
        } else {
            $stmtP = $pdo->prepare('
                SELECT COUNT(*) FROM extension_requests
                WHERE student_id = ? AND status = "pending"
            ');
            $stmtP->execute([$userId]);
            $pendingExtCount = (int)$stmtP->fetchColumn();
        }
    } catch (Exception $e) {
        $pendingExtCount = 0;
    }
}

$iconDash   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
$iconClass  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$iconTask   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
$iconExt    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
$iconStud   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>';
$iconReport = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
$iconLogout = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>';
?>
<aside class="dash-sidebar">
  <div class="sidebar-header">
    <a href="dashboard.php" class="brand-logo">
      <span class="brand-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </span>
      CommitTrack
    </a>
  </div>

  <nav class="sidebar-nav">
    <?= navItem('Dashboard', 'dashboard.php', $iconDash, $currentPage) ?>
    <?php if ($userRole === 'admin'): ?>
      <?= navItem('Classrooms', 'classrooms.php', $iconClass, $currentPage) ?>
      <?= navItem('Tasks', 'tasks.php', $iconTask, $currentPage) ?>
      <?= navItem('Extensions', 'extensions.php', $iconExt, $currentPage, $pendingExtCount) ?>
      <?= navItem('Students', 'students.php', $iconStud, $currentPage) ?>
      <?= navItem('Reports & Analytics', 'reports.php', $iconReport, $currentPage) ?>
    <?php else: ?>
      <?= navItem('My Tasks', 'my_tasks.php', $iconTask, $currentPage) ?>
      <?= navItem('Classrooms', 'my_classrooms.php', $iconClass, $currentPage) ?>
      <?= navItem('Extensions', 'my_extensions.php', $iconExt, $currentPage, $pendingExtCount) ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-profile-badge">
      <div class="avatar-circle <?= $userRole === 'admin' ? 'avatar-purple' : '' ?>"><?= e($userInitial) ?></div>
      <div class="user-info-text">
        <span class="user-display-name"><?= e($userName) ?></span>
        <span class="user-display-email"><?= e($userEmail) ?></span>
      </div>
    </div>
    <a href="logout.php" title="Sign out" style="color:var(--text-light);padding:4px"><?= $iconLogout ?></a>
  </div>
</aside>
