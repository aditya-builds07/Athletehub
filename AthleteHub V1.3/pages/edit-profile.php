<?php
session_start();
require_once '../includes/session.php';
require_once '../config/db.php';

$userId = $_SESSION['user_id'];
$basePath = defined('BASE_URL') ? BASE_URL : '';


$stmt = $pdo->prepare("SELECT name, role, sport, location, bio, profile_pic FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: $basePath/pages/feed.php");
    exit;
}

$pageTitle = 'Edit Profile';
$pageCSS = ['profile'];
$pageJS  = ['edit-profile'];
require_once '../includes/header.php';
?>

<div class="page-wrapper" style="max-width: 100%; padding: var(--space-6) 24px;">
    <div class="glass-card" style="padding: 30px;">
        <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 24px;">Edit Profile</h2>

        <form id="editProfileForm">
            <!-- Avatar Section -->
            <div style="display: flex; align-items: center; gap: 20px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 24px; margin-bottom: 24px;">
                <div style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700; flex-shrink: 0;" id="avatarPreviewContainer">
                    <?php if ($user['profile_pic']): ?>
                        <img id="avatarPreview" src="<?= $basePath ?>/uploads/profile_pics/<?= htmlspecialchars($user['profile_pic']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <img id="avatarPreview" style="display:none; width: 100%; height: 100%; object-fit: cover;">
                        <span id="avatarInitials"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="btn btn-outline btn-sm" for="profileImageInput" style="cursor: pointer; display: inline-flex;">
                        <span class="material-icons-round" style="font-size: 16px;">photo_camera</span> Change Picture
                    </label>
                    <input type="file" id="profileImageInput" accept="image/jpeg,image/png,image/webp" style="display: none;">
                    <p style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">JPEG, PNG, WEBP. Max 5MB. Will be cropped square.</p>
                </div>
            </div>

            <!-- Detailed Info -->
            <div class="form-group mb-4">
                <label class="form-label">Full Name</label>
                <input type="text" class="glass-input" id="epName" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>
            
            <div style="display:flex; gap: 16px; margin-bottom: 16px;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Sport</label>
                    <input type="text" class="glass-input" id="epSport" value="<?= htmlspecialchars($user['sport']) ?>" placeholder="e.g. Cricket, Football">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Location</label>
                    <input type="text" class="glass-input" id="epLocation" value="<?= htmlspecialchars($user['location']) ?>" placeholder="e.g. Mumbai, India">
                </div>
            </div>

            <div class="form-group mb-4">
                <label class="form-label">Bio Details</label>
                <textarea class="glass-input glass-textarea" id="epBio" rows="4" placeholder="Tell us about your achievements..."><?= htmlspecialchars($user['bio']) ?></textarea>
            </div>

            <div class="flex gap-sm justify-end" style="border-top: 1px solid var(--border-subtle); padding-top: 24px; margin-top: 16px;">
                <a href="<?= $basePath ?>/pages/profile.php" class="btn btn-glass">Cancel</a>
                <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                    <span class="material-icons-round" style="font-size:16px">save</span> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
