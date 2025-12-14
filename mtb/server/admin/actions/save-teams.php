<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteId = $_POST['delete'] ?? null;
    if ($deleteId !== null) {
        $tid = (int)$deleteId;
        $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$tid]);
        header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=teams&saved=1");
        exit;
    }
    if (isset($_POST['teams']) && is_array($_POST['teams'])) {
        foreach ($_POST['teams'] as $id => $data) {
            if ($id === 'new') {
                $newName = trim($data['name'] ?? '');
                if ($newName !== '') {
                    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE name = ?");
                    $stmtCheck->execute([$newName]);
                    if ($stmtCheck->fetchColumn() == 0) {
                        $pdo->prepare("INSERT INTO teams (name) VALUES (?)")->execute([$newName]);
                    }
                }
            } else {
                $tid = (int)$id;
                $newName = trim($data['name'] ?? '');
                if ($newName === '') {
                    $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$tid]);
                } else {
                    $pdo->prepare("UPDATE teams SET name = ? WHERE id = ?")
                        ->execute([$newName, $tid]);
                }
            }
        }
    }
    header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=teams&saved=1");
    exit;
}
header("Location: $baseUrl/public/admin/admin-dashboard.php");
exit;
