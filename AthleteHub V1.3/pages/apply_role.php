<?php
/**
 * AthleteHub — Apply for Club / Recruiter Role
 * Professional Multi-Step Application Form
 */
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'athlete';
$userName = $_SESSION['name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';

// Fetch email if missing from session
if (empty($userEmail) && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn();
    $_SESSION['email'] = $userEmail;
}

// If user is already club or recruiter
if ($userRole === 'club' || $userRole === 'recruiter' || $userRole === 'admin') {
    $_SESSION['flash_msg'] = "You already have an elevated role.";
    header("Location: feed.php");
    exit;
}

$pageTitle = "Apply for Elevated Role";

// Check if user has an existing application
$stmt = $pdo->prepare("SELECT * FROM role_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="layout-wrapper pt-24 pb-12">
    <div class="container mx-auto px-4 max-w-4xl">
        
        <!-- Hero Header Card -->
        <div class="glass-card mb-8 p-8 text-center relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <span class="material-icons-round text-8xl">verified_user</span>
            </div>
            <div class="text-6xl mb-4 text-primary flex justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                    <path d="M0 0h24v24H0z" fill="none" />
                    <path fill="currentColor" d="M12 2.15q.2 0 .363.025t.337.1l6 2.25q.575.225.938.725T20 6.375V9.5q0 .425-.287.713T19 10.5t-.712-.288T18 9.5V6.4l-6-2.25L6 6.4v4.7q0 1.25.363 2.5t1 2.375T8.913 18t1.987 1.475cS.375.2.538.575t.012.75q-.175.4-.562.55t-.763-.05Q7.3 19.9 5.65 17.075T4 11.1V6.375q0-.625.363-1.125t.937-.725l6-2.25cS.175-.075.35-.1T12 2.15M17 22cS-2.075 0-3.537-1.463T12 17t1.463-3.537T17 12t3.538 1.463T22 17t-1.463 3.538T17 22m-1.5-4.5v2q0 .2.15.35T17 20t.35-.15t.15-.35v-2h2q.2 0 .35-.15T20 17t-.15-.35t-.35-.15h-2v-2cS0-.2-.15-.35T17 14t-.35.15t-.15.35v2h-2cS-.2 0-.35.15T14 17t.15.35t.35.15z" />
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold mb-3 bg-clip-text text-transparent bg-gradient-to-r from-white to-gray-400">Apply for an Elevated Role</h1>
            <p class="text-gray-400 max-w-2xl mx-auto leading-relaxed">
                Join AthleteHub as an official Club or Recruiter. Fill in your details below and submit your application for admin review. Verified roles unlock exclusive platform features like tournament hosting and advanced recruitment tools.
            </p>
        </div>

        <?php if ($application && $application['status'] === 'pending'): ?>
            <!-- Pending Status Tracker -->
            <div class="glass-card p-10 text-center border-yellow-500/30 bg-yellow-500/5">
                <div class="mb-6">
                    <span class="material-icons-round text-7xl text-yellow-500 animate-pulse">hourglass_top</span>
                </div>
                <h3 class="text-2xl font-bold mb-3">Application Under Review</h3>
                <p class="text-gray-400 mb-6">You applied for the <strong class="text-white"><?= strtoupper($application['requested_role']) ?></strong> role on <?= date('d M Y', strtotime($application['created_at'])) ?>.</p>
                <div class="inline-flex items-center gap-2 bg-yellow-500/20 text-yellow-400 px-6 py-3 rounded-full font-bold text-sm border border-yellow-500/30">
                    <span class="w-2 h-2 bg-yellow-500 rounded-full animate-ping"></span>
                    Verification in Progress
                </div>
                <p class="mt-8 text-xs text-gray-500 italic">Our team usually reviews applications within 3-5 business days. You will be notified via messages.</p>
            </div>
        <?php else: ?>
            
            <!-- Role Selection Row -->
            <div id="roleSelectorSection" class="role-options-row">
                <div class="role-card-item" id="roleCardClub" onclick="selectRole('club')">
                    <span class="material-icons-round check-icon">check_circle</span>
                    <div class="role-icon-wrapper">
                        <span class="role-emoji flex items-center justify-center">
                            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="1em" height="1em" fill="currentColor">
                                <g>
                                    <path d="M147.57,320.188c-0.078-0.797-0.328-1.531-0.328-2.328v-6.828c0-3.25,0.531-6.453,1.594-9.5
                                        c0,0,17.016-22.781,25.063-49.547c-8.813-18.594-16.813-41.734-16.813-64.672c0-5.328,0.391-10.484,0.938-15.563
                                        c-11.484-12.031-27-18.844-44.141-18.844c-35.391,0-64.109,28.875-64.109,73.75c0,35.906,29.219,74.875,29.219,74.875
                                        c1.031,3.047,1.563,6.25,1.563,9.5v6.828c0,8.516-4.969,16.266-12.719,19.813l-46.391,18.953
                                        C10.664,361.594,2.992,371.5,0.852,383.156l-0.797,10.203c-0.406,5.313,1.406,10.547,5.031,14.438
                                        c3.609,3.922,8.688,6.125,14.016,6.125H94.93l3.109-39.953l0.203-1.078c3.797-20.953,17.641-38.766,36.984-47.672L147.57,320.188z"
                                        />
                                    <path d="M511.148,383.156c-2.125-11.656-9.797-21.563-20.578-26.531l-46.422-18.953
                                        c-7.75-3.547-12.688-11.297-12.688-19.813v-6.828c0-3.25,0.516-6.453,1.578-9.5c0,0,29.203-38.969,29.203-74.875
                                        c0-44.875-28.703-73.75-64.156-73.75c-17.109,0-32.625,6.813-44.141,18.875c0.563,5.063,0.953,10.203,0.953,15.531
                                        c0,22.922-7.984,46.063-16.781,64.656c8.031,26.766,25.078,49.563,25.078,49.563c1.031,3.047,1.578,6.25,1.578,9.5v6.828
                                        c0,0.797-0.266,1.531-0.344,2.328l11.5,4.688c20.156,9.219,34,27.031,37.844,47.984l0.188,1.094l3.094,39.969h75.859
                                        c5.328,0,10.406-2.203,14-6.125c3.625-3.891,5.438-9.125,5.031-14.438L511.148,383.156z"/>
                                    <path d="M367.867,344.609l-56.156-22.953c-9.375-4.313-15.359-13.688-15.359-23.969v-8.281
                                        c0-3.906,0.625-7.797,1.922-11.5c0,0,35.313-47.125,35.313-90.594c0-54.313-34.734-89.234-77.594-89.234
                                        c-42.844,0-77.594,34.922-77.594,89.234c0,43.469,35.344,90.594,35.344,90.594c1.266,3.703,1.922,7.594,1.922,11.5v8.281
                                        c0,10.281-6.031,19.656-15.391,23.969l-56.156,22.953c-13.047,5.984-22.344,17.984-24.906,32.109l-2.891,37.203h139.672h139.672
                                        l-2.859-37.203C390.211,362.594,380.914,350.594,367.867,344.609z"/>
                                </g>
                            </svg>
                        </span>
                    </div>
                    <div class="role-content">
                        <h3 class="role-title">Club</h3>
                        <p class="role-description">Host tournaments, post matches, manage rosters, and build your club's legacy.</p>
                    </div>
                    <button class="role-select-btn" id="btnClub">Select Club</button>
                </div>
                
                <div class="role-card-item" id="roleCardRecruiter" onclick="selectRole('recruiter')">
                    <span class="material-icons-round check-icon">check_circle</span>
                    <div class="role-icon-wrapper">
                        <span class="role-emoji flex items-center justify-center">
                            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 297 297" width="1em" height="1em" fill="currentColor">
                                <g>
                                    <path d="M148.51,117.216c32.317,0,58.608-26.291,58.608-58.608S180.827,0,148.51,0c-32.317,0-58.608,26.291-58.608,58.608   S116.193,117.216,148.51,117.216z"/>
                                    <path d="m227.154,145.618c-0.025-0.008-0.073-0.026-0.098-0.032-7.631-1.864-30.999-5.133-30.999-5.133-2.638-0.812-5.457,0.585-6.406,3.188l-35.174,96.509c-2.029,5.567-9.903,5.567-11.932,0l-35.174-96.509c-0.766-2.102-2.75-3.42-4.876-3.42-0.504,0-24.531,3.369-32.53,5.358-21.858,5.435-35.645,26.929-35.645,49.329v80.302c0,12.034 9.756,21.79 21.79,21.79h184.782c12.034,0 21.79-9.756 21.79-21.79v-80.569c-0.001-22.303-14.328-42.096-35.528-49.023z"/>
                                    <path d="m161.775,138.613c-1.404-1.53-3.456-2.299-5.532-2.299h-15.485c-2.076,0-4.129,0.77-5.532,2.299-2.173,2.368-2.489,5.789-0.946,8.462l8.278,12.479-3.875,32.69 7.631,20.3c0.744,2.042 3.631,2.042 4.375,0l7.631-20.3-3.875-32.69 8.278-12.479c1.541-2.673 1.225-6.094-0.948-8.462z"/>
                                </g>
                            </svg>
                        </span>
                    </div>
                    <div class="role-content">
                        <h3 class="role-title">Recruiter</h3>
                        <p class="role-description">Post job listings, scout talented athletes, and manage your placement pipeline.</p>
                    </div>
                    <button class="role-select-btn" id="btnRecruiter">Select Recruiter</button>
                </div>
            </div>

            <!-- Multi-Step Form Container -->
            <div id="mainFormContainer" class="hidden opacity-0 translate-y-4 transition-all duration-500">
                
                <!-- Progress Bar -->
                <div class="glass-card mb-8 p-6">
                    <div class="flex justify-between items-center relative px-2">
                        <!-- Progress Line Background -->
                        <div class="absolute top-1/2 left-0 w-full h-[2px] bg-white/10 -translate-y-1/2 z-0"></div>
                        <div id="progressBarLine" class="absolute top-1/2 left-0 h-[2px] bg-primary -translate-y-1/2 z-0 transition-all duration-500" style="width: 0%;"></div>
                        
                        <!-- Step Indicators -->
                        <div class="step-indicator active z-10" data-step="1">
                            <div class="step-dot">1</div>
                            <span class="step-label">Basic Info</span>
                        </div>
                        <div class="step-indicator z-10" data-step="2">
                            <div class="step-dot">2</div>
                            <span class="step-label">Details</span>
                        </div>
                        <div class="step-indicator z-10" data-step="3">
                            <div class="step-dot">3</div>
                            <span class="step-label">Documents</span>
                        </div>
                        <div class="step-indicator z-10" data-step="4">
                            <div class="step-dot">4</div>
                            <span class="step-label">Review</span>
                        </div>
                    </div>
                </div>

                <form id="applyMultiStepForm" enctype="multipart/form-data">
                    <input type="hidden" name="requested_role" id="inputRequestedRole">
                    
                    <!-- STEP 1: Basic Information -->
                    <div class="form-step glass-card p-8" id="step1">
                        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
                            <span class="material-icons-round text-primary">person</span>
                            Basic Information
                        </h2>
                        
                        <div class="flex flex-col md:flex-row gap-8 mb-8">
                            <div class="flex flex-col items-center justify-start pt-2">
                                <input type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/png" class="hidden" onchange="previewImage(this, 'profilePreviewImg')">
                                <label for="profile_photo" class="avatar-upload-placeholder group relative overflow-hidden" id="avatarContainer">
                                    <span class="material-icons-round z-10" id="avatarPlaceholderIcon">photo_camera</span>
                                    <img id="profilePreviewImg" src="" class="absolute inset-0 w-full h-full object-cover hidden">
                                    <div class="absolute inset-0 bg-black/50 hidden group-hover:flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-20" id="avatarHoverOverlay">
                                        <span class="material-icons-round text-white">edit</span>
                                    </div>
                                </label>
                                <label for="profile_photo" class="text-xs text-primary cursor-pointer font-bold hover:underline mt-3 text-center">Click to upload photo</label>
                                <p class="text-[10px] text-gray-500 mt-1">Max 2MB. JPG/PNG</p>
                            </div>
                            
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-2">
                                <div class="floating-group">
                                    <input type="text" value="<?= htmlspecialchars($userName) ?>" class="glass-input w-full bg-white/5 opacity-70" readonly placeholder=" ">
                                    <label class="floating-label">Full Name*</label>
                                    <span class="material-icons-round input-icon">person</span>
                                </div>
                                <div class="floating-group">
                                    <input type="email" value="<?= htmlspecialchars($userEmail) ?>" class="glass-input w-full bg-white/5 opacity-70" readonly placeholder=" ">
                                    <label class="floating-label">Email*</label>
                                    <span class="material-icons-round input-icon">email</span>
                                </div>
                                <div class="floating-group md:col-span-2">
                                    <input type="tel" name="phone" id="phone" class="glass-input w-full" placeholder=" " required>
                                    <label class="floating-label">Phone Number*</label>
                                    <span class="material-icons-round input-icon">phone</span>
                                    <span class="error-msg" id="err-phone"></span>
                                </div>
                                <div class="floating-group">
                                    <input type="text" name="city" id="city" class="glass-input w-full" placeholder=" " required>
                                    <label class="floating-label">City*</label>
                                    <span class="material-icons-round input-icon">location_city</span>
                                    <span class="error-msg" id="err-city"></span>
                                </div>
                                <div class="floating-group">
                                    <input type="text" name="country" id="country" class="glass-input w-full" placeholder=" " required>
                                    <label class="floating-label">Country*</label>
                                    <span class="material-icons-round input-icon">public</span>
                                    <span class="error-msg" id="err-country"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end pt-4">
                            <button type="button" class="btn btn-primary px-10 flex items-center gap-2" onclick="nextStep(2)">
                                Next Step <span class="material-icons-round">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Organisation Details -->
                    <div class="form-step glass-card p-8 hidden" id="step2">
                        <h2 class="text-2xl font-bold mb-6 flex items-center gap-3" id="step2Title">
                            <span class="material-icons-round text-primary" id="step2Icon">stadium</span>
                            Organisation Details
                        </h2>
                        
                        <!-- Dynamic Fields for Club -->
                        <div id="fieldsClub" class="hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-2 mb-2">
                                <div class="floating-group md:col-span-2">
                                    <input type="text" name="organisation_name_club" id="orgNameClub" class="glass-input w-full" placeholder=" ">
                                    <label class="floating-label">Club Name*</label>
                                    <span class="material-icons-round input-icon">stadium</span>
                                    <span class="error-msg" id="err-orgNameClub"></span>
                                </div>
                                <div class="floating-group">
                                    <input type="number" name="year_established" id="yearEst" class="glass-input w-full" min="1900" max="<?= date('Y') ?>" placeholder=" ">
                                    <label class="floating-label">Year Established*</label>
                                    <span class="material-icons-round input-icon">event</span>
                                </div>
                                <div class="floating-group">
                                    <input type="number" name="team_player_count" id="playerCount" class="glass-input w-full" placeholder=" ">
                                    <label class="floating-label">Total Players / Members*</label>
                                    <span class="material-icons-round input-icon">groups</span>
                                </div>
                                <div class="floating-group md:col-span-2">
                                    <input type="text" name="sports_covered" id="sportsCovered" class="glass-input w-full" placeholder=" ">
                                    <label class="floating-label">Sports Covered*</label>
                                    <span class="material-icons-round input-icon">sports_soccer</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dynamic Fields for Recruiter -->
                        <div id="fieldsRecruiter" class="hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-2 mb-2">
                                <div class="floating-group md:col-span-2">
                                    <input type="text" name="organisation_name_rec" id="orgNameRec" class="glass-input w-full" placeholder=" ">
                                    <label class="floating-label">Company / Agency Name*</label>
                                    <span class="material-icons-round input-icon">business</span>
                                    <span class="error-msg" id="err-orgNameRec"></span>
                                </div>
                                <div class="floating-group">
                                    <input type="number" name="years_experience" id="yearsExp" class="glass-input w-full" placeholder=" ">
                                    <label class="floating-label">Years of Experience*</label>
                                    <span class="material-icons-round input-icon">work_history</span>
                                </div>
                                <div class="floating-group">
                                    <input type="number" name="athletes_placed" id="athletesPlaced" class="glass-input w-full" placeholder=" ">
                                    <label class="floating-label">Athletes Placed*</label>
                                    <span class="material-icons-round input-icon">people_alt</span>
                                </div>
                                <div class="floating-group md:col-span-2">
                                    <input type="text" name="sports_specialization" id="sportsSpec" class="glass-input w-full" placeholder=" ">
                                    <label class="floating-label">Sports Specialization*</label>
                                    <span class="material-icons-round input-icon">sports</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="floating-group mb-4 mt-2">
                            <textarea name="description" id="description" class="glass-input w-full min-h-[120px]" placeholder=" "></textarea>
                            <label class="floating-label" id="descLabel">Description*</label>
                            <span class="material-icons-round input-icon">description</span>
                            <span class="error-msg" id="err-description"></span>
                        </div>
                        
                        <div class="floating-group mb-8">
                            <input type="url" name="website" id="website" class="glass-input w-full" placeholder=" ">
                            <label class="floating-label">Official Website (Optional)</label>
                            <span class="material-icons-round input-icon">language</span>
                        </div>
                        
                        <div class="flex justify-between pt-4">
                            <button type="button" class="btn btn-outline px-8 flex items-center gap-2" onclick="nextStep(1)">
                                <span class="material-icons-round">arrow_back</span> Back
                            </button>
                            <button type="button" class="btn btn-primary px-10 flex items-center gap-2" onclick="nextStep(3)">
                                Next Step <span class="material-icons-round">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Documents & Social Media -->
                    <div class="form-step glass-card p-8 hidden" id="step3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <!-- Documents Sub-section -->
                            <div>
                                <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
                                    <span class="material-icons-round text-primary">description</span>
                                    Official Documents
                                </h2>
                                
                                <div class="floating-group mb-5">
                                    <select name="document_type" id="document_type" class="glass-input w-full" required>
                                        <!-- Populated via JS -->
                                    </select>
                                    <label class="floating-label">Document Type*</label>
                                    <span class="material-icons-round input-icon">folder</span>
                                    <span class="error-msg" id="err-docType"></span>
                                </div>
                                
                                <div class="form-group">
                                    <label class="text-xs text-gray-400 font-semibold mb-2 block">Upload Official Document*</label>
                                    <div class="file-drop-area glass-input p-6 border-dashed border-2 flex flex-col items-center justify-center text-center group cursor-pointer" onclick="document.getElementById('docUpload').click()">
                                        <span class="material-icons-round text-4xl text-gray-500 group-hover:text-primary transition-colors mb-2">cloud_upload</span>
                                        <p id="fileNameDisplay" class="text-xs text-gray-400">Click to upload or drag & drop</p>
                                        <p class="text-[10px] text-gray-600 mt-1">PDF, JPG, PNG (Max 5MB)</p>
                                    </div>
                                    <input type="file" name="document" id="docUpload" class="hidden" accept=".pdf,.jpg,.jpeg,.png" onchange="handleFileSelect(this)">
                                    <span class="error-msg" id="err-document"></span>
                                </div>
                                <p class="text-[11px] text-gray-500 mt-4 leading-relaxed bg-white/5 p-3 rounded border border-white/5">
                                    <span class="material-icons-round text-[14px] align-middle text-primary">info</span> 
                                    Your documents are reviewed only by AthleteHub admins and are kept strictly confidential.
                                </p>
                            </div>
                            
                            <!-- Social Media Sub-section -->
                            <div>
                                <h2 class="text-2xl font-bold mb-6 flex items-center gap-3">
                                    <span class="material-icons-round text-primary">language</span>
                                    Social Media Profiles
                                </h2>
                                <p class="text-[11px] text-gray-500 mb-4 font-semibold uppercase tracking-wider">Optional but recommended</p>
                                
                                <div class="social-grid">
                                    <div class="social-input-group flex items-center">
                                        <div class="social-icon bg-gradient-to-tr from-yellow-500 via-red-500 to-purple-500">
                                            <i class="fab fa-instagram"></i>
                                        </div>
                                        <input type="text" name="instagram" id="instagram" class="glass-input w-full rounded-l-none pl-3" placeholder="Handle">
                                    </div>
                                    <div class="social-input-group flex items-center">
                                        <div class="social-icon bg-[#1DA1F2]">
                                            <i class="fab fa-twitter"></i>
                                        </div>
                                        <input type="text" name="twitter" id="twitter" class="glass-input w-full rounded-l-none pl-3" placeholder="Handle">
                                    </div>
                                    <div class="social-input-group flex items-center">
                                        <div class="social-icon bg-[#0077b5]">
                                            <i class="fab fa-linkedin-in"></i>
                                        </div>
                                        <input type="text" name="linkedin" id="linkedin" class="glass-input w-full rounded-l-none pl-3" placeholder="Profile URL">
                                    </div>
                                    <div class="social-input-group flex items-center">
                                        <div class="social-icon bg-[#1877F2]">
                                            <i class="fab fa-facebook-f"></i>
                                        </div>
                                        <input type="text" name="facebook" id="facebook" class="glass-input w-full rounded-l-none pl-3" placeholder="Page / Profile">
                                    </div>
                                    <div class="social-input-group flex items-center md:col-span-2">
                                        <div class="social-icon bg-[#FF0000]">
                                            <i class="fab fa-youtube"></i>
                                        </div>
                                        <input type="text" name="youtube" id="youtube" class="glass-input w-full rounded-l-none pl-3" placeholder="Channel / @handle">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between pt-10">
                            <button type="button" class="btn btn-outline px-8 flex items-center gap-2" onclick="nextStep(2)">
                                <span class="material-icons-round">arrow_back</span> Back
                            </button>
                            <button type="button" class="btn btn-primary px-10 flex items-center gap-2" onclick="nextStep(4)">
                                Next Step <span class="material-icons-round">arrow_forward</span>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 4: Review & Submit -->
                    <div class="form-step glass-card p-8 hidden" id="step4">
                        <h2 class="text-2xl font-bold mb-8 flex items-center gap-3">
                            <span class="material-icons-round text-primary">fact_check</span>
                            Review Your Application
                        </h2>
                        
                        <div class="glass-card bg-black/20 p-8 border border-white/5 rounded-xl mb-10">
                            <!-- Identity Section -->
                            <div class="review-section">
                                <a href="javascript:void(0)" onclick="nextStep(1)" class="edit-btn">Edit</a>
                                <div class="flex items-center gap-4 mb-3">
                                    <img id="reviewProfileImg" src="../assets/img/default-avatar.png" class="w-12 h-12 rounded-full object-cover">
                                    <div>
                                        <h4 class="font-bold text-lg leading-tight"><?= htmlspecialchars($userName) ?></h4>
                                        <span id="reviewRole" class="micro-pill bg-primary/20 text-primary uppercase font-bold text-[10px]">Role</span>
                                    </div>
                                </div>
                                <table class="review-table">
                                    <tr><td class="label">Location</td><td class="value" id="reviewLocation">City, Country</td></tr>
                                    <tr><td class="label">Phone</td><td class="value" id="reviewPhone">Phone</td></tr>
                                </table>
                            </div>

                            <!-- Organisation Section -->
                            <div class="review-section" style="border-color: #3b82f6;">
                                <a href="javascript:void(0)" onclick="nextStep(2)" class="edit-btn">Edit</a>
                                <h4 class="font-bold text-base mb-3 text-blue-400" id="reviewOrgName">Organisation Name</h4>
                                <table class="review-table">
                                    <tr><td class="label">Experience / Est.</td><td class="value" id="reviewExp">X Years</td></tr>
                                    <tr><td class="label" id="reviewStatLabel">Members / Placed</td><td class="value" id="reviewStatVal">0</td></tr>
                                    <tr><td class="label">Website</td><td class="value text-blue-400"><span id="reviewWebsite" class="break-all">Website</span></td></tr>
                                </table>
                            </div>

                            <!-- Documents Section -->
                            <div class="review-section" style="border-color: #10b981;">
                                <a href="javascript:void(0)" onclick="nextStep(3)" class="edit-btn">Edit</a>
                                <table class="review-table">
                                    <tr><td class="label">Document Type</td><td class="value" id="reviewDocType">Type Name</td></tr>
                                    <tr><td class="label">File Uploaded</td><td class="value"><span class="flex items-center gap-1 text-emerald-400"><span class="material-icons-round text-sm">check_circle</span> <span id="reviewDocName">Filename.pdf</span></span></td></tr>
                                </table>
                            </div>

                            <!-- Socials Section -->
                            <div class="review-section" style="border-color: #8b5cf6;">
                                <a href="javascript:void(0)" onclick="nextStep(3)" class="edit-btn">Edit</a>
                                <p class="text-xs font-bold text-purple-400 mb-3 uppercase tracking-wider">Social Profiles</p>
                                <div class="flex flex-wrap gap-2">
                                    <div id="badgeInsta" class="hidden social-badge bg-gradient-to-tr from-yellow-500 via-red-500 to-purple-500"><i class="fab fa-instagram"></i></div>
                                    <div id="badgeTwitter" class="hidden social-badge bg-[#1DA1F2]"><i class="fab fa-twitter"></i></div>
                                    <div id="badgeLinkedIn" class="hidden social-badge bg-[#0077b5]"><i class="fab fa-linkedin-in"></i></div>
                                    <div id="badgeFB" class="hidden social-badge bg-[#1877F2]"><i class="fab fa-facebook-f"></i></div>
                                    <div id="badgeYT" class="hidden social-badge bg-[#FF0000]"><i class="fab fa-youtube"></i></div>
                                    <p id="noSocials" class="text-xs text-gray-500 italic">No social profiles added</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Declaration -->
                        <div class="bg-primary/5 border border-primary/20 p-6 rounded-lg mb-10">
                            <label class="flex items-start gap-4 cursor-pointer group">
                                <div class="mt-0.5">
                                    <input type="checkbox" id="declaration_check" required class="w-5 h-5 rounded border-white/20 bg-black/40 text-primary focus:ring-primary/50 transition-all">
                                </div>
                                <span class="text-sm text-gray-300 leading-relaxed group-hover:text-white transition-colors">
                                    I confirm all information and documents provided are genuine and accurate. I understand that submitting false information will result in permanent account suspension.
                                </span>
                            </label>
                        </div>
                        
                        <div class="flex justify-between pt-4">
                            <button type="button" class="btn btn-outline px-8 flex items-center gap-2" onclick="nextStep(3)">
                                <span class="material-icons-round">arrow_back</span> Back
                            </button>
                            <button type="submit" class="btn-submit-gradient rounded-lg px-12 py-4 text-lg font-bold flex justify-center items-center gap-3 w-full md:w-auto" id="finalSubmitBtn">
                                <span class="material-icons-round">verified</span>
                                Submit Application
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Success Overlay Card -->
            <div id="successCard" class="hidden glass-card success-card p-12 text-center border-green-500/30 bg-green-500/10 shadow-[0_0_50px_rgba(34,197,94,0.1)]">
                <div class="mb-8 relative inline-block">
                    <div class="absolute inset-0 bg-green-500/30 blur-2xl rounded-full animate-pulse"></div>
                    <span class="material-icons-round text-[100px] text-green-500 relative z-10 drop-shadow-[0_0_15px_rgba(34,197,94,0.5)]">check_circle</span>
                </div>
                <h2 class="text-3xl font-extrabold mb-4 text-white">Application Submitted Successfully!</h2>
                <p class="text-gray-300 max-w-lg mx-auto mb-10 leading-relaxed text-lg">
                    Your application has been received and is under review. Our admin team will verify your documents and notify you via your AthleteHub message box within 3-5 business days.
                </p>
                <div class="flex flex-col md:flex-row gap-4 justify-center">
                    <a href="feed.php" class="btn btn-primary px-10 py-3 shadow-glow font-bold">Go to Home Feed</a>
                    <a href="messages.php" class="btn btn-outline px-10 py-3 font-bold">Check Messages</a>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Extra Styles for this page -->
<style>
/* =============================================
   ROLE SELECTOR ROW
   ============================================= */
.role-options-row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2.5rem;
    width: 100%;
    padding: 2.5rem;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 2rem;
    background: rgba(255, 255, 255, 0.02);
}

@media (max-width: 768px) {
    .role-options-row {
        flex-direction: column;
        padding: 1.5rem;
    }
}

/* =============================================
   ROLE CARD — DEFAULT (border now visible)
   ============================================= */
.role-card-item {
    flex: 1;
    background: rgba(255, 255, 255, 0.02);
    border: 1.5px solid #000000;
    border-radius: 2rem;
    padding: 2.5rem 1.5rem;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: all 0.25s ease;
}

.role-card-item:hover {
    border-color: rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.04);
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

/* =============================================
   ROLE CARD — SELECTED STATE
   ============================================= */
.role-card-item.selected {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border: 2px solid var(--primary);
    box-shadow: 0 0 25px rgba(var(--primary-rgb), 0.25),
                0 15px 35px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
}

.role-card-item.dimmed {
    opacity: 0.4;
    filter: grayscale(1);
    transform: none;
}

/* =============================================
   ROLE CARD — CHECK ICON
   ============================================= */
.role-card-item .check-icon {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    color: var(--primary);
    font-size: 1.5rem;
    display: none;
    z-index: 10;
}

.role-card-item.selected .check-icon {
    display: block;
}

/* =============================================
   ROLE ICON WRAPPER
   ============================================= */
.role-icon-wrapper {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    border: 1.5px solid rgba(255, 255, 255, 0.12);
    transition: all 0.25s ease;
}

.role-card-item:hover .role-icon-wrapper {
    border-color: rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.08);
}

.role-card-item.selected .role-icon-wrapper {
    border-color: var(--primary);
    background: rgba(var(--primary-rgb), 0.12);
    box-shadow: 0 0 15px rgba(var(--primary-rgb), 0.2);
}

.role-emoji {
    font-size: 2.2rem;
}

/* =============================================
   ROLE CARD — TEXT
   ============================================= */
.role-title {
    font-size: 1.5rem;
    font-weight: 800;
    margin-bottom: 1rem;
    color: white;
}

.role-description {
    font-size: 0.875rem;
    color: var(--text-muted);
    line-height: 1.6;
    margin-bottom: 2rem;
    max-width: 250px;
}

.role-select-btn {
    width: auto;
    padding: 0.5rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 0.875rem;
    font-weight: 700;
    opacity: 0.6;
    pointer-events: none;
}

.role-card-item.selected .role-select-btn {
    color: var(--primary);
    opacity: 1;
}

/* =============================================
   PROGRESS BAR & STEP INDICATORS
   ============================================= */
.step-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 80px;
}

.step-dot {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--surface);
    border: 2px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 14px;
    color: var(--text-muted);
    transition: all 0.3s ease;
}

.step-indicator.active .step-dot {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 0 15px rgba(var(--primary-rgb), 0.4);
}

.step-indicator.completed .step-dot {
    background: #16A34A;
    border-color: #16A34A;
    color: white;
}

.step-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
}

.step-indicator.active .step-label {
    color: var(--primary);
    font-weight: 800;
}

/* =============================================
   FLOATING LABEL INPUTS
   ============================================= */
.floating-group {
    position: relative;
    margin-bottom: 0.5rem;
}

.floating-group input.glass-input,
.floating-group textarea.glass-input,
.floating-group select.glass-input {
    padding-top: 1.5rem;
    padding-bottom: 0.5rem;
    padding-left: 2.5rem;
}

.floating-group label.floating-label {
    position: absolute;
    left: 2.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.875rem;
    color: var(--text-muted);
    pointer-events: none;
    transition: all 0.2s ease-out;
}

.floating-group textarea.glass-input {
    padding-top: 1.5rem;
}

.floating-group textarea.glass-input ~ label.floating-label {
    top: 1.25rem;
    transform: none;
}

.floating-group select.glass-input ~ label.floating-label {
    top: 0.35rem;
    font-size: 0.65rem;
    color: var(--primary);
    transform: none;
}

.floating-group input:focus ~ label.floating-label,
.floating-group input:not(:placeholder-shown) ~ label.floating-label,
.floating-group textarea:focus ~ label.floating-label,
.floating-group textarea:not(:placeholder-shown) ~ label.floating-label {
    top: 0.35rem;
    font-size: 0.65rem;
    color: var(--primary);
    transform: translateY(0);
}

.floating-group .input-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.floating-group textarea.glass-input ~ .input-icon {
    top: 1.25rem;
    transform: none;
}

.glass-input:focus {
    box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.3);
    border-color: var(--primary);
}

.glass-input.has-error {
    border-color: #ef4444;
}

/* =============================================
   PROFILE PHOTO UPLOAD
   ============================================= */
.avatar-upload-placeholder {
    width: 128px;
    height: 128px;
    border-radius: 50%;
    border: 2px dashed rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    cursor: pointer;
    transition: all 0.2s ease;
}

.avatar-upload-placeholder:hover {
    border-color: var(--primary);
    background: rgba(var(--primary-rgb), 0.1);
}

.avatar-upload-placeholder .material-icons-round {
    font-size: 32px;
    color: var(--text-muted);
}

/* =============================================
   SOCIAL MEDIA GRID
   ============================================= */
.social-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 768px) {
    .social-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.social-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px 0 0 8px;
    font-size: 18px;
    color: white;
    flex-shrink: 0;
}

.social-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-size: 14px;
}

/* =============================================
   REVIEW SUMMARY
   ============================================= */
.review-section {
    border-left: 4px solid var(--primary);
    padding-left: 1rem;
    margin-bottom: 1.5rem;
    position: relative;
}

.review-section .edit-btn {
    position: absolute;
    top: 0;
    right: 0;
    font-size: 0.75rem;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-muted);
    transition: all 0.2s;
}

.review-section .edit-btn:hover {
    background: rgba(var(--primary-rgb), 0.2);
    color: var(--primary);
}

.review-table {
    width: 100%;
    border-collapse: collapse;
}

.review-table td {
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.review-table td.label {
    color: var(--text-muted);
    font-size: 0.875rem;
    width: 40%;
    font-weight: 600;
}

.review-table td.value {
    color: white;
    font-size: 0.875rem;
    font-weight: 500;
}

.review-table tr:last-child td {
    border-bottom: none;
}

/* =============================================
   SUBMIT BUTTON
   ============================================= */
.btn-submit-gradient {
    background: linear-gradient(135deg, var(--primary) 0%, rgba(var(--primary-rgb), 0.6) 100%);
    border: none;
    color: white;
    transition: all 0.3s ease;
}

.btn-submit-gradient:hover:not(:disabled) {
    filter: brightness(1.2);
    box-shadow: 0 0 20px rgba(var(--primary-rgb), 0.5);
    transform: translateY(-1px);
}

/* =============================================
   SUCCESS CARD
   ============================================= */
.success-card {
    animation: fadeInScale 0.5s ease-out forwards;
}

@keyframes fadeInScale {
    0%   { opacity: 0; transform: scale(0.95) translateY(10px); }
    100% { opacity: 1; transform: scale(1) translateY(0); }
}

/* =============================================
   UTILITIES
   ============================================= */
.error-msg {
    color: #ef4444;
    font-size: 10px;
    margin-top: 4px;
    display: none;
    font-weight: 600;
}

.rotate-anim {
    animation: rotate 2s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>

<!-- Font Awesome for social icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script src="../assets/js/apply_role.js?t=<?= time() ?>"></script>

<?php require_once '../includes/footer.php'; ?>