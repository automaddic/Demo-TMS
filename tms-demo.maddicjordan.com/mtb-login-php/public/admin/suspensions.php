<?php

require_once '/home/automaddic/mtb/server/config/bootstrap.php';
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie(basename(__FILE__), $pdo);
// Fetch suspended users for admin review
$stmt = $pdo->query("SELECT id, first_name, last_name, preferred_name, email, created_at FROM users WHERE is_suspended = 1 ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suspended Accounts - Admin | Sope Creek MTB</title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/admin/styles/admin-suspensions.css" />

</head>

<body>
  <?php

  include $_SERVER['DOCUMENT_ROOT'] . "/mtb-login-php/public/inserts/navbar.php";


  ?>

  <div class="page-wrapper">
    <div class="header-wrapper">
      <div class="header-content">
        <div class="container">
          <h1>Suspended Accounts</h1>
          <?php if (count($users) === 0): ?>
            <p>No suspended accounts found.</p>
          <?php else: ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th class="col-id">ID</th>
                    <th>Name</th>
                    <th class="col-preferred">Preferred Name</th>
                    <th class="col-email">Email</th>
                    <th>Suspended Since</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $user): ?>
                    <tr>
                      <td class="col-id"><?= htmlspecialchars($user['id']) ?></td>
                      <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                      <td class="col-preferred"><?= htmlspecialchars($user['preferred_name'] ?: '—') ?></td>
                      <td class="col-email"><?= htmlspecialchars($user['email']) ?></td>
                      <td><?= htmlspecialchars($user['created_at']) ?></td>
                      <td class="actions-cell">
                        <div class="action-wrapper">
                          <form class="inline-form" method="POST"
                            action="../router-api.php?path=admin/api/pages/suspensions/reinstate-user.php">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                            <button type="submit" class="action-btn">Approve</button>
                          </form>
                          <form class="inline-form" method="POST" action="../router-api.php?path=admin/api/pages/suspensions/delete-user.php"
                            onsubmit="return confirm('Are you sure you want to delete this user?');">
                            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                            <button type="submit" class="action-btn delete-btn">Delete</button>
                          </form>
                        </div>
                      </td>

                    </tr>
                  <?php endforeach; ?>
                </tbody>

              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
