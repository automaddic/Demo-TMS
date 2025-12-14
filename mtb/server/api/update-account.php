<?php

require_once __DIR__ . '/../config/bootstrap.php';

$userId = $_SESSION['user']['id'];

$preferred = trim($_POST['preferred_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$schoolId = $_POST['school_id'] ?: null;
$rideGroupId = $_POST['ride_group_id'] ?: null;
$teamId = $_POST['team_id'] ?: null;
$wantsTexts = isset($_POST['wants_texts']) ? 1 : 0;
$wantsEmails = isset($_POST['wants_emails']) ? 1 : 0;
$phone = $wantsTexts ? trim($_POST['phone_number']) : null;

// Handle profile picture upload
$profilePicturePath = null;
if (isset($_FILES['pfp']) && $_FILES['pfp']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = "{$baseUrl}/server/user-data/profile-pictures/{$userId}/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    $ext = pathinfo($_FILES['pfp']['name'], PATHINFO_EXTENSION);
    $safeFilename = uniqid('pfp_', true) . '.' . $ext;
    $destination = $uploadDir . $safeFilename;
    move_uploaded_file($_FILES['pfp']['tmp_name'], $destination);
    $profilePicturePath = "{$baseUrl}/server/user-data/profile-pictures/{$userId}/{$safeFilename}";
}

// Update user data
$query = "
    UPDATE users SET
      preferred_name = ?, email = ?, username = ?,
      school_id = ?, ride_group_id = ?, team_id = ?,
      phone_number = ?, wants_texts = ?, wants_emails = ?
      " . ($profilePicturePath ? ", profile_picture_url = ?" : "") . "
    WHERE id = ?
";
$params = [$preferred, $email, $username, $schoolId, $rideGroupId, $teamId, $phone, $wantsTexts, $wantsEmails];
if ($profilePicturePath) $params[] = $profilePicturePath;
$params[] = $userId;

$stmt = $pdo->prepare($query);
if (!$stmt->execute($params)) {
    $error = $stmt->errorInfo();
    die("Error updating profile: " . $error[2]);
}

// Refresh session
$stmt2 = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt2->execute([$userId]);
$_SESSION['user'] = $stmt2->fetch(PDO::FETCH_ASSOC);

$redirectUrl = $_POST['return_url'] ?? $baseUrl . '/public/home.php';
header("Location: $redirectUrl");
exit;