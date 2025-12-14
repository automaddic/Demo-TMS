<?php
// server/admin/actions/approval-handler.php

require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('check-in.php', $pdo);

$action = $_POST['action'] ?? '';
$checkinId = isset($_POST['checkin_id']) ? (int)$_POST['checkin_id'] : 0;

if (!$checkinId) {
    http_response_code(400);
    echo "Invalid parameters";
    exit;
}

// Fetch record
$stmt = $pdo->prepare("SELECT check_in_time, check_out_time FROM check_ins WHERE id = ?");
$stmt->execute([$checkinId]);
$ci = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ci) {
    http_response_code(404);
    echo "Record not found";
    exit;
}

if ($action === 'approve') {
    // Mark approved
    $upd = $pdo->prepare("UPDATE check_ins SET approved = 1, requires_approval = 0 WHERE id = ?");
    $upd->execute([$checkinId]);
    exit;
}
elseif ($action === 'reject') {
    // Clear check_out_time so user can check out again or be edited
    // Also reset elapsed_seconds, requires_approval, approved
    $upd = $pdo->prepare("UPDATE check_ins SET elapsed_seconds = 0, requires_approval = 0, approved = 1 WHERE id = ?");
    $upd->execute([$checkinId]);
    exit;
}
else {
    http_response_code(400);
    echo "Invalid action";
    exit;
}
