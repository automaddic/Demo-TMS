<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update/delete existing
    if (isset($_POST['action']) && $_POST['action']==='update-existing') {
        if (isset($_POST['rg_name']) && is_array($_POST['rg_name'])) {
            foreach ($_POST['rg_name'] as $id => $name) {
                $rid = (int)$id;
                $name = trim($name);
                $colorName = trim($_POST['rg_color_id'][$id] ?? '');
                $delete = isset($_POST['rg_delete'][$id]);
                if ($delete) {
                    $pdo->prepare("DELETE FROM ride_groups WHERE id = ?")->execute([$rid]);
                } else {
                    if ($name==='') continue;
                    // Store colorName (string) in ride_groups.color column
                    $pdo->prepare("UPDATE ride_groups SET name = ?, color = ? WHERE id = ?")
                        ->execute([$name, $colorName?:null, $rid]);
                }
            }
        }
        header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=ride-groups&saved=1");
        exit;
    }
    // Add new
    if (isset($_POST['action']) && $_POST['action']==='add-new') {
        $name = trim($_POST['new_name'] ?? '');
        $colorName = trim($_POST['new_color_id'] ?? '');
        if ($name!=='') {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ride_groups WHERE name = ?");
            $stmtCheck->execute([$name]);
            if ($stmtCheck->fetchColumn()==0) {
                $pdo->prepare("INSERT INTO ride_groups (name, color) VALUES (?, ?)")
                    ->execute([$name, $colorName?:null]);
            }
        }
        header("Location: $baseUrl/public/admin/admin-dashboard.php?mode=ride-groups&saved=1");
        exit;
    }
}
header("Location: $baseUrl/public/admin/admin-dashboard.php");
exit;
