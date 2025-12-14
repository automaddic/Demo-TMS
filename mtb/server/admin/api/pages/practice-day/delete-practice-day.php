<?php
// server/admin/api/delete-practice-day.php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie('practice-days.php', $pdo);

header('Content-Type: application/json');
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['success'=>false,'error'=>'Invalid JSON']);
    exit;
}
$id = isset($data['id']) ? (int)$data['id'] : 0;
if (!$id) {
    echo json_encode(['success'=>false,'error'=>'Invalid ID']);
    exit;
}
try {
    $stmt = $pdo->prepare("DELETE FROM practice_days WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'DB error: '.$e->getMessage()]);
}
