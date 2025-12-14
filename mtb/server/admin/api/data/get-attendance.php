<?php
// server/admin/api/get-attendance.php

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../auth/check-role-access.php';
enforceAccessOrDie('attendance.php', $pdo);

header('Content-Type: application/json');
$pdId = isset($_GET['practice_day_id']) ? (int)$_GET['practice_day_id'] : 0;
if (!$pdId) {
    echo json_encode(['error'=>'Invalid practice_day_id']);
    exit;
}

// Fetch all users who could attend: e.g., active users? For simplicity, fetch all users not suspended?
$stmtU = $pdo->prepare("
    SELECT u.id AS user_id, u.first_name, u.last_name, s.name AS school, t.name AS team, rg.name AS ride_group
    FROM users u
    LEFT JOIN schools s ON u.school_id = s.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN ride_groups rg ON u.ride_group_id = rg.id
    WHERE u.is_suspended = 0
");
$stmtU->execute();
$allUsers = $stmtU->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing attendance for this practice day
$stmtA = $pdo->prepare("SELECT user_id, status FROM practice_attendance WHERE practice_day_id = ?");
$stmtA->execute([$pdId]);
$existing = $stmtA->fetchAll(PDO::FETCH_KEY_PAIR); // [user_id => status]

// Build array: for each user, include status (default 'no' or blank? we choose 'no' by default but you might prefer blank)
$attendance = [];
foreach ($allUsers as $u) {
    $status = isset($existing[$u['user_id']]) ? $existing[$u['user_id']] : 'no';
    $attendance[] = [
        'user_id' => $u['user_id'],
        'first_name' => $u['first_name'] ?? '',
        'last_name'  => $u['last_name']  ?? '',
        'school'     => $u['school']     ?? '',
        'team'       => $u['team']       ?? '',
        'ride_group' => $u['ride_group'] ?? '',
        'status'     => $status
    ];
}
echo json_encode(['attendance' => $attendance]);
exit;
