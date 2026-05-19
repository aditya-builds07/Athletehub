<?php
/**
 * AthleteHub Admin — Login Page
 * Authenticates admin users against the users table.
 */

session_start();

// Already logged in? Go to dashboard.
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: pages/dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, profile_pic FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['user_id']      = $admin['id'];
            $_SESSION['user_name']    = $admin['name'];
            $_SESSION['user_email']   = $admin['email'];
            $_SESSION['role']         = $admin['role'];
            $_SESSION['user_pic']     = $admin['profile_pic'];
            header('Location: pages/dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials or insufficient privileges.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AthleteHub Administration Panel — Secure Login">
    <title>Admin Login — AthleteHub</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">

    <style>
        body.login-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bg-primary);
            margin: 0;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            animation: fadeSlideUp .5s ease;
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: var(--accent-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: .75rem;
        }

        .login-logo .icon-circle .material-icons-round {
            font-size: 32px;
            color: #fff;
        }

        .login-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .login-logo p {
            color: var(--text-muted);
            font-size: .875rem;
            margin-top: .25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: .4rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .form-group input {
            width: 100%;
            padding: .75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-input);
            color: var(--text-primary);
            font-size: .95rem;
            font-family: 'Inter', sans-serif;
            transition: border-color .2s, box-shadow .2s;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .25);
        }

        .login-btn {
            width: 100%;
            padding: .85rem;
            border: none;
            border-radius: 10px;
            background: var(--accent-gradient);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: transform .15s, box-shadow .2s;
            margin-top: .5rem;
        }

        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, .35);
        }

        .login-btn:active { transform: translateY(0); }

        .error-msg {
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.3);
            color: #f87171;
            padding: .65rem 1rem;
            border-radius: 8px;
            font-size: .85rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .error-msg .material-icons-round { font-size: 18px; }

        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="login-body">

    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <div class="icon-circle">
                    <span class="material-icons-round">shield</span>
                </div>
                <h1>Athlete<strong>Hub</strong></h1>
                <p>Administration Panel</p>
            </div>

            <?php if ($error): ?>
                <div class="error-msg">
                    <span class="material-icons-round">error_outline</span>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="admin@athletehub.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="material-icons-round" style="vertical-align:middle;margin-right:.35rem;font-size:20px">login</span>
                    Sign In
                </button>
            </form>
        </div>
    </div>

</body>
</html>
