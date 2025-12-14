<?php
try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once __DIR__ . '/../scripts/google-sheet-match.php';
    require_once __DIR__ . '/../scripts/local-file-match.php';  // <-- include your local match script

    $userId = $_SESSION['user']['id'] ?? null;
    error_log("[SetupProfile] Start processing for user ID: " . var_export($userId, true));

    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $preferred = trim($_POST['preferred_name'] ?? '');
    $rideGroup = $_POST['ride_group_id'] ?? null;
    $wantsTexts = isset($_POST['wants_texts']) ? 1 : 0;
    $wantsEmails = isset($_POST['wants_emails']) ? 1 : 0;
    $phoneNumber = trim($_POST['phone_number'] ?? '');

    error_log("[SetupProfile] Received input - First: $first, Last: $last, Preferred: $preferred, RideGroup: $rideGroup, WantsTexts: $wantsTexts, WantsEmails: $wantsEmails, Phone: $phoneNumber");

    if (!$userId || !$first || !$last || !$rideGroup) {
        $msg = "Missing required fields or session user ID.";
        error_log("[SetupProfile][Error] $msg Input: " . json_encode($_POST));
        $_SESSION['error'] = 'Missing required fields.';
        header("Location: {$baseUrl}/public/home.php");
        exit;
    }

    $forceSave = ($_POST['force_save'] ?? '0') === '1';
    error_log("[SetupProfile] Force save flag: " . ($forceSave ? 'true' : 'false'));

    // CONFIG: decide which match checks to do
    $useGoogleSheet = false;
    $useLocalFile = true;

    $localFilePath = __DIR__ . '/../user-data/spreadsheets/latest.xlsx';

    // Run checks
    $googleMatch = false;
    if ($useGoogleSheet) {
        error_log("[SetupProfile] Running Google Sheet match check.");
        $googleMatch = isNameInTeamSheet($first, $last);
        error_log("[SetupProfile] Google Sheet match result: " . ($googleMatch ? 'true' : 'false'));
    }

    $localMatch = false;
    if ($useLocalFile) {
        error_log("[SetupProfile] Running Local File match check.");
        $localMatch = isNameInLocalFile($first, $last, $localFilePath);
        error_log("[SetupProfile] Local File match result: " . ($localMatch ? 'true' : 'false'));
    }

    // Final decision: matched if either source matched
    $match = $googleMatch || $localMatch;
    error_log("[SetupProfile] Final match result: " . ($match ? 'true' : 'false'));

    if (!$match && !$forceSave) {
        error_log("[SetupProfile] User does NOT match and not forcing save; setting suspension pending.");
        $_SESSION['suspension_pending'] = true;

        // Store submitted fields temporarily
        $_SESSION['profile_form'] = [
            'first_name' => $first,
            'last_name' => $last,
            'preferred_name' => $preferred,
            'ride_group_id' => $rideGroup,
            'wants_texts' => $wantsTexts,
            'wants_emails' => $wantsEmails,
            'phone_number' => $phoneNumber
        ];

        header("Location: {$baseUrl}/public/home.php");
        exit;
    }

    if (!$match && $forceSave) {
        error_log("[SetupProfile] User not matched but force save true; suspending user ID: $userId");
        $stmt = $pdo->prepare("UPDATE users SET is_suspended = 1 WHERE id = ?");
        if (!$stmt->execute([$userId])) {
            $errorInfo = $stmt->errorInfo();
            error_log("[SetupProfile][Error] Failed to update is_suspended for user ID $userId: " . print_r($errorInfo, true));
            die("DB update error: " . htmlspecialchars($errorInfo[2]));
        }
        unset($_SESSION['profile_form']);
    }

    // Save profile data regardless (matched or forced save)
    error_log("[SetupProfile] Updating user profile for user ID: $userId");
    $stmt = $pdo->prepare("
        UPDATE users SET
          first_name = ?, last_name = ?, preferred_name = ?, phone_number = ?,
          ride_group_id = ?, wants_texts = ?, wants_emails = ?, is_profile_complete = 1
        WHERE id = ?
    ");

    if (!$stmt->execute([$first, $last, $preferred ?: null, $phoneNumber, $rideGroup, $wantsTexts, $wantsEmails, $userId])) {
        $errorInfo = $stmt->errorInfo();
        error_log("[SetupProfile][Error] Failed to update profile for user ID $userId: " . print_r($errorInfo, true));
        die("DB update error: " . htmlspecialchars($errorInfo[2]));
    }

    error_log("[SetupProfile] Profile updated successfully for user ID: $userId");

    $stmt2 = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt2->execute([$userId]);
    $user = $stmt2->fetch(PDO::FETCH_ASSOC);

    $_SESSION['user'] = $user;

    if (isset($_SESSION['suspension_pending'])) {
        unset($_SESSION['suspension_pending']);
    }

    $_SESSION['success'] = 'Profile updated successfully.';
    header("Location: {$_ENV['BASE_URL']}/public/home.php");
    exit;

} catch (Throwable $e) {
    error_log("[SetupProfile][Exception] " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);

    // Show error details directly for debugging - remove on production
    echo "<h2>An unexpected error occurred.</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    exit;
}
