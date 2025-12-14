<?php
require_once __DIR__ . '/../../config/bootstrap.php'; // loads .env
require_once __DIR__ . '/../data/weather.php';

header('Content-Type: application/json');

// Default fallback location
$lat = $_SESSION['user_location']['lat'] ?? 33.9526;
$lon = $_SESSION['user_location']['lon'] ?? -84.5499;
$date = $_SESSION['user_location']['date'] ?? date("Y-m-d");
$hour = $_SESSION['user_location']['hour'] ?? intval(date("H"));

$weather = getWeatherForecast($date, $hour, $lat, $lon);

echo json_encode($weather);
