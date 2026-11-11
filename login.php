<?php
/**
 * CommitTrack - User Login Page
 * Authenticates users using PDO prepared statements and password_verify().
 * Protects against session fixation via session_regenerate_id(true).
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// If already authenticated, redirect directly to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/db.php';

$errors = [];
$successMessage = '';
$email = $_GET['email'] ?? '';

// Flash message checks
if (isset($_GET['registered'])) {
    $successMessage = 'Account created successfully! Please sign in with your credentials.';
} elseif (isset($_GET['logged_out'])) {
    $successMessage = 'You have safely signed out of CommitTrack.';
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $errors[] = 'Security token expired. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($password)) {
            $errors[] = 'Please enter your password.';
        }

        if (empty($errors)) {
            try {
                // Fetch user by email via prepared statement
                $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Prevent Session Fixation Attacks
                    session_regenerate_id(true);

                    // Store authenticated user session attributes
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();

                    // Generate a new CSRF token for the authenticated session
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    // Redirect to intended target or dashboard
                    $redirectUrl = $_SESSION['return_to'] ?? 'dashboard.php';
                    unset($_SESSION['return_to']);
                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    $errors[] = 'Invalid email address or password. Please check your credentials.';
                }
            } catch (PDOException $e) {
                error_log('Login Error: ' . $e->getMessage());
                $errors[] = 'A system error occurred. Please try again shortly.';
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
    <title>Welcome Back - CommitTrack Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

    <!-- Decorative Botanical Shapes -->
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
        <!-- Brand Header -->
        <div class="auth-header">
            <a href="index.php" class="brand-logo">
                <span class="brand-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </span>
                CommitTrack
            </a>
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Log in to your account to continue.</p>
        </div>

        <!-- Success Notification -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <div><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <!-- Error Notifications -->
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

        <!-- Login Form -->
        <form action="login.php" method="POST" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

            <!-- Email Address -->
            <div class="form-group">
                <label for="loginEmail" class="form-label">Email Address</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                    </svg>
                    <input type="email" 
                           id="loginEmail" 
                           name="email" 
                           class="form-control" 
                           placeholder="Email Address" 
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" 
                           required>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="loginPassword" class="form-label">Password</label>
                <div class="input-wrapper">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <input type="password" 
                           id="loginPassword" 
                           name="password" 
                           class="form-control has-toggle" 
                           placeholder="Password" 
                           required>
                    <button type="button" class="password-toggle-btn" data-target="loginPassword" aria-label="Toggle password visibility">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Remember me & Forgot Password -->
            <div class="auth-remember-row">
                <label class="remember-label">
                    <input type="checkbox" id="rememberMeCheckbox">
                    Remember me
                </label>
                <a href="javascript:void(0)" onclick="alert('For this project phase, please contact your administrator or re-create your account if you forgot your credentials.')" class="forgot-link">Forgot password?</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary btn-block">
                Login &rarr;
            </button>

            <!-- Divider OR -->
            <div class="auth-divider">
                <span>OR</span>
            </div>

            <!-- Secondary Sign Up Button -->
            <a href="signup.php" class="btn btn-secondary btn-block">
                Sign Up
            </a>
        </form>

        <!-- Footer -->
        <div class="auth-footer">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
