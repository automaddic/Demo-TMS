<?php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);
?>

<form method="POST" action="../router-api.php?path=admin/actions/page-access-handler.php">
  <?php if (isset($_GET['saved']) && $_GET['mode'] === 'page-access'): ?>
        <p class="alert success">Page access saved.</p>
      <?php elseif (isset($_GET['error']) && $_GET['mode'] === 'page-access'): ?>
        <p class="alert error">Error saving page access.</p>
      <?php endif; ?>
  <table>
    <thead><tr><th>Page</th><th>Minimum Role Required</th></tr></thead>
    <tbody>
      <?php foreach ($pages as $file): ?>
        <tr>
          <td><?= htmlspecialchars($file) ?></td>
          <td>
            <select name="role[<?= htmlspecialchars($file) ?>]">
              <?php foreach ($rolesList as $lvl => $rName): ?>
                <?php if ($lvl <= $currentUserLevel): ?>
                  <option value="<?= $lvl ?>"
                    <?= (isset($accessMap[$file]) && $accessMap[$file] == $lvl) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($rName) ?> (<?= $lvl ?>)
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <button type="submit">Save Page Access</button>
</form>

