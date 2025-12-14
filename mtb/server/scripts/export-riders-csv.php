<?php
require_once __DIR__ . '/../config/bootstrap.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Force download as CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="checkins_all_' . date("Y-m-d_His") . '.csv"');

// Pivot function used for BOTH riders + coaches combined
function pivotCheckInsAll(array $rows): array {
    $data = [];
    $allEvents = [];

    foreach ($rows as $row) {
        $name = $row['name'];
        $event = $row['event'];
        $elapsed = floatval($row['elapsed']);

        if (!isset($data[$name])) {
            $data[$name] = [];
        }
        if (!isset($data[$name][$event])) {
            $data[$name][$event] = 0;
        }

        $data[$name][$event] += $elapsed;

        // Track unique event names
        $allEvents[$event] = true;
    }

    // Sort events by alphabetical (usually date-based strings sort naturally)
    ksort($allEvents);
    $events = array_keys($allEvents);

    // Output CSV header
    $header = array_merge(['Name'], $events, ['Total']);
    $output = [$header];

    foreach ($data as $name => $eventTimes) {
        $row = [$name];
        $total = 0;

        foreach ($events as $event) {
            if (isset($eventTimes[$event])) {
                $elapsed = $eventTimes[$event];
                $row[] = number_format($elapsed, 2);
                $total += $elapsed;
            } else {
                $row[] = '';
            }
        }

        $row[] = number_format($total, 2);
        $output[] = $row;
    }

    return $output;
}

// ► MASTER QUERY — pulls ALL CHECKINS for ALL USERS
$allCheckInsQuery = "
    SELECT 
        CONCAT(
            COALESCE(u.first_name, au.first_name), ' ', 
            COALESCE(u.last_name, au.last_name)
        ) AS name,
        CONCAT(DATE_FORMAT(pd.date, '%m/%d'), ' ', pd.name) AS event,
        ci.elapsed_seconds / 3600 AS elapsed
    FROM check_ins ci
    LEFT JOIN users u ON ci.user_id = u.id AND ci.is_alt_user = 0
    LEFT JOIN alt_users au ON ci.user_id = au.id AND ci.is_alt_user = 1
    JOIN practice_days pd ON ci.practice_day_id = pd.id
    WHERE ci.approved = 1
    ORDER BY name, event
";

// FETCH ALL check-ins
$stmt = $pdo->query($allCheckInsQuery);
$allCheckInsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PIVOT the combined data
$pivot = pivotCheckInsAll($allCheckInsRaw);

// CSV Output
$fp = fopen('php://output', 'w');

// UTF-8 BOM (Excel compatibility)
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

foreach ($pivot as $row) {
    fputcsv($fp, $row);
}

fclose($fp);
exit;
