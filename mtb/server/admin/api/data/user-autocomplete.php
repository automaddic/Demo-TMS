<?php
require_once __DIR__ . '/../../../config/bootstrap.php';
requireRole(2);

header('Content-Type: application/json');
try {
$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = "%$q%";

// Query users
$stmtUsers = $pdo->prepare("
    SELECT id, CONCAT(first_name, ' ', last_name) AS name
    FROM users
    WHERE first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?
    ORDER BY last_name, first_name
    LIMIT 10
");
$stmtUsers->execute([$like, $like, $like]);
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Query alt_users (no join)
$stmtAlt = $pdo->prepare("
    SELECT id AS alt_user_id, first_name, last_name, CONCAT(first_name, ' ', last_name) AS name
    FROM alt_users
    WHERE first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?
    ORDER BY last_name, first_name
    LIMIT 10
");
$stmtAlt->execute([$like, $like, $like]);
$altUsers = $stmtAlt->fetchAll(PDO::FETCH_ASSOC);

// Normalize alt_users data
foreach ($altUsers as &$altUser) {
    $altUser['id'] = $altUser['alt_user_id'];  // use alt user id as id
    $altUser['is_alt_user'] = true;
    unset($altUser['alt_user_id']);
}

// Normalize users data
foreach ($users as &$user) {
    $user['is_alt_user'] = false;
}

// Combine both arrays
$combined = array_merge($users, $altUsers);

// Optionally: remove duplicates by id if needed
$seenIds = [];
$filtered = [];
foreach ($combined as $entry) {
    if (!in_array($entry['id'], $seenIds)) {
        $seenIds[] = $entry['id'];
        $filtered[] = $entry;
    }
}

// Return limited results (up to 10)
echo json_encode(array_slice($filtered, 0, 10));

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}