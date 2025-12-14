<?php
require_once __DIR__ . '/../../config/bootstrap.php'; // loads .env
function getCurrentUser(PDO $pdo)
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . __DIR__ .'/../../public/login.php');
        exit;

    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    return $stmt->fetch();
}
