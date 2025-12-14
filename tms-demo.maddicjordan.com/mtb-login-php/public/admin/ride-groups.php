<?php

require_once '/home/automaddic/mtb/server/config/bootstrap.php';
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie(basename(__FILE__), $pdo);
// Get all users and ride groups

$allowedSortFields = [
    'username',
    'email',
    'first_name',
    'last_name',
    'ride_group_name',
    'school_name',
    'team_name'
];

$sort = $_GET['sort'] ?? 'username';
$sortDir = $_GET['dir'] ?? 'asc';

// Validate sort inputs
if (!in_array($sort, $allowedSortFields))
    $sort = 'username';
$sortDir = ($sortDir === 'desc') ? 'desc' : 'asc';


$usersStmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.ride_group_id,
           rg.name AS ride_group_name, s.name AS school_name, t.name AS team_name
    FROM alt_users u
    LEFT JOIN ride_groups rg ON u.ride_group_id = rg.id
    LEFT JOIN schools s ON u.school_id = s.id
    LEFT JOIN teams t ON u.team_id = t.id
    ORDER BY $sort $sortDir
");
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);




// Map ride group IDs to names for easy reference
$groupMap = [];
foreach ($rideGroups as $group) {
    $groupMap[$group['id']] = $group['name'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ride Group Editor - Admin | Sope Creek MTB</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/admin/styles/ride-group.css" />
</head>

<body>
    <?php

    include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php";


    ?>
    <div class="page-wrapper">
        <div class="header-wrapper">
            <div class="header-content">
                <h1>Ride Group Editor</h1>
                <div class="container">
                    <?php if (isset($_SESSION['flash_status'], $_SESSION['flash_message'])): ?>
                        <div class="flash <?= $_SESSION['flash_status'] === 'success' ? 'flash-success' : 'flash-error' ?>">
                            <?= htmlspecialchars($_SESSION['flash_message']) ?>
                        </div>
                        <?php unset($_SESSION['flash_status'], $_SESSION['flash_message']); ?>
                    <?php endif; ?>


                    <form method="POST" class="table-container"
                        action="../router-api.php?path=admin/api/pages/update-ride-group.php">
                        <button type="submit" class="action-btn">Save Changes</button>

                        <div class="toolbar">
                            <input type="text" id="searchInput" placeholder="Search users...">

                            <div class="sort-buttons">
                                <button type="button" class="user-sort-btn" data-sort="first_name">First Name</button>
                                <button type="button" class="user-sort-btn" data-sort="last_name">Last Name</button>
                                <button type="button" class="user-sort-btn" data-sort="email">Email</button>
                                <button type="button" class="user-sort-btn" data-sort="ride_group_name">Ride
                                    Group</button>
                                <button type="button" class="user-sort-btn" data-sort="school_name">School</button>
                                <button type="button" class="user-sort-btn" data-sort="team_name">Team</button>
                            </div>
                        </div>


                        <table id="ride-group-table" class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Ride Group</th>
                                    <th>School</th>
                                    <th>Team</th>
                                    <th>Assign Group</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['first_name']) ?>
                                            <?= htmlspecialchars($user['last_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['ride_group_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($user['school_name'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($user['team_name'] ?? '—') ?></td>
                                        <td>
                                            <select name="ride_group[<?= $user['id'] ?>]">
                                                <option value="">-- Select Group --</option>
                                                <?php foreach ($rideGroups as $group): ?>
                                                    <option value="<?= $group['id'] ?>" <?= $user['ride_group_id'] == $group['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($group['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>


                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const searchInput = document.getElementById("searchInput");
            const sortButtons = document.querySelectorAll(".user-sort-btn");
            const table = document.getElementById("ride-group-table");
            const tbody = table.querySelector("tbody");

            let currentSort = { field: "", direction: "asc" };

            // Map sort keys to column indexes (0-based)
            const sortKeyToColIndex = {
                "first_name": 0,
                "last_name": 0,
                "email": 1,
                "ride_group_name": 2,
                "school_name": 3,
                "team_name": 4
            };

            // Search filter
            searchInput.addEventListener("input", () => {
                const query = searchInput.value.toLowerCase();
                Array.from(tbody.rows).forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? "" : "none";
                });
            });

            // Sort buttons behavior
            sortButtons.forEach(btn => {
                btn.addEventListener("click", () => {
                    const field = btn.dataset.sort;
                    const isSameField = currentSort.field === field;
                    currentSort.direction = isSameField && currentSort.direction === "asc" ? "desc" : "asc";
                    currentSort.field = field;

                    // Update table data-sort attribute to trigger CSS column display
                    if (field === "first_name" || field === "last_name") {
                        table.setAttribute("data-sort", "name");  // We'll treat both as "name"
                    } else {
                        table.setAttribute("data-sort", field);
                    }

                    // Highlight active button
                    sortButtons.forEach(b => b.classList.remove("active"));
                    btn.classList.add("active");

                    // Get colIndex for sorting
                    const colIndex = sortKeyToColIndex[field] ?? 0;

                    // Get rows and sort
                    const rows = Array.from(tbody.rows);
                    rows.sort((a, b) => {
                        let valA = a.cells[colIndex]?.textContent.trim().toLowerCase() || "";
                        let valB = b.cells[colIndex]?.textContent.trim().toLowerCase() || "";

                        // For first_name: compare first word
                        if (field === "first_name") {
                            valA = valA.split(" ")[0] || "";
                            valB = valB.split(" ")[0] || "";
                        }
                        // For last_name: compare last word
                        else if (field === "last_name") {
                            const partsA = valA.split(" ");
                            const partsB = valB.split(" ");
                            valA = partsA.length > 1 ? partsA[partsA.length - 1] : valA;
                            valB = partsB.length > 1 ? partsB[partsB.length - 1] : valB;
                        }

                        if (currentSort.direction === "asc") {
                            return valA.localeCompare(valB);
                        } else {
                            return valB.localeCompare(valA);
                        }
                    });

                    // Append sorted rows back
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        });

    </script>


</body>

</html>
