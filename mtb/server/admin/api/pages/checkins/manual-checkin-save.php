<?php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('check-in.php', $pdo);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user_id = $_POST['user_id'] ?? null;
if ($user_id === '') {
    $user_id = null;
}

$manual_name = $_POST['user_name'] ?? null;
if ($manual_name === '') {
    $manual_name = null;
}

$practice_day_id = $_POST['practice_day_id'] ?? null;
$check_in_time = $_POST['check_in_time'] ?? null;
$check_out_time = $_POST['check_out_time'] ?? null;

// New: get is_alt_user, cast to 0 or 1
$is_alt_user = isset($_POST['is_alt_user']) && ($_POST['is_alt_user'] == '1' || $_POST['is_alt_user'] === 1) ? 1 : 0;

if (!$practice_day_id) {
    echo json_encode(['success' => false, 'error' => 'Practice day is required']);
    exit;
}

function toTimestamp($dt) {
    return $dt ? strtotime($dt) : null;
}

$elapsed = 0;
if ($check_in_time && $check_out_time) {
    $start = toTimestamp($check_in_time);
    $end = toTimestamp($check_out_time);
    if ($start && $end && $end > $start) {
        $elapsed = $end - $start;
    }
}

try {
    $stmt = $pdo->prepare("
    INSERT INTO check_ins (user_id, manual_name, practice_day_id, check_in_time, check_out_time, elapsed_seconds, requires_approval, approved, is_alt_user)
    VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)
    ");
    $stmt->execute([
        $user_id,
        $manual_name,
        $practice_day_id,
        $check_in_time ?: null,
        $check_out_time ?: null,
        $elapsed,
        $is_alt_user
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
