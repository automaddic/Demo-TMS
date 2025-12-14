<?php
// server/admin/api/save-practice-note.php
require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../auth/check-role-access.php';
enforceAccessOrDie('edit-notes.php', $pdo);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$pdId = isset($input['practice_day_id']) ? (int)$input['practice_day_id'] : 0;
$notes = isset($input['notes']) ? trim($input['notes']) : '';
$userId = $_SESSION['user']['id'] ?? null;
$rideGroupId = isset($input['ride_group_id']) ? (int)$input['ride_group_id'] : 1;

if (!$pdId || !$userId || !$rideGroupId) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Check practice day exists and is not in the past
$stmtChk = $pdo->prepare("SELECT start_datetime FROM practice_days WHERE id = ?");
$stmtChk->execute([$pdId]);
$pd = $stmtChk->fetch(PDO::FETCH_ASSOC);

if (!$pd) {
    echo json_encode(['success' => false, 'error' => 'Practice day not found']);
    exit;
}

$now = new DateTime('now', new DateTimeZone('America/New_York'));
$start = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
$startPlus10 = clone $start;
$startPlus10->modify('+10 minutes');

if ($startPlus10 < $now) {
    // Past: do not allow editing
    echo json_encode(['success' => false, 'error' => 'Cannot edit notes for past practice']);
    exit;
}

try {
    // Insert or update with updated_at timestamp
    $stmt = $pdo->prepare("
        INSERT INTO practice_notes (practice_day_id, ride_group_id, coach_id, notes, updated_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE notes = VALUES(notes), updated_at = NOW()
    ");
    $stmt->execute([$pdId, $rideGroupId, $userId, $notes]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Save note error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

exit;

