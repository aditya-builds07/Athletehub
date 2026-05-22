<?php
/**
 * AthleteHub — Landing / Login / Register
 * Entry point for unauthenticated users.
 */

session_start();
require_once __DIR__ . '/config/db.php';

// ── Redirect if already logged in ──
if (isset($_SESSION['user_id'])) {
    header('Location: pages/feed.php');
    exit();
}

$loginError    = '';
$registerError = '';
$registerSuccess = '';
$activeTab     = 'login';

// ══════════════════════════════════════
//  LOGIN Handler
// ══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $loginError = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginError = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // ── Security: Regenerate session ID to prevent session fixation ──
                session_regenerate_id(true);
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['name']        = $user['name'];
                $_SESSION['role']        = $user['role'];
                $_SESSION['sport']       = $user['sport'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                header('Location: pages/feed.php');
                exit();
            } else {
                $loginError = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $loginError = 'Something went wrong. Please try again.';
        }
    }
    $activeTab = 'login';
}

// ══════════════════════════════════════
//  REGISTER Handler
// ══════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $firstName       = trim($_POST['first_name'] ?? '');
    $lastName        = trim($_POST['last_name'] ?? '');
    $email           = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role            = $_POST['role'] ?? 'athlete';
    $sport           = trim($_POST['sport'] ?? '');
    $location        = trim($_POST['location'] ?? '');

    $allowedRoles = ['athlete', 'coach'];

    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $registerError = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $registerError = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $registerError = 'Passwords do not match.';
    } elseif (!in_array($role, $allowedRoles)) {
        $registerError = 'Please select a valid role.';
    } else {
        try {
            // Check if email already exists
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->execute([$email]);
            if ($check->fetch()) {
                $registerError = 'An account with this email already exists.';
            } else {
                $fullName     = $firstName . ' ' . $lastName;
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    "INSERT INTO users (name, email, password_hash, role, sport, location) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $insert->execute([$fullName, $email, $passwordHash, $role, $sport, $location]);

                $newUserId = $pdo->lastInsertId();

                $_SESSION['user_id']     = $newUserId;
                $_SESSION['name']        = $fullName;
                $_SESSION['role']        = $role;
                $_SESSION['sport']       = $sport;
                $_SESSION['profile_pic'] = '';
                header('Location: pages/feed.php');
                exit();
            }
        } catch (PDOException $e) {
            $registerError = 'Something went wrong. Please try again.';
        }
    }
    $activeTab = 'register';
}

// Check URL param for tab
if (isset($_GET['tab']) && $_GET['tab'] === 'register') {
    $activeTab = 'register';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AthleteHub — The premier sports networking platform for athletes, coaches, recruiters, and clubs across India.">
    <title>AthleteHub — Sports Networking Platform</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/glass.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>

    <!-- Decorative Background Circles -->
    <div class="deco-circle deco-circle--1"></div>
    <div class="deco-circle deco-circle--2"></div>

    <!-- ═══════════════ AUTH PAGE GRID ═══════════════ -->
    <div class="auth-page">

        <!-- ━━━━━━ LEFT PANEL ━━━━━━ -->
        <div class="auth-left">

            <!-- Brand Logo -->
            <a href="index.php" class="auth-brand" id="authLogo" style="display: flex; align-items: center; gap: 12px; text-decoration: none;">
                <img src="assets/images/logo-trophy.png" alt="AthleteHub Logo" style="width: 52px; height: 52px; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.15));">
                <span class="auth-brand-text">Athlete<strong>Hub</strong></span>
            </a>

            <!-- Hero Headline -->
            <h1 class="auth-hero-title">Your Sports Career Starts Here</h1>

            <!-- Subheading -->
            <p class="auth-hero-sub">
                Connect with athletes, coaches, recruiters and sports 
                clubs — all in one platform built for Indian sports.
            </p>

            <!-- Feature List -->
            <div class="auth-features">

                <div class="auth-feature-item">
                    <span class="auth-feature-icon" style="background:#E8F0FE;">🏃</span>
                    <div>
                        <span class="auth-feature-title">Build your sports profile</span>
                        <span class="auth-feature-sub">Showcase achievements, stats and sport history</span>
                    </div>
                </div>

                <div class="auth-feature-item">
                    <span class="auth-feature-icon" style="background:#FEF3C7;">🔎</span>
                    <div>
                        <span class="auth-feature-title">Get discovered by recruiters</span>
                        <span class="auth-feature-sub">Connect with clubs, coaches and opportunities</span>
                    </div>
                </div>

                <div class="auth-feature-item">
                    <span class="auth-feature-icon" style="background:#DCFCE7;">🏆</span>
                    <div>
                        <span class="auth-feature-title">Join tournaments</span>
                        <span class="auth-feature-sub">Register and compete in events across India</span>
                    </div>
                </div>

                <div class="auth-feature-item">
                    <span class="auth-feature-icon" style="background:#EDE9FE;">💬</span>
                    <div>
                        <span class="auth-feature-title">Direct messaging</span>
                        <span class="auth-feature-sub">Talk directly to coaches and recruiters</span>
                    </div>
                </div>

            </div>

            <!-- Stats Row -->
            <div class="auth-stats">
                <div class="auth-stat">
                    <span class="auth-stat-number">500+</span>
                    <span class="auth-stat-label">Athletes</span>
                </div>
                <div class="auth-stat">
                    <span class="auth-stat-number">50+</span>
                    <span class="auth-stat-label">Clubs</span>
                </div>
                <div class="auth-stat">
                    <span class="auth-stat-number">100+</span>
                    <span class="auth-stat-label">Recruiters</span>
                </div>
            </div>

        </div>

        <!-- ━━━━━━ RIGHT PANEL ━━━━━━ -->
        <div class="auth-right">

            <div class="glass-modal auth-form-card" id="authFormCard">

                <!-- Tab Switcher -->
                <div class="auth-tabs" id="authTabs">
                    <button class="auth-tab <?php echo ($activeTab === 'login') ? 'active' : ''; ?>"
                            id="tabLogin" data-tab="login" type="button">Sign In</button>
                    <button class="auth-tab <?php echo ($activeTab === 'register') ? 'active' : ''; ?>"
                            id="tabRegister" data-tab="register" type="button">Register</button>
                </div>

                <!-- ━━━━ LOGIN PANEL ━━━━ -->
                <div class="auth-panel <?php echo ($activeTab !== 'login') ? 'hidden' : ''; ?>" id="panelLogin">

                    <h2 class="auth-form-title">Welcome back!</h2>
                    <p class="auth-form-sub">Sign in to your AthleteHub account</p>

                    <?php if ($loginError): ?>
                        <div class="auth-alert auth-alert--error" id="loginErrorAlert">
                            <span class="material-icons-round">error_outline</span>
                            <span><?php echo htmlspecialchars($loginError); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" class="auth-form" id="loginForm" novalidate>
                        <input type="hidden" name="action" value="login">

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label" for="loginEmail">Email address</label>
                            <input type="email"
                                   id="loginEmail"
                                   name="email"
                                   class="glass-input"
                                   placeholder="you@example.com"
                                   autocomplete="email"
                                   required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <span class="field-error" id="loginEmailError"></span>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label class="form-label" for="loginPassword">Password</label>
                            <div class="input-wrapper">
                                <input type="password"
                                       id="loginPassword"
                                       name="password"
                                       class="glass-input"
                                       placeholder="Enter your password"
                                       autocomplete="current-password"
                                       required>
                                <button type="button" class="password-toggle" id="toggleLoginPass" aria-label="Toggle password visibility">
                                    <span class="material-icons-round">visibility_off</span>
                                </button>
                            </div>
                            <span class="field-error" id="loginPasswordError"></span>
                        </div>

                        <!-- Forgot Password -->
                        <div class="auth-forgot-row">
                            <a href="javascript:void(0)" role="button" class="auth-forgot-link" id="forgotPasswordLink">Forgot password?</a>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary btn-lg auth-submit" id="loginSubmitBtn">
                            Sign In
                            <span class="material-icons-round">arrow_forward</span>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="auth-divider">
                        <span>or continue with</span>
                    </div>

                    <!-- Google SSO -->
                    <button type="button" class="btn btn-glass auth-google-btn" id="googleLoginBtn">
                        <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Continue with Google
                    </button>

                    <!-- Switch to register -->
                    <p class="auth-switch-text">
                        Don't have an account? 
                        <a href="javascript:void(0)" role="button" class="auth-switch-link" data-tab="register" id="switchToRegister">Create one</a>
                    </p>

                </div>

                <!-- ━━━━ REGISTER PANEL ━━━━ -->
                <div class="auth-panel <?php echo ($activeTab !== 'register') ? 'hidden' : ''; ?>" id="panelRegister">

                    <h2 class="auth-form-title">Create your account</h2>
                    <p class="auth-form-sub">Join AthleteHub — it's free</p>

                    <?php if ($registerError): ?>
                        <div class="auth-alert auth-alert--error" id="registerErrorAlert">
                            <span class="material-icons-round">error_outline</span>
                            <span><?php echo htmlspecialchars($registerError); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" class="auth-form" id="registerForm" novalidate>
                        <input type="hidden" name="action" value="register">

                        <!-- Name Row -->
                        <div class="auth-row-2col">
                            <div class="form-group">
                                <label class="form-label" for="regFirstName">First name</label>
                                <input type="text"
                                       id="regFirstName"
                                       name="first_name"
                                       class="glass-input"
                                       placeholder="Arjun"
                                       autocomplete="given-name"
                                       required
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                <span class="field-error" id="regFirstNameError"></span>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="regLastName">Last name</label>
                                <input type="text"
                                       id="regLastName"
                                       name="last_name"
                                       class="glass-input"
                                       placeholder="Sharma"
                                       autocomplete="family-name"
                                       required
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                <span class="field-error" id="regLastNameError"></span>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label" for="regEmail">Email address</label>
                            <input type="email"
                                   id="regEmail"
                                   name="email"
                                   class="glass-input"
                                   placeholder="you@example.com"
                                   autocomplete="email"
                                   required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <span class="field-error" id="regEmailError"></span>
                        </div>

                        <!-- Password -->
                        <div class="form-group">
                            <label class="form-label" for="regPassword">Password</label>
                            <div class="input-wrapper">
                                <input type="password"
                                       id="regPassword"
                                       name="password"
                                       class="glass-input"
                                       placeholder="Min. 8 characters"
                                       autocomplete="new-password"
                                       required>
                                <button type="button" class="password-toggle" id="toggleRegPass" aria-label="Toggle password visibility">
                                    <span class="material-icons-round">visibility_off</span>
                                </button>
                            </div>
                            <!-- Strength Indicator -->
                            <div class="strength-bar" id="strengthBar">
                                <div class="strength-segment" id="seg1"></div>
                                <div class="strength-segment" id="seg2"></div>
                                <div class="strength-segment" id="seg3"></div>
                                <div class="strength-segment" id="seg4"></div>
                            </div>
                            <span class="strength-label" id="strengthLabel"></span>
                            <span class="field-error" id="regPasswordError"></span>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label class="form-label" for="regConfirmPassword">Confirm password</label>
                            <div class="input-wrapper">
                                <input type="password"
                                       id="regConfirmPassword"
                                       name="confirm_password"
                                       class="glass-input"
                                       placeholder="Re-enter your password"
                                       autocomplete="new-password"
                                       required>
                                <button type="button" class="password-toggle" id="toggleRegConfirmPass" aria-label="Toggle password visibility">
                                    <span class="material-icons-round">visibility_off</span>
                                </button>
                            </div>
                            <span class="field-error" id="regConfirmPasswordError"></span>
                        </div>

                        <!-- Role -->
                        <div class="form-group">
                            <label class="form-label" for="regRole">I am a</label>
                            <select id="regRole" name="role" class="glass-input glass-select" required>
                                <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>Select your role</option>
                                <option value="athlete"   <?php echo (($_POST['role'] ?? '') === 'athlete')   ? 'selected' : ''; ?>>Athlete</option>
                                <option value="coach"     <?php echo (($_POST['role'] ?? '') === 'coach')     ? 'selected' : ''; ?>>Coach</option>
                            </select>
                            <span class="field-error" id="regRoleError"></span>
                        </div>

                        <!-- Sport -->
                        <div class="form-group">
                            <label class="form-label" for="regSport">Sport / Specialization</label>
                            <input type="text"
                                   id="regSport"
                                   name="sport"
                                   class="glass-input"
                                   placeholder="e.g. Cricket, Football"
                                   value="<?php echo htmlspecialchars($_POST['sport'] ?? ''); ?>">
                            <span class="field-error" id="regSportError"></span>
                        </div>

                        <!-- Location -->
                        <div class="form-group">
                            <label class="form-label" for="regLocation">City / Location</label>
                            <input type="text"
                                   id="regLocation"
                                   name="location"
                                   class="glass-input"
                                   placeholder="e.g. Mumbai, India"
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                            <span class="field-error" id="regLocationError"></span>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-primary btn-lg auth-submit" id="registerSubmitBtn">
                            Create Account
                            <span class="material-icons-round">arrow_forward</span>
                        </button>
                    </form>

                    <!-- Switch to login -->
                    <p class="auth-switch-text">
                        Already have an account? 
                        <a href="javascript:void(0)" role="button" class="auth-switch-link" data-tab="login" id="switchToLogin">Sign In</a>
                    </p>

                </div>

            </div>
        </div>

    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- JavaScript -->
    <script src="assets/js/auth.js"></script>
</body>
</html>
