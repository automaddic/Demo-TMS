<?php
// server/admin/api/create-practice-day.php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('practice-days.php', $pdo);

header('Content-Type: application/json');

// Read JSON body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
    exit;
}

// Helper functions
function respErr($msg) {
    echo json_encode(['success'=>false,'error'=>$msg]);
    exit;
}
function respOk() {
    echo json_encode(['success'=>true]);
    exit;
}

// If this is a single‑day creation (no repeat parameters)
if (empty($data['repeat'])) {
    // Required: name, start_date, start_time, end_date, end_time
    $name       = trim($data['name'] ?? '');
    $startDate  = $data['start_date'] ?? '';
    $startTime  = $data['start_time'] ?? '';
    $endDate    = $data['end_date']   ?? '';
    $endTime    = $data['end_time']   ?? '';

    if (!$name || !$startDate || !$startTime ) {
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
    
    if (!$startDt) {
        respErr('Could not parse combined date/time');
    }
    

    // Optional fields
    $location    = trim($data['location'] ?? '') ?: null;
    $map_link    = trim($data['map_link'] ?? '') ?: null;
    $day_type_id = !empty($data['day_type_id']) ? (int)$data['day_type_id'] : null;

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO practice_days 
          (name, location, date, start_datetime, end_datetime, map_link, day_type_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        $pdo->beginTransaction();
        $stmt->execute([
            $name,
            $location,
            // date column: store as Y-m-d 00:00:00 for the chosen start date
            $startDt->format('Y-m-d 00:00:00'),
            $startDt->format('Y-m-d H:i:s'),
            (isset($endDt) && $endDt instanceof DateTime) ? $endDt->format('Y-m-d H:i:s') : null,
            $map_link,
            $day_type_id
        ]);
        $pdo->commit();
        respOk();
    } catch (Exception $e) {
        $pdo->rollBack();
        respErr('DB error: ' . $e->getMessage());
    }
}

// ——————————————————————————
// Repeat logic (unchanged; uses 'weekdays' and 'weeks')
// ——————————————————————————
$weekdays = $data['weekdays'] ?? [];
$weeks    = isset($data['weeks']) ? (int)$data['weeks'] : 0;
if (!is_array($weekdays) || empty($weekdays) || $weeks < 1) {
    respErr('Invalid repeat parameters');
}

try {
    $pdo->beginTransaction();
    foreach ($weekdays as $wd) {
        $wd = (int)$wd; // 0=Monday..6=Sunday
        // Find latest existing practice for this weekday
        $stmtMax = $pdo->prepare("
            SELECT MAX(start_datetime) AS max_dt 
            FROM practice_days 
            WHERE WEEKDAY(start_datetime) = ?
        ");
        $stmtMax->execute([$wd]);
        $maxDtStr = $stmtMax->fetchColumn();
        if (!$maxDtStr) {
            continue; // no existing, skip
        }

        // Fetch that row’s data
        $stmtRow = $pdo->prepare("
            SELECT * FROM practice_days 
            WHERE start_datetime = ?
            LIMIT 1
        ");
        $stmtRow->execute([$maxDtStr]);
        $pd = $stmtRow->fetch(PDO::FETCH_ASSOC);
        if (!$pd) continue;

        $origStart = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));

        if ($pd['end_datetime']) {

            $origEnd   = new DateTime($pd['end_datetime'],   new DateTimeZone('America/New_York'));
            $duration  = $origEnd->getTimestamp() - $origStart->getTimestamp();

        } else {

            $origEnd   = null;

        }

        // Insert clones for the next N weeks
        for ($i = 1; $i <= $weeks; $i++) {
            $newStart = clone $origStart;
            $newStart->modify('+' . (7*$i) . ' days');

            if ($origEnd) {

                $newEnd = clone $newStart;
                $newEnd->modify('+' . $duration . ' seconds');

            } else {

                $newEnd = null;

            }

            $stmtIns = $pdo->prepare("
                INSERT INTO practice_days
                  (name, location, date, start_datetime, end_datetime, map_link, day_type_id, has_ended)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([
                $pd['name'],
                $pd['location'],
                $newStart->format('Y-m-d 00:00:00'),
                $newStart->format('Y-m-d H:i:s'),
                isset($newEnd) && $newEnd instanceof DateTime 
                ? $newEnd->format('Y-m-d H:i:s') 
                : null,
                $pd['map_link'],
                $pd['day_type_id'],
                false
            ]);
        }
    }
    $pdo->commit();
    respOk();
} catch (Exception $e) {
    $pdo->rollBack();
    respErr('Repeat DB error: ' . $e->getMessage());
}
