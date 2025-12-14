<?php
// server/admin/actions/edit-time-handler.php

require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('check-in.php', $pdo);

date_default_timezone_set('America/New_York');

$checkinId = isset($_POST['checkin_id']) ? (int)$_POST['checkin_id'] : 0;
$newElapsed = isset($_POST['new_elapsed']) ? (int)$_POST['new_elapsed'] : null;

if (!$checkinId || $newElapsed === null || $newElapsed < 0) {
    http_response_code(400);
    echo "Invalid parameters";
    exit;
}

// Fetch record
$stmt = $pdo->prepare("SELECT check_in_time FROM check_ins WHERE id = ?");
$stmt->execute([$checkinId]);
$ci = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ci) {
    http_response_code(404);
    echo "Record not found";
    exit;
}
$dtIn = new DateTime($ci['check_in_time']);
// Compute new check_out_time = check_in_time + newElapsed seconds
$dtIn->modify("+{$newElapsed} seconds");
$newCheckOut = $dtIn->format('Y-m-d H:i:s');

// Update: set elapsed_seconds, check_out_time, approved, requires_approval=0
$upd = $pdo->prepare("UPDATE check_ins SET elapsed_seconds = ?, check_out_time = ?, approved = 1, requires_approval = 0 WHERE id = ?");
$upd->execute([$newElapsed, $newCheckOut, $checkinId]);

echo "OK";
exit;
