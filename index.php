<?php
/**
 * CommitTrack - Landing Page
 * Matches Panel 1 of the CommitTrack visual design reference.
 * Highlights key college project capabilities with working Login & Sign Up links.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? 'student';
$userName = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CommitTrack - Turn Your Commitments Into Real Progress</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <nav class="landing-nav">
            <a href="index.php" class="brand-logo">
                <span class="brand-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </span>
                CommitTrack
            </a>

            <ul class="nav-links">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
            </ul>

            <div class="nav-actions">
                <?php if ($isLoggedIn): ?>
                    <span class="text-muted" style="font-size: 0.88rem;">Hi, <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong></span>
                    <a href="dashboard.php" class="btn btn-primary btn-sm">Dashboard &rarr;</a>
                    <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary btn-sm">Login</a>
                    <a href="signup.php" class="btn btn-primary btn-sm">Sign Up</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <main>
        <section class="hero-section">
            <div class="hero-text-col">
                <div class="hero-tag">
                    <span>Stay Consistent &bull; Build a Better You</span>
                </div>

                <h1 class="hero-title">
                    Turn Your<br>
                    Commitments Into<br>
                    Real Progress
                </h1>

                <p class="hero-subtitle">
                    Track your tasks, manage your classes, and stay on top of your goals &mdash; together.
                </p>

                <div class="hero-ctas">
                    <?php if ($isLoggedIn): ?>
                        <a href="dashboard.php" class="btn btn-primary">
                            Go to Dashboard &rarr;
                        </a>
                    <?php else: ?>
                        <a href="signup.php" class="btn btn-primary">
                            Get Started &rarr;
                        </a>
                        <a href="#features" class="btn btn-secondary">
                            Learn More
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hero Visual Card (Matching Panel 1 Mockup) -->
            <div class="hero-visual-col">
                <div class="hero-visual-card">
                    <div class="hero-visual-bg"></div>

                    <!-- Floating Task Progress Widget -->
                    <div class="hero-floating-pill">
                        <div class="hero-pill-item">
                            <span class="hero-pill-check">&#10003;</span>
                            <span>Plan</span>
                        </div>
                        <div class="hero-pill-item">
                            <span class="hero-pill-check">&#10003;</span>
                            <span>Track</span>
                            <!-- Mini bar graph icon -->
                            <svg width="20" height="14" viewBox="0 0 24 18" fill="none" style="margin-left:auto;">
                                <rect x="2" y="10" width="4" height="8" rx="1" fill="#16a34a"/>
                                <rect x="10" y="5" width="4" height="13" rx="1" fill="#16a34a"/>
                                <rect x="18" y="1" width="4" height="17" rx="1" fill="#16a34a"/>
                            </svg>
                        </div>
                        <div class="hero-pill-item">
                            <span class="hero-pill-check">&#10003;</span>
                            <span>Achieve</span>
                        </div>
                    </div>

                    <!-- Central Character Illustration with Laptop and Foliage -->
                    <div class="hero-visual-content">
                        <svg class="hero-svg-character" viewBox="0 0 320 280" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <!-- Background Foliage & Desk -->
                            <circle cx="80" cy="180" r="28" fill="#c3e4d4" opacity="0.6"/>
                            <circle cx="70" cy="155" r="22" fill="#9cd2b6" opacity="0.7"/>
                            <rect x="74" y="180" width="12" height="40" rx="3" fill="#699d84"/>
                            
                            <!-- Right Potted Plant -->
                            <rect x="236" y="190" width="24" height="25" rx="3" fill="#e8c7a7"/>
                            <circle cx="242" cy="175" r="14" fill="#a4d7be"/>
                            <circle cx="254" cy="165" r="16" fill="#84c5a5"/>

                            <!-- Desk Surface -->
                            <rect x="20" y="210" width="280" height="8" rx="4" fill="#e5ece8"/>

                            <!-- Student with Hoodie Sitting at Desk -->
                            <path d="M120 180 Q160 140 200 180 L210 220 L110 220 Z" fill="#205345"/>
                            <circle cx="160" cy="130" r="24" fill="#f8cfb5"/>
                            <!-- Dark Hair -->
                            <path d="M142 128 C142 110 178 110 178 128 C172 116 148 116 142 128 Z" fill="#263238"/>
                            <circle cx="152" cy="132" r="2.5" fill="#263238"/>
                            <circle cx="168" cy="132" r="2.5" fill="#263238"/>
                            
                            <!-- Laptop Open -->
                            <polygon points="170,175 220,175 210,210 160,210" fill="#2e3a36"/>
                            <rect x="180" y="152" width="46" height="30" rx="3" fill="#3a4b45"/>
                            <rect x="184" y="156" width="38" height="22" rx="2" fill="#ffffff" opacity="0.9"/>
                            <line x1="188" y1="165" x2="204" y2="165" stroke="#173f35" stroke-width="2" stroke-linecap="round"/>
                            <line x1="188" y1="170" x2="214" y2="170" stroke="#879b91" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why CommitTrack? Features Grid (Matching Panel 1) -->
        <section class="features-section" id="features">
            <div class="section-header">
                <h2 class="section-title">Why CommitTrack?</h2>
                <p class="section-subtitle">Everything you need to stay focused and productive.</p>
            </div>

            <div class="features-grid">
                <!-- Feature 1: Classroom Management -->
                <div class="feature-card">
                    <div class="feature-icon-badge badge-purple">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                        </svg>
                    </div>
                    <h3>Classroom Management</h3>
                    <p>Create classes, invite students and manage easily.</p>
                </div>

                <!-- Feature 2: Task Tracking -->
                <div class="feature-card">
                    <div class="feature-icon-badge badge-green">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <h3>Task Tracking</h3>
                    <p>Track your tasks, deadlines and progress.</p>
                </div>

                <!-- Feature 3: Progress Analytics -->
                <div class="feature-card">
                    <div class="feature-icon-badge badge-orange">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 20V10"></path>
                            <path d="M12 20V4"></path>
                            <path d="M6 20v-6"></path>
                        </svg>
                    </div>
                    <h3>Progress Analytics</h3>
                    <p>See your performance and stay motivated.</p>
                </div>

                <!-- Feature 4: Secure & Simple -->
                <div class="feature-card">
                    <div class="feature-icon-badge badge-shield">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                    </div>
                    <h3>Secure & Simple</h3>
                    <p>Your data is safe and always in your control.</p>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section class="features-section" id="about" style="margin-top: -30px; margin-bottom: 60px;">
            <div class="card-widget" style="background: #ffffff; border-radius: var(--radius-lg); padding: 32px;">
                <h3 style="font-size: 1.3rem; margin-bottom: 10px;">About CommitTrack</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; max-width: 820px;">
                    CommitTrack is built for students and educators to bridge coursework assignments, commitment deadlines, and progress feedback into an elegant, distraction-free environment. Built natively using PHP, MySQL, HTML, and CSS.
                </p>
                <div style="margin-top: 20px; display: flex; gap: 14px; flex-wrap: wrap;">
                    <a href="signup.php" class="btn btn-primary btn-sm">Create Free Student Account &rarr;</a>
                    <a href="login.php" class="btn btn-secondary btn-sm">Sign In to Existing Account</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer style="border-top: 1px solid var(--border-color); background: #ffffff; padding: 32px 28px; text-align: center; color: var(--text-muted); font-size: 0.88rem;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div class="brand-logo" style="font-size: 1rem;">
                <span class="brand-icon" style="width: 24px; height: 24px; font-size: 11px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </span>
                CommitTrack
            </div>
            <div>
                &copy; <?= date('Y') ?> CommitTrack. College Project. All rights reserved.
            </div>
            <div>
                <a href="login.php" style="color: var(--primary); font-weight: 600; margin-right: 14px;">Login</a>
                <a href="signup.php" style="color: var(--primary); font-weight: 600;">Sign Up</a>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>
