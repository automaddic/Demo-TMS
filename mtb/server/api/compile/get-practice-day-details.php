<?php
// server/api/get-practice-day-details.php

require_once __DIR__ . '/../../config/bootstrap.php'; // loads .env
require __DIR__ . '/../data/weather.php';

header('Content-Type: application/json');

// Ensure user is logged in
$userId = $_SESSION['user']['id'] ?? null;
$userId = $_SESSION['user']['id'] ?? null;
$rideGroupId = null;

$includeWeather = isset($_GET['include_weather']) && $_GET['include_weather'] == '1';

if ($userId) {
    $stmtRG = $pdo->prepare("SELECT ride_group_id FROM users WHERE id = ?");
    $stmtRG->execute([$userId]);
    $rideGroupId = $stmtRG->fetchColumn();
}

if (!$userId || !$rideGroupId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if (!$id) {
    echo json_encode(['error' => 'Invalid practice day ID']);
    exit;
}

// Fetch practice day and type
$stmt = $pdo->prepare("
    SELECT pd.*, dt.name AS day_type_name
    FROM practice_days pd
    LEFT JOIN day_types dt ON pd.day_type_id = dt.id
    WHERE pd.id = ?
");
$stmt->execute([$id]);
$pd = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pd) {
    echo json_encode(['error' => 'Practice day not found']);
    exit;
}

function getCachedWeather($pdo, $mapLink, $lat, $lon, $date, $hour)
{
    // 1. Try by full map link
    $stmt = $pdo->prepare("SELECT data FROM weather_cache WHERE map_link = ? AND date = ? AND hour = ?");
    $stmt->execute([$mapLink, $date, $hour]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) return json_decode($row['data'], true);

    // 2. Try by lat/lon
    if ($lat !== null && $lon !== null) {
        $stmt = $pdo->prepare("
            SELECT data FROM weather_cache
            WHERE lat = ? AND lon = ? AND date = ? AND hour = ?
        ");
        $stmt->execute([$lat, $lon, $date, $hour]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return json_decode($row['data'], true);
    }

    return null; // Not cached
}

function cacheWeather($pdo, $mapLink, $lat, $lon, $date, $hour, $weather)
{
    $stmt = $pdo->prepare("
        INSERT INTO weather_cache (map_link, lat, lon, date, hour, data)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $mapLink,
        $lat,
        $lon,
        $date,
        $hour,
        json_encode($weather)
    ]);
}


// Notes for user's ride group
$stmtN = $pdo->prepare("
    SELECT pn.notes, u.username AS coach_name
    FROM practice_notes pn
    JOIN users u ON pn.coach_id = u.id
    WHERE pn.practice_day_id = ? AND pn.ride_group_id = ?
    ORDER BY pn.id DESC
    LIMIT 10
");
$stmtN->execute([$id, $rideGroupId]);
$notes = $stmtN->fetchAll(PDO::FETCH_ASSOC);

function resolveFinalUrl($url)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_MAXREDIRS => 5,
    ]);

    curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return $finalUrl;
}

function extractLatLonFromMapLink($mapLink)
{
    // Resolve shortened links
    if (strpos($mapLink, 'goo.gl') !== false || strpos($mapLink, 'maps.app.goo.gl') !== false) {
        $mapLink = resolveFinalUrl($mapLink);
    }

    $lat = null;
    $lon = null;

    if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $mapLink, $matches) ||
        preg_match('/[?&](?:q|ll)=(-?\d+\.\d+),(-?\d+\.\d+)/', $mapLink, $matches) ||
        preg_match('/\/place\/(-?\d+\.\d+),(-?\d+\.\d+)/', $mapLink, $matches) ||
        preg_match('/(-?\d+\.\d+),\s*(-?\d+\.\d+)/', $mapLink, $matches)) {
        $lat = floatval($matches[1]);
        $lon = floatval($matches[2]);
    }

    return [$lat, $lon];
}

function extractDateHourFromDatetime($datetime)
{
    $dt = new DateTime($datetime);
    return [$dt->format('Y-m-d'), (int) $dt->format('G')]; // e.g., '2025-07-16', 17
}


[$date, $hour] = extractDateHourFromDatetime($pd['start_datetime'] ?? '');
$mapLinkRaw = $pd['map_link'] ?? '';

// 1. Try direct cache hit by original map link
$weather = getCachedWeather($pdo, $mapLinkRaw, null, null, $date, $hour);

// 2. If not found, resolve final URL and extract lat/lon
if (!$weather) {
    $finalMapLink = resolveFinalUrl($mapLinkRaw);
    [$lat, $lon] = extractLatLonFromMapLink($finalMapLink);

    // Try again to find cached result using resolved lat/lon
    if ($lat !== null && $lon !== null) {
        $weather = getCachedWeather($pdo, null, $lat, $lon, $date, $hour);
    }

    // 3. If still not found, fetch from OpenWeather and cache it
    if (!$weather && $lat !== null && $lon !== null) {
        $weather = getWeatherForecast($date, $hour, $lat, $lon);
        if ($weather) {
            cacheWeather($pdo, $mapLinkRaw, $lat, $lon, $date, $hour, $weather);
        }
    }
}


// Prepare output
$response = [
    'id' => $pd['id'],
    'name' => $pd['name'],
    'start_datetime' => $pd['start_datetime'],
    'end_datetime' => $pd['end_datetime'],
    'location' => $pd['location'],
    'map_link' => $pd['map_link'],
    'day_type_name' => $pd['day_type_name'],
    'weather' => $weather ?: null,
    'notes' => $notes,
];

echo json_encode($response);

exit;
