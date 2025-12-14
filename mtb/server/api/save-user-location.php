<?php

require_once __DIR__ . '/../config/bootstrap.php'; // loads .env

header('Content-Type: application/json');

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['lat'], $data['lon'], $data['date'], $data['hour'])) {
    // Save location & time as you already do (DB or session, etc)
    // For example:
    $_SESSION['user_location'] = [
        'lat' => $data['lat'],
        'lon' => $data['lon'],
        'date' => $data['date'],
        'hour' => $data['hour'],
    ];


    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Invalid data']);
}