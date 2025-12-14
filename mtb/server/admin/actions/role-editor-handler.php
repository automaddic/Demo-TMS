<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.$baseUrl.'/public/admin/admin-dashboard.php?mode=role-editor');
    exit;
}

$currentUserId = $_SESSION['user']['id'] ?? 0;
$currentUserLevel = $_SESSION['user']['role_level'] ?? 0;

if (!isset($_POST['role']) || !is_array($_POST['role'])) {
    header('Location: '.$baseUrl.'/public/admin/admin-dashboard.php?mode=role-editor&error=1');
    exit;
}

$errors = [];
foreach ($_POST['role'] as $userId => $newLevel) {
    $userId = (int)$userId;
    $newLevel = (int)$newLevel;
    // Prevent changing own role or setting higher than self
    if ($userId === $currentUserId || $newLevel > $currentUserLevel) {
        continue;
    }
    // Update in DB
    $stmt = $pdo->prepare("UPDATE users SET role_level = ? WHERE id = ?");
    if (!$stmt->execute([$newLevel, $userId])) {
        $errors[] = $userId;
    }
}
if (empty($errors)) {
    header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=role-editor&saved=1");
} else {
    header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=role-editor&error=1");
}
exit;