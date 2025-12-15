<?php
// public/admin/attendance.php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie(basename(__FILE__), $pdo);

$now = new DateTime('now', new DateTimeZone('America/New_York'));
$todayStart = (clone $now)->setTime(0, 0, 0);   
$yesterdayStart = (clone $now)->setTime(0, 0, 0)->modify('-1 day');
$twoWeeksFwd = (clone $todayStart)->modify('+14 days'); // 14 days from today

// 1) Active / Upcoming: start today or later, but within next 14 days
$stmtAct = $pdo->prepare("
  SELECT pd.*, dt.name AS day_type_name
    FROM practice_days pd
    LEFT JOIN day_types dt ON pd.day_type_id = dt.id
    WHERE pd.start_datetime >= ? AND pd.start_datetime <= ?
    ORDER BY pd.start_datetime ASC
");
$stmtAct->execute([
  $todayStart->format('Y-m-d H:i:s'),
  $twoWeeksFwd->format('Y-m-d H:i:s')
]);
$active = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

// 2) Archived: ended before today
$stmtArch = $pdo->prepare("
  SELECT pd.*, dt.name AS day_type_name
    FROM practice_days pd
    LEFT JOIN day_types dt ON pd.day_type_id = dt.id
    WHERE pd.start_datetime < ?
    ORDER BY pd.start_datetime DESC
");
$stmtArch->execute([
  $yesterdayStart->format('Y-m-d H:i:s'),
]);
$archived = $stmtArch->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RSVP Management</title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/admin/styles/attendance.css">
</head>

<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php"; ?>
  <div class="page-wrapper">
    <h1>RSVP</h1>
    <p>Click a practice day to view RSVP information.</p>

    <!-- Active / Ongoing / Upcoming -->
    <div class="practices-list" id="attendance-days">
      <?php foreach ($active as $pd):
        $start = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
        $end = $pd['end_datetime'] ? new DateTime($pd['end_datetime'], new DateTimeZone('America/New_York')) : null;
        $date = $start->format('M j, Y');
        $time = $start->format('g:ia') . ($end ? ' – ' . $end->format('g:ia') : '');
        ?>
        <div class="practice-item" data-id="<?= $pd['id'] ?>">
          <strong><?= htmlspecialchars($pd['name']) ?></strong><br>
          <?= $date ?> • <?= $time ?>
          <button class="btn-edit-attendance">View RSVP Info</button>
        </div>
      <?php endforeach; ?>

      <?php if (empty($active)): ?>
        <p><em>No active or upcoming practice days in the next 14 days.</em></p>
      <?php endif; ?>
    </div>

    <!-- Archived Dropdown -->
    <details class="past-practices" style="margin-top:2rem;">
      <summary>Archived Practices (ended more than 1 day ago)</summary>
      <div class="practices-list" id="archived-days" style="margin-top:1rem;">
        <?php foreach ($archived as $pd):
          $start = new DateTime($pd['start_datetime'], new DateTimeZone('America/New_York'));
          $end = $pd['end_datetime'] ? new DateTime($pd['end_datetime'], new DateTimeZone('America/New_York')) : null;
          $date = $start->format('M j, Y');
          $time = $start->format('g:ia') . ($end ? ' – ' . $end->format('g:ia') : '');
          ?>
          <div class="practice-item" data-id="<?= $pd['id'] ?>">
            <strong><?= htmlspecialchars($pd['name']) ?></strong><br>
            <?= $date ?> • <?= $time ?>
            <button class="btn-edit-attendance">View RSVP Info</button>
          </div>
        <?php endforeach; ?>

        <?php if (empty($archived)): ?>
          <p><em>No archived practices in the last 14 days.</em></p>
        <?php endif; ?>
      </div>
    </details>
  </div>

  <!-- Attendance Modal (read‑only) -->
  <div id="modal-attendance" class="attendance-modal-overlay" style="display:none;">
    <div class="attendance-modal-content">
      <button id="modal-close-attendance" class="modal-close">&times;</button>
      <h2 id="att-practice-title">RSVP for Practice</h2>
      <p id="att-practice-datetime"></p>

      <!-- Sort Buttons -->
      <div id="att-sort-buttons" style="margin-bottom:1rem;">
        <button class="att-sort-btn" data-sort="first_name">First Name ↑↓</button>
        <button class="att-sort-btn" data-sort="last_name">Last Name ↑↓</button>
        <button class="att-sort-btn" data-sort="school">School ↑↓</button>
        <button class="att-sort-btn" data-sort="team">Team ↑↓</button>
        <button class="att-sort-btn" data-sort="ride_group">Ride Group ↑↓</button>
        <button class="att-sort-btn" data-sort="status">Status ↑↓</button>
      </div>

      <!-- Read‑only table -->
      <div class="table-container" style="max-height:60vh; overflow-y:auto;">
        <table id="attendance-table">
          <thead>
            <tr>
              <th class="col-first_name">Name</th>
              <th class="col-school">School</th>
              <th class="col-team">Team</th>
              <th class="col-ride_group">Ride Group</th>
              <th class="col-status">Status</th>
            </tr>
          </thead>
          <tbody>
            <!-- Populated dynamically -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const baseUrl = '<?= $baseUrl ?>';
      const modal = document.getElementById('modal-attendance');
      const closeBtn = document.getElementById('modal-close-attendance');
      const titleEl = document.getElementById('att-practice-title');
      const datetimeEl = document.getElementById('att-practice-datetime');
      const tableBody = document.querySelector('#attendance-table tbody');
      const sortButtons = document.querySelectorAll('.att-sort-btn');
      let attendanceData = [];
      let currentSort = { field: null, order: 'asc' };

      // Render table (read‑only, no dropdowns)
      function renderTable(data) {
        tableBody.innerHTML = '';
        const colors = { yes: 'rgba(40,167,69,0.2)', no: 'rgba(220,53,69,0.2)', maybe: 'rgba(255,193,7,0.2)' };
        data.forEach(item => {
          const tr = document.createElement('tr');
          tr.style.backgroundColor = colors[item.status] || '';
          tr.innerHTML = `
            <td class="col-name">${item.first_name} ${item.last_name}</td>
            <td class="col-school">${item.school || ''}</td>
            <td class="col-team">${item.team || ''}</td>
            <td class="col-ride_group">${item.ride_group || ''}</td>
            <td class="col-status">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</td>
          `;

          tableBody.appendChild(tr);
        });
      }

      // Sorting handler
      sortButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          const field = btn.dataset.sort;
          // Toggle or set sort order
          if (currentSort.field === field) {
            currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
          } else {
            currentSort.field = field;
            currentSort.order = 'asc';
          }
          // Update arrows
          sortButtons.forEach(b => {
            b.textContent = b.textContent.replace(/^[↑↓]\s*/, '');
          });
          const arrow = currentSort.order === 'asc' ? '↑ ' : '↓ ';
          btn.textContent = arrow + btn.textContent;

          // Sort data
          // Sort data
          attendanceData.sort((a, b) => {
            let va = a[field] || '';
            let vb = b[field] || '';
            if (field === 'status') {
              const map = { no: 0, maybe: 1, yes: 2 };
              va = map[va] ?? 0;
              vb = map[vb] ?? 0;
            } else {
              va = va.toString().toLowerCase();
              vb = vb.toString().toLowerCase();
            }
            if (va < vb) return currentSort.order === 'asc' ? -1 : 1;
            if (va > vb) return currentSort.order === 'asc' ? 1 : -1;
            return 0;
          });

          renderTable(attendanceData);
          updateColumnVisibility();

        });
      });

      function updateColumnVisibility() {
        // Columns to control
        const columns = ['school', 'team', 'ride_group'];
        columns.forEach(col => {
          const els = document.querySelectorAll(`.col-${col}`);
          els.forEach(el => el.classList.remove('show'));
        });

        if (columns.includes(currentSort.field)) {
          const showEls = document.querySelectorAll(`.col-${currentSort.field}`);
          showEls.forEach(el => el.classList.add('show'));
        }
      }


      // Close modal
      function closeModal() {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');

      }
      closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', ev => {
        if (ev.target === modal) closeModal();
      });

      // Open modal on button click
      document.getElementById('attendance-days').addEventListener('click', async ev => {
        const btn = ev.target.closest('.btn-edit-attendance');
        if (!btn) return;
        document.body.classList.add('modal-open');
        const panel = btn.closest('.practice-item');
        const pdId = panel.dataset.id;

        // Fetch practice info
        try {
          const res = await fetch(`../router-api.php?path=api/compile/get-practice-day-details.php&id=${pdId}`);
          const info = await res.json();
          titleEl.textContent = `Attendance - ${info.name}`;
          const s = new Date(info.start_datetime);
        const optsDate = { year: 'numeric', month: 'short', day: 'numeric' };
        const optsTime = { hour: 'numeric', minute: '2-digit' };

        let datetimeStr = `${s.toLocaleDateString(undefined, optsDate)} • ${s.toLocaleTimeString(undefined, optsTime)}`;

        if (info.end_datetime) {
        const e = new Date(info.end_datetime);
        datetimeStr += ' - ' + e.toLocaleTimeString(undefined, optsTime);
        }

        datetimeEl.textContent = datetimeStr;

        } catch {
          titleEl.textContent = 'Attendance';
          datetimeEl.textContent = '';
        }

        // Fetch attendance data
        try {
          const res2 = await fetch(`../router-api.php?path=admin/api/data/get-attendance.php&practice_day_id=${pdId}`);
          const d2 = await res2.json();
          attendanceData = d2.attendance || [];
        } catch {
          attendanceData = [];
        }

        // Reset sort indicators
        currentSort = { field: null, order: 'asc' };
        sortButtons.forEach(b => b.textContent = b.textContent.replace(/^[↑↓]\s*/, ''));

        renderTable(attendanceData);
        modal.style.display = 'flex';
      });
      document.getElementById('archived-days').addEventListener('click', async ev => {
        const btn = ev.target.closest('.btn-edit-attendance');
        if (!btn) return;
        document.body.classList.add('modal-open');
        const panel = btn.closest('.practice-item');
        const pdId = panel.dataset.id;

        // Fetch practice info
        try {
          const res = await fetch(`../router-api.php?path=api/compile/get-practice-day-details.php&id=${pdId}`);
          const info = await res.json();
          titleEl.textContent = `Attendance - ${info.name}`;
          const s = new Date(info.start_datetime), e = new Date(info.end_datetime);
          const optsDate = { year: 'numeric', month: 'short', day: 'numeric' };
          const optsTime = { hour: 'numeric', minute: '2-digit' };
          datetimeEl.textContent = `${s.toLocaleDateString(undefined, optsDate)} • `
            + `${s.toLocaleTimeString(undefined, optsTime)} - `
            + `${e.toLocaleTimeString(undefined, optsTime)}`;
        } catch {
          titleEl.textContent = 'Attendance';
          datetimeEl.textContent = '';
        }

        // Fetch attendance data
        try {
          const res2 = await fetch(`../router-api.php?path=admin/api/data/get-attendance.php&practice_day_id=${pdId}`);
          const d2 = await res2.json();
          attendanceData = d2.attendance || [];
        } catch {
          attendanceData = [];
        }

        // Reset sort indicators
        currentSort = { field: null, order: 'asc' };
        sortButtons.forEach(b => b.textContent = b.textContent.replace(/^[↑↓]\s*/, ''));

        renderTable(attendanceData);
        modal.style.display = 'flex';
      });
    });
  </script>


</body>

</html>
