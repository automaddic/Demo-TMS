<?php
// public/admin/practice-days.php

require_once '/home/automaddic/mtb/server/config/bootstrap.php';

require_once '/home/automaddic/mtb/server/auth/check-role-access.php';

// Enforce that current user can access this admin page. Adjust role/page as needed.
enforceAccessOrDie(basename(__FILE__), $pdo);

// Helpers for pagination of past days
$pagePast = isset($_GET['page_past']) ? max(1, (int) $_GET['page_past']) : 1;
$pastPerPage = 14;

// Today boundaries
$today = new DateTime('today', new DateTimeZone('America/New_York'));
$now = new DateTime('now', new DateTimeZone('America/New_York'));

// Fetch practice days
// Fetch upcoming practice days (start in future or today)
$stmtUp = $pdo->prepare("SELECT * FROM practice_days WHERE start_datetime >= ? ORDER BY start_datetime ASC");
$stmtUp->execute([$now->format('Y-m-d H:i:s')]);
$upcoming = $stmtUp->fetchAll(PDO::FETCH_ASSOC);

// Fetch all past practice days (started before now)
$stmtPastAll = $pdo->prepare("SELECT * FROM practice_days WHERE start_datetime < ? ORDER BY start_datetime DESC");
$stmtPastAll->execute([$now->format('Y-m-d H:i:s')]);
$pastAll = $stmtPastAll->fetchAll(PDO::FETCH_ASSOC);

// Pagination for past days
$totalPast = count($pastAll);
$pastPages = (int) ceil($totalPast / $pastPerPage);
$startIndex = ($pagePast - 1) * $pastPerPage;
$pastPageSlice = array_slice($pastAll, $startIndex, $pastPerPage);


// Day types for forms
$dayTypes = $pdo->query("SELECT id, name FROM day_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practice Days Admin</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/admin/styles/practice-days.css">
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php"; ?>
    <div class="page-wrapper">
        <h1>Practice Days Management</h1>
        <div class="header-actions">
            <button id="btn-new-practice">New Practice Day</button>
            <button id="btn-repeat-ui">Weekly Repeat</button>
        </div>

        <!-- Upcoming practice days grid -->
        <div class="practices-grid" id="upcoming-grid">
            <?php foreach ($upcoming as $pd):
                $startDt = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
                $endDt = !empty($pd['end_datetime']) ? new DateTime($pd['end_datetime'], new DateTimeZone('America/New_York')) : null;
                $isToday = $startDt->format('Y-m-d') === $today->format('Y-m-d');
            ?>
                <div class="practice-panel <?= $isToday ? 'today' : '' ?>" data-id="<?= $pd['id'] ?>">
                    <h3><?= htmlspecialchars($pd['name']) ?></h3>
                    <div class="datetime">
                        <?= $startDt->format('M j, Y') ?>
                        <?= $startDt->format('g:ia') ?>
                        <?php if ($endDt): ?>
                            - <?= $endDt->format('g:ia') ?>
                        <?php endif; ?>
                    </div>
                    <div class="location"><?= htmlspecialchars($pd['location'] ?? '') ?></div>
                    <?php if (!empty($pd['map_link'])): ?>
                        <a href="<?= htmlspecialchars($pd['map_link']) ?>" target="_blank" rel="noopener noreferrer">Get Directions</a>
                    <?php endif; ?>
                    <?php if (!empty($pd['day_type_id'])):
                        $dtName = '';
                        foreach ($dayTypes as $dt)
                            if ($dt['id'] == $pd['day_type_id']) {
                                $dtName = $dt['name'];
                                break;
                            }
                        ?>
                        <div class="day-type">Type: <?= htmlspecialchars($dtName) ?></div>
                    <?php endif; ?>

                    <div class="panel-actions">
                        <button class="btn-edit" title="Edit">Edit</button>
                        <button class="btn-delete" title="Delete">Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <!-- Past practices dropdown -->
        <details class="past-practices">
            <summary>Past Practice Days</summary>
            <div id="past-list">
                <ul class="past-list">
                    <?php foreach ($pastPageSlice as $pd):
                        $startDt = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
                        $hasEnded = !empty($pd['has_ended']) && $pd['has_ended'] == 1 ? true : false;
                        ?>
                        <div class="practice-panel-list" data-id="<?= $pd['id'] ?>">
                            <li>
                                <div class="info">
                                    <h4><?= htmlspecialchars($pd['name']) ?></h4>
                                    <div class="datetime"><?= $startDt->format('M j, Y g:ia') ?></div>
                                    <?php if (!$hasEnded): ?>
                                        <div class="panel-actions">
                                            <button class="btn-edit" title="Edit">Edit</button>
                                            <button class="btn-delete" title="Delete">Delete</button>
                                        </div>
                                    <?php endif; ?>
                                </div>  
                            </li>
                        </div>
                    <?php endforeach; ?>
                </ul>
                <?php if ($pastPages > 1): ?>
                    <div class="past-pagination">
                        <?php if ($pagePast > 1): ?><a href="?page_past=<?= $pagePast - 1 ?>"
                                class="btn-prev">Previous</a><?php endif; ?>
                        <?php if ($pagePast < $pastPages): ?><a href="?page_past=<?= $pagePast + 1 ?>"
                                class="btn-next">Next</a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
    </div>

    <!-- Modals -->
    <!-- New/Edit Practice Day Modal -->
    <div class="modal-overlay" id="modal-practice">
        <div class="modal-box">
            <button class="modal-close" id="modal-close-practice">&times;</button>
            <h2 id="modal-title">New Practice Day</h2>
            <form id="form-practice">
                <input type="hidden" name="id" id="practice-id">
                <label for="practice-name">Name</label>
                <input type="text" id="practice-name" name="name" required>

                <label for="practice-start-date">Start</label>
                <div class="datetime-group">
                    <input type="date" id="practice-start-date" name="start_date" required min="<?= date('Y-m-d') ?>">
                    <input type="time" id="practice-start-time" name="start_time" required>
                </div>

                <label for="practice-end-date">End</label>
                <div class="datetime-group">
                    <input type="time" id="practice-end-time" name="end_time">
                </div>

                <label for="practice-location">Location</label>
                <input type="text" id="practice-location" name="location">

                <label for="practice-map">Google Maps Link (URL)</label>
                <input type="url" id="practice-map" name="map_link">

                <label for="practice-daytype">Day Type</label>
                <select id="practice-daytype" name="day_type_id">
                    <option value="">-- Select Day Type --</option>
                    <?php foreach ($dayTypes as $dt): ?>
                        <option value="<?= $dt['id'] ?>"><?= htmlspecialchars($dt['name']) ?></option><?php endforeach; ?>
                </select>

                <div class="modal-buttons">
                    <button type="button" id="btn-save-practice">Save</button>
                    <button type="button" id="btn-cancel-practice">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Repeat UI Modal -->
    <div class="modal-overlay" id="modal-repeat">
        <div class="modal-box">
            <button class="modal-close" id="modal-close-repeat">&times;</button>
            <h2>Repeat Practice Days</h2>
            <form id="form-repeat">
                <div class="repeat-ui">
                    <label>Select practice days to repeat:</label>
                    <div id="repeat-practice-options" class="repeat-practice-options">
                        <?php foreach ($upcoming as $pd):
                            $startDt = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));

                            $endDt = !empty($pd['end_datetime']) ? new DateTime($pd['end_datetime'], new DateTimeZone('America/New_York')) : null;
                            ?>
                            <label class="repeat-option">
                                <input type="checkbox" name="repeat_ids[]" value="<?= $pd['id'] ?>">
                                <div class="preview-tile">
                                    <strong><?= htmlspecialchars($pd['name']) ?></strong><br>
                                    <span><?= $startDt->format('M j, Y') ?></span><br>
                                    <span><?= $startDt->format('g:ia') ?>
                                    <?php if ($endDt): ?>
                                        - <?= $endDt->format('g:ia') ?>
                                    <?php endif; ?>
                                    </span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="week-count">
                        <label for="repeat-weeks">Repeat for how many weeks?</label>
                        <select id="repeat-weeks">
                            <?php for ($i = 1; $i <= 16; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> week<?= $i > 1 ? 's' : '' ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <button type="button" id="btn-confirm-repeat">Repeat</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const baseUrl = '<?= $baseUrl ?>';

            // Practice Modal Elements
            const modalPractice = document.getElementById('modal-practice');
            const formPractice = document.getElementById('form-practice');
            const btnNewPractice = document.getElementById('btn-new-practice');
            const btnSavePractice = document.getElementById('btn-save-practice');
            const btnCancelPractice = document.getElementById('btn-cancel-practice');
            const modalClosePractice = document.getElementById('modal-close-practice');

            const startDateInput = document.getElementById('practice-start-date');
            const startTimeInput = document.getElementById('practice-start-time');
            const endDateInput = document.getElementById('practice-end-date');
            const endTimeInput = document.getElementById('practice-end-time');
            const copyDateBtn = document.getElementById('btn-copy-date');

            // Repeat Modal Elements
            const modalRepeat = document.getElementById('modal-repeat');
            const btnRepeatUI = document.getElementById('btn-repeat-ui');
            const btnCloseRepeat = document.getElementById('modal-close-repeat');

            // Open/Close Practice Modal
            btnNewPractice.addEventListener('click', () => {
                openPracticeModal();
                document.body.classList.add('modal-open');

            });
            modalClosePractice.addEventListener('click', () => {
                modalPractice.classList.remove('active');
                document.body.classList.remove('modal-open');

            });
            btnCancelPractice.addEventListener('click', () => {
                modalPractice.classList.remove('active');
                document.body.classList.remove('modal-open');

            });

            // Open/Close Repeat Modal
            btnRepeatUI.addEventListener('click', () => {
                modalRepeat.classList.add('active');
                document.body.classList.add('modal-open');


            });
            btnCloseRepeat.addEventListener('click', () => {
                modalRepeat.classList.remove('active');
                document.body.classList.remove('modal-open');

            });


            // Save Practice
            btnSavePractice.addEventListener('click', () => {
                const fd = new FormData(formPractice);
                const payload = {
                    id: fd.get('id') || null,
                    name: fd.get('name'),
                    start_date: fd.get('start_date'),
                    start_time: fd.get('start_time'),
                    end_date: fd.get('start_date'),
                    end_time: fd.get('end_time'),
                    location: fd.get('location'),
                    map_link: fd.get('map_link'),
                    day_type_id: fd.get('day_type_id') || null
                };
                const endpoint = payload.id ? '/public/router-api.php?path=admin/api/pages/practice-day/update-practice-day.php' : '/public/router-api.php?path=admin/api/pages/practice-day/create-practice-day.php';
                fetch(baseUrl + endpoint, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) location.reload();
                        else alert('Save failed: ' + (resp.error || ''));
                    })
                    .catch(() => alert('Error saving.'));
            });

            // Delegate Upcoming Grid Actions
            document.getElementById('upcoming-grid').addEventListener('click', ev => {
                const btn = ev.target.closest('button'); if (!btn) return;
                const panel = btn.closest('.practice-panel'); const id = panel.dataset.id;
                if (btn.classList.contains('btn-edit')) {
                    fetch(`../router-api.php?path=admin/api/data/get-practice-day.php&id=${id}`)
                        .then(r => r.json()).then(data => openPracticeModal(data)).catch(() => alert('Load failed'));
                }
                if (btn.classList.contains('btn-delete') && confirm('Delete this practice day?')) {
                    fetch(`../router-api.php?path=admin/api/pages/practice-day/delete-practice-day.php`, {
                        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
                    }).then(r => r.json()).then(resp => resp.success ? location.reload() : alert('Delete failed'))
                        .catch(() => alert('Error deleting'));
                }
            });

            document.getElementById('past-list').addEventListener('click', ev => {
                const btn = ev.target.closest('button'); if (!btn) return;
                const panel = btn.closest('.practice-panel-list'); const id = panel.dataset.id;
                if (btn.classList.contains('btn-edit')) {
                    fetch(`../router-api.php?path=admin/api/data/get-practice-day.php&id=${id}`)
                        .then(r => r.json()).then(data => openPracticeModal(data)).catch(() => alert('Load failed'));
                }
                if (btn.classList.contains('btn-delete') && confirm('Delete this practice day?')) {
                    fetch(`../router-api.php?path=admin/api/pages/practice-day/delete-practice-day.php`, {
                        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
                    }).then(r => r.json()).then(resp => resp.success ? location.reload() : alert('Delete failed'))
                        .catch(() => alert('Error deleting'));
                }
            });

            document.getElementById('btn-confirm-repeat').addEventListener('click', () => {
                const checkboxes = document.querySelectorAll('#repeat-practice-options input[type="checkbox"]:checked');
                const repeatIds = Array.from(checkboxes).map(cb => cb.value);
                const weeks = parseInt(document.getElementById('repeat-weeks').value, 10);

                if (repeatIds.length === 0) {
                    alert('Please select at least one practice day to repeat.');
                    return;
                }

                fetch(baseUrl + '/public/router-api.php?path=admin/api/pages/practice-day/repeat-practice-days.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ repeat_ids: repeatIds, weeks: weeks })
                })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            alert('Practice days repeated successfully.');
                            document.getElementById('modal-repeat').classList.remove('active');
                            location.reload();
                        } else {
                            alert('Repeat failed: ' + (resp.error || 'Unknown error'));
                        }
                    })
                    .catch(() => alert('Error repeating practice days.'));
            });

            function openPracticeModal(pdData = null) {
                formPractice.reset();
                document.getElementById('practice-id').value = '';
                document.getElementById('modal-title').textContent = pdData ? 'Edit Practice Day' : 'New Practice Day';

                if (pdData) {
                    document.getElementById('practice-id').value = pdData.id;
                    document.getElementById('practice-name').value = pdData.name;

                    const pad = n => n.toString().padStart(2, '0');

                    // parse datetimes
                    const start = new Date(pdData.start_datetime);
                    if (pdData.end_datetime) {
                        const end = new Date(pdData.end_datetime);
                        endTimeInput.value = `${pad(end.getHours())}:${pad(end.getMinutes())}`;
                    } 

                    // set start date/time
                    startDateInput.value = `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}`;
                    startTimeInput.value = `${pad(start.getHours())}:${pad(start.getMinutes())}`;
                    

                    document.getElementById('practice-location').value = pdData.location || '';
                    document.getElementById('practice-map').value = pdData.map_link || '';
                    document.getElementById('practice-daytype').value = pdData.day_type_id || '';
                }

                modalPractice.classList.add('active');
            }
        });
    </script>

</body>

</html>
