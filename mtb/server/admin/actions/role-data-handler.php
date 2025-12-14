<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rolesInput = $_POST['roles'] ?? null;
    $newOrderCsv = $_POST['new_order'] ?? '';
    if (!is_array($rolesInput)) {
        header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=role-data&error=1");
        exit;
    }
    // Trim and filter existing names
    $namesByLevel = [];
    foreach ($rolesInput as $key => $val) {
        $trim = trim($val);
        if ($key === 'new') {
            if ($trim !== '') {
                $namesByLevel['new'] = $trim;
            }
        } else {
            $lvl = (int)$key;
            // Only include if non-empty
            if ($trim !== '') {
                $namesByLevel[$lvl] = $trim;
            }
        }
    }
    // Check duplicates among existing+new, case-insensitive
    $lowerNames = [];
    foreach ($namesByLevel as $k => $n) {
        $ln = mb_strtolower($n);
        if (in_array($ln, $lowerNames, true)) {
            header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=role-data&error=dup");
            exit;
        }
        $lowerNames[] = $ln;
    }
    // Parse new_order
    $ordering = [];
    if ($newOrderCsv !== '') {
        $parts = explode(',', $newOrderCsv);
        foreach ($parts as $p) {
            if (is_numeric($p)) {
                $ordering[] = (int)$p;
            }
        }
    }
    // Begin transaction: rebuild roles table with new ordering and updated names
    try {
        $pdo->beginTransaction();
        // If you want to preserve existing role entries: simplest is to delete all and reinsert in new order.
        // But protected roles (>= current user level) keep their names unchanged? We already prevented editing via readonly.
        // We rebuild from ordering:
        $pdo->exec("DELETE FROM roles");
        $stmtInsert = $pdo->prepare("INSERT INTO roles (role_level, role_name) VALUES (?, ?)");
        $newLevel = 1;
        // First, insert roles in the ordering sequence
        foreach ($ordering as $origLevel) {
            if (isset($namesByLevel[$origLevel])) {
                $name = $namesByLevel[$origLevel];
                $stmtInsert->execute([$newLevel, $name]);
                $newLevel++;
            }
        }
        // Next, insert any roles not in ordering but in namesByLevel (shouldn't happen normally)
        foreach ($namesByLevel as $key => $name) {
            if ($key === 'new') continue;
            if (!in_array((int)$key, $ordering, true)) {
                $stmtInsert->execute([$newLevel, $name]);
                $newLevel++;
            }
        }
        // Finally, if new role provided:
        if (isset($namesByLevel['new'])) {
            $stmtInsert->execute([$newLevel, $namesByLevel['new']]);
            $newLevel++;
        }
        $pdo->commit();
        header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=role-data&saved=1");
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=role-data&error=1");
    }
    exit;
}
header("Location: $baseUrl/public/admin/admin-dashboard.php");
exit;
