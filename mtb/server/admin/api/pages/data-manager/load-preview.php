<?php
// load-preview.php
require_once __DIR__ . '/../../../../config/bootstrap.php';
require_once __DIR__ . '/../../../../auth/check-role-access.php';
enforceAccessOrDie("import-users.php", $pdo);
require_once __DIR__ . '/../../../../scripts/xlsx-loader.php';

header('Content-Type: application/json');

$spreadsheet = $_POST['spreadsheet'] ?? '';
$filepath = __DIR__ . '/../../../../user-data/spreadsheets/' . basename($spreadsheet);


try {
    $users = loadStructuredUsers($filepath);
    echo json_encode(['success' => true, 'users' => $users, 'filepath' => $filepath]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
