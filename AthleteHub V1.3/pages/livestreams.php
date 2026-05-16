<?php
/**
 * AthleteHub — Live Streams Page
 */
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'athlete';

// Fetch eligible tournaments for "Go Live" modal if club
$myTournaments = [];
if ($userRole === 'club') {
    $stmt = $pdo->prepare("SELECT id, name FROM tournaments WHERE created_by = ? AND status != 'closed' ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $myTournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../includes/header.php';
?>

<div class="layout-wrapper pt-20 pb-12">
    <div class="container mx-auto px-4 max-w-6xl">
        
        <!-- Header Section -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold flex items-center gap-2">
                <span class="material-icons-round text-red-500">sensors</span>
                🔴 Live Matches
            </h2>
            
            <?php if ($userRole === 'club'): ?>
                <button class="btn btn-primary shadow-glow flex items-center gap-1" id="btnGoLive">
                    <span class="material-icons-round text-sm">videocam</span> Go Live
                </button>
            <?php endif; ?>
        </div>

        <!-- Grid Container -->
        <div id="streamsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Populated by JS -->
            <div class="col-span-full text-center text-muted py-10" id="loadingStreams">
                <span class="material-icons-round rotate-anim text-3xl">sync</span>
                <p class="mt-2 text-sm font-semibold">Loading live streams...</p>
            </div>
        </div>

    </div>
</div>

<!-- ═══════════════ "GO LIVE" MODAL ═══════════════ -->
<?php if ($userRole === 'club'): ?>
<div class="modal-overlay" id="goLiveModal">
    <div class="glass-modal" style="position:relative; max-width: 500px;">
        <button class="modal-close" id="closeGoLiveModal" aria-label="Close">
            <span class="material-icons-round">close</span>
        </button>
        <h3 class="modal-title mb-4 flex items-center gap-2">
            <span class="material-icons-round text-red-500">sensors</span> Start Stream
        </h3>
        
        <form id="goLiveForm" class="flex flex-col gap-3">
            <div>
                <label class="form-label text-xs font-semibold">Stream Title *</label>
                <input type="text" name="title" class="glass-input w-full" required placeholder="e.g. U-19 Finals: Bulls vs Tigers">
            </div>

            <div>
                <label class="form-label text-xs font-semibold">YouTube Live URL *</label>
                <input type="url" name="youtube_url" class="glass-input w-full" required placeholder="https://www.youtube.com/watch?v=XXXXXXXXXXX">
                <p class="text-[10px] text-muted mt-1">Must be a valid YouTube URL</p>
            </div>

            <div>
                <label class="form-label text-xs font-semibold">Link to Tournament (Optional)</label>
                <select name="tournament_id" class="glass-input w-full">
                    <option value="">None</option>
                    <?php foreach ($myTournaments as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-full mt-2" style="background: var(--red-500); border-color: var(--red-500);">
                🔴 Go Live Now
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════ WATCH MODAL ═══════════════ -->
<div class="modal-overlay" id="watchModal">
    <div class="glass-modal" style="position:relative; max-width: 800px; width: 100%; padding: 15px;">
        <button class="modal-close" id="closeWatchModal" aria-label="Close" style="top: 10px; right: 10px; z-index: 10;">
            <span class="material-icons-round text-white" style="text-shadow: 0 0 5px rgba(0,0,0,0.5);">close</span>
        </button>
        
        <div class="video-wrapper" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 12px; background: #000;">
            <iframe id="watchIframe" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        
        <div class="mt-4">
            <h3 id="watchTitle" class="text-xl font-bold mb-1">Loading...</h3>
            <p id="watchHost" class="text-sm font-semibold text-primary flex items-center gap-1">
                <span class="material-icons-round text-sm">verified</span>
                <span>Host Name</span>
            </p>
        </div>
    </div>
</div>

<script src="../assets/js/livestreams.js"></script>
<?php require_once '../includes/footer.php'; ?>
