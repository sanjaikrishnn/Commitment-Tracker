<?php
/**
 * CommitTrack - db.php (shared helpers appended)
 */

// Load .env file into getenv/$_ENV if present
if (file_exists(__DIR__ . '/.env')) {
    $envLines = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
    if (is_array($envLines)) {
        foreach ($envLines as $k => $v) {
            if (getenv($k) === false) {
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT') ?: '3306');
define('DB_NAME',    getenv('DB_NAME') ?: 'committrack');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get configured Admin Setup Security PIN.
 * Reads from environment variable or .env configuration.
 */
function get_admin_setup_pin(): string {
    $pin = getenv('COMMITTRACK_ADMIN_PIN');
    if ($pin !== false && $pin !== '') {
        return (string)$pin;
    }
    return 'commit2026';
}

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('CommitTrack DB Error: ' . $e->getMessage());
            if (php_sapi_name() !== 'cli') {
                http_response_code(500);
                echo '<!DOCTYPE html><html><head><title>DB Error</title>
                <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f7faf8}
                .box{background:#fff;padding:32px;border-radius:16px;border:1px solid #e3ebe6;max-width:520px;text-align:center}
                h2{color:#173f35}p{color:#5e7268}code{background:#f0f7f3;padding:2px 6px;border-radius:4px}</style></head>
                <body><div class="box"><h2>Database Not Connected</h2>
                <p>Start <strong>MySQL</strong> in XAMPP Control Panel, then import <code>database.sql</code> and <code>migration.sql</code> via phpMyAdmin.</p></div></body></html>';
                exit;
            }
            throw new Exception('DB connection failed.');
        }
    }
    return $pdo;
}

/** HTML-escape helper */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Generate CSRF token */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verify CSRF and die on failure */
function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF validation failed. Please go back and try again.');
    }
}

/** Require any authenticated session or redirect to login */
function require_auth(): void {
    if (!isset($_SESSION['user_id'])) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($uri && !str_contains($uri, 'login.php') && !str_contains($uri, 'logout.php') && !str_contains($uri, 'signup.php')) {
            $_SESSION['return_to'] = $uri;
        }
        header('Location: login.php');
        exit;
    }
}

/** Require admin role */
function require_admin(): void {
    require_auth();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

/** Require student role */
function require_student(): void {
    require_auth();
    if (($_SESSION['user_role'] ?? '') !== 'student') {
        header('Location: dashboard.php');
        exit;
    }
}

$pdo = getDBConnection();
