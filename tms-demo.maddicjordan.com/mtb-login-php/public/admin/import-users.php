<?php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie(basename(__FILE__), $pdo);

$currentDir = '/home/automaddic/mtb/server/user-data/spreadsheets/';
$archiveDir = $currentDir . 'archive/';

function getXlsxFiles($dir, $prefix = '') {
    $files = [];
    if (!is_dir($dir)) return $files;
    foreach (scandir($dir) as $file) {
        if (is_file($dir . $file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xlsx') {
            $files[] = $prefix . $file;
        }
    }
    return $files;
}

$currentFiles = getXlsxFiles($currentDir);
$archivedFiles = getXlsxFiles($archiveDir, 'archive/');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Import Users</title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/admin/styles/import-users.css">
</head>
<body>
  
<?php include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php"; ?>

<div id="mobile-blocker" class="mobile-modal-overlay">
    <div class="mobile-modal-content">
      <h2>Desktop Required</h2>
      <p>Importing Users is only available on desktop or larger screens.</p>
      <button class="btn autoImportBtn" style="background: #8DC74C !important;">Update rider data</button>
      <span class="import-desc"></span>
    </div>
  </div>
  <div class="container">
    <h2>Import Users from Spreadsheet</h2>

    <form id="spreadsheetForm" method="post" action="">
        <label>Select spreadsheet:</label>
        <select name="spreadsheet" required>
            <optgroup label="Current">
            <?php foreach ($currentFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars(basename($file)) ?></option>
            <?php endforeach; ?>
            </optgroup>
            <optgroup label="Archived">
            <?php foreach ($archivedFiles as $file): ?>
                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars(basename($file)) ?></option>
            <?php endforeach; ?>
            </optgroup>
        </select>

        <div class="btn-menu">
            <div>
                <button type="submit" class="btn">Load Preview</button>
            </div>
            <div>
                <button type="button" id="gen-report-csv" class="btn" style="background: #de7009 !important;">Export rider data as CSV</button>

            </div>
        </div> 
    </form>
    <span class="import-desc"></span>



    <div id="previewContainer"><p style="color: white;">Please load a spreadsheet</p></div>

    <div style="margin-top: 1em;">
      <button onclick="importSelected()" class="btn">Import Selected Rows</button>
    </div>
  </div>

  <script>
    const baseUrl = '<?= $baseUrl ?>';

    document.getElementById('spreadsheetForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const response = await fetch('../router-api.php?path=admin/api/pages/data-manager/load-preview.php', {
        method: 'POST',
        body: formData
      });
      const data = await response.json();
      renderPreview(data);
    });

    function renderPreview(data) {
        const container = document.getElementById('previewContainer');
        if (!data.success) {
            container.innerHTML = `<p style="color: red;">${data.error}</p>`;
            return;
        }

        const users = data.users;
        window.importUsersData = users; // Store in global

        let html = `
            <table>
            <thead>
                <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>First</th>
                <th>Last</th>
                <th>Email</th>
                <th>Full Name</th>
                <th>Role Level</th>
                </tr>
            </thead>
            <tbody>
        `;

        users.forEach((user, index) => {
            html += `
            <tr>
                <td><input type="checkbox" class="user-checkbox" name="selected" value="${index}"></td>
                <td>${user.first_name}</td>
                <td>${user.last_name}</td>
                <td>${user.email}</td>
                <td>${user.full_name}</td>
                <td>${user.role_level ?? '1'}</td>
            </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;

        // Add select-all functionality
        document.getElementById('select-all').addEventListener('change', function () {
            const isChecked = this.checked;
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = isChecked);
        });
    }


    async function importSelected() {
        const checkboxes = document.querySelectorAll('input[name="selected"]:checked');
        const indexes = Array.from(checkboxes).map(cb => parseInt(cb.value));
        const allData = window.importUsersData;
        const selectedUsers = indexes.map(i => allData[i]);
        const cleanArray = Array.from(selectedUsers);

        const response = await fetch('../router-api.php?path=admin/api/pages/data-manager/import-selected-users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({users: cleanArray})
        });

        const result = await response.text();
        alert(result);
    }
  </script>

  
    

    <script>
    document.getElementById('gen-report-csv').addEventListener('click', () => {
        window.location.href = '../router-api.php?path=scripts/export-riders-csv.php';
    });

    </script>


</body>
</html>
