<?php

require_once __DIR__ . '/xlsx-loader.php';  // Adjust path as needed

function isNameInLocalFile(string $first, string $last, string $filepath): bool {
    try {
        $users = loadStructuredUsers($filepath);
    } catch (Exception $e) {
        error_log("Local file match error: " . $e->getMessage());
        return false;
    }

    $first = strtolower(trim($first));
    $last = strtolower(trim($last));

    foreach ($users as $user) {
        $uFirst = strtolower(trim($user['first_name']));
        $uLast = strtolower(trim($user['last_name']));
        if ($uFirst === $first && $uLast === $last) {
            return true;
        }
    }

    return false;
}
