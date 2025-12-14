<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentUserLevel = $_SESSION['user']['role_level'] ?? 0;

    if (!isset($_POST['role']) || !is_array($_POST['role'])) {
        // No roles posted; redirect with error
        $redirect = $baseUrl . '/public/admin/admin-dashboard.php?mode=page-access&error=1';
        header("Location: $redirect");
        exit;
    }

    foreach ($_POST['role'] as $filename => $submittedLevel) {
        $submittedLevel = (int)$submittedLevel;

        // Prevent setting level higher than self
        if ($submittedLevel > $currentUserLevel) {
            // skip or optionally log attempt
            continue;
        }

        // Upsert into page_access
        $stmt = $pdo->prepare("
            INSERT INTO page_access (page_name, min_role_level)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE min_role_level = VALUES(min_role_level)
        ");
        $stmt->execute([$filename, $submittedLevel]);
    }

    // Redirect back to admin-dashboard page-access section
    $redirect = $baseUrl . '/public/admin/admin-dashboard.php?mode=page-access&saved=1';
    header("Location: $redirect");
    exit;
}

// If GET or other, we typically do not render here; admin-dashboard.php includes controller logic.
// But if you want to allow direct GET to this handler to fetch data, you can do so. For example:

// === Fetch required data for view ===
// 1. All admin PHP/HTML files (excluding subfolders and optionally partials)
$adminDir = $_SERVER['DOCUMENT_ROOT'] . '/mtb-login-php/public/admin';
$files = [];
if (is_dir($adminDir)) {
    foreach (scandir($adminDir) as $file) {
        if ($file === '.' || $file === '..') continue;
        $full = $adminDir . '/' . $file;
        if (is_file($full) && preg_match('/\.(php|html)$/i', $file)) {
            // Optionally skip partial files or specific filenames if desired:
            // e.g., if you prefix partials with underscore: if (strpos($file, '_')===0) continue;
            $files[] = $file;
        }
    }
    // Ensure admin-dashboard.php is included
    $dashboard = 'admin-dashboard.php';
    if (!in_array($dashboard, $files, true)) {
        $files[] = $dashboard;
    }
    // Optionally sort alphabetically:
    sort($files, SORT_STRING | SORT_FLAG_CASE);
}

// 2. Page → min role level
$accessMap = [];
$stmt = $pdo->query("SELECT page_name, min_role_level FROM page_access");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $accessMap[$row['page_name']] = $row['min_role_level'];
}

// 3. Role level → name
$roles = $pdo->query("SELECT role_level, role_name FROM roles ORDER BY role_level ASC")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

// Return or echo JSON if this is used via AJAX, or simply return array if included:
return [
    'files' => $files,
    'accessMap' => $accessMap,
    'roles' => $roles,
];
