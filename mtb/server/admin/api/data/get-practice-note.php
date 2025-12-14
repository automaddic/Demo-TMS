<?php
// server/admin/api/get-practice-note.php

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../auth/check-role-access.php';
enforceAccessOrDie('edit-notes.php', $pdo);

header('Content-Type: application/json');

// Input
$pdId = isset($_GET['practice_day_id']) ? (int)$_GET['practice_day_id'] : 0;
$userId = $_SESSION['user']['id'] ?? null;

// Fallback: Use user's group if none specified
$rideGroupId = isset($_GET['ride_group_id'])
    ? (int)$_GET['ride_group_id']
    : ($_SESSION['user']['ride_group_id'] ?? null);

// Validation
if (!$pdId || !$rideGroupId || !$userId) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Retrieve the note
$stmt = $pdo->prepare("
    SELECT notes 
    FROM practice_notes 
    WHERE practice_day_id = ? AND ride_group_id = ? AND coach_id = ?
");
$stmt->execute([$pdId, $rideGroupId, $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['notes' => $row['notes'] ?? '']);
exit;
