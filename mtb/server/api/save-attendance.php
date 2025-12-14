<?php
// server/api/save-attendance.php

require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

$userId = $_SESSION['user']['id'] ?? null;

// Parse input JSON
$input = json_decode(file_get_contents('php://input'), true);

$pdId = isset($input['practice_day_id']) ? (int)$input['practice_day_id'] : 0;
$status = isset($input['response']) ? strtolower(trim($input['response'])) : '';

if (!$pdId || !in_array($status, ['yes', 'maybe', 'no'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Save or update attendance
$stmt = $pdo->prepare("
    INSERT INTO practice_attendance (practice_day_id, user_id, status)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status)
");

try {
    $stmt->execute([$pdId, $userId, $status]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
