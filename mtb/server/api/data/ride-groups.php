<?php
require_once __DIR__ . '/../../config/bootstrap.php'; // loads .env
function getRideGroups(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, name FROM ride_groups");
    return $stmt->fetchAll();
}
