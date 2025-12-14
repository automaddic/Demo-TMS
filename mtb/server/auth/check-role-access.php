<?php
// server/check-role-access.php
require_once __DIR__ . '/../config/bootstrap.php'; // adjust path

function userCanAccess(string $page, PDO $pdo): bool {
    // $page should be basename, e.g. 'admin-dashboard.php'
    $stmt = $pdo->prepare("SELECT min_role_level FROM page_access WHERE page_name = ?");
    $stmt->execute([$page]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $role = $_SESSION['user']['role_level'] ?? 0;

    if (!$result) {
        // No restriction defined: allow
        return true;
    }
    return $role >= (int)$result['min_role_level'];
}

function enforceAccessOrDie(string $page, PDO $pdo): void {
    if (!userCanAccess($page, $pdo)) {
        http_response_code(403);
        echo "<h2>Access Denied</h2><p>You do not have permission to view this page.</p>";
        exit;
    }
}
