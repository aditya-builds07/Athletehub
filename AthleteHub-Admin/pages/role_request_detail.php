<?php
/**
 * AthleteHub Admin — Role Request Detail Page
 * Full view of a single application with approve/reject decision panel.
 */
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

/* ── Fetch Application ────────────────────────────────── */
$appId = (int) ($_GET['id'] ?? 0);
if ($appId <= 0) {
    header('Location: ' . BASE_URL . '/pages/role_requests.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT ra.*,
           u.name        AS applicant_name,
           u.email       AS applicant_email,
           u.profile_pic AS applicant_pic,
           r.name        AS reviewer_name
    FROM role_applications ra
    JOIN users u ON ra.user_id = u.id
    LEFT JOIN users r ON ra.reviewed_by = r.id
    WHERE ra.id = ?
");
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app) {
    header('Location: ' . BASE_URL . '/pages/role_requests.php');
    exit;
}

$pageTitle   = 'Review: ' . $app['applicant_name'];
$currentPage = 'role_requests';

/* ── Helper: social link ─────────────────────────────── */
function socialLink(string $platform, ?string $handle, string $baseUrl, string $icon): string {
    if (empty($handle)) return '';
    $h = e($handle);
    $u = e($baseUrl . $handle);
    return "<a href=\"{$u}\" target=\"_blank\" rel=\"noopener\" class=\"rr-social-link\">
                <span class=\"material-icons-round\">{$icon}</span>{$h}</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> — AthleteHub Admin</title>
    <meta name="description" content="Review role application from <?php echo e($app['applicant_name']); ?>.">
    <meta name="admin-csrf" content="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>">
    <meta name="rr-api-url" content="<?php echo e(BASE_URL); ?>/api/handle_role.php">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(ASSETS); ?>/css/admin.css">
    <link rel="stylesheet" href="<?php echo e(ASSETS); ?>/css/role_requests.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <div class="admin-content">

            <!-- Back Link -->
            <a href="<?php echo e(BASE_URL); ?>/pages/role_requests.php" class="rr-back-link" id="backToRequests">
                <span class="material-icons-round">arrow_back</span>
                Back to Role Requests
            </a>

            <div class="rr-detail-grid">

                <!-- ── LEFT COLUMN ─────────────────────── -->
                <div>

                    <!-- Applicant Info -->
                    <div class="rr-section">
                        <div class="rr-section-header">
                            <span class="material-icons-round">person</span>
                            <h3>Applicant Information</h3>
                        </div>
                        <div class="rr-section-body">
                            <div class="rr-applicant-card" style="border:none;padding:0 0 1.25rem;text-align:left;display:flex;align-items:center;gap:1.25rem;margin-bottom:1.25rem;border-bottom:1px solid var(--border-color)">
                                <div class="rr-applicant-avatar" style="margin:0;flex-shrink:0">
                                    <?php if (!empty($app['applicant_pic'])): ?>
                                        <img src="<?php echo e(MAIN_APP_URL); ?>/uploads/profile_pics/<?php echo e($app['applicant_pic']); ?>" alt="<?php echo e($app['applicant_name']); ?>">
                                    <?php else: ?>
                                        <?php echo e(strtoupper(substr($app['applicant_name'], 0, 1))); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="rr-applicant-name"><?php echo e($app['applicant_name']); ?></div>
                                    <div class="rr-applicant-email"><?php echo e($app['applicant_email']); ?></div>
                                    <span class="badge badge-role-<?php echo e($app['requested_role']); ?>">
                                        Applying for <?php echo e(ucfirst($app['requested_role'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field-label">Phone</div>
                                <div class="rr-field-value"><?php echo e($app['phone'] ?: '—'); ?></div>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field-label">Location</div>
                                <div class="rr-field-value">
                                    <?php
                                    $loc = array_filter([$app['city'] ?? '', $app['country'] ?? '']);
                                    echo e($loc ? implode(', ', $loc) : '—');
                                    ?>
                                </div>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field-label">Submitted</div>
                                <div class="rr-field-value">
                                    <?php
                                    $ts = $app['submitted_at'] ?? $app['created_at'];
                                    echo $ts ? e(date('F j, Y \a\t g:i A', strtotime($ts))) : '—';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Organisation Details -->
                    <div class="rr-section">
                        <div class="rr-section-header">
                            <span class="material-icons-round">business</span>
                            <h3>Organisation Details</h3>
                        </div>
                        <div class="rr-section-body">
                            <div class="rr-field">
                                <div class="rr-field-label">Organisation</div>
                                <div class="rr-field-value"><?php echo e($app['organisation_name']); ?></div>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field-label">Description</div>
                                <div class="rr-field-value" style="white-space:pre-line"><?php echo e($app['description']); ?></div>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field-label">Website</div>
                                <div class="rr-field-value">
                                    <?php if (!empty($app['website'])): ?>
                                        <a href="<?php echo e($app['website']); ?>" target="_blank" rel="noopener"><?php echo e($app['website']); ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($app['years_experience'])): ?>
                            <div class="rr-field">
                                <div class="rr-field-label">Experience</div>
                                <div class="rr-field-value"><?php echo e($app['years_experience']); ?> years</div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($app['team_player_count'])): ?>
                            <div class="rr-field">
                                <div class="rr-field-label">Team Size</div>
                                <div class="rr-field-value"><?php echo e($app['team_player_count']); ?> players</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Social Handles -->
                    <?php
                    $socials = [
                        'instagram' => [$app['instagram'] ?? '', 'https://instagram.com/', 'photo_camera'],
                        'twitter'   => [$app['twitter']   ?? '', 'https://x.com/',         'tag'],
                        'linkedin'  => [$app['linkedin']  ?? '', 'https://linkedin.com/in/', 'work'],
                        'facebook'  => [$app['facebook']  ?? '', 'https://facebook.com/',   'thumb_up'],
                        'youtube'   => [$app['youtube']   ?? '', 'https://youtube.com/@',   'play_circle'],
                    ];
                    $hasSocials = array_filter($socials, fn($s) => !empty($s[0]));
                    if ($hasSocials):
                    ?>
                    <div class="rr-section">
                        <div class="rr-section-header">
                            <span class="material-icons-round">share</span>
                            <h3>Social Profiles</h3>
                        </div>
                        <div class="rr-section-body">
                            <div class="rr-social-links">
                                <?php foreach ($hasSocials as $platform => [$handle, $base, $icon]): ?>
                                    <?php echo socialLink($platform, $handle, $base, $icon); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Document -->
                    <div class="rr-section">
                        <div class="rr-section-header">
                            <span class="material-icons-round">description</span>
                            <h3>Submitted Document</h3>
                        </div>
                        <div class="rr-section-body">
                            <div class="rr-field">
                                <div class="rr-field-label">Document Type</div>
                                <div class="rr-field-value"><?php echo e($app['document_type'] ?: '—'); ?></div>
                            </div>
                            <?php if (!empty($app['document_path'])): ?>
                                <?php
                                $docUrl = MAIN_APP_URL . '/' . $app['document_path'];
                                $ext    = strtolower(pathinfo($app['document_path'], PATHINFO_EXTENSION));
                                $isImg  = in_array($ext, ['jpg','jpeg','png','gif','webp'], true);
                                ?>
                                <?php if ($isImg): ?>
                                    <div class="rr-doc-preview">
                                        <img src="<?php echo e($docUrl); ?>" alt="Submitted document">
                                    </div>
                                    <a href="<?php echo e($docUrl); ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-top:.75rem">
                                        <span class="material-icons-round">open_in_new</span> View Full Size
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo e($docUrl); ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-top:.5rem"
                                       id="downloadDoc">
                                        <span class="material-icons-round">download</span> Download Document (<?php echo strtoupper($ext); ?>)
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <p style="color:var(--text-muted);font-size:.85rem">No document uploaded.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /left column -->

                <!-- ── RIGHT COLUMN ────────────────────── -->
                <div>
                    <div class="rr-decision-panel">
                        <div class="rr-decision-header">
                            <span class="material-icons-round">gavel</span>
                            <h3>Admin Decision</h3>
                        </div>
                        <div class="rr-decision-body">

                            <?php if ($app['status'] !== 'pending'): ?>
                                <!-- Already reviewed -->
                                <div class="rr-reviewed-banner <?php echo e($app['status']); ?>">
                                    <span class="material-icons-round">
                                        <?php echo $app['status'] === 'approved' ? 'check_circle' : 'cancel'; ?>
                                    </span>
                                    <div>
                                        <strong><?php echo e(ucfirst($app['status'])); ?></strong>
                                        <?php if (!empty($app['reviewer_name'])): ?>
                                        <div class="rr-reviewed-meta">
                                            by <?php echo e($app['reviewer_name']); ?>
                                            <?php if (!empty($app['reviewed_at'])): ?>
                                                on <?php echo e(date('M j, Y', strtotime($app['reviewed_at']))); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($app['admin_note'])): ?>
                                <div class="form-group" style="margin-top:1rem">
                                    <label>Admin Note</label>
                                    <div class="form-control" style="height:auto;min-height:60px;background:var(--bg-secondary);color:var(--text-secondary);cursor:default">
                                        <?php echo e($app['admin_note']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- Pending — show decision form -->
                                <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.25rem">
                                    Review the application details on the left, then approve or reject below.
                                    An admin note is <strong style="color:var(--text-secondary)">required</strong> for rejection.
                                </p>

                                <div class="form-group">
                                    <label for="adminNoteInput">Admin Note <span style="color:var(--text-muted)">(required for rejection)</span></label>
                                    <textarea class="form-control" id="adminNoteInput"
                                              placeholder="Enter reason for rejection or approval notes…"
                                              rows="4"></textarea>
                                </div>

                                <div class="rr-decision-actions">
                                    <button type="button"
                                            class="btn btn-success"
                                            id="approveBtn-<?php echo (int)$appId; ?>"
                                            onclick="handleApprove(<?php echo (int)$appId; ?>)">
                                        <span class="material-icons-round">check_circle</span>
                                        Approve Application
                                    </button>
                                    <button type="button"
                                            class="btn btn-danger"
                                            id="rejectBtn-<?php echo (int)$appId; ?>"
                                            onclick="handleReject(<?php echo (int)$appId; ?>)">
                                        <span class="material-icons-round">cancel</span>
                                        Reject Application
                                    </button>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Application Meta -->
                    <div class="rr-section" style="margin-top:1.5rem">
                        <div class="rr-section-header">
                            <span class="material-icons-round">info</span>
                            <h3>Application Info</h3>
                        </div>
                        <div class="rr-section-body">
                            <div class="rr-field">
                                <div class="rr-field-label">App ID</div>
                                <div class="rr-field-value">#<?php echo (int)$app['id']; ?></div>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field-label">Status</div>
                                <div class="rr-field-value">
                                    <span class="badge badge-status-<?php echo e($app['status']); ?>">
                                        <?php echo e(ucfirst($app['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="rr-field">
                                <div class="rr-field-label">Role</div>
                                <div class="rr-field-value">
                                    <span class="badge badge-role-<?php echo e($app['requested_role']); ?>">
                                        <?php echo e(ucfirst($app['requested_role'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /right column -->
            </div><!-- /rr-detail-grid -->

        </div><!-- /admin-content -->
    </div><!-- /admin-main -->
</div><!-- /admin-layout -->

<!-- Confirm Modal -->
<div class="modal-overlay" id="rrConfirmModal" role="dialog" aria-modal="true" aria-labelledby="rrConfirmTitle">
    <div class="modal" style="max-width:440px">
        <div class="modal-body" style="text-align:center;padding-top:2rem">
            <div class="rr-confirm-modal-icon" id="rrConfirmIcon"></div>
            <div class="rr-confirm-modal-title" id="rrConfirmTitle">Confirm Action</div>
            <div class="rr-confirm-modal-subtitle" id="rrConfirmSubtitle"></div>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:1rem">
            <button class="btn btn-outline" id="rrCancelBtn" type="button">Cancel</button>
            <button class="btn btn-success" id="rrConfirmBtn" type="button">Confirm</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="<?php echo e(ASSETS); ?>/js/role_requests.js"></script>
</body>
</html>
