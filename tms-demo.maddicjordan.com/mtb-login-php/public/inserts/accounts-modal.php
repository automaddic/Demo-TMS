<?php

require_once '/home/automaddic/mtb/server/config/bootstrap.php';
require_once '/home/automaddic/mtb/server/api/data/user.php';
require_once '/home/automaddic/mtb/server/api/data/ride-groups.php';
require_once '/home/automaddic/mtb/server/api/data/schools.php';
require_once '/home/automaddic/mtb/server/api/data/teams.php';

$user = getCurrentUser($pdo);
if (!$user) {
    exit('Unauthorized');
}
$rideGroups = getRideGroups($pdo);
$schools = getSchools($pdo);
$teams = getTeams($pdo);

$isGoogleUser = is_null($user['password']);
$pfp = $user['profile_picture_url'] ?: $baseUrl . 'router-api.php?path=user-data/profile-pictures/defaults/default_pfp.png';
?>

<link rel="stylesheet" href="<?= $baseUrl ?>/public/inserts/styles/accounts-modal.css">
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js">
</script>

<?php if (isset($_GET['modal']) && $_GET['modal'] === 'accounts'): ?>
    <script>
        document.body.classList.add('modal-open');
    </script>
<?php endif; ?>

<div id="accounts-modal"
    class="modal-overlay<?= isset($_GET['modal']) && $_GET['modal'] === 'accounts' ? ' active' : '' ?>">



    <div class="modal-content">

        <a class="modal-close" href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>"
            aria-label="Close Accounts Modal">&times;</a>

        <h2 id="accounts-modal-title">Account Settings</h2>

        <form id="accounts-form" action="router-api.php?path=api/update-account.php" method="POST"
            enctype="multipart/form-data">

            <input type="hidden" name="return_url"
                value="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?')) ?>">



            <div class="pfp-row">
                <div class="pfp-wrapper">
                    <img id="pfp-preview-img" src="<?= htmlspecialchars($pfp) ?>" alt="Profile Picture Preview" />
                    <label>
                        <input type="file" name="pfp" id="pfp" accept="image/*"
                            onchange="document.getElementById('pfp-upload-btn').innerText = 'Change PFP';" />
                        <button type="button" id="pfp-upload-btn"
                            onclick="document.getElementById('pfp').click();">Upload PFP</button>
                    </label>
                </div>
                <div class="preferred-name-row">
                    <div class="preferred-name">
                        <label for="preferred_name">Preferred Name</label>
                        <input type="text" name="preferred_name"
                            value="<?= htmlspecialchars($user['preferred_name'] ?? '') ?>" />
                    </div>

                    <div class="reset-password">
                        <a href="<?= $baseUrl ?>/public/reset-password.php" class="reset-password-btn">Reset
                            Password</a>
                    </div>
                </div>
            </div>

            <label for="email">Email <?= $isGoogleUser ? '(Google-linked, cannot change)' : '*' ?></label>
            <input type="email" name="email" <?= $isGoogleUser ? 'readonly' : 'required' ?>
                value="<?= htmlspecialchars($user['email']) ?>" />

            <label for="username">Username *</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($user['username']) ?>" />

            <label for="school_id">School</label>
            <select name="school_id">
                <option value="">Select School</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?= $school['id'] ?>" <?= $user['school_id'] == $school['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($school['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="ride_group_id">Ride Group</label>
            <select name="ride_group_id">
                <option value="">Select Ride Group</option>
                <?php foreach ($rideGroups as $group): ?>
                    <option value="<?= $group['id'] ?>" <?= $user['ride_group_id'] == $group['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="team_id">Team</label>
            <select name="team_id">
                <option value="">Select Team</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['id'] ?>" <?= $user['team_id'] == $team['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($team['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="wants_texts" id="wants_texts" <?= $user['wants_texts'] ? 'checked' : '' ?>>
                    Receive Text Updates
                </label>
                <label>
                    <input type="checkbox" name="wants_emails" <?= $user['wants_emails'] ? 'checked' : '' ?>>
                    Receive Email Updates
                </label>
            </div>

            <!-- Phone Number (conditionally shown) -->
            <div id="phone-field" style="display: <?= $user['wants_texts'] ? 'block' : 'none' ?>;">
                <label for="phone_number">Phone Number</label>
                <input type="tel" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" />
            </div>

            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>
<div id="pfp-cropper-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="width: 400px; max-width: 90%; text-align: center;">
        <h3>Crop your picture</h3>
        <div style="width: 100%; height: 300px; overflow: hidden;">
            <img id="pfp-cropper-img" src="" alt="Crop me" style="max-width: 100%;" />
        </div>
        <div style="margin-top: 1rem;">
            <button id="pfp-crop-cancel" type="button">Cancel</button>
            <button id="pfp-crop-ok" type="button">Crop &amp; OK</button>
        </div>
    </div>
</div>
<script>
    (function () {
        const fileInput = document.getElementById('pfp');
        const inlinePreview = document.getElementById('pfp-preview-img');
        const cropperModal = document.getElementById('pfp-cropper-modal');
        const cropperImg = document.getElementById('pfp-cropper-img');
        const cancelBtn = document.getElementById('pfp-crop-cancel');
        const okBtn = document.getElementById('pfp-crop-ok');
        let cropper;  // will hold our CropperJS instance

        // 1) When a file is selected, read it and open the cropper modal
        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;

            const reader = new FileReader();
            reader.onload = function (ev) {
                cropperImg.src = ev.target.result;

                // show modal
                cropperModal.style.display = 'flex';

                // init cropper (destroy old if exists)
                if (cropper) cropper.destroy();
                cropper = new Cropper(cropperImg, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                });
            };
            reader.readAsDataURL(file);
        });

        // 2) Cancel just closes the cropper (and resets the input)
        cancelBtn.addEventListener('click', function () {
            cropperModal.style.display = 'none';
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            // clear file input so user can re-select
            fileInput.value = '';
        });

        // 3) OK takes the cropped area, updates the inline preview AND replaces the file input
        okBtn.addEventListener('click', function () {
            if (!cropper) return;

            // get a Blob of the cropped area
            cropper.getCroppedCanvas().toBlob(function (blob) {
                // 3a) update inline preview
                const url = URL.createObjectURL(blob);
                inlinePreview.src = url;

                // 3b) replace fileInput's file with the blob so your form will upload it
                const dt = new DataTransfer();
                const croppedFile = new File([blob], 'pfp.png', { type: blob.type });
                dt.items.add(croppedFile);
                fileInput.files = dt.files;

                // cleanup
                cropperModal.style.display = 'none';
                cropper.destroy();
                cropper = null;
            }, 'image/png');
        });

        // 4) click outside content closes too
        cropperModal.addEventListener('click', function (e) {
            if (e.target === cropperModal) cancelBtn.click();
        });
    })();
</script>
