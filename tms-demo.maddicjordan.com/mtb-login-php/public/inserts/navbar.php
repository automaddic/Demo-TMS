<?php

require_once '/home/automaddic/mtb/server/auth/check-role-access.php';

$profilePicture = $user['profile_picture_url'] ?? null;


$defaultAvatar = '{$baseUrl}/public/router-api.php?path=user-data/profile-pictures/defaults/default_pfp.png';

include $_SERVER['DOCUMENT_ROOT'] . '/mtb-login-php/public/inserts/accounts-modal.php';

// Determine which avatar to use
$avatarSrc = $profilePicture ?: $defaultAvatar;
?>

<link rel="stylesheet" href="<?= $baseUrl ?>/public/inserts/styles/navbar.css">
<div class="sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()">
    <span class="icon"></span>
</div>
<div id="sidebar-wrapper" class="sidebar-wrapper">

    <div class="sidebar">

        <div class="nav-top">
            <a href="<?= $baseUrl ?>/public/home.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'home.php' ? 'active' : '' ?>">
                <img class="icon" src="<?= $baseUrl ?>/public/images/icons/home.png" alt="Home Icon">
                <span class="nav-text">Home</span>
            </a>
            <?php if (userCanAccess('check-in.php', $pdo)): ?>
                <a href="<?= $baseUrl ?>/public/admin/check-in.php"
                    class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'check-in.php' ? 'active' : '' ?>">
                    <img class="icon" src="<?= $baseUrl ?>/public/images/icons/check-in.png" alt="Check Ins Icon">
                    <span class="nav-text">Check Ins</span>
                </a>
            <?php endif; ?>
            <?php if (userCanAccess('attendance.php', $pdo)): ?>
                    <a href="<?= $baseUrl ?>/public/admin/attendance.php"
                        class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : '' ?>">
                        <img class="icon" src="<?= $baseUrl ?>/public/images/icons/attendance.png" alt="Attendance Icon">
                        <span class="nav-text">RSVP</span>
                    </a>
                <?php endif; ?>
            <?php if (userCanAccess('admin-dashboard.php', $pdo)): ?>
                <a href="<?= $baseUrl ?>/public/admin/admin-dashboard.php"
                    class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : '' ?>"
                    id="admin">
                    <img class="icon" src="<?= $baseUrl ?>/public/images/icons/admin.png" alt="Admin Icon">
                    <span class="nav-text">Admin</span>
                </a>
            <?php elseif (
                !userCanAccess('admin-dashboard.php', $pdo) && (userCanAccess('attendance.php', $pdo) || userCanAccess('practice-days.php', $pdo)
                    || userCanAccess('edit-notes.php', $pdo) || userCanAccess('ride-groups.php', $pdo) || userCanAccess('check-in.php', $pdo) || userCanAccess('suspensions.php', $pdo))
            ): ?>
                <a class="nav-item disabled-nav-admin <?= basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : '' ?>"
                    id="admin">
                    <img class="icon" src="<?= $baseUrl ?>/public/images/icons/admin.png" alt="Admin Icon">
                    <span class="nav-text">Admin</span>
                </a>

            <?php endif; ?>
            <div class="admin-submenu">
                <?php if (userCanAccess('practice-days.php', $pdo)): ?>
                    <a href="<?= $baseUrl ?>/public/admin/practice-days.php"
                        class="nav-item sub-item <?= basename($_SERVER['PHP_SELF']) === 'practice-days.php' ? 'active' : '' ?>">
                        <img class="icon" src="<?= $baseUrl ?>/public/images/icons/practice-days.png" alt="Practice Icon">
                        <span class="nav-text">Practice Days</span>
                    </a>
                <?php endif; ?>
                <?php if (userCanAccess('edit-notes.php', $pdo)): ?>
                    <a href="<?= $baseUrl ?>/public/admin/edit-notes.php"
                        class="nav-item sub-item <?= basename($_SERVER['PHP_SELF']) === 'edit-notes.php' ? 'active' : '' ?>">
                        <img class="icon" src="<?= $baseUrl ?>/public/images/icons/practice-notes.png" alt="Notes Icon">
                        <span class="nav-text">Practice Notes</span>
                    </a>
                <?php endif; ?>
                <?php if (userCanAccess('ride-groups.php', $pdo)): ?>
                    <a href="<?= $baseUrl ?>/public/admin/ride-groups.php"
                        class="nav-item sub-item <?= basename($_SERVER['PHP_SELF']) === 'ride-groups.php' ? 'active' : '' ?>">
                        <img class="icon" src="<?= $baseUrl ?>/public/images/icons/ride-groups.png" alt="Ride Groups Icon">
                        <span class="nav-text">Ride Groups</span>
                    </a>
                <?php endif; ?>
            
                <?php if (userCanAccess('suspensions.php', $pdo)): ?>
                    <a href="<?= $baseUrl ?>/public/admin/suspensions.php"
                        class="nav-item sub-item <?= basename($_SERVER['PHP_SELF']) === 'suspensions.php' ? 'active' : '' ?>">
                        <img class="icon" src="<?= $baseUrl ?>/public/images/icons/suspend.png" alt="Suspensions Icon">
                        <span class="nav-text">Suspensions</span>
                    </a>
                <?php endif; ?>
                <?php if (userCanAccess('import-users.php', $pdo)): ?>
                    <a href="<?= $baseUrl ?>/public/admin/import-users.php"
                        class="nav-item sub-item <?= basename($_SERVER['PHP_SELF']) === 'import-users.php' ? 'active' : '' ?>">
                        <img class="icon" src="<?= $baseUrl ?>/public/images/icons/import.png" alt="Import Icon">
                        <span class="nav-text">Import Users</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-bottom">
            <!-- Accounts Button with user profile pic -->
            <a href="?modal=accounts" class="nav-item" id="account-button">
                <img class="icon" src="<?= htmlspecialchars($avatarSrc) ?>" alt="X"
                    style="border-radius:50%;">
                <span class="nav-text">Account</span>
            </a>


            <a href="<?= $baseUrl ?>/public/router-api.php?path=logout.php" class="nav-item" id="logout">
                <img class="icon" src="<?= $baseUrl ?>/public/images/icons/logout.png" alt="Logout Icon">
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </div>


</div>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<script>
    function toggleSidebar() {
        const wrapper = document.getElementById('sidebar-wrapper');
        const overlay = document.querySelector('.sidebar-overlay');
        const toggle = document.getElementById('sidebar-toggle');
        const body = document.body;

        wrapper.classList.toggle('expanded');
        overlay.classList.toggle('active');
        toggle.classList.toggle('active');
        body.style.overflow = overlay.classList.contains('active') ? 'hidden' : '';



    }


</script>
