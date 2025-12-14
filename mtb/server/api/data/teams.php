<?php
require_once __DIR__ . '/../../config/bootstrap.php'; // loads .env
function getTeams(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, name FROM teams");
    return $stmt->fetchAll();
}
