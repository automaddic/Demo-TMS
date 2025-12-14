<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// adjust path to your DB connection; this file should create $pdo (PDO)
require_once __DIR__ . '/../../db.php';

// Read and validate input
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$source = isset($_GET['source']) ? $_GET['source'] : '';

$allowedSources = ['users', 'alt_users'];

if ($user_id <= 0 || !in_array($source, $allowedSources, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters. Expecting user_id (int) and source (users|alt_users).']);
    exit;
}

$table = $source; // safe because we validated above

try {
    // Select the columns we care about. Table name is validated above.
    $sql = "
        SELECT
            id,
            first_name,
            last_name,
            role_level,
            phone_number,
            emergency_contact_name,
            emergency_contact_phone,
            medical_info
        FROM {$table}
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }

    // Build full name (prefer existing full_name if you have it; otherwise concat)
    $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));

    $response = [
        'success' => true,
        'user' => [
            'id' => (int)$row['id'],
            'full_name' => $fullName ?: null,
            'role_level' => isset($row['role_level']) ? (int)$row['role_level'] : null,
            'phone_number' => $row['phone_number'] !== null ? (string)$row['phone_number'] : null,
            'emergency_contact_name' => $row['emergency_contact_name'] !== null ? (string)$row['emergency_contact_name'] : null,
            'emergency_contact_phone' => $row['emergency_contact_phone'] !== null ? (string)$row['emergency_contact_phone'] : null,
            'medical_info' => $row['medical_info'] !== null ? (string)$row['medical_info'] : null,
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    // log the error server-side and return a generic message
    error_log('user-info error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    exit;
}
