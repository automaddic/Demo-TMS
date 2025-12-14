<?php
require_once __DIR__ . '/../../config/bootstrap.php'; // loads .env

function getWeatherForecast(string $targetDate, int $targetHour, float $lat, float $lon): array {
    $apiKey = $_ENV['OPENWEATHER_API_KEY'];

    $url = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&units=imperial&appid={$apiKey}";
    $json = @file_get_contents($url);

    if ($json === false) {
        return [
            'main' => 'Unavailable',
            'temp' => '--',
            'feels_like' => '--',
            'humidity' => '--',
            'icon' => '01d',
            'error' => 'API request failed'
        ];
    }

    $data = json_decode($json, true);
    if (!isset($data['list'])) {
        return [
            'main' => 'Unavailable',
            'temp' => '--',
            'feels_like' => '--',
            'humidity' => '--',
            'icon' => '01d',
            'error' => 'Malformed API response'
        ];
    }

    // Convert target date + hour into a timestamp
    $targetTimestamp = strtotime("{$targetDate} {$targetHour}:00:00");

    $closest = null;
    $minDiff = PHP_INT_MAX;

    foreach ($data['list'] as $entry) {
        $entryTimestamp = strtotime($entry['dt_txt']);
        $diff = abs($entryTimestamp - $targetTimestamp);

        // Find the forecast closest in time (within 5 days)
        if ($diff < $minDiff) {
            $minDiff = $diff;
            $closest = $entry;
        }
    }

    if ($closest) {
        return [
            'main' => $closest['weather'][0]['main'],
            'temp' => round($closest['main']['temp']),
            'feels_like' => round($closest['main']['feels_like']),
            'humidity' => $closest['main']['humidity'],
            'icon' => $closest['weather'][0]['icon'],
            'dt_txt' => $closest['dt_txt'] // debugging info
        ];
    }

    return [
        'main' => 'Unavailable',
        'temp' => '--',
        'feels_like' => '--',
        'humidity' => '--',
        'icon' => '01d',
        'error' => 'No matching forecast found'
    ];
}
