<?php
// middleware/require-role.php

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role_level'])) {
    http_response_code(401);
    exit('Access denied: Not logged in.');
}

// Usage: requireRole(3); // requires role_level 3 or higher
function requireRole(int $minimumRoleLevel) {
    $userRole = $_SESSION['user']['role_level'];

    if ($userRole < $minimumRoleLevel) {
        http_response_code(403);
        exit('Access denied: Insufficient permissions.');
    }
}
