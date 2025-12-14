<?php
require_once __DIR__ . '/../../config/bootstrap.php'; // loads .env
function getSchools(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, name FROM schools");
    return $stmt->fetchAll();
}
