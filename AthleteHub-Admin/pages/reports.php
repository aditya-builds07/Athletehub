<?php
/**
 * AthleteHub Admin — Reports Page
 * Placeholder — no reports table exists yet.
 * Shows a coming-soon state for now.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$pageTitle   = 'Reports';
$currentPage = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> — AthleteHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="admin-content">
            <div class="card">
                <div class="card-body">
                    <div class="empty-state" style="padding:5rem 1rem;">
                        <span class="material-icons-round" style="font-size:64px;">flag</span>
                        <h3 style="margin:.75rem 0 .5rem;font-size:1.2rem;color:var(--text-primary)">Reports Module</h3>
                        <p>The reporting system is coming soon.<br>This section will allow users to flag inappropriate content and admins to review/resolve reports.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
