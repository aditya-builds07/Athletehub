document.addEventListener('DOMContentLoaded', () => {
    const basePath = window.AthleteHubBaseUrl || '';
    
    let currentBase64Image = null;
    const profileImageInput = document.getElementById('profileImageInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarInitials = document.getElementById('avatarInitials');

    if (profileImageInput) {
        profileImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        // Profile pictures are square, max 400x400
                        const MAX_SIZE = 400;
                        let size = Math.min(img.width, img.height);
                        let sourceX = (img.width - size) / 2;
                        let sourceY = (img.height - size) / 2;

                        canvas.width = MAX_SIZE;
                        canvas.height = MAX_SIZE;
                        const ctx = canvas.getContext('2d');
                        
                        // Crop to center and draw
                        ctx.drawImage(img, sourceX, sourceY, size, size, 0, 0, MAX_SIZE, MAX_SIZE);

                        currentBase64Image = canvas.toDataURL('image/jpeg', 0.85);

                        if (avatarPreview) {
                            avatarPreview.style.display = 'block';
                            avatarPreview.src = currentBase64Image;
                        }
                        if (avatarInitials) avatarInitials.style.display = 'none';
                    };
                    img.src = evt.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    const editProfileForm = document.getElementById('editProfileForm');
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const name = document.getElementById('epName').value.trim();
            const sport = document.getElementById('epSport').value.trim();
            const location = document.getElementById('epLocation').value.trim();
            const bio = document.getElementById('epBio').value.trim();

            if (!name) {
                if (typeof showToast === 'function') showToast('Name is required', 'warning');
                return;
            }

            const saveBtn = document.getElementById('saveProfileBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">hourglass_empty</span> Saving...';

            fetch(`${basePath}/api/profile.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_profile',
                    name,
                    sport,
                    location,
                    bio,
                    profile_pic: currentBase64Image
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') showToast('Profile updated!', 'success');
                    setTimeout(() => {
                        window.location.href = `${basePath}/pages/profile.php`;
                    }, 1000);
                } else {
                    if (typeof showToast === 'function') showToast(data.message || 'Failed to update', 'error');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">save</span> Save Changes';
                }
            })
            .catch(() => {
                if (typeof showToast === 'function') showToast('Network Error', 'error');
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<span class="material-icons-round" style="font-size:16px">save</span> Save Changes';
            });
        });
    }
});
