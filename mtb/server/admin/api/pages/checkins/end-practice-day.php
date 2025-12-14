<?php
// server/admin/actions/end-practice-day.php

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        header('Content-Type: application/json');
        echo json_encode(['fatal_error' => $err]);
    }
});

require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('check-in.php', $pdo);

// ensure we always return JSON and no extraneous output
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/New_York');

try {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
        exit;
    }

    // get and validate inputs
    $practiceDayId = isset($_POST['practice_day_id']) ? (int)$_POST['practice_day_id'] : 0;
    if ($practiceDayId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid practice_day_id']);
        exit;
    }

    // compute current time in America/New_York
    $dt = new DateTime('now', new DateTimeZone('America/New_York'));
    // format for MySQL DATETIME
    $endDatetime = $dt->format('Y-m-d H:i:s');

    // Prepare and run the update
    $sql = "UPDATE practice_days
            SET end_datetime = :end_dt,
                has_ended = 1,
                updated_at = NOW()
            WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([
        ':end_dt' => $endDatetime,
        ':id' => $practiceDayId,
    ]);

    if ($ok && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'practice_day_id' => $practiceDayId,
            'end_datetime' => $endDatetime,
        ]);
        exit;
    }

    // If rowCount is 0, either the id doesn't exist or nothing changed.
    // Check whether the practice day exists
    $exists = $pdo->prepare("SELECT id, end_datetime, has_ended FROM practice_days WHERE id = :id");
    $exists->execute([':id' => $practiceDayId]);
    $row = $exists->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Practice day not found']);
        exit;
    }

    // If found but rowCount 0, maybe values were identical. Return success but indicate no change.
    echo json_encode([
        'success' => true,
        'practice_day_id' => $practiceDayId,
        'end_datetime' => $row['end_datetime'] ?? $endDatetime,
        'note' => 'No row updated (values may have been identical).'
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
    exit;
}
