/**
 * AthleteHub — Apply Role Multi-Step Logic
 */

let currentStep = 1;
let selectedRole = '';

const clubDocTypes = [
    "Sports Club Registration Certificate",
    "Government Sports Body Authorization Letter",
    "Municipal Sports Association Certificate",
    "National Sports Federation Letter"
];

const recruiterDocTypes = [
    "Company Registration Certificate",
    "Sports Agency License",
    "Official Employment / Authorization Letter",
    "Professional Scouting Credentials"
];

/**
 * Handle Role Selection
 */
function selectRole(role) {
    selectedRole = role;
    document.getElementById('inputRequestedRole').value = role;
    
    // UI Feedback
    const cardClub = document.getElementById('roleCardClub');
    const cardRec = document.getElementById('roleCardRecruiter');
    const btnClub = document.getElementById('btnClub');
    const btnRec = document.getElementById('btnRecruiter');
    
    if (role === 'club') {
        cardClub.classList.add('selected');
        cardClub.classList.remove('dimmed');
        cardRec.classList.add('dimmed');
        cardRec.classList.remove('selected');
        btnClub.classList.add('btn-primary');
        btnClub.textContent = 'Selected';
        btnRec.classList.remove('btn-primary');
        btnRec.textContent = 'Select Recruiter';
        
        // Step 2 Labels
        document.getElementById('step2Title').innerHTML = '<span class="material-icons-round text-primary">stadium</span> Club Details';
        document.getElementById('descLabel').textContent = 'Club Description*';
        document.getElementById('fieldsClub').classList.remove('hidden');
        document.getElementById('fieldsRecruiter').classList.add('hidden');
        populateDocTypes(clubDocTypes);
    } else {
        cardRec.classList.add('selected');
        cardRec.classList.remove('dimmed');
        cardClub.classList.add('dimmed');
        cardClub.classList.remove('selected');
        btnRec.classList.add('btn-primary');
        btnRec.textContent = 'Selected';
        btnClub.classList.remove('btn-primary');
        btnClub.textContent = 'Select Club';
        
        // Step 2 Labels
        document.getElementById('step2Title').innerHTML = '<span class="material-icons-round text-primary">person_search</span> Recruiter Details';
        document.getElementById('descLabel').textContent = 'About Your Agency*';
        document.getElementById('fieldsRecruiter').classList.remove('hidden');
        document.getElementById('fieldsClub').classList.add('hidden');
        populateDocTypes(recruiterDocTypes);
    }
    
    // Show Form
    const container = document.getElementById('mainFormContainer');
    container.classList.remove('hidden');
    setTimeout(() => {
        container.classList.remove('opacity-0', 'translate-y-4');
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 10);
}

/**
 * Populate Document Dropdown
 */
function populateDocTypes(types) {
    const select = document.getElementById('document_type');
    select.innerHTML = '<option value="" disabled selected>Select document type...</option>';
    types.forEach(type => {
        const opt = document.createElement('option');
        opt.value = type;
        opt.textContent = type;
        select.appendChild(opt);
    });
}

/**
 * Step Navigation
 */
function nextStep(step) {
    if (step > currentStep) {
        if (!validateStep(currentStep)) return;
    }
    
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(s => s.classList.add('hidden'));
    
    // Show current
    document.getElementById(`step${step}`).classList.remove('hidden');
    currentStep = step;
    
    // Update Indicators
    updateStepIndicators();
    
    // If step 4, populate review
    if (step === 4) populateReview();
    
    window.scrollTo({ top: document.getElementById('mainFormContainer').offsetTop - 100, behavior: 'smooth' });
}

/**
 * Update Progress Bar & Indicators
 */
function updateStepIndicators() {
    const indicators = document.querySelectorAll('.step-indicator');
    indicators.forEach(ind => {
        const stepNum = parseInt(ind.dataset.step);
        ind.classList.remove('active', 'completed');
        
        if (stepNum === currentStep) {
            ind.classList.add('active');
        } else if (stepNum < currentStep) {
            ind.classList.add('completed');
            ind.querySelector('.step-dot').innerHTML = '<span class="material-icons-round">check</span>';
        } else {
            ind.querySelector('.step-dot').textContent = stepNum;
        }
    });
    
    const progressLine = document.getElementById('progressBarLine');
    const percent = ((currentStep - 1) / 3) * 100;
    progressLine.style.width = `${percent}%`;
}

/**
 * Validation
 */
function validateStep(step) {
    let isValid = true;
    clearErrors();
    
    if (step === 1) {
        if (!val('phone')) isValid = err('phone', 'Phone number is required');
        if (!val('city')) isValid = err('city', 'City is required');
        if (!val('country')) isValid = err('country', 'Country is required');
    }
    
    if (step === 2) {
        if (selectedRole === 'club') {
            if (!val('orgNameClub')) isValid = err('orgNameClub', 'Club name is required');
        } else {
            if (!val('orgNameRec')) isValid = err('orgNameRec', 'Company name is required');
        }
        if (!val('description')) isValid = err('description', 'Description is required');
    }
    
    if (step === 3) {
        if (!val('document_type')) isValid = err('docType', 'Select a document type');
        if (!document.getElementById('docUpload').files[0]) isValid = err('document', 'Document upload is required');
    }
    
    return isValid;
}

function val(id) {
    const el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

function err(id, msg) {
    const el = document.getElementById(`err-${id}`);
    if (el) {
        el.textContent = msg;
        el.style.display = 'block';
    }
    return false;
}

function clearErrors() {
    document.querySelectorAll('.error-msg').forEach(el => el.style.display = 'none');
}

/**
 * File Preview
 */
function previewImage(input, targetId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(targetId);
            img.src = e.target.result;
            img.classList.remove('hidden');
            const icon = document.getElementById('avatarPlaceholderIcon');
            if(icon) icon.classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function handleFileSelect(input) {
    const display = document.getElementById('fileNameDisplay');
    if (input.files && input.files[0]) {
        display.textContent = input.files[0].name;
        display.classList.add('text-primary', 'font-bold');
    }
}

/**
 * Populate Review Step — null-safe version.
 * A crash anywhere here causes all subsequent fields to stay blank.
 */
function populateReview() {
    // Safe setter helper — never throws
    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text || '—';
    }

    // ── Profile photo ──────────────────────────────────────────
    try {
        const previewImg = document.getElementById('profilePreviewImg');
        const reviewImg  = document.getElementById('reviewProfileImg');
        if (reviewImg && previewImg && previewImg.src && !previewImg.classList.contains('hidden')) {
            reviewImg.src = previewImg.src;
        }
    } catch (e) { /* ignore photo errors */ }

    // ── Role badge ─────────────────────────────────────────────
    setText('reviewRole', selectedRole.toUpperCase());

    // ── Location & Phone ───────────────────────────────────────
    const city    = val('city');
    const country = val('country');
    setText('reviewLocation', [city, country].filter(Boolean).join(', ') || '—');
    setText('reviewPhone', val('phone'));

    // ── Organisation ───────────────────────────────────────────
    const orgName = selectedRole === 'club' ? val('orgNameClub') : val('orgNameRec');
    setText('reviewOrgName', orgName);

    if (selectedRole === 'club') {
        const yearEst    = val('yearEst');
        const playerCnt  = val('playerCount');
        setText('reviewExp',       yearEst    ? `Est. ${yearEst}`    : '—');
        setText('reviewStatLabel', 'Members');
        setText('reviewStatVal',   playerCnt  || '0');
    } else {
        const yearsExp      = val('yearsExp');
        const athletesPlaced = val('athletesPlaced');
        setText('reviewExp',       yearsExp       ? `${yearsExp} Yrs Exp` : '—');
        setText('reviewStatLabel', 'Placed');
        setText('reviewStatVal',   athletesPlaced || '0');
    }

    const website = val('website');
    setText('reviewWebsite', website || 'No website provided');

    // ── Document ───────────────────────────────────────────────
    const docFile = document.getElementById('docUpload')?.files?.[0];
    setText('reviewDocName', docFile ? docFile.name : 'No file selected');
    setText('reviewDocType', val('document_type') || 'Not selected');

    // ── Social profiles ────────────────────────────────────────
    const socialMap = {
        instagram: 'badgeInsta',
        twitter:   'badgeTwitter',
        linkedin:  'badgeLinkedIn',
        facebook:  'badgeFB',
        youtube:   'badgeYT',
    };

    let hasSocials = false;
    const noSocialsEl = document.getElementById('noSocials');

    Object.entries(socialMap).forEach(([inputId, badgeId]) => {
        const value  = val(inputId);
        const badge  = document.getElementById(badgeId);
        if (badge) {
            if (value) {
                badge.classList.remove('hidden');
                hasSocials = true;
            } else {
                badge.classList.add('hidden');
            }
        }
    });

    if (noSocialsEl) {
        noSocialsEl.classList.toggle('hidden', hasSocials);
    }
}

function capitalize(s) {
    if (s === 'instagram') return 'Insta';
    if (s === 'twitter') return 'Twitter';
    if (s === 'linkedin') return 'LinkedIn';
    if (s === 'facebook') return 'FB';
    if (s === 'youtube') return 'YT';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

/**
 * Handle Final Submission
 */
document.getElementById('applyMultiStepForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('finalSubmitBtn');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="material-icons-round rotate-anim">sync</span> Submitting...';
    btn.disabled = true;
    
    const formData = new FormData(this);
    
    // Fix org name mapping (the form has two separate inputs for UI but we need one for API)
    if (selectedRole === 'club') {
        formData.set('organisation_name', val('orgNameClub'));
    } else {
        formData.set('organisation_name', val('orgNameRec'));
    }
    
    // ── Attach CSRF token from meta tag ──────────────────────
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        formData.set('csrf_token', csrfMeta.content);
    }

    try {
        const basePath = window.AthleteHubBaseUrl || '';
        const response = await fetch(`${basePath}/api/role_application.php?action=submit`, {
            method: 'POST',
            body: formData
        });

        // ── Surface HTTP-level errors before trying to parse JSON ──
        if (!response.ok) {
            const text = await response.text();
            console.error('Server error response:', text);
            const msg = response.status === 403
                ? 'Session expired. Please refresh the page and try again.'
                : `Server error (${response.status}). Please try again.`;
            if (window.showToast) showToast(msg, 'error');
            else alert(msg);
            return;
        }

        const result = await response.json();

        if (result.success) {
            // Show Success Card
            document.getElementById('mainFormContainer').classList.add('hidden');
            document.getElementById('roleSelectorSection').classList.add('hidden');
            const successCard = document.getElementById('successCard');
            successCard.classList.remove('hidden');
            window.scrollTo({ top: successCard.offsetTop - 150, behavior: 'smooth' });

            if (window.showToast) showToast('Application submitted successfully!', 'success');
        } else {
            if (window.showToast) showToast(result.error || 'Submission failed. Please check all fields.', 'error');
            else alert(result.error || 'Submission failed');
        }
    } catch (error) {
        console.error('Submission error:', error);
        if (window.showToast) showToast('A network error occurred. Please check your connection and try again.', 'error');
    } finally {
        btn.innerHTML = origHtml;
        btn.disabled = false;
    }
});
