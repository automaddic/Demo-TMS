<?php
// import-selected-users-alt.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie("import-users.php", $pdo);

// read raw JSON POST body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if ($data === null) {
    file_put_contents(__DIR__ . '/debug_error.log', date('c') . ' - JSON decode error: ' . json_last_error_msg() . "\n", FILE_APPEND);
    http_response_code(400);
    die('Invalid JSON input');
}

$users = $data['users'] ?? null;
if ($users === null || !is_array($users)) {
    http_response_code(400);
    die("No users key found or invalid format");
}

// helper - normalize phone to digits only (or null)
function normalize_phone($raw) {
    $raw = (string)$raw;
    $digits = preg_replace('/\D+/', '', $raw);
    return $digits === '' ? null : $digits;
}

// helper - create a username candidate
function make_username($first, $last, $email = '') {
    if (!empty($email) && strpos($email, '@') !== false) {
        $local = strstr($email, '@', true);
        if ($local) {
            return strtolower(preg_replace('/[^a-z0-9._-]/i', '_', $local));
        }
    }
    $candidate = trim(strtolower($first . '.' . $last));
    $candidate = preg_replace('/[^a-z0-9._-]/', '_', $candidate);
    $candidate = preg_replace('/_+/', '_', $candidate);
    return $candidate === '' ? 'user' . time() : $candidate;
}

// counters
$insertCount = 0;
$updateCount = 0;
$skippedCount = 0;
$errors = [];


$findUserByNameAltStmt  = $pdo->prepare("SELECT id FROM alt_users WHERE first_name = ? AND last_name = ? LIMIT 1");

// Insert into alt_users
$insertAltStmt = $pdo->prepare("
    INSERT INTO alt_users (
        first_name, last_name, username, password, email, preferred_name,
        role_level, is_suspended, ride_group_id, team_id, school_id,
        phone_number, emergency_contact_name, emergency_contact_phone,
        gca_coach_lvl, gca_coach_status, medical_info, created_at
    ) VALUES (
        :first_name, :last_name, :username, NULL, :email, :preferred_name,
        :role_level, 0, NULL, NULL, NULL,
        :phone_number, :emergency_contact_name, :emergency_contact_phone,
        :gca_coach_lvl, :gca_coach_status, :medical_info, NOW()
    )
");

// Update alt_users by id
$updateAltStmt = $pdo->prepare("
    UPDATE alt_users SET
        username = :username,
        email = :email,
        preferred_name = :preferred_name,
        role_level = :role_level,
        is_suspended = 0,
        phone_number = :phone_number,
        emergency_contact_name = :emergency_contact_name,
        emergency_contact_phone = :emergency_contact_phone,
        gca_coach_lvl = :gca_coach_lvl,
        gca_coach_status = :gca_coach_status,
        medical_info = :medical_info,
        updated_at = NOW()
    WHERE id = :id
");

foreach ($users as $idx => $user) {
    try {
        $firstName = trim($user['first_name'] ?? '');
        $lastName  = trim($user['last_name'] ?? '');
        $email     = trim($user['email'] ?? '');
        $roleLevel = isset($user['role_level']) ? (int)$user['role_level'] : 1;

        // phone
        $phoneRaw = $user['phone_number'] ?? $user['phone'] ?? $user['registrant_telephone'] ?? '';
        $phone = normalize_phone($phoneRaw);

        // emergency contact
        $emergencyContactName = trim($user['emergency_contact_name'] ?? $user['emergency_contact_full_name'] ?? '');
        $emergencyContactPhoneRaw = $user['emergency_contact_phone'] ?? $user['emergency_contact_number'] ?? $user['emergency_contact_cell_phone_number'] ?? '';
        $emergencyContactPhone = normalize_phone($emergencyContactPhoneRaw);

        // gca fields
        $gca_lvl_raw = $user['gca_coach_lvl'] ?? $user['gca_level'] ?? null;
        $gca_lvl = ($gca_lvl_raw === '' || $gca_lvl_raw === null) ? null : (is_numeric($gca_lvl_raw) ? (int)$gca_lvl_raw : null);
        $gca_status = $user['gca_coach_status'] ?? $user['gca_status'] ?? null;
        if ($gca_status !== null) $gca_status = trim($gca_status) ?: null;

        // medical info
        $medical_info = $user['medical_info'] ?? null;

        if ($firstName === '' && $lastName === '') {
            $skippedCount++;
            continue;
        }

        $username = make_username($firstName, $lastName, $email);
        $preferredName = $user['preferred_name'] ?? $firstName;

        // Check for existing alt_user by email or name
        $existingTable = 'alt_users';
        $existingId = null;

        // Check for existing alt_user by name ONLY
        $existingId = null;

        if ($firstName !== '' && $lastName !== '') {
            $findUserByNameAltStmt->execute([$firstName, $lastName]);
            $existingId = $findUserByNameAltStmt->fetchColumn();
        }


        if ($existingId) {
            // update alt_user
            $updateAltStmt->execute([
                ':username' => $username,
                ':email' => $email ?: null,
                ':preferred_name' => $preferredName ?: null,
                ':role_level' => $roleLevel,
                ':phone_number' => $phone,
                ':emergency_contact_name' => $emergencyContactName ?: null,
                ':emergency_contact_phone' => $emergencyContactPhone,
                ':gca_coach_lvl' => $gca_lvl,
                ':gca_coach_status' => $gca_status ?: null,
                ':medical_info' => $medical_info ?: null,
                ':id' => $existingId
            ]);
            $updateCount++;
        } else {
            // insert alt_user
            $insertAltStmt->execute([
                ':first_name' => $firstName ?: null,
                ':last_name' => $lastName ?: null,
                ':username' => $username,
                ':email' => $email ?: null,
                ':preferred_name' => $preferredName ?: null,
                ':role_level' => $roleLevel,
                ':phone_number' => $phone,
                ':emergency_contact_name' => $emergencyContactName ?: null,
                ':emergency_contact_phone' => $emergencyContactPhone,
                ':gca_coach_lvl' => $gca_lvl,
                ':gca_coach_status' => $gca_status ?: null,
                ':medical_info' => $medical_info ?: null
            ]);
            $insertCount++;
        }
    } catch (Throwable $e) {
        $errors[] = "Row #{$idx} error: " . $e->getMessage();
        file_put_contents(__DIR__ . '/debug_error.log', date('c') . ' - Import error: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Final output
$output = "✅ Imported: $insertCount | Updated: $updateCount | Skipped (missing data): $skippedCount";
if (!empty($errors)) {
    $output .= "\nErrors:\n" . implode("\n", $errors);
}

echo $output;
