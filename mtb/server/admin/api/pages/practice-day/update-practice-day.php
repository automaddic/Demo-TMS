<?php
// server/admin/api/update-practice-day.php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('practice-days.php', $pdo);

header('Content-Type: application/json');

// Helper functions
function respErr($msg) {
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}
function respOk() {
    echo json_encode(['success' => true]);
    exit;
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    respErr('Invalid JSON');
}

// Required fields
$id         = isset($data['id']) ? (int)$data['id'] : 0;
$name       = trim($data['name'] ?? '');
$startDate  = $data['start_date'] ?? '';
$startTime  = $data['start_time'] ?? '';
$endDate    = $data['end_date'] ?? '';
$endTime    = $data['end_time'] ?? '';

if (!$id || !$name || !$startDate || !$startTime) {
    respErr('Missing required fields');
}

// Validate date formats
    $sdDate = DateTime::createFromFormat('Y-m-d', $startDate, new DateTimeZone('America/New_York'));
    if (!$sdDate) {
        respErr('Invalid start date format');
    }
    if (!empty($endDate)) {
        $edDate = DateTime::createFromFormat('Y-m-d', $endDate,   new DateTimeZone('America/New_York'));
        if (!$edDate) {
            respErr('Invalid end date format');
        }

    }

    // Validate time formats
    $sdTime = DateTime::createFromFormat('H:i', $startTime, new DateTimeZone('America/New_York'));
    if (!$sdTime) {
        respErr('Invalid start time format');
    }
    if(!empty($endTime)) {
        $edTime = DateTime::createFromFormat('H:i', $endTime,   new DateTimeZone('America/New_York'));
        if (!$edTime) {
            respErr('Invalid end time format');
        }

    }

    // Combine into full DateTime objects
    $startDt = DateTime::createFromFormat(
        'Y-m-d H:i',
        $startDate . ' ' . $startTime,
        new DateTimeZone('America/New_York')
    );
    
    if (isset($edDate) && isset($edTime)) {

        $endDt   = DateTime::createFromFormat(
            'Y-m-d H:i',
            $endDate   . ' ' . $endTime,
            new DateTimeZone('America/New_York')
        );
        if (!$endDt) {
            respErr('Could not parse combined date/time');
        }
        if ($endDt <= $startDt) {
            respErr('End must be after start');
        }

    }

// Optional fields
$location    = trim($data['location'] ?? '') ?: null;
$map_link    = trim($data['map_link'] ?? '') ?: null;
$day_type_id = !empty($data['day_type_id']) ? (int)$data['day_type_id'] : null;

try {
    $stmt = $pdo->prepare("
        UPDATE practice_days SET 
            name = ?, 
            location = ?, 
            date = ?, 
            start_datetime = ?, 
            end_datetime = ?, 
            map_link = ?, 
            day_type_id = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $location,
        $startDt->format('Y-m-d 00:00:00'),
        $startDt->format('Y-m-d H:i:s'),
        isset($endDt) && $endDt instanceof DateTime 
            ? $endDt->format('Y-m-d H:i:s') 
            : null,
        $map_link,
        $day_type_id,
        $id
    ]);

    respOk();
} catch (Exception $e) {
    respErr('DB error: ' . $e->getMessage());
}
