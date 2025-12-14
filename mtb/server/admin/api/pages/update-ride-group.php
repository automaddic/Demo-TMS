<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../auth/check-role-access.php';
enforceAccessOrDie('ride-groups.php', $pdo);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ride_group'])) {
        $changesMade = 0;

        foreach ($_POST['ride_group'] as $userId => $groupId) {
            $userId = (int)$userId;
            $groupId = is_numeric($groupId) ? (int)$groupId : null;

            // Confirm user exists
            $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $userCheck->execute([$userId]);
            if (!$userCheck->fetch()) continue;

            // Allow setting to NULL (clearing group)
            $stmt = $pdo->prepare("UPDATE users SET ride_group_id = ? WHERE id = ?");
            $stmt->execute([$groupId, $userId]);

            if ($stmt->rowCount() > 0) {
                $changesMade++;
            }
        }

        $_SESSION['flash_status'] = 'success';
        $_SESSION['flash_message'] = "$changesMade user(s) updated successfully.";
    } else {
        $_SESSION['flash_status'] = 'error';
        $_SESSION['flash_message'] = "Invalid request.";
    }
} catch (Exception $e) {
    error_log("Ride group update error: " . $e->getMessage());
    $_SESSION['flash_status'] = 'error';
    $_SESSION['flash_message'] = "An unexpected error occurred.";
}

header('Location: ' . $baseUrl . '/public/admin/ride-groups.php');
exit;
