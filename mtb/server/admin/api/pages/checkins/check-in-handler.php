<?php
// server/admin/actions/check-in-handler.php
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        echo json_encode(['fatal_error' => $err]);
    }
});

require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('check-in.php', $pdo);

date_default_timezone_set('America/New_York');

$action = $_POST['action'] ?? '';
$inputUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$practiceDayId = isset($_POST['practice_day_id']) ? (int)$_POST['practice_day_id'] : 0;
$source = $_POST['source'] ?? '';  // 'users' or 'alt_users'

if (!$inputUserId || !$practiceDayId || !in_array($source, ['users', 'alt_users'])) {
    http_response_code(400);
    echo "Invalid parameters";
    exit;
}

// Check user exists and is not suspended in the correct table
$table = ($source === 'users') ? 'users' : 'alt_users';

$stmt = $pdo->prepare("SELECT is_suspended FROM {$table} WHERE id = ?");
$stmt->execute([$inputUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['is_suspended']) {
    http_response_code(403);
    echo "User not allowed";
    exit;
}

// Fetch practice_day window
$stmtPd = $pdo->prepare("SELECT start_datetime, end_datetime FROM practice_days WHERE id = ?");
$stmtPd->execute([$practiceDayId]);
$pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

$now = new DateTime();
$start = ($pd && $pd['start_datetime']) ? new DateTime($pd['start_datetime']) : null;
$end   = ($pd && $pd['end_datetime'])   ? new DateTime($pd['end_datetime'])   : null;

if ($start) $start->modify('-2 hours');
if ($end)   $end->modify('+2 hours');

if ($action === 'in') {
    // Enforce check-in only if within window (if window is defined)
    if ($start && $now < $start) {
        http_response_code(400);
        echo "Cannot check in before practice window starts.";
        exit;
    }
    if ($end && $now > $end) {
        http_response_code(400);
        echo "Cannot check in after practice window ends.";
        exit;
    }

    // Check if existing record
    $stmt2 = $pdo->prepare("SELECT id, check_in_time, check_out_time FROM check_ins WHERE user_id = ? AND practice_day_id = ? AND is_alt_user = ?");
    $isAltUserFlag = ($source === 'alt_users') ? 1 : 0;
    $stmt2->execute([$inputUserId, $practiceDayId, $isAltUserFlag]);
    $ci = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($ci) {
        if ($ci['check_in_time'] && !$ci['check_out_time']) {
            echo "Already checked in";
            exit;
        } elseif ($ci['check_out_time']) {
            echo "Already checked out; please reset to check in again";
            exit;
        } else {
            // Existing row but no check_in_time - update and set is_alt_user
            $nowStr = $now->format('Y-m-d H:i:s');
            $upd = $pdo->prepare("UPDATE check_ins SET check_in_time = ?, elapsed_seconds = 0, requires_approval = 0, approved = 0, check_out_time = NULL WHERE id = ?");
            $upd->execute([$nowStr, $ci['id']]);
            exit;
        }
    } else {
        // Insert new record with is_alt_user
        $nowStr = $now->format('Y-m-d H:i:s');
        $ins = $pdo->prepare("INSERT INTO check_ins (user_id, practice_day_id, check_in_time, elapsed_seconds, requires_approval, approved, is_alt_user) VALUES (?, ?, ?, 0, 0, 0, ?)");
        $ins->execute([$inputUserId, $practiceDayId, $nowStr, $isAltUserFlag]);
        exit;
    }
}
elseif ($action === 'out') {
    // Allow check-out anytime
    $stmt2 = $pdo->prepare("SELECT id, check_in_time, check_out_time FROM check_ins WHERE user_id = ? AND practice_day_id = ? AND is_alt_user = ?");
    $isAltUserFlag = ($source === 'alt_users') ? 1 : 0;
    $stmt2->execute([$inputUserId, $practiceDayId, $isAltUserFlag]);
    $ci = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$ci || !$ci['check_in_time']) {
        echo "No active check-in";
        exit;
    }
    if ($ci['check_out_time']) {
        echo "Already checked out";
        exit;
    }

    // Compute elapsed
    $dtIn = new DateTime($ci['check_in_time']);
    $delta = $now->getTimestamp() - $dtIn->getTimestamp();
    if ($delta < 0) $delta = 0;

    $threshold = 3*3600 + 30*60;  // 3h30m threshold

    // Determine if after window end (only if end exists)
    $afterWindowCheckOut = ($end && $now > $end);

    $requiresApproval = ($delta > $threshold || $afterWindowCheckOut) ? 1 : 0;
    $approved = $requiresApproval ? 0 : 1;
    $outStr = $now->format('Y-m-d H:i:s');

    $upd = $pdo->prepare("UPDATE check_ins SET check_out_time = ?, elapsed_seconds = ?, requires_approval = ?, approved = ? WHERE id = ?");
    $upd->execute([$outStr, $delta, $requiresApproval, $approved, $ci['id']]);
    exit;
}
else {
    http_response_code(400);
    echo "Invalid action";
    exit;
}
