<?php
// public/admin/edit-notes.php

require_once '/home/automaddic/mtb/server/config/bootstrap.php';
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie(basename(__FILE__), $pdo);

// Load current user and their ride groups
require_once '/home/automaddic/mtb/server/api/data/user.php';
require_once '/home/automaddic/mtb/server/api/data/ride-groups.php';
$user = getCurrentUser($pdo);
$rideGroups = getRideGroups($pdo);

$now = new DateTime('now', new DateTimeZone('America/New_York'));
$nowIso = $now->format(DateTime::ATOM); // JS-friendly format

// 1) Active (starts <= 2 weeks ahead AND end >= 10min ago)
$twoWeeksFwd = (clone $now)->modify('+14 days');
$nowPlus10 = (clone $now)->modify('+10 minutes');

$stmtAct = $pdo->prepare("
    SELECT pd.*, dt.name AS day_type_name
    FROM practice_days pd
    LEFT JOIN day_types dt ON pd.day_type_id = dt.id
    WHERE pd.start_datetime <= ?
      AND DATE_ADD(pd.start_datetime, INTERVAL 10 MINUTE) >= ?
    ORDER BY pd.start_datetime ASC
");
$stmtAct->execute([
    $twoWeeksFwd->format('Y-m-d H:i:s'),
    $now->format('Y-m-d H:i:s')
]);

$active = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

// 2) Archived (ended < 10min ago OR start >2 weeks ahead)
$stmtArch = $pdo->prepare("
    SELECT pd.*, dt.name AS day_type_name
    FROM practice_days pd
    LEFT JOIN day_types dt ON pd.day_type_id = dt.id
    WHERE DATE_ADD(pd.start_datetime, INTERVAL 10 MINUTE) < ?
       OR pd.start_datetime > ?
    ORDER BY pd.start_datetime DESC
");
$stmtArch->execute([
    $now->format('Y-m-d H:i:s'),
    $twoWeeksFwd->format('Y-m-d H:i:s')
]);

$archived = $stmtArch->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Coach Notes</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/admin/styles/edit-notes.css">
</head>

<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php"; ?>
    <div class="page-wrapper">
        <div class="notes-header">
            <h1>Edit Coach Notes</h1>
            <div class="ride-group-box">
                <label for="ride_group_id">Ride Group</label>
                <select id="ride_group_id">
                    <?php foreach ($rideGroups as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $g['id'] == $user['ride_group_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h2>Active (Editable Until 10 min After Start)</h2>
        <div class="practices-grid" id="notes-grid">
            <?php foreach ($active as $pd):
                $start = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
                $end = new DateTime($pd['end_datetime'], new DateTimeZone('America/New_York'));
                $date = $start->format('M j, Y');
                $time = $start->format('g:ia') . ' – ' . $end->format('g:ia');
                ?>
                <div class="practice-panel" data-id="<?= $pd['id'] ?>" data-start="<?= $pd['start_datetime'] ?>"
                    data-end="<?= $pd['end_datetime'] ?>">
                    <h3><?= htmlspecialchars($pd['name']) ?></h3>
                    <div class="datetime"><?= $date ?> • <?= $time ?></div>
                    <button class="btn-edit-notes">Edit Notes</button>
                </div>
            <?php endforeach; ?>
            <?php if (empty($active)): ?>
                <p><em>No active sessions available.</em></p><?php endif; ?>
        </div>

        <details style="margin-top:2rem">
            <summary>Archived Practices</summary>
            <div class="practices-grid" id="notes-archived">
                <?php foreach ($archived as $pd):
                    $start = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
                    $end = new DateTime($pd['end_datetime'], new DateTimeZone('America/New_York'));
                    $date = $start->format('M j, Y');
                    $time = $start->format('g:ia') . ' – ' . $end->format('g:ia');
                    ?>
                    <div class="practice-panel archived" data-id="<?= $pd['id'] ?>"
                        data-start="<?= $pd['start_datetime'] ?>" data-end="<?= $pd['end_datetime'] ?>">
                        <h3><?= htmlspecialchars($pd['name']) ?></h3>
                        <div class="datetime"><?= $date ?> • <?= $time ?></div>
                        <button class="btn-edit-notes" data-locked="true">View Notes</button>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($archived)): ?>
                    <p><em>No archived sessions.</em></p><?php endif; ?>
            </div>
        </details>
    </div>

    <!-- Modal -->
    <div id="modal-notes" class="modal-overlay">
        <div class="modal-content">
            <button id="modal-close-notes" class="modal-close">&times;</button>
            <h2 id="notes-practice-title"></h2>
            <p id="notes-practice-datetime"></p>
            <form id="form-notes">
                <input type="hidden" id="notes-practice-id">
                <textarea id="notes-textarea" rows="6"
                    style="width:100%;background:#2C2A2A;color:#fff;border:1px solid #555;border-radius:4px;padding:8px;"></textarea>
                <div style="margin-top:1rem;text-align:right;">
                    <button type="button" id="btn-save-notes">Save</button>
                    <button type="button" id="btn-cancel-notes">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const baseUrl = '<?= $baseUrl ?>';
            const modal = document.getElementById('modal-notes');
            const closeBtn = document.getElementById('modal-close-notes');
            const saveBtn = document.getElementById('btn-save-notes');
            const cancelBtn = document.getElementById('btn-cancel-notes');
            const titleEl = document.getElementById('notes-practice-title');
            const datetimeEl = document.getElementById('notes-practice-datetime');
            const ta = document.getElementById('notes-textarea');
            const hidId = document.getElementById('notes-practice-id');
            const groupSel = document.getElementById('ride_group_id');

            // 1) Flip expired active sessions to locked/view-only
            (function () {
                const now = new Date('<?= $nowIso ?>');
                document.querySelectorAll('#notes-grid .practice-panel').forEach(panel => {
                    const start = new Date(panel.dataset.start);
                    const tenAfter = new Date(start.getTime() + 10 * 60000);
                    if (now > tenAfter) {
                        const btn = panel.querySelector('.btn-edit-notes');
                        btn.textContent = 'View Notes';
                        btn.dataset.locked = 'true';
                    }
                });
            })();

            // 2) Attach listener to both grids
            ['notes-grid', 'notes-archived'].forEach(id => {
                document.getElementById(id).addEventListener('click', async e => {
                    const btn = e.target.closest('.btn-edit-notes');
                    if (!btn) return;

                    document.body.classList.add('modal-open');


                    const panel = btn.closest('.practice-panel');
                    const pdId = panel.dataset.id;
                    const start = new Date(panel.dataset.start);
                    const end = new Date(panel.dataset.end);

                    // Determine editability
                    const now = new Date('<?= $nowIso ?>');
                    const tenAfter = new Date(start.getTime() + 10 * 60000);
                    const locked = btn.dataset.locked === 'true';
                    const editable = !locked && now <= tenAfter;

                    // Populate modal header
                    titleEl.textContent = panel.querySelector('h3').innerText;
                    datetimeEl.textContent = `${start.toLocaleString()} - ${end.toLocaleTimeString()}`;
                    hidId.value = pdId;

                    console.log('Dropdown group before loading notes:', groupSel.value);

                    // Load note text for current dropdown selection
                    await loadNote();

                    // Toggle textarea & buttons
                    ta.readOnly = !editable;
                    if (editable) {
                        saveBtn.style.display = 'inline-block';
                        cancelBtn.style.display = 'inline-block';
                    } else {
                        saveBtn.style.display = 'none';
                        cancelBtn.style.display = 'none';
                    }

                    modal.style.display = 'flex';
                });
            });

            // Reload notes if group changes
            groupSel.addEventListener('change', () => {
                if (hidId.value) {
                    console.log('Dropdown changed, reloading notes for practice_day_id:', hidId.value, 'group:', groupSel.value);
                    loadNote();
                }
            });

            async function loadNote() {
                try {
                    const res = await fetch(`../router-api.php?path=admin/api/data/get-practice-note.php&practice_day_id=${hidId.value}&ride_group_id=${groupSel.value}`);
                    const json = await res.json();
                    ta.value = json.notes ?? '';
                } catch (e) {
                    console.error('Failed to load note:', e);
                    ta.value = '';
                }
            }

            // Close modal
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');

            });
            cancelBtn.addEventListener('click', () => {
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');

            });
            modal.addEventListener('click', e => {
                if (e.target === modal) modal.style.display = 'none';
                document.body.classList.remove('modal-open');

            });

            // Save notes
            saveBtn.addEventListener('click', async () => {
                const payload = JSON.stringify({ practice_day_id: hidId.value, ride_group_id: groupSel.value, notes: ta.value.trim() });
                try {
                    const res = await fetch(`../router-api.php?path=admin/api/pages/save-practice-note.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload });
                    const json = await res.json();
                    if (json.success) {
                        alert('Notes saved.');
                        modal.style.display = 'none';
                    } else {
                        alert('Save failed: ' + (json.error || ''));
                    }
                } catch {
                    alert('Error saving notes.');
                }
            });
        });

    </script>
</body>

</html>
