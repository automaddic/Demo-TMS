<?php
$markerFile = __DIR__ . '/../../../../user-data/spreadsheets/latest_update.txt';
header('Content-Type: application/json');

if (!file_exists($markerFile)) {
    echo json_encode(['updated' => false, 'timestamp' => null]);
    exit;
}

$timestamp = (int) trim(file_get_contents($markerFile));
echo json_encode([
    'updated' => true,
    'timestamp' => $timestamp
]);
