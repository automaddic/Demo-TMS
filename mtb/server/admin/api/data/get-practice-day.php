<?php
// server/admin/api/get-practice-day.php

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../../auth/check-role-access.php';
enforceAccessOrDie('practice-days.php', $pdo);

header('Content-Type: application/json');

$input = $_GET;
$id = isset($input['id']) ? (int)$input['id'] : 0;
if (!$id) {
    echo json_encode(['success'=>false,'error'=>'Invalid ID']);
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM practice_days WHERE id = ?");
$stmt->execute([$id]);
$pd = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pd) {
    echo json_encode(['success'=>false,'error'=>'Not found']);
    exit;
}
// Return as JSON; keep date-time strings
echo json_encode($pd);
