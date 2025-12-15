<?php
// public/admin/check-in.php

require_once '/home/automaddic/mtb/server/config/bootstrap.php'; // adjust path
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie(basename(__FILE__), $pdo);

// Fetch all school names
$schoolNames = $pdo->query("SELECT name FROM schools ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Fetch all team names
$teamNames = $pdo->query("SELECT name FROM teams ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Fetch all ride group names
$rideGroupNames = $pdo->query("SELECT name FROM ride_groups ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$now = new DateTime();

// Base URL, adjust if needed
?>
<?php
// 1. Check if anyone is still checked in on ANY practice day
$check = $pdo->query("
    SELECT ci.practice_day_id, COUNT(*) AS count_checked_in
    FROM check_ins ci
    WHERE ci.check_in_time IS NOT NULL AND ci.check_out_time IS NULL
    GROUP BY ci.practice_day_id
    LIMIT 1
");

$checkedInRow = $check->fetch(PDO::FETCH_ASSOC);
$isSomeoneStillCheckedIn = $checkedInRow && $checkedInRow['count_checked_in'] > 0;

if ($isSomeoneStillCheckedIn) {
    // Use the practice day where someone is still checked in
    $practiceDayId = (int)$checkedInRow['practice_day_id'];
    
    $stmt = $pdo->prepare("
        SELECT pd.id, pd.name, pd.date, pd.start_datetime, pd.end_datetime, dt.name AS day_type_name
        FROM practice_days pd
        LEFT JOIN day_types dt ON pd.day_type_id = dt.id
        WHERE pd.id = ?
        LIMIT 1
    ");
    $stmt->execute([$practiceDayId]);
    $practiceDay = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // 2. No one checked in — load all practice days today ordered by start_datetime ASC
    $stmt = $pdo->prepare("
        SELECT pd.id, pd.name, pd.date, pd.start_datetime, pd.end_datetime, pd.has_ended, dt.name AS day_type_name
        FROM practice_days pd
        LEFT JOIN day_types dt ON pd.day_type_id = dt.id
        WHERE DATE(CONVERT_TZ(pd.start_datetime, '+00:00', 'America/New_York')) = CURDATE()
        ORDER BY pd.start_datetime ASC
    ");
    $stmt->execute();
    $practiceDaysToday = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasPracticeDaysToday = count($practiceDaysToday) > 0;

    $hasUpcomingOrOngoingPracticeToday = false;
    foreach ($practiceDaysToday as $pdCheck) {
        if (!empty($pdCheck['end_datetime'])) {

            $endCheck = new DateTime($pdCheck['end_datetime'], new DateTimeZone('America/New_York'));
            $endCheck->modify('+2 hours');

            if ($now <= $endCheck) {
                $hasUpcomingOrOngoingPracticeToday = true;
                break;
            }
        } elseif (!empty($pdCheck['start_datetime']) && ($pdCheck['has_ended'] === 0)) {

            $startCheck = new DateTime($pdCheck['start_datetime'], new DateTimeZone('America/New_York'));
            $startCheck->modify('-2 hours');

            if ($now >= $startCheck) {
                $hasUpcomingOrOngoingPracticeToday = true;
                break;
            }
            
        }
        

        
    }

    $practiceDay = null;

    foreach ($practiceDaysToday as $pd) {
        if (empty($pd['start_datetime'])) {
            // skip malformed entries
            continue;
        }
        $start = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
        $start->modify('-2 hours');

        if (!empty($pd['end_datetime'])) {

            $end = new DateTime($pd['end_datetime'], new DateTimeZone('America/New_York'));
            $end->modify('+2 hours');

        }
        
        if ($now < $start) {
            // The practice hasn't started yet - pick this one and break
            $practiceDay = $pd;
            break;
        } elseif ($now >= $start && $pd['has_ended'] === 0) {
            // Practice currently ongoing - pick this one and break
            $practiceDay = $pd;
            break;
        }

        
        // else current practice day ended, so keep looping to find next one
    }

    // If none found yet, pick the last practice day (the one that ended last today)
    if (!$practiceDay && !empty($practiceDaysToday)) {
        $practiceDay = end($practiceDaysToday);
    }
}


$practiceDayId = $practiceDay['id'] ?? null;

$practiceStartIso = !empty($practiceDay['start_datetime'])
    ? (function() {
        $dt = new DateTime($practiceDay['start_datetime'], new DateTimeZone('America/New_York'));
        $dt->modify('-2 hours');
        return $dt->format(DateTime::ATOM);
      })()
    : null;

$practiceEndIso = !empty($practiceDay['end_datetime'])
    ? (function() {
        $dt = new DateTime($practiceDay['end_datetime'], new DateTimeZone('America/New_York'));
        $dt->modify('+2 hours');
        return $dt->format(DateTime::ATOM);
      })()
    : null;

// Fetch all upcoming practice days (starting today or later) for the dropdown
$stmtPracticeDays = $pdo->prepare("
    SELECT id, name, date, start_datetime, end_datetime 
    FROM practice_days
    WHERE DATE(CONVERT_TZ(start_datetime, '+00:00', 'America/New_York')) <= CURDATE()
    ORDER BY start_datetime DESC
    LIMIT 14
");
$stmtPracticeDays->execute();
$upcomingPracticeDays = $stmtPracticeDays->fetchAll(PDO::FETCH_ASSOC);



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practice Check-In - Admin</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/admin/styles/check-in.css">
</head>

<body>
    <?php

    include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php";


    ?>
    
    <div class="page-wrapper">
       <!--<button id="manual-checkin-btn" class="manual-checkin-btn">Manual Check-In</button>-->

        <div id="manual-checkin-modal" class="modal-overlay" style="display:none; z-index:10000;">
            <div class="modal-content">
                <button class="modal-close">&times;</button>
                <h2>Manual Check‑In</h2>
                <form id="manual-checkin-form">
                <input type="hidden" name="user_id" id="mc-user-id" value="">
                <input type="hidden" name="is_alt_user" id="mc-alt-check" value="">

                <label for="mc-practice-day">Practice Day</label>
                <select id="mc-practice-day" name="practice_day_id" required>
                    <option value="" disabled selected>Select a practice day</option>
                    <?php foreach ($upcomingPracticeDays as $pd): ?>
                        <option 
                            value="<?= htmlspecialchars($pd['id']) ?>"
                            data-start="<?= htmlspecialchars($pd['start_datetime']) ?>"
                            data-end="<?= htmlspecialchars($pd['end_datetime']) ?>"
                        >
                            <?= htmlspecialchars($pd['name']) ?> (<?= date('F j, Y', strtotime($pd['date'])) ?>)
                        </option>
                    <?php endforeach; ?>

                </select>

                <label for="mc-user-search">Name</label>
                <div style="position: relative;">
                    <input type="text" id="mc-user-search" name="user_name" autocomplete="off" required>
                    <ul id="mc-suggestions" class="autocomplete-list" style="display: none;"></ul>
                </div>


                <label for="mc-checkin">Check‑In Time</label>
                <input type="time" id="mc-checkin" name="check_in_time">

                <label for="mc-checkout">Check‑Out Time</label>
                <input type="time" id="mc-checkout" name="check_out_time">

                <button type="submit">Save</button>
                </form>
            </div>
        </div>

        <div id="user-info-modal" class="modal-overlay" style="display:none; z-index:10000;">
            <div class="modal-content">
                <button class="modal-close">&times;</button>
                <h2>Emergency Contact Info</h2>

                <div id="user-info-body">
                <p>Loading...</p>
                </div>
            </div>
        </div>


        <?php if (!$isSomeoneStillCheckedIn && !$hasPracticeDaysToday): ?>
            <div class="overlay">
                <div class="no-practice-modal">
                    <h2>No Practice Scheduled</h2>
                    <p>There is no practice scheduled for today (<?= date('F j, Y') ?>).</p>
                </div>
            </div>
        <?php elseif (!$isSomeoneStillCheckedIn && $hasPracticeDaysToday && (!$hasUpcomingOrOngoingPracticeToday || ($practiceDay['has_ended'] === 1))): ?>
            <div class="overlay">
                <div class="no-practice-modal">
                    <h2>Practice has ended</h2>
                    <p>All practice scheduled for today (<?= date('F j, Y') ?>) has ended. Everyone has been checked out successfully.</p>
                </div>
            </div>
        <?php else: ?>
            <?php
            $start = $practiceDay['start_datetime'] ? new DateTime($practiceDay['start_datetime']) : null;
            $end = $practiceDay['end_datetime'] ? new DateTime($practiceDay['end_datetime']) : null;
            $beforeStart = $start && $now < $start;
            $withinWindow = $start && $end && ($now >= $start && $now <= $end);
            $afterEnd = $end && $now > $end;
            ?>
            <h1>
                <?= htmlspecialchars($practiceDay['name']) ?>
                (<?= date('F j, Y', strtotime($practiceDay['date'])) ?>)
                <?php if (!empty($practiceDay['day_type_name'])): ?>
                    <span class="day-type-badge"><?= htmlspecialchars($practiceDay['day_type_name']) ?></span>
                <?php endif; ?>
            </h1>
            <?php if ($start): ?>
                <div id="practice-status"
                    data-start="<?= $start->getTimestamp() ?>"
                    data-end="<?= $end ? $end->getTimestamp() : ''?>">
                    <!-- Initial fallback message -->
                    <p>Loading practice status...</p>
                </div>
            <?php endif; ?>


            <!-- Controls -->
        
                <div id="controls">
                    <div class="search-wrapper">
                        <input type="text" id="search-bar" placeholder="Search by name, team, school, ride group..." />
                        <button type="button" id="search-clear">✕</button>
                    </div>
                    <details style="margin-left: 0; width: 100%; margin-bottom: 10px;">
                        <summary>Sorting Options</summary>
                        <span id="sort-header"></span>
                        <div id="sort-buttons">
                            <button class="sort-btn" data-sort="firsts">First Name</button>
                            <button class="sort-btn" data-sort="lasts">Last Name</button>
                            <button class="sort-btn" data-sort="schools">School</button>
                            <button class="sort-btn" data-sort="teams">Team</button>
                            <button class="sort-btn" data-sort="groups">Ride Group</button>
                            <button class="sort-btn" data-sort="status">Status</button>
                            <button class="sort-btn" data-sort="orders">Rider Order</button>

                        </div>
                    </details>
                    <button id="reset-all" class="reset-btn">Reset All</button>
                    <button id="end-day" class="end-btn">End Pracice Day</button>
                </div>

            <!-- Main check-in table -->
            <div class="table-container">
                <div id="loading-spinner" class="hidden">Loading…</div>
                <table id="checkin-table">
                    <thead>
                        <tr>
                            <th class="col-first_name">Name</th>
                            <th class="col-school">School</th>
                            <th class="col-team">Team</th>
                            <th class="col-ride_group">Ride Group</th>
                            <th class="col-status">Status</th>
                            <th class="col-checkin">Check In</th>
                            <th class="col-checkout">Check Out</th>
                            <th class="col-reset">Reset</th>
                            <th class="col-elapsed">Elapsed Time</th>
                            <th class="col-confirmed">Confirmed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Filled by AJAX -->
                    </tbody>
                </table>
            </div>
            <!-- 
            DEPRICATED
            
            <h2>Requires Approval / Edit (Elapsed > 3h30m OR check-out after window)</h2>
            <div class="table-container">
                <div id="loading-spinner-approval" class="hidden">Loading…</div>
                <table id="approval-table">
                    <thead>
                        <tr>
                            <th class="col-first_name">Name</th>
                            <th class="col-school">School</th>
                            <th class="col-team">Team</th>
                            <th class="col-ride_group">Ride Group</th>
                            <th class="col-elapsed">Elapsed Time</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        
                    </tbody>
                </table>
            </div>

            -->

            <!-- Confirm modal -->
            <div id="confirm-modal" class="time-modal hidden">
                <div class="time-modal-content">
                    <p id="confirm-message">Are you sure?</p>
                    <button id="confirm-yes">Yes</button>
                    <button id="confirm-no">No</button>
                </div>
            </div>
            <!-- Edit-time modal -->
            <div id="edit-time-modal" class="time-modal hidden">
                <div class="time-modal-content">
                    <h3>Edit Elapsed Time</h3>
                    <p>Enter elapsed time as HH:MM:SS:</p>
                    <input type="number" id="edit-hours" min="0" placeholder="HH" /> :
                    <input type="number" id="edit-minutes" min="0" max="59" placeholder="MM" /> :
                    <input type="number" id="edit-seconds" min="0" max="59" placeholder="SS" />
                    <div style="margin-top:12px;">
                        <button id="edit-time-save">Save</button>
                        <button id="edit-time-cancel">Cancel</button>
                    </div>
                </div>
            </div>

            <div id="auto-refresh-toast" class="toast">
                🔄 This table auto-refreshes every 30 seconds.
            </div>

            
            
            <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const practiceStart = <?= $start ? ('new Date("' . $start->format('c') . '")') : 'null' ?>;
                    const practiceEnd = <?= $end ? ('new Date("' . $end->format('c') . '")') : 'null' ?>;
                    const hasEnded = <?= !empty($practiceDay['has_ended']) && $practiceDay['has_ended'] ? 0 : 1 ?>;

                    const RELOAD_FLAG = 'practiceWindowReloaded';

                    function checkPracticeWindowStatus() {
                        const now = new Date();
                        const isAfterEnd = (hasEnded === 1) || (practiceEnd && now > practiceEnd);
                        if (!sessionStorage.getItem(RELOAD_FLAG) && isAfterEnd) {
                            sessionStorage.setItem(RELOAD_FLAG, '1');
                            location.reload();
                        }
                    }
                    setInterval(checkPracticeWindowStatus, 30000);
                    checkPracticeWindowStatus();    

                });
            </script>

           <script>
                document.addEventListener("DOMContentLoaded", () => {
                    const toast = document.getElementById("auto-refresh-toast");

                    // Show toast after short delay
                    setTimeout(() => {
                    toast.classList.add("show");
                    }, 500);

                    // Auto-hide after 10 seconds
                    setTimeout(() => {
                    toast.classList.remove("show");
                    }, 7500);

                    // Dismiss on click
                    toast.addEventListener("click", () => {
                    toast.classList.remove("show");
                    });
                });
            </script>

            <script>
                function updatePracticeStatus() {
                    const statusEl = document.getElementById("practice-status");
                    if (!statusEl) return;

                    const start = parseInt(statusEl.dataset.start);
                    const end = statusEl.dataset.end ? parseInt(statusEl.dataset.end) : null; // allow null
                    const now = Math.floor(Date.now() / 1000); // current time in seconds

                    let html = `<p>Window: ${formatTime(start)} – ${end ? formatTime(end) : 'N/A'}</p>`;

                    const adjustedStart = start - 2 * 3600; // minus 2 hours
                    const adjustedEnd = end ? end + 2 * 3600 : null; // plus 2 hours, only if end exists

                    html += `<p style="font-size: 16px !important; padding: 0 5px; max-width: 250px; border: 2px solid white; border-radius: 20px;">
                                Check-in window: ${formatTime(adjustedStart)} ${adjustedEnd ? ("- " + formatTime(adjustedEnd)) : 'OPEN'}
                            </p>`;

                    if (now < adjustedStart) {
                        html += `<p style="color:yellow;">Practice has not started yet. Check-in opens at ${formatTime(adjustedStart)}.</p>`;
                    } else if (adjustedEnd && now <= adjustedEnd) {
                        html += `<p style="color:lightgreen;">Practice is ongoing. Check-in is open until ${formatTime(adjustedEnd)}.</p>`;
                    } else if (!adjustedEnd) {
                        html += `<p style="color:red;">Practice is ongoing. Check-in time open until MANUAL CLOSURE.</p>`;
                    } else {
                        html += `<p style="color:orange;">Practice window ended at ${formatTime(adjustedEnd)}. Displaying users still checked in.</p>`;
                    }

                    statusEl.innerHTML = html;
                }

                function formatTime(unixTimestamp) {
                    if (!unixTimestamp) return "N/A";
                    const date = new Date(unixTimestamp * 1000);
                    let hours = date.getHours();
                    const minutes = date.getMinutes();
                    const ampm = hours >= 12 ? "pm" : "am";
                    hours = hours % 12 || 12;
                    return `${hours}:${minutes.toString().padStart(2, "0")}${ampm}`;
                }

                document.addEventListener("DOMContentLoaded", () => {
                    updatePracticeStatus();
                    setInterval(updatePracticeStatus, 30000); // update every 30 seconds
                });
            </script>


            <script>
                const baseUrl = '<?= $baseUrl?>';
                // Embed window times
                const practiceStart = <?= $practiceStartIso ? json_encode($practiceStartIso) : 'null' ?>;
                const practiceEnd = <?= $practiceEndIso ? json_encode($practiceEndIso) : 'null' ?>;
                let practiceStartDate = practiceStart ? new Date(practiceStart) : null;
                let practiceEndDate = practiceEnd ? new Date(practiceEnd) : null;

                function isWithinWindow() {
                    if (!practiceStartDate) return false;
                    if (!practiceEndDate) return true;
                    const now = new Date();
                    return now >= practiceStartDate && now <= practiceEndDate;
                }
                function isAfterEnd() {
                    if (!practiceEndDate) return false;
                    return new Date() > practiceEndDate;
                }

               
                const practiceDayId = <?= json_encode($practiceDayId) ?>;

                let currentSearch = '';
                let sort = '';
                let currentOrder = 'asc';

                function pad(n) { return n < 10 ? '0' + n : n; }
                function fmt(sec) {
                    const h = Math.floor(sec / 3600),
                        m = Math.floor(sec % 3600 / 60),
                        s = sec % 60;
                    return `${pad(h)}:${pad(m)}:${pad(s)}`;
                }

                function showSpinner(which) {
                    if (which === 'main') {
                        document.getElementById('loading-spinner').classList.remove('hidden');
                    } 
                }
                function hideSpinner(which) {
                    if (which === 'main') {
                        document.getElementById('loading-spinner').classList.add('hidden');
                    } 
                }

                function showConfirm(message) {
                    return new Promise(resolve => {
                        const modal = document.getElementById('confirm-modal');
                        const msgEl = document.getElementById('confirm-message');
                        const yesBtn = document.getElementById('confirm-yes');
                        const noBtn = document.getElementById('confirm-no');
                        msgEl.textContent = message;
                        modal.classList.remove('hidden');
                        function cleanup() {
                            modal.classList.add('hidden');
                            yesBtn.removeEventListener('click', onYes);
                            noBtn.removeEventListener('click', onNo);
                        }
                        function onYes() { cleanup(); resolve(true); }
                        function onNo() { cleanup(); resolve(false); }
                        yesBtn.addEventListener('click', onYes);
                        noBtn.addEventListener('click', onNo);
                    });
                }

                // DEPRICATED INSET HERE
                
                

            </script>

            <script>
                function normalizeName(name) {
                    if (!name) return '';
                    // trim whitespace
                    name = name.trim();
                    // collapse multiple spaces
                    name = name.replace(/\s+/g, ' ');
                    // replace spaces with underscores
                    name = name.replace(/ /g, '_');
                    // lowercase everything
                    return name.toLowerCase();
                }

                const rawSchool = <?= json_encode($schoolNames) ?>;
                const schoolNormalized = ['none', ...[...new Set(rawSchool.map(normalizeName))].sort()];

                const rawTeam = <?= json_encode($teamNames) ?>;
                const teamNormalized = ['none', ...[...new Set(rawTeam.map(normalizeName))].sort()];

                const rawGroup = <?= json_encode($rideGroupNames) ?>;
                const groupNormalized = ['none', ...[...new Set(rawGroup.map(normalizeName))].sort()];

                let sortCycleState = {
                    firsts: ['asc','desc'],
                    lasts:  ['asc','desc'],
                    schools: schoolNormalized,
                    teams:   teamNormalized,
                    groups:  groupNormalized,
                    status: ['none','not','in','out'],
                    orders:  ['none','coach','student']
                };

                let sortIndexer = {};
                </script>


            <script>

            async function loadTables() {
                    const searchInput = document.getElementById('search-bar');
                    const wasSearchFocused = (document.activeElement === searchInput);

                    // Disable buttons while loading
                    document.querySelectorAll('#controls button').forEach(el => el.disabled = true);
                    showSpinner('main');
                    

                    try {
                        const params = new URLSearchParams();
                        if (currentSearch) params.append('search', currentSearch);
                        if (sort) {
                            params.append('sort', sort);
                        }
                        params.append('practice_day_id', practiceDayId);

                        // Always fetch main table
                        const resMain = await fetch(baseUrl + '/public/admin/partials/checkin-table-body.php?' + params.toString());
                        const htmlMain = await resMain.text();


                        // 1) Inject new HTML
                        document.querySelector('#checkin-table tbody').innerHTML = htmlMain;

                        // ─── Insert the new snippet right here ───
                        // 2) Toggle the mobile “show-col-…” class

                        const tbl = document.getElementById('checkin-table');

                        tbl.classList.remove(
                            'show-col-school', 'show-col-team', 'show-col-ride_group', 'show-col-status'
                        );

                        // 3) Color each row by its “Status” cell
                        document.querySelectorAll('#checkin-table tbody tr').forEach(row => {
                            row.classList.remove('status-not-checked-out', 'status-ok');
                            const statusCell = row.querySelector('td:nth-child(5)');
                            if (!statusCell) return;
                            const txt = statusCell.textContent.trim().toLowerCase();
                            if (txt === 'checked in') {
                                row.classList.add('status-not-checked-out');
                            } else if (txt === 'checked out') {
                                row.classList.add('status-ok');
                            }
                        });
                        // ────────────────────────────────────────

                    } catch (err) {
                        console.error('Error loading tables', err);

                    } finally {
                        hideSpinner('main');
                        
                        document.querySelectorAll('#controls button').forEach(el => el.disabled = false);

                        if (wasSearchFocused) {
                            searchInput.focus();
                            const len = searchInput.value.length;
                            searchInput.setSelectionRange(len, len);
                        }
                    }
                }




                setInterval(() => {
                    document.querySelectorAll('.elapsed').forEach(span => {
                        if (span.dataset.checkedOut === '0' && span.dataset.checkedIn === '1') {
                            let sec = parseInt(span.dataset.startDelta, 10) || 0;
                            sec++;
                            span.dataset.startDelta = sec;
                            span.textContent = fmt(sec);
                        }
                    });
                }, 1000);

                



                document.body.addEventListener('click', async ev => {
                    const t = ev.target;
                    if (t.matches('button.check-in')) {
                        const userId = t.dataset.userId;
                        const source = t.dataset.source;

                        if (!isWithinWindow()) {
                            alert('Cannot check in outside practice window.');
                            return;
                        }
                        const fd = new FormData();
                        fd.append('action', 'in');
                        fd.append('user_id', userId);
                        fd.append('practice_day_id', practiceDayId);
                        fd.append('source', source);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/check-in-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('button.check-out')) {
                        const userId = t.dataset.userId;
                        const source = t.dataset.source;
                        const fd = new FormData();
                        fd.append('action', 'out');
                        fd.append('user_id', userId);
                        fd.append('practice_day_id', practiceDayId);
                        fd.append('source', source);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/check-in-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('button.reset-user')) {
                        const userId = t.dataset.userId;
                        const source = t.dataset.source;
                        const ok = await showConfirm('Reset this user? This archives their record and allows fresh check-in.');
                        if (!ok) return;
                        const fd = new FormData();
                        fd.append('action', 'reset_user');
                        fd.append('user_id', userId);
                        fd.append('practice_day_id', practiceDayId);
                        fd.append('source', source);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/reset-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('#reset-all')) {
                        const ok = await showConfirm('Reset all users? This archives all records for this practice day.');
                        if (!ok) return;
                        const fd = new FormData();
                        fd.append('action', 'reset_all');
                        fd.append('practice_day_id', practiceDayId);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/reset-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('#end-day')) {
                        const ok = await showConfirm('End this practice day? This will prevent any more checkins for this day.');
                        if (!ok) return;
                        const fd = new FormData();
                        fd.append('action', 'end-day');
                        fd.append('practice_day_id', practiceDayId);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/end-practice-day.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        location.reload();

                    }
                    
                    if (t.matches('.sort-btn')) {

                        const field = t.dataset.sort;

                        const zeroDefaultFields = ['status','orders','groups','schools','teams'];
                        const defaultValue = zeroDefaultFields.includes(field) ? 0 : -1;


                        // Increment (or initialize if undefined)
                        sortIndexer[field] = (sortIndexer[field] ?? defaultValue) + 1;
                        if (sortIndexer[field] >= sortCycleState[field].length) sortIndexer[field] = 0;

                        // --- preserve mutually-exclusive behavior for first/last ---
                        if (field === 'firsts' && sortIndexer['firsts'] >= 0) {
                            // when turning first on, turn last off
                            sortIndexer['lasts'] = -1;
                        } else if (field === 'lasts' && sortIndexer['lasts'] >= 0) {
                            // when turning last on, turn first off
                            sortIndexer['firsts'] = -1;
                        }

                        // --- group / school / team: toggleable and mutually exclusive when one is active ---
                        const middleFields = ['groups', 'schools', 'teams'];
                        if (middleFields.includes(field)) {
                            // If the clicked middle field is now active (>0), turn the other middle fields to "none" (index 0).
                            // If it was cycled to 0 (none), leave the others as they are.
                            if (sortIndexer[field] > 0) {
                                middleFields.forEach(f => { if (f !== field) sortIndexer[f] = 0; });
                            }
                        }

                        // Clear all active classes, then reapply based on current indexes
                        document.querySelectorAll('.sort-btn.active').forEach(b => b.classList.remove('active'));

                        // Build the sort string in the exact stacking order: status -> order -> first/last -> middle -> (no duplicate append at the end)
                        let sortParts = [];

                        // status (stack on top)
                        if (sortIndexer['status'] && sortIndexer['status'] > 0) {
                            sortParts.push('status_' + sortCycleState['status'][sortIndexer['status']] + '_');
                        }

                        // order (stack on top)
                        if (sortIndexer['orders'] && sortIndexer['orders'] > 0) {
                            sortParts.push('orders_' + sortCycleState['orders'][sortIndexer['orders']] + '_');
                        }

                        // first / last (either or none; they are mutually exclusive by your logic)
                        if (typeof sortIndexer['firsts'] !== 'undefined' && sortIndexer['firsts'] >= 0) {
                            sortParts.push('firsts_' + sortCycleState['firsts'][sortIndexer['firsts']] + '_');
                        }
                        if (typeof sortIndexer['lasts'] !== 'undefined' && sortIndexer['lasts'] >= 0) {
                            sortParts.push('lasts_' + sortCycleState['lasts'][sortIndexer['lasts']] + '_');
                        }

                        // middle three (only append ones that are > 0)
                        if (sortIndexer['groups'] && sortIndexer['groups'] > 0) {
                            sortParts.push('groups_' + sortCycleState['groups'][sortIndexer['groups']] + '_');
                        }
                        if (sortIndexer['schools'] && sortIndexer['schools'] > 0) {
                            sortParts.push('schools_' + sortCycleState['schools'][sortIndexer['schools']] + '_');
                        }
                        if (sortIndexer['teams'] && sortIndexer['teams'] > 0) {
                            sortParts.push('teams_' + sortCycleState['teams'][sortIndexer['teams']] + '_');
                        }

                        // Re-apply .active to any button whose sort is currently active (fast and single pass)
                        const activeFields = ['status','orders','firsts','lasts','groups','schools','teams'];
                        activeFields.forEach(f => {
                            const isActive = (f === 'firsts' || f === 'lasts')
                                ? (typeof sortIndexer[f] !== 'undefined' && sortIndexer[f] >= 0)
                                : (sortIndexer[f] && sortIndexer[f] > 0);
                            if (isActive) {
                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === f) b.classList.add('active');
                                });
                            }
                        });

                        sort = sortParts.join('');
                        
                        // major headers
                        const majorHeaders = ['status','orders','firsts','lasts','schools','teams','groups'];

                        // remove trailing underscore and split on underscores
                        const tokens = sort.replace(/_$/, '').split('_');

                        let displayParts = [];
                        let i = 0;

                        tokensHeaderUse = [];

                        while (i < tokens.length) {
                            const token = tokens[i];
                            if (majorHeaders.includes(token)) {
                                const key = token;
                                let valueTokens = [];
                                i++;
                                // collect all tokens until next header or end
                                while (i < tokens.length && !majorHeaders.includes(tokens[i])) {
                                    valueTokens.push(tokens[i]);
                                    i++;
                                }
                                const rawValue = valueTokens.join('_'); // keep underscores inside value
                                if (rawValue.toLowerCase() !== 'none') {
                                    const keyDisplay = key.charAt(0).toUpperCase() + key.slice(1);
                                    const valueDisplay = rawValue.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase());
                                    displayParts.push(`${keyDisplay}: ${valueDisplay}`);
                                }
                            } else {
                                i++;
                            }
                        }

                        document.getElementById('sort-header').textContent = displayParts.join(' | ');

                        console.log(sort);

                        loadTables();
                        return;
                    }

                });

                let searchTimeout = null;
                document.getElementById('search-bar').addEventListener('input', ev => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        currentSearch = ev.target.value.trim();
                        loadTables();
                    }, 300);
                });

                // Initial load & periodic reload
                loadTables();
                setInterval(loadTables, 30000);

            </script>



        <?php endif; ?>



        <script>
                document.addEventListener('DOMContentLoaded', () => {
                const modal = document.getElementById('manual-checkin-modal');
                const openBtn = document.getElementById('manual-checkin-btn');
                const closeBtn = modal.querySelector('.modal-close');
                const form = document.getElementById('manual-checkin-form');
                const userSearchInput = document.getElementById('mc-user-search');
                const userIdInput = document.getElementById('mc-user-id');
                const suggestionsList = document.getElementById('mc-suggestions');

                const baseUrl = '<?= $baseUrl ?>';

                // Open modal
                openBtn.addEventListener('click', () => {
                    modal.style.display = 'flex';
                });

                // Close modal
                closeBtn.addEventListener('click', () => {
                    modal.style.display = 'none';
                    form.reset();
                    suggestionsList.innerHTML = '';
                    suggestionsList.style.display = 'none';
                });

                // Autocomplete logic
                let debounceTimeout = null;

                userSearchInput.addEventListener('input', () => {
                    const query = userSearchInput.value.trim();
                    userIdInput.value = '';

                    if (debounceTimeout) clearTimeout(debounceTimeout);

                    if (query.length < 2) {
                    suggestionsList.innerHTML = '';
                    suggestionsList.style.display = 'none';
                    return;
                    }

                    debounceTimeout = setTimeout(() => {
                    fetch(`../router-api.php?path=admin/api/data/user-autocomplete.php&q=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(users => {
                        suggestionsList.innerHTML = '';
                        if (!users.length) {
                            suggestionsList.style.display = 'none';
                            return;
                        }

                        users.forEach(user => {
                            const li = document.createElement('li');
                            li.textContent = user.name;
                            li.dataset.userId = user.id;
                            li.addEventListener('click', () => {
                                userSearchInput.value = user.name;
                                userIdInput.value = user.id;
                                document.getElementById('mc-alt-check').value = user.is_alt_user ? 1 : 0;
                                suggestionsList.innerHTML = '';
                                suggestionsList.style.display = 'none';
                            });

                            suggestionsList.appendChild(li);
                        });
                        suggestionsList.style.display = 'block';
                        });
                    }, 300);
                });

                document.addEventListener('click', (e) => {
                    if (!suggestionsList.contains(e.target) && e.target !== userSearchInput) {
                    suggestionsList.innerHTML = '';
                    suggestionsList.style.display = 'none';
                    }
                });

                // Submit form via AJAX
                form.addEventListener('submit', (e) => {
                    e.preventDefault();

                    const formData = new FormData(form);
                    if (!formData.get('practice_day_id')) {
                    alert('Please select a practice day.');
                    return;
                    }

                    fetch('../router-api.php?path=admin/api/pages/checkins/manual-checkin-save.php', {
                    method: 'POST',
                    body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                    if (data.success) {
                        alert('Manual check-in successful!');
                        form.reset();
                        modal.style.display = 'none';
                    } else {
                        alert('Error: ' + data.error);
                    }
                    })
                    .catch(err => {
                    console.error(err);
                    alert('Server error occurred.');
                    });
                });
                });
            </script>

            <script>
                const searchInput = document.getElementById('search-bar');
                const clearBtn = document.getElementById('search-clear');

                clearBtn.addEventListener('click', () => {
                    searchInput.value = '';   // clear the input
                    currentSearch = ''; 
                    loadTables();
                    searchInput.focus();      // optional: put focus back in the input
                    // optionally trigger any filtering function if you have one
                    // filterFunction('');
                });
            </script>

            <script>
                document.getElementById('mc-practice-day').addEventListener('change', function () {
                    const selected = this.options[this.selectedIndex];
                    const start = new Date(selected.dataset.start);
                    const end = new Date(selected.dataset.end);

                    if (!start || !end) return;

                    const pad = n => String(n).padStart(2, '0');

                    const getTimeString = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}`;

                    // Get time limits
                    const minTime = new Date(start.getTime() - 60 * 60 * 1000); // 1 hr before
                    const maxTime = new Date(end.getTime() + 60 * 60 * 1000);   // 1 hr after

                    const checkin = document.getElementById('mc-checkin');
                    const checkout = document.getElementById('mc-checkout');

                    // Convert to time input
                    checkin.type = 'time';
                    checkout.type = 'time';

                    checkin.min = getTimeString(minTime);
                    checkin.max = getTimeString(maxTime);
                    checkout.min = getTimeString(minTime);
                    checkout.max = getTimeString(maxTime);

                    checkin.value = getTimeString(start);
                    checkout.value = getTimeString(end);

                });

            </script>

            <script>
                // return a tel: href from digits-only phone string
                function telHref(digits) {
                if (!digits) return '';
                // If 10 digits, assume US +1; change if you need different behavior
                if (digits.length === 10) return 'tel:+1' + digits;
                // If 11 and starts with 1, keep +1
                if (digits.length === 11 && digits.startsWith('1')) return 'tel:+' + digits;
                // otherwise use as-is
                return 'tel:' + digits;
                }

                // pretty-format for display: (123) 456-7890 or +1 (123) 456-7890
                function formatDisplayPhone(digits) {
                if (!digits) return '—';
                if (digits.length === 10) {
                    return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
                }
                if (digits.length === 11 && digits.startsWith('1')) {
                    return `+1 (${digits.slice(1,4)}) ${digits.slice(4,7)}-${digits.slice(7)}`;
                }
                // fallback: show raw
                return digits;
                }


                document.addEventListener('click', async function (e) {
                const btn = e.target.closest('.user-info-btn');
                if (!btn) return;

                const userId = btn.dataset.userId;
                const source = btn.dataset.source;

                const modal = document.getElementById('user-info-modal');
                const body = document.getElementById('user-info-body');

                // show modal and loading state
                modal.style.display = 'flex';
                body.innerHTML = `<p>Loading...</p>`;

                try {
                    const url = `../router-api.php?path=api/data/user-info.php&user_id=${encodeURIComponent(userId)}&source=${encodeURIComponent(source)}`;
                    const res = await fetch(url, { credentials: 'same-origin' });

                    if (!res.ok) {
                    // try to parse body text for helpful debug, otherwise throw
                    const txt = await res.text();
                    throw new Error(txt || 'Network error');
                    }

                    const json = await res.json();

                    if (!json || json.success !== true || !json.user) {
                    throw new Error((json && json.error) ? json.error : 'Invalid API response');
                    }

                    const u = json.user;

                    // Format medical_info (preserve newlines)
                    const medicalHtml = u.medical_info ? u.medical_info.replace(/\n/g, '<br>') : '—';

                    const phoneHtml = u.phone_number
                    ? `<a href="${telHref(u.phone_number)}">${escapeHtml(formatDisplayPhone(u.phone_number))}</a>`
                    : '—';

                    const emPhoneHtml = u.emergency_contact_phone
                    ? `<a href="${telHref(u.emergency_contact_phone)}">${escapeHtml(formatDisplayPhone(u.emergency_contact_phone))}</a>`
                    : '—';

                    body.innerHTML = `
                    <p><strong>Name:</strong> ${escapeHtml(u.full_name) || '—'}</p>
                    <p><strong>Role Level:</strong> ${typeof u.role_level === 'number' ? u.role_level : (u.role_level || '—')}</p>
                    <p><strong>Phone Number:</strong> ${phoneHtml}</p>
                    <hr>
                    <p><strong>Emergency Contact Name:</strong> ${escapeHtml(u.emergency_contact_name) || '—'}</p>
                    <p><strong>Emergency Contact Phone:</strong> ${emPhoneHtml}</p>
                    <hr>
                    <p><strong>Medical Info:</strong><br>${medicalHtml}</p>
                    `;
                } catch (err) {
                    console.error('User info load error:', err);
                    body.innerHTML = `<p style="color:red;">Error loading user info.</p>`;
                }
                });

                // Close modal: close button or clicking overlay background
                document.addEventListener('click', function (e) {
                if (e.target.matches('.modal-close') || e.target.id === 'user-info-modal') {
                    const modal = document.getElementById('user-info-modal');
                    modal.style.display = 'none';
                }
                });

                // small helper to prevent XSS in injected strings
                function escapeHtml(str) {
                if (!str && str !== 0) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
                }
            </script>




    </div>
</body>

</html>

<script> 
/*
const schoolSortCycle = <?= json_encode(array_map(fn($s) => 'school_' . strtolower(str_replace(' ', '_', $s)), $schoolNames)) ?>;
                const teamSortCycle = <?= json_encode(array_map(fn($t) => 'team_' . strtolower(str_replace(' ', '_', $t)), $teamNames)) ?>;
                const rideGroupSortCycle = <?= json_encode(array_map(fn($r) => 'rg_' . strtolower(str_replace(' ', '_', $r)), $rideGroupNames)) ?>;

                let sortCycleState = {
                    first_name: ['asc', 'desc'],
                    last_name: ['asc', 'desc'],
                    school: schoolSortCycle,
                    team: teamSortCycle,
                    ride_group: rideGroupSortCycle,
                    status: ['not_checked_in', 'checked_in', 'checked_out'],
                    coach: ['coach_first', 'student_first']
                };

                let sortCycleIndex = {};
*/

/* 




DEPRICATED APPROVAL TABLE --------

                let editCheckinId = null;
                function showEditTimeModal(checkinId, currentElapsedSec) {
                    editCheckinId = checkinId;
                    const h = Math.floor(currentElapsedSec / 3600);
                    const m = Math.floor((currentElapsedSec % 3600) / 60);
                    const s = currentElapsedSec % 60;
                    document.getElementById('edit-hours').value = h;
                    document.getElementById('edit-minutes').value = m;
                    document.getElementById('edit-seconds').value = s;
                    document.getElementById('edit-time-modal').classList.remove('hidden');
                }
                function hideEditTimeModal() {
                    document.getElementById('edit-time-modal').classList.add('hidden');
                    editCheckinId = null;
                }
                document.getElementById('edit-time-cancel').addEventListener('click', () => {
                    hideEditTimeModal();
                });
                document.getElementById('edit-time-save').addEventListener('click', async () => {
                    const h = parseInt(document.getElementById('edit-hours').value) || 0;
                    let m = parseInt(document.getElementById('edit-minutes').value);
                    let s = parseInt(document.getElementById('edit-seconds').value);
                    if (isNaN(m) || m < 0) m = 0;
                    if (isNaN(s) || s < 0) s = 0;
                    if (m > 59) m = 59;
                    if (s > 59) s = 59;
                    const newElapsed = h * 3600 + m * 60 + s;
                    if (newElapsed < 0) {
                        alert('Elapsed must be non-negative');
                        return;
                    }
                    const fd = new FormData();
                    fd.append('checkin_id', editCheckinId);
                    fd.append('new_elapsed', newElapsed);
                    const res = await fetch('../router-api.php?path=admin/api/pages/checkins/edit-time-handler.php', {
                        method: 'POST',
                        body: fd
                    });
                    if (!res.ok) {
                        const text = await res.text();
                        alert('Error: ' + text);
                    }
                    hideEditTimeModal();
                    loadTables();
                });

                ----------
                
                DEPRICATED SORTER / LOADER ---------

                async function loadTables() {
                    const searchInput = document.getElementById('search-bar');
                    const wasSearchFocused = (document.activeElement === searchInput);

                    // Disable buttons while loading
                    document.querySelectorAll('#controls button').forEach(el => el.disabled = true);
                    showSpinner('main');
                    showSpinner('approval');

                    try {
                        const params = new URLSearchParams();
                        if (currentSearch) params.append('search', currentSearch);
                        if (currentSort) {
                            params.append('sort', currentSort);
                            if (currentOrder) {
                                params.append('order', currentOrder);
                            }
                        }
                        params.append('practice_day_id', practiceDayId);

                        // Always fetch main table
                        const resMain = await fetch(baseUrl + '/public/admin/partials/checkin-table-body.php?' + params.toString());
                        const htmlMain = await resMain.text();

                        
                        let htmlApp = '';
                        const isApprovalSortExcluded = currentSort.startsWith('status_') || currentSort.startsWith('coach_');

                        if (!isApprovalSortExcluded) {
                            const resApp = await fetch(baseUrl + '/public/admin/partials/approval-table-body.php?' + params.toString());
                            htmlApp = await resApp.text();
                        }

                        // 1) Inject new HTML
                        document.querySelector('#checkin-table tbody').innerHTML = htmlMain;
                        document.querySelector('#approval-table tbody').innerHTML = htmlApp;

                        // ─── Insert the new snippet right here ───
                        // 2) Toggle the mobile “show-col-…” class
                        const tbl = document.getElementById('checkin-table');
                        tbl.classList.remove(
                            'show-col-school', 'show-col-team', 'show-col-ride_group', 'show-col-status'
                        );
                        if (currentSort.startsWith('school_')) {
                            tbl.classList.add('show-col-school');
                        } else if (currentSort.startsWith('team_')) {
                            tbl.classList.add('show-col-team');
                        } else if (currentSort.startsWith('rg_') || currentSort === 'ride_group') {
                            tbl.classList.add('show-col-ride_group');
                        } else if (currentSort.startsWith('status_') || currentSort === 'status') {
                            tbl.classList.add('show-col-status');
                        }
                        const tbl2 = document.getElementById('approval-table');
                        tbl2.classList.remove(
                            'show-col-school', 'show-col-team', 'show-col-ride_group'
                        );
                        if (currentSort.startsWith('school_')) {
                            tbl2.classList.add('show-col-school');
                        } else if (currentSort.startsWith('team_')) {
                            tbl2.classList.add('show-col-team');
                        } else if (currentSort.startsWith('rg_') || currentSort === 'ride_group') {
                            tbl2.classList.add('show-col-ride_group');
                        }

                        // 3) Color each row by its “Status” cell
                        document.querySelectorAll('#checkin-table tbody tr').forEach(row => {
                            row.classList.remove('status-not-checked-out', 'status-ok');
                            const statusCell = row.querySelector('td:nth-child(5)');
                            if (!statusCell) return;
                            const txt = statusCell.textContent.trim().toLowerCase();
                            if (txt === 'checked in') {
                                row.classList.add('status-not-checked-out');
                            } else if (txt === 'checked out') {
                                row.classList.add('status-ok');
                            }
                        });
                        // ────────────────────────────────────────

                    } catch (err) {
                        console.error('Error loading tables', err);

                    } finally {
                        hideSpinner('main');
                        hideSpinner('approval');
                        document.querySelectorAll('#controls button').forEach(el => el.disabled = false);

                        if (wasSearchFocused) {
                            searchInput.focus();
                            const len = searchInput.value.length;
                            searchInput.setSelectionRange(len, len);
                        }
                    }
                }




                setInterval(() => {
                    document.querySelectorAll('.elapsed').forEach(span => {
                        if (span.dataset.checkedOut === '0' && span.dataset.checkedIn === '1') {
                            let sec = parseInt(span.dataset.startDelta, 10) || 0;
                            sec++;
                            span.dataset.startDelta = sec;
                            span.textContent = fmt(sec);
                        }
                    });
                }, 1000);

                



                document.body.addEventListener('click', async ev => {
                    const t = ev.target;
                    if (t.matches('button.check-in')) {
                        const userId = t.dataset.userId;
                        const source = t.dataset.source;

                        if (!isWithinWindow()) {
                            alert('Cannot check in outside practice window.');
                            return;
                        }
                        const fd = new FormData();
                        fd.append('action', 'in');
                        fd.append('user_id', userId);
                        fd.append('practice_day_id', practiceDayId);
                        fd.append('source', source);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/check-in-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('button.check-out')) {
                        const userId = t.dataset.userId;
                        const source = t.dataset.source;
                        const fd = new FormData();
                        fd.append('action', 'out');
                        fd.append('user_id', userId);
                        fd.append('practice_day_id', practiceDayId);
                        fd.append('source', source);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/check-in-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('button.reset-user')) {
                        const userId = t.dataset.userId;
                        const source = t.dataset.source;
                        const ok = await showConfirm('Reset this user? This archives their record and allows fresh check-in.');
                        if (!ok) return;
                        const fd = new FormData();
                        fd.append('action', 'reset_user');
                        fd.append('user_id', userId);
                        fd.append('practice_day_id', practiceDayId);
                        fd.append('source', source);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/reset-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('#reset-all')) {
                        const ok = await showConfirm('Reset all users? This archives all records for this practice day.');
                        if (!ok) return;
                        const fd = new FormData();
                        fd.append('action', 'reset_all');
                        fd.append('practice_day_id', practiceDayId);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/reset-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('button.approve-time')) {
                        const checkInId = t.dataset.checkInId;
                        const fd = new FormData();
                        fd.append('action', 'approve');
                        fd.append('checkin_id', checkInId);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/approval-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('button.reject-time')) {
                        const checkInId = t.dataset.checkInId;
                        const ok = await showConfirm('Reject this elapsed time? User will need to check out again or have time edited.');
                        if (!ok) return;
                        const fd = new FormData();
                        fd.append('action', 'reject');
                        fd.append('checkin_id', checkInId);
                        const res = await fetch('../router-api.php?path=admin/api/pages/checkins/approval-handler.php', {
                            method: 'POST',
                            body: fd
                        });
                        if (!res.ok) {
                            const text = await res.text();
                            alert('Error: ' + text);
                        }
                        loadTables();
                    }
                    if (t.matches('button.edit-time')) {
                        const checkInId = t.dataset.checkInId;
                        const currentElapsed = parseInt(t.dataset.elapsed, 10) || 0;
                        showEditTimeModal(checkInId, currentElapsed);
                    }
                    if (t.matches('.sort-btn')) {
                        const field = t.dataset.sort;

                        // Define toggle cycles for status and coach
                        const toggleCycles = {
                            status: ['not_checked_in', 'checked_in', 'checked_out', 'off'],
                            coach: ['coach_first', 'student_first', 'off']
                        };

                        if (field === 'status' || field === 'coach') {
                            const cycle = toggleCycles[field];
                            sortCycleIndex[field] = (sortCycleIndex[field] ?? -1) + 1;
                            if (sortCycleIndex[field] >= cycle.length) sortCycleIndex[field] = 0;

                            const cycleValue = cycle[sortCycleIndex[field]];

                            if (cycleValue === 'off') {
                                currentSort = '';
                                currentOrder = '';
                                t.classList.remove('active');
                            } else {
                                currentSort = field + (field === 'status' ? '' : ''); // you can append if needed
                                currentOrder = cycleValue;
                                t.classList.add('active');
                            }

                            loadTables();
                            return;
                        }

                        // ── Normal cycles ──
                        const cycle = sortCycleState[field];
                        if (!cycle || !cycle.length) return; // prevent undefined

                        // Reset other indexes
                        Object.keys(sortCycleIndex).forEach(k => {
                            if (k !== field) sortCycleIndex[k] = -1;
                        });

                        sortCycleIndex[field] = (sortCycleIndex[field] ?? -1) + 1;
                        if (sortCycleIndex[field] >= cycle.length) sortCycleIndex[field] = 0;

                        const cycleValue = cycle[sortCycleIndex[field]];

                        if (field === 'first_name' || field === 'last_name') {
                            currentSort = field;
                            currentOrder = cycleValue;
                        } else {
                            currentSort = cycleValue;
                            currentOrder = 'asc';
                        }

                        // Highlight active button for normal cycles
                        document.querySelectorAll('.sort-btn').forEach(btn => {
                            if (!['status', 'coach'].includes(btn.dataset.sort)) btn.classList.remove('active');
                        });
                        t.classList.add('active');

                        loadTables();
                    }



                });

                let searchTimeout = null;
                document.getElementById('search-bar').addEventListener('input', ev => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        currentSearch = ev.target.value.trim();
                        loadTables();
                    }, 300);
                });

                // Initial load & periodic reload
                loadTables();
                setInterval(loadTables, 30000);

                */

                /*if (t.matches('.sort-btn')) {

                        const field = t.dataset.sort;

                        // Determine default based on field
                        const defaultValue = (field === 'status' || field === 'order') ? 0 : -1;

                        // Increment (or initialize if undefined)
                        sortIndexer[field] = (sortIndexer[field] ?? defaultValue) + 1;
       
                        if (sortIndexer[field] >= sortCycleState[field].length) sortIndexer[field] = 0;

                        let sort = '';

                        document.querySelectorAll('.sort-btn.active').forEach(b => b.classList.remove('active'));

                        console.log("field: " + field + "  index: " + sortIndexer[field]);

                        if (sortIndexer['status'] && sortIndexer['status'] > 0) {

                            sort += 'status_' + sortCycleState['status'][sortIndexer['status']] + '_';

                            document.querySelectorAll('.sort-btn').forEach(b => {
                                if (b.dataset.sort === 'status') {
                                    b.classList.add('active'); // no dot
                                }
                            });

                        }
                        if (sortIndexer['order'] && sortIndexer['order'] > 0) {

                            sort += 'order_' + sortCycleState['order'][sortIndexer['order']] + '_';

                            document.querySelectorAll('.sort-btn').forEach(b => {
                                if (b.dataset.sort === 'order') {
                                    b.classList.add('active'); // no dot
                                }
                            });

                        } 

                        if (field === 'first' || field === 'last') {

                            if (field === 'first'){

                                sortIndexer['last'] = -1;
                                
                                sort += 'first_' + sortCycleState['first'][sortIndexer['first']] + '_';

                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'first') {
                                        b.classList.add('active'); // no dot
                                    }
                                });

                            } else {

                                sortIndexer['first'] = -1;
                                
                                sort += 'last_' + sortCycleState['last'][sortIndexer['last']] + '_';

                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'last') {
                                        b.classList.add('active'); // no dot
                                    }
                                });

                            }

                        } else {

                            if (sortIndexer['first'] && sortIndexer['first'] >= 0) {

                                sort += 'first_' + sortCycleState['first'][sortIndexer['first']] + '_';

                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'first') {
                                        b.classList.add('active'); // no dot
                                    }
                                });

                            } 
                            if (sortIndexer['last'] && sortIndexer['last'] >= 0) {

                                sort += 'last_' + sortCycleState['last'][sortIndexer['last']] + '_';

                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'last') {
                                        b.classList.add('active'); // no dot
                                    }
                                });

                            } 

                        }

                        if (field === 'group' || field === 'school' || field === 'team') {

                            if (field === 'group') {
                                // Turn off the other two
                                sortIndexer['school'] = 0;
                                sortIndexer['team'] = 0;

                                // Add to sort string
                                sort += 'group_' + sortCycleState['group'][sortIndexer['group']] + '_';

                                // Set active class
                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'group') {
                                        b.classList.add('active');
                                    }
                                });

                            } else if (field === 'school') {
                                // Turn off the other two
                                sortIndexer['group'] = 0;
                                sortIndexer['team'] = 0;

                                sort += 'school_' + sortCycleState['school'][sortIndexer['school']] + '_';

                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'school') {
                                        b.classList.add('active');
                                    }
                                });

                            } else { // field === 'team'
                                // Turn off the other two
                                sortIndexer['group'] = 0;
                                sortIndexer['school'] = 0;

                                sort += 'team_' + sortCycleState['team'][sortIndexer['team']] + '_';

                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'team') {
                                        b.classList.add('active');
                                    }
                                });
                            }

                        } else {

                            // If any of them are already active, append them to the sort string
                            if (sortIndexer['group'] && sortIndexer['group'] > 0) {
                                sort += 'group_' + sortCycleState['group'][sortIndexer['group']] + '_';
                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'group') b.classList.add('active');
                                });
                            }
                            if (sortIndexer['school'] && sortIndexer['school'] > 0) {
                                sort += 'school_' + sortCycleState['school'][sortIndexer['school']] + '_';
                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'school') b.classList.add('active');
                                });
                            }
                            if (sortIndexer['team'] && sortIndexer['team'] > 0) {
                                sort += 'team_' + sortCycleState['team'][sortIndexer['team']] + '_';
                                document.querySelectorAll('.sort-btn').forEach(b => {
                                    if (b.dataset.sort === 'team') b.classList.add('active');
                                });
                            }

                        }

                        console.log(sort);


                        loadTables();
                        return;
                        
                    } */

</script>
