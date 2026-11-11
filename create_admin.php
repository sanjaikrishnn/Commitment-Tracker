<?php
/**
 * CommitTrack - Controlled Admin Account Provisioning Procedure
 *
 * Security Requirement:
 * Public self-registration (signup.php) is strictly restricted to Student accounts.
 * Administrator accounts can only be provisioned through this controlled procedure:
 * 1. CLI Execution:  `c:\xampp\php\php.exe create_admin.php`
 * 2. Or Localhost setup via browser with security confirmation.
 */

require_once __DIR__ . '/db.php';

$message = '';
$messageType = '';

// Check if running via Command Line Interface (CLI)
if (php_sapi_name() === 'cli') {
    echo "====================================================\n";
    echo " CommitTrack - Controlled Admin Provisioning (CLI) \n";
    echo "====================================================\n";

    echo "Enter Admin Full Name [Administrator]: ";
    $name = trim(fgets(STDIN));
    if ($name === '') $name = 'Administrator';

    echo "Enter Admin Email [admin@committrack.com]: ";
    $email = trim(fgets(STDIN));
    if ($email === '') $email = 'admin@committrack.com';

    echo "Enter Admin Password [Admin@123]: ";
    $password = trim(fgets(STDIN));
    if ($password === '') $password = 'Admin@123';

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('
            INSERT INTO users (name, email, password_hash, role)
            VALUES (?, ?, ?, "admin")
            ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = "admin"
        ');
        $stmt->execute([$name, $email, $hash]);

        echo "\n[SUCCESS] Admin account provisioned successfully!\n";
        echo "Email: $email\n";
        echo "Role:  admin\n";
        echo "You may now sign in at http://localhost/committrack/login.php\n";
    } catch (Exception $e) {
        echo "\n[ERROR] " . $e->getMessage() . "\n";
    }
    exit;
}

// Localhost Browser Interface for Controlled Setup
// Protect from non-localhost access
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = in_array($remoteIp, ['127.0.0.1', '::1', 'localhost']);

if (!$isLocal) {
    http_response_code(403);
    die('Forbidden: Admin provisioning is only accessible on the local server.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $secretPin = trim($_POST['setup_pin'] ?? '');

    // Controlled safeguard: requires the configured Admin Setup PIN
    if (!hash_equals(get_admin_setup_pin(), (string)$secretPin)) {
        $message = 'Invalid Setup Security PIN. Please enter the secure PIN configured in .env.';
        $messageType = 'danger';
    } elseif (empty($name) || empty($email) || empty($password)) {
        $message = 'All fields are required.';
        $messageType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please provide a valid email address.';
        $messageType = 'danger';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'danger';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO users (name, email, password_hash, role)
                VALUES (?, ?, ?, "admin")
                ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = "admin"
            ');
            $stmt->execute([$name, $email, $hash]);

            $message = "Admin account for '$email' provisioned successfully with Admin privileges! You can now log in.";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controlled Admin Provisioning - CommitTrack</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <div class="auth-container" style="max-width: 520px;">
        <div class="auth-header">
            <a href="index.php" class="brand-logo">
                <span class="brand-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </span>
                CommitTrack
            </a>
            <h1 class="auth-title" style="font-size: 1.35rem; margin-top: 14px;">Controlled Admin Setup</h1>
            <p class="auth-subtitle">Provision an authorized Administrator account.</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>" role="alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="create_admin.php">
            <div class="form-group">
                <label class="form-label">Admin Full Name</label>
                <input type="text" name="name" class="form-control" style="padding-left: 14px;" value="College Admin" required>
            </div>

            <div class="form-group">
                <label class="form-label">Admin Email Address</label>
                <input type="email" name="email" class="form-control" style="padding-left: 14px;" value="admin@committrack.com" required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" style="padding-left: 14px;" placeholder="Admin password" required>
            </div>

            <div class="form-group">
                <label class="form-label">Setup Security PIN</label>
                <input type="password" name="setup_pin" class="form-control" style="padding-left: 14px;" placeholder="Enter configured Admin Setup PIN" required>
                <small class="text-muted" style="font-size: 0.75rem; display: block; margin-top: 4px;">
                    Requires the secure administrator PIN configured in <code>.env</code>.
                </small>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Provision Admin Account &rarr;
            </button>
        </form>

        <div class="auth-footer" style="display: flex; justify-content: space-between; font-size: 0.8rem;">
            <a href="login.php">&larr; Back to Login</a>
            <a href="index.php">Landing Page</a>
        </div>
    </div>
</body>
</html>
