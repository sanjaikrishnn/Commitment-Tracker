<?php
// partials/topbar.php — shared topbar for all dashboard pages

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$userRole    = $userRole    ?? $_SESSION['user_role']    ?? 'student';
$userName    = $userName    ?? $_SESSION['user_name']    ?? 'User';
$userEmail   = $userEmail   ?? $_SESSION['user_email']   ?? '';
$userInitial = $userInitial ?? mb_strtoupper(mb_substr($userName, 0, 1));

$roleBadgeClass  = ($userRole === 'admin') ? 'role-badge-admin' : 'role-badge-student';
$placeholderText = ($userRole === 'admin') ? 'Search classrooms, students, tasks…' : 'Search tasks, classrooms…';
?>
<header class="dash-topbar">
  <div class="topbar-search">
    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input type="text" placeholder="<?= e($placeholderText) ?>" id="globalSearch">
  </div>
  <div class="topbar-actions">
    <div class="topbar-user-chip">
      <div class="avatar-circle" style="width:28px;height:28px;font-size:.75rem;<?= $userRole === 'admin' ? 'background:#7e22ce;' : '' ?>"><?= e($userInitial) ?></div>
      <span><?= e($userName) ?></span>
      <span class="role-badge <?= $roleBadgeClass ?>"><?= e(ucfirst($userRole)) ?></span>
    </div>
    <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
  </div>
</header>
