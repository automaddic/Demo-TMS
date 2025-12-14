<?php
// public/admin/partials/checkin-table-body.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '/home/automaddic/mtb/server/config/bootstrap.php';
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('check-in.php', $pdo);

date_default_timezone_set('America/New_York');

// Get practice_day_id
$practiceDayId = isset($_GET['practice_day_id']) ? (int) $_GET['practice_day_id'] : 0;
if (!$practiceDayId) exit;

$now = new DateTime();

// Get window
$stmtPd = $pdo->prepare("SELECT start_datetime, end_datetime FROM practice_days WHERE id = ?");
$stmtPd->execute([$practiceDayId]);
$pd = $stmtPd->fetch(PDO::FETCH_ASSOC);

$start = $pd['start_datetime'] ? new DateTime($pd['start_datetime']) : null;
$start->modify('-2 hours');

$end = $pd['end_datetime'] ? new DateTime($pd['end_datetime']) : null;
if ($end) $end->modify('+2 hours');

$withinWindow = $start && $end && ($now >= $start && $now <= $end);
$afterEnd = $end && ($now > $end);

// Helpers
function getOrderedList(PDO $pdo, string $table): array {
    $allowed = ['schools', 'teams', 'ride_groups'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException("Invalid table name: {$table}");
    }
    $stmt = $pdo->query("SELECT id, name FROM {$table} ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildCaseOrder(string $column, array $orderedItems, bool $nullFirst = false): string {
    if (empty($orderedItems)) return "CASE WHEN 1=1 THEN 999 END";
    $cases = [];
    foreach ($orderedItems as $i => $row) {
        $val = (int) $row['id'];
        $cases[] = "WHEN {$column} = {$val} THEN {$i}";
    }
    $nullOrder = $nullFirst ? 0 : count($orderedItems); // NULLs first or last
    return "CASE WHEN {$column} IS NULL THEN {$nullOrder} ELSE " . implode(" ", $cases) . " ELSE {$nullOrder} END";
}

$searchTerm = trim($_GET['search'] ?? '');
$searchSql = '';
$searchParams = [];

if ($searchTerm !== '') {
    $searchSql = "AND (
        au.first_name LIKE :term1 OR
        au.last_name LIKE :term2 OR
        CONCAT(au.first_name,' ',au.last_name) LIKE :term3 OR
        s.name LIKE :term4 OR
        t.name LIKE :term5 OR
        rg.name LIKE :term6
    )";
    $searchParams = [
        'term1' => "%{$searchTerm}%",
        'term2' => "%{$searchTerm}%",
        'term3' => "%{$searchTerm}%",
        'term4' => "%{$searchTerm}%",
        'term5' => "%{$searchTerm}%",
        'term6' => "%{$searchTerm}%",
    ];
}

// Sorting tokens
$sortRaw = $_GET['sort'] ?? '';
$raw = preg_replace('/\s+order\s+by\s+/i', '', trim($sortRaw));
$raw = trim($raw, '_ ');
$tokens = $raw === '' ? [] : explode('_', $raw);

$indexOf = function($needle) use ($tokens) {
    foreach ($tokens as $i => $t) if ($t === $needle) return $i;
    return false;
};

// Sorting CASE for alt_users
$schoolCaseSql = buildCaseOrder('au.school_id', getOrderedList($pdo, 'schools'));
$teamCaseSql = buildCaseOrder('au.team_id', getOrderedList($pdo, 'teams'));
$rideGroupCaseSql = buildCaseOrder('au.ride_group_id', getOrderedList($pdo, 'ride_groups'));

$orderParts = [];

// Status sorting
if (($i = $indexOf('status')) !== false && isset($tokens[$i+1])) {
    $status = $tokens[$i+1];
    if ($status === 'not') {
        $orderParts[] = "CASE WHEN combined.check_in_time IS NULL THEN 0 ELSE 1 END ASC";
    } elseif ($status === 'in') {
        $orderParts[] = "CASE WHEN combined.check_in_time IS NOT NULL AND combined.check_out_time IS NULL THEN 0 ELSE 1 END ASC";
    } elseif ($status === 'out') {
        $orderParts[] = "CASE WHEN combined.check_out_time IS NOT NULL THEN 0 ELSE 1 END ASC";
    }
}

// Coach/student ordering
if (($i = $indexOf('orders')) !== false && isset($tokens[$i+1])) {
    $ord = $tokens[$i+1];
    if ($ord === 'coach') {
        $orderParts[] = "(role_level > 1) DESC";
    } elseif ($ord === 'student') {
        $orderParts[] = "(role_level <= 1) DESC";
    }
}

// Name sorting
if (stripos($raw, 'firsts_asc') !== false)  $orderParts[] = "combined.first_name ASC";
if (stripos($raw, 'firsts_desc') !== false) $orderParts[] = "combined.first_name DESC";
if (stripos($raw, 'lasts_asc') !== false)   $orderParts[] = "combined.last_name ASC";
if (stripos($raw, 'lasts_desc') !== false)  $orderParts[] = "combined.last_name DESC";

// Schools, teams, groups
foreach (['schools' => 'school_name', 'teams' => 'team_name', 'groups' => 'ride_group_name'] as $tok => $col) {
    if (($i = $indexOf($tok)) !== false && isset($tokens[$i+1])) {
        $rawVal = $tokens[$i+1];
        if (strtolower($rawVal) !== 'none') {
            $val = strtolower(str_replace('_', ' ', $rawVal));
            $orderParts[] = "CASE WHEN LOWER(combined.{$col}) = " . $pdo->quote($val) . " THEN 0 ELSE 1 END ASC";
            $orderParts[] = "combined.{$col} ASC";
        }
    }
}

if (empty($orderParts)) $orderParts[] = "combined.first_name ASC";
$sortClause = 'ORDER BY ' . implode(', ', $orderParts);

file_put_contents(__DIR__ . '/debug-sort.log', date('c') . " - ORDER BY clause: {$sortClause}\n", FILE_APPEND);


// --- SQL FOR ALT USERS ONLY ---
if ($afterEnd) {
    // Only checked-in & not checked-out users
    $sql = "
    SELECT * FROM (
        SELECT
            au.id AS user_id,
            CONCAT(au.first_name, ' ', au.last_name) AS full_name,
            au.first_name, au.last_name, au.preferred_name,
            au.role_level,
            au.ride_group_id, rg.name AS ride_group_name,
            au.team_id, t.name AS team_name,
            au.school_id, s.name AS school_name,
            ci.id AS checkin_id,
            ci.check_in_time, ci.check_out_time, ci.elapsed_seconds,
            ci.requires_approval, ci.approved,
            'alt_users' AS source
        FROM check_ins ci
        JOIN alt_users au ON ci.user_id = au.id
        LEFT JOIN ride_groups rg ON au.ride_group_id = rg.id
        LEFT JOIN teams t ON au.team_id = t.id
        LEFT JOIN schools s ON au.school_id = s.id
        WHERE ci.practice_day_id = :practice_day_id
          AND au.is_suspended = 0
          AND ci.check_in_time IS NOT NULL
          AND ci.check_out_time IS NULL
          $searchSql
    ) AS combined
    $sortClause
    ";
} else {
    // All alt_users (with or without check-ins)
    $sql = "
    SELECT * FROM (
        SELECT
            au.id AS user_id,
            CONCAT(au.first_name, ' ', au.last_name) AS full_name,
            au.first_name, au.last_name, au.preferred_name,
            au.role_level,
            au.ride_group_id, rg.name AS ride_group_name,
            au.team_id, t.name AS team_name,
            au.school_id, s.name AS school_name,
            ci.id AS checkin_id,
            ci.check_in_time, ci.check_out_time, ci.elapsed_seconds,
            ci.requires_approval, ci.approved,
            'alt_users' AS source
        FROM alt_users au
        LEFT JOIN check_ins ci ON ci.user_id = au.id AND ci.practice_day_id = :practice_day_id
        LEFT JOIN ride_groups rg ON au.ride_group_id = rg.id
        LEFT JOIN teams t ON au.team_id = t.id
        LEFT JOIN schools s ON au.school_id = s.id
        WHERE au.is_suspended = 0
        $searchSql
    ) AS combined
    $sortClause
    ";
}

$params = $searchParams + ['practice_day_id' => $practiceDayId];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ----- OUTPUT -----
foreach ($rows as $row) {
    $uid = $row['user_id'];
    $name = htmlspecialchars($row['full_name']);
    $school = htmlspecialchars($row['school_name'] ?? '');
    $team = htmlspecialchars($row['team_name'] ?? '');
    $rg = htmlspecialchars($row['ride_group_name'] ?? '');

    // Status
    if (!$row['check_in_time']) {
        $status = 'Not In';
    } elseif (!$row['check_out_time']) {
        $status = 'Checked In';
    } else {
        $status = 'Checked Out';
    }

    // Elapsed time
    $delta = (int)$row['elapsed_seconds'];
    if ($row['check_in_time'] && !$row['check_out_time']) {
        $in = new DateTime($row['check_in_time']);
        $delta = max(0, $now->getTimestamp() - $in->getTimestamp());
    } elseif ($row['check_in_time'] && $row['check_out_time']) {
        $in = new DateTime($row['check_in_time']);
        $out = new DateTime($row['check_out_time']);
        $delta = max(0, $out->getTimestamp() - $in->getTimestamp());
    }

    $fmt = sprintf('%02d:%02d:%02d', floor($delta/3600), floor(($delta%3600)/60), $delta%60);
    $deltaAttr = ($status === 'Checked In') ? "data-start-delta='{$delta}'" : "";
    $checkedOut = ($status === 'Checked Out') ? 1 : 0;
    $checkedIn = ($status === 'Checked In') ? 1 : 0;

    if (!empty($end)) {
        $inDisabled = ($status === 'Not In' && $withinWindow) ? '' : 'disabled';
    } else {
        $inDisabled = ($status === 'Not In') ? '' : 'disabled';
    }

    $outDisabled = ($status === 'Checked In') ? '' : 'disabled';
    $rsDisabled = in_array($status, ['Checked In', 'Checked Out']) ? '' : 'disabled';

    // Approval indicator
    if ($status === 'Checked Out') {
        if ($row['requires_approval'] && !$row['approved']) {
            $conf = '<span class="alert error">Requires Approval</span>';
        } elseif ($row['approved']) {
            $conf = '<span class="alert">Approved</span>';
        } else {
            $conf = '<span>—</span>';
        }
    } else {
        $conf = '<span>—</span>';
    }

    $roleFlag = ((int)$row['role_level'] > 1) ? 'coach' : 'user';

    echo "<tr data-role='{$roleFlag}'>";
    echo "  <td class='col-first_name'>
                <button class='user-info-btn' data-user-id='{$uid}' data-source='alt_users'>
                    {$name}
                </button>
            </td>";
    echo "  <td class='col-school'>{$school}</td>";
    echo "  <td class='col-team'>{$team}</td>";
    echo "  <td class='col-ride_group'>{$rg}</td>";
    echo "  <td class='col-status'>{$status}</td>";
    echo "  <td class='col-checkin'><button class='check-in' data-user-id='{$uid}' data-source='alt_users' {$inDisabled}>Check In</button></td>";
    echo "  <td class='col-checkout'><button class='check-out' data-user-id='{$uid}' data-source='alt_users' {$outDisabled}>Check Out</button></td>";
    echo "  <td class='col-reset'><button class='reset-user' data-user-id='{$uid}' data-source='alt_users' {$rsDisabled}>↺</button></td>";
    echo "  <td class='col-elapsed'><span class='elapsed' data-user-id='{$uid}' {$deltaAttr} data-checked-out='{$checkedOut}' data-checked-in='{$checkedIn}'>{$fmt}</span></td>";
    echo "  <td class='col-confirmed'>{$conf}</td>";
    echo "</tr>";
}
