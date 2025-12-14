<?php
// public/admin/admin-dashboard.php

require_once '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed

require_once '/home/automaddic/mtb/server/auth/check-role-access.php';

// Enforce that current user can access this admin dashboard
// INSERT your locking logic inside enforceAccessOrDie call
enforceAccessOrDie(basename(__FILE__), $pdo);

// Base URL for links (assumes BASE_URL in environment or config)

// Helper: build query string preserving other GET params, overriding keys in $overrides
function buildQuery(array $overrides = []): string
{
  $qs = $_GET;
  foreach ($overrides as $k => $v) {
    if ($v === null) {
      unset($qs[$k]);
    } else {
      $qs[$k] = $v;
    }
  }
  if (empty($qs)) {
    return '';
  }
  return '?' . http_build_query($qs);
}
// Helper: toggle order: null -> 'asc', 'asc' -> 'desc', 'desc' -> null
function nextOrder(?string $current): ?string
{
  if ($current === null || $current === '')
    return 'asc';
  if (strtolower($current) === 'asc')
    return 'desc';
  return null;
}

// Current user info
$currentUserId = $_SESSION['user']['id'] ?? 0;
$currentUserLevel = $_SESSION['user']['role_level'] ?? 0;

// === 1) Roles List (for Role Data Editor and User Role Editor) ===
$rolesStmt = $pdo->query("SELECT role_level, role_name FROM roles ORDER BY role_level ASC");
$rolesList = $rolesStmt->fetchAll(PDO::FETCH_KEY_PAIR); // [level => name]
$maxRoleLevel = max(array_keys($rolesList));

// === 2) Users for Role Editor (with search & sort) ===
// === 2) Users for Role Editor (with search & sort) ===
$search_user = trim($_GET['search_user'] ?? '');
$sort_user = $_GET['sort_user'] ?? '';
$order_user = $_GET['order_user'] ?? '';

$params = [];
$sql = "SELECT id, username, email, first_name, last_name, role_level FROM users";
if ($search_user !== '') {
  // Use positional placeholders for each LIKE
  $sql .= " WHERE username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
  $like = "%{$search_user}%";
  // supply the same value 4 times
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}
$validSortsUser = ['username', 'email', 'first_name', 'last_name', 'role_level'];
if (in_array($sort_user, $validSortsUser, true) && in_array(strtolower($order_user), ['asc', 'desc'], true)) {
  // Prevent SQL injection by limiting to valid column names and order
  $sql .= " ORDER BY {$sort_user} " . strtoupper($order_user);
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);


// === 3) Ride Groups (no search/sort UI) ===
$rideGroupsStmt = $pdo->query("SELECT id, name, color FROM ride_groups ORDER BY name ASC");
$rideGroups = $rideGroupsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch predefined colors for Ride Groups
$colorsStmt = $pdo->query("SELECT id, name, hex FROM predefined_colors ORDER BY name ASC");
$colors = $colorsStmt->fetchAll(PDO::FETCH_ASSOC);

// === 4) Schools ===
$schoolsStmt = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC");
$schools = $schoolsStmt->fetchAll(PDO::FETCH_ASSOC);

// === 5) Teams (no school association) ===
$teamsStmt = $pdo->query("SELECT id, name FROM teams ORDER BY name ASC");
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

// === 6) Day Types ===
$dayTypesStmt = $pdo->query("SELECT id, name FROM day_types ORDER BY name ASC");
$dayTypes = $dayTypesStmt->fetchAll(PDO::FETCH_ASSOC);


// === 7) Page Access ===
function getAdminPages(string $adminDir, string $currentFile): array
{
  $files = [];
  if (!is_dir($adminDir)) {
    return $files;
  }
  foreach (scandir($adminDir) as $f) {
    if ($f === '.' || $f === '..') {
      continue;
    }
    $full = $adminDir . '/' . $f;
    // Only top-level files, not directories
    if (is_file($full) && preg_match('/\.(php|html)$/i', $f)) {
      // Optionally skip partials or helper scripts; for example, if you keep partials in a subfolder, 
      // but since this is top-level, you may not need further filtering.
      // Skip the current script if you want to avoid double-adding; but we'll add it explicitly later.
      if ($f === $currentFile) {
        continue;
      }
      $files[] = $f;
    }
  }
  // Ensure current file (admin-dashboard.php) is included
  if (!in_array($currentFile, $files, true)) {
    $files[] = $currentFile;
  }
  // Sort alphabetically (optional)
  sort($files, SORT_FLAG_CASE | SORT_STRING);
  return $files;
}

$adminDir = $_SERVER['DOCUMENT_ROOT'] . '/mtb-login-php/public/admin';
$currentFile = basename(__FILE__); // e.g. "admin-dashboard.php"
$pages = getAdminPages($adminDir, $currentFile);

// Fetch existing access map
$accessMap = [];
$paStmt = $pdo->query("SELECT page_name, min_role_level FROM page_access");
while ($r = $paStmt->fetch(PDO::FETCH_ASSOC)) {
  $accessMap[$r['page_name']] = $r['min_role_level'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/public/admin/styles/admin-dashboard.css">
</head>

<body>

  <div id="mobile-blocker" class="mobile-modal-overlay">
    <div class="mobile-modal-content">
      <h2>Desktop Required</h2>
      <p>The Admin Dashboard is only available on desktop or larger screens.</p>
    </div>
  </div>


  <?php include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php"; ?>

  <div class="page-wrapper">
    <h1>Admin Dashboard</h1>

    <!-- Role Data Editor -->
    <details <?= (isset($_GET['mode']) && $_GET['mode'] === 'role-data') ? 'open' : '' ?>>
      <summary>Role Data Editor</summary>
      <?php include __DIR__ . '/partials/admin_actions/role-data-editor.php'; ?>
    </details>

    <!-- User Role Editor -->
    <details <?= (isset($_GET['mode']) && $_GET['mode'] === 'role-editor') ? 'open' : '' ?>>
      <summary>User Role Editor</summary>
      <p>Depricated.</p>
    </details>

    <!-- Page Access Editor -->
    <details <?= (isset($_GET['mode']) && $_GET['mode'] === 'page-access') ? 'open' : '' ?>>
      <summary>Page Access Editor</summary>

      <?php include __DIR__ . '/partials/admin_actions/page-access-editor.php'; ?>
    </details>

    <!-- Ride Groups Editor -->
    <details <?= (isset($_GET['mode']) && $_GET['mode'] === 'ride-groups') ? 'open' : '' ?>>
      <summary>Ride Groups Editor</summary>

      <?php include __DIR__ . '/partials/admin_actions/ride-groups-editor.php'; ?>
    </details>

    <!-- Schools Editor -->
    <details <?= (isset($_GET['mode']) && $_GET['mode'] === 'schools') ? 'open' : '' ?>>
      <summary>Schools Editor</summary>

      <?php include __DIR__ . '/partials/admin_actions/schools-editor.php'; ?>
    </details>

    <!-- Teams Editor -->
    <details <?= (isset($_GET['mode']) && $_GET['mode'] === 'teams') ? 'open' : '' ?>>
      <summary>Teams Editor</summary>

      <?php include __DIR__ . '/partials/admin_actions/teams-editor.php'; ?>
    </details>

    <!-- Day Types Editor -->
    <details <?= (isset($_GET['mode']) && $_GET['mode'] === 'day-types') ? 'open' : '' ?>>
      <summary>Day Types Editor</summary>

      <?php include __DIR__ . '/partials/admin_actions/daytypes-editor.php'; ?>
    </details>

  </div>
</body>

</html>
