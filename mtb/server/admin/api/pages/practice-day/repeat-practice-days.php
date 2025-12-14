<?php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('practice-days.php', $pdo);

$data = json_decode(file_get_contents("php://input"), true);
$repeatIds = $data['repeat_ids'] ?? [];
$weeks = $data['weeks'] ?? 0;

if (!is_array($repeatIds) || !$weeks) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

try {
    $pdo->beginTransaction();

    foreach ($repeatIds as $id) {
        $stmt = $pdo->prepare("SELECT * FROM practice_days WHERE id = ?");
        $stmt->execute([$id]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$original)
            continue;

        $start = new DateTime($original['start_datetime'], new DateTimeZone('America/New_York'));
        if (!empty($original['end_datetime'])){
            $end = new DateTime($original['end_datetime'], new DateTimeZone('America/New_York'));
        } else {
            $end = null;
        }
        

        for ($w = 1; $w <= $weeks; $w++) {
            $newStart = clone $start;
            $newEnd = !empty($end) ? clone $end : null;
            $newStart->modify("+{$w} week");
            
            if ($newEnd !== null) {
                $newEnd->modify("+{$w} week");
            }

            if ($newStart < new DateTime('now', new DateTimeZone('America/New_York')))
                continue;

            // Don't insert duplicates (same name + date)
            $check = $pdo->prepare("SELECT COUNT(*) FROM practice_days WHERE name = ? AND DATE(start_datetime) = ?");
            $check->execute([$original['name'], $newStart->format('Y-m-d')]);
            if ($check->fetchColumn() > 0)
                continue;

            $insert = $pdo->prepare("
                INSERT INTO practice_days (name, start_datetime, end_datetime, location, map_link, day_type_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $original['name'],
                $newStart->format('Y-m-d H:i:s'),
                (isset($newEnd) && $newEnd instanceof DateTime) ? $newEnd->format('Y-m-d H:i:s') : null,
                $original['location'],
                $original['map_link'],
                $original['day_type_id']
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
