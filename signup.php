<?php
/**
 * CommitTrack - User Registration Page
 * Allows selecting Student or Admin role (with controlled project setup PIN for Admin),
 * validates inputs, prevents duplicate emails, and auto-logs in to the dashboard upon completion.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/db.php';

$errors = [];
$name = '';
$email = '';
$selectedRole = 'student';

// Handle CSRF token creation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors[] = 'Security validation failed (CSRF). Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $selectedRole = trim($_POST['role'] ?? 'student');
        $adminPasscode = trim($_POST['admin_passcode'] ?? '');

        // Validation
        if (empty($name)) {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = 'Name must be between 2 and 100 characters.';
        }

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }

        if (empty($confirmPassword)) {
            $errors[] = 'Please confirm your password.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match. Please re-enter.';
        }

        // Role verification
        if ($selectedRole === 'admin') {
            // Controlled Admin authorization check using secure configuration PIN
            if (hash_equals(get_admin_setup_pin(), (string)$adminPasscode)) {
                $role = 'admin';
            } else {
                $errors[] = 'Invalid Admin Setup PIN. Please check the security PIN configured in .env or select Student.';
            }
        } else {
            $role = 'student';
        }

        // Check if email already exists
        if (empty($errors)) {
            try {
                $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    $errors[] = 'An account with this email address already exists. Please login instead.';
                } else {
                    // Hash password using PHP standard BCRYPT
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user record
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
                    );
                    $insertStmt->execute([$name, $email, $passwordHash, $role]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // Automatically authenticate and redirect to dashboard
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = $role;
                    $_SESSION['login_time'] = time();
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    $redirectUrl = $_SESSION['return_to'] ?? 'dashboard.php?welcome=1';
                    unset($_SESSION['return_to']);
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Registration Error: ' . $e->getMessage());
                $errors[] = 'A system error occurred while processing registration. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account - CommitTrack</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

    <!-- Decorative Botanical Corner SVGs -->
    <svg class="botanical-decor decor-top-left" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M-20 -20 Q80 40 40 140 Q10 200 -20 200 Z" fill="#d8ebe1" />
        <path d="M-40 60 Q120 100 80 200" stroke="#bfe1d0" stroke-width="3" stroke-linecap="round" />
    </svg>
    <svg class="botanical-decor decor-bottom-right" viewBox="0 0 240 240" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M260 260 Q140 180 180 80 Q210 20 260 20 Z" fill="#dcefe5" />
        <path d="M260 140 Q150 120 180 0" stroke="#bde0cf" stroke-width="3" stroke-linecap="round" />
        <circle cx="210" cy="90" r="14" fill="#a7d5be" opacity="0.6"/>
        <circle cx="170" cy="140" r="10" fill="#a7d5be" opacity="0.4"/>
    </svg>

    <div class="auth-container">
        <!-- Logo -->
        <div class="auth-header">
            <a href="index.php" class="brand-logo">
                <span class="brand-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </span>
                CommitTrack
            </a>
            <h1 class="auth-title">Create Your Account</h1>
            <p class="auth-subtitle">Join us and start tracking your commitments.</p>
        </div>

        <!-- Feedback Alert Container -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div id="clientErrorContainer" class="alert alert-danger" style="display: none;" role="alert"></div>

        <!-- Registration Form -->
        <form action="signup.php" method="POST" id="signupForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Full Name -->
            <div class="form-group">
                <label for="signupName" class="form-label">Full Name</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <input type="text" 
                           id="signupName" 
                           name="name" 
                           class="form-control" 
                           placeholder="Full Name" 
                           value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" 
                           required>
                </div>
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <label for="signupEmail" class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                    <input type="email" 
                           id="signupEmail" 
                           name="email" 
                           class="form-control" 
                           placeholder="Email Address" 
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" 
                           required>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="signupPassword" class="form-label">Password</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" 
                           id="signupPassword" 
                           name="password" 
                           class="form-control has-toggle" 
                           placeholder="Password (min 6 characters)" 
                           required>
                    <button type="button" class="password-toggle-btn" data-target="signupPassword" aria-label="Toggle password visibility">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
                <label for="signupConfirmPassword" class="form-label">Confirm Password</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" 
                           id="signupConfirmPassword" 
                           name="confirm_password" 
                           class="form-control has-toggle" 
                           placeholder="Re-enter your password" 
                           required>
                    <button type="button" class="password-toggle-btn" data-target="signupConfirmPassword" aria-label="Toggle password visibility">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Role Selector ("I am a") matching UI reference -->
            <div class="role-select-section">
                <span class="role-select-label">I am a</span>
                <div class="role-cards-grid">
                    <!-- Student Card Option -->
                    <label class="role-card <?= $selectedRole === 'student' ? 'active' : '' ?>" id="roleStudentCard" for="roleRadioStudent">
                        <input type="radio" 
                               name="role" 
                               id="roleRadioStudent" 
                               value="student" 
                               <?= $selectedRole === 'student' ? 'checked' : '' ?> 
                               style="position: absolute; opacity: 0; pointer-events: none;">
                        <span class="role-card-badge" id="studentBadge" style="<?= $selectedRole === 'student' ? '' : 'display:none;' ?>">Selected</span>
                        <div class="role-card-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                            </svg>
                        </div>
                        <div class="role-card-title">Student</div>
                        <div class="role-card-desc">Track your tasks and progress</div>
                    </label>

                    <!-- Admin Card Option -->
                    <label class="role-card <?= $selectedRole === 'admin' ? 'active' : '' ?>" id="roleAdminCard" for="roleRadioAdmin">
                        <input type="radio" 
                               name="role" 
                               id="roleRadioAdmin" 
                               value="admin" 
                               <?= $selectedRole === 'admin' ? 'checked' : '' ?> 
                               style="position: absolute; opacity: 0; pointer-events: none;">
                        <span class="role-card-badge" id="adminBadge" style="<?= $selectedRole === 'admin' ? '' : 'display:none;' ?>">Selected</span>
                        <div class="role-card-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                        </div>
                        <div class="role-card-title">Admin</div>
                        <div class="role-card-desc">Manage classes, assign tasks</div>
                    </label>
                </div>

                <!-- Admin Security Authorization PIN (smoothly toggled when Admin is selected) -->
                <div id="adminCodeSection" style="<?= $selectedRole === 'admin' ? '' : 'display:none;' ?>; margin-top: 14px; background: #f3f8f5; padding: 14px; border-radius: 12px; border: 1px solid #d3e5dc;">
                    <label for="adminPasscode" class="form-label" style="font-size: 0.82rem; color: #173f35; font-weight: 700;">
                        Admin Authorization PIN
                    </label>
                    <input type="password" 
                           id="adminPasscode" 
                           name="admin_passcode" 
                           class="form-control" 
                           style="padding-left: 14px; background: #ffffff;" 
                           placeholder="Enter Admin Setup PIN">
                    <small style="font-size: 0.74rem; color: #52685d; display: block; margin-top: 5px;">
                        This is separate from your account password. If <code>COMMITTRACK_ADMIN_PIN</code> is not set in <code>.env</code>, use the local default PIN <code>commit2026</code>.
                    </small>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary btn-block" id="signupSubmitBtn" style="margin-top: 10px;">
                Sign Up &rarr;
            </button>
        </form>

        <!-- Footer Link -->
        <div class="auth-footer">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
