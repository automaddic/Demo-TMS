<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.$baseUrl.'/public/admin/admin-dashboard.php?mode=schools');
    exit;
}

$errors = [];
// Handle deletions
if (isset($_POST['delete'])) {
    $id = (int)$_POST['delete'];
    $stmt = $pdo->prepare("DELETE FROM schools WHERE id = ?");
    if (!$stmt->execute([$id])) {
        $errors[] = $id;
    }
}
// Handle updates
if (isset($_POST['schools']) && is_array($_POST['schools'])) {
    foreach ($_POST['schools'] as $id => $name) {
        if ($id === 'new') continue;
        $id = (int)$id;
        $name = trim($name);
        if ($name === '') continue;
        $stmt = $pdo->prepare("UPDATE schools SET name = ? WHERE id = ?");
        if (!$stmt->execute([$name, $id])) {
            $errors[] = $id;
        }
    }
}
// Handle new
if (!empty($_POST['schools']['new'])) {
    $newName = trim($_POST['schools']['new']);
    if ($newName !== '') {
        $stmt = $pdo->prepare("INSERT INTO schools (name) VALUES (?)");
        if (!$stmt->execute([$newName])) {
            $errors[] = 'new';
        }
    }
}

if (empty($errors)) {
    header('Location: '.$baseUrl.'/public/admin/admin-dashboard.php?mode=schools&saved=1');
} else {
    header('Location: '.$baseUrl.'/public/admin/admin-dashboard.php?mode=schools&error=1');
}
exit;