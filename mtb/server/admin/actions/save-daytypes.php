<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteId = $_POST['delete'] ?? null;
    if ($deleteId !== null) {
        $did = (int)$deleteId;
        $pdo->prepare("DELETE FROM day_types WHERE id = ?")->execute([$did]);
        header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=day-types&saved=1");
        exit;
    }
    if (isset($_POST['daytypes']) && is_array($_POST['daytypes'])) {
        foreach ($_POST['daytypes'] as $id => $name) {
            if ($id === 'new') {
                $newName = trim($name);
                if ($newName !== '') {
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM day_types WHERE name = ?");
                    $stmtCheck->execute([$newName]);
                    if ($stmtCheck->fetchColumn() == 0) {
                        $pdo->prepare("INSERT INTO day_types (name) VALUES (?)")->execute([$newName]);
                    }
                }
            } else {
                $did = (int)$id;
                $newName = trim($name);
                if ($newName === '') {
                    $pdo->prepare("DELETE FROM day_types WHERE id = ?")->execute([$did]);
                } else {
                    $pdo->prepare("UPDATE day_types SET name = ? WHERE id = ?")
                        ->execute([$newName, $did]);
                }
            }
        }
    }
    header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=day-types&saved=1");
    exit;
}
header("Location: $baseUrl/public/admin/admin-dashboard.php");
exit;
