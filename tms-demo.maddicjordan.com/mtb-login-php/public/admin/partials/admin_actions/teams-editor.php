<?php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);
?>

<form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/server/admin/actions/save-teams.php">
  <?php if (isset($_GET['saved']) && $_GET['mode'] === 'teams'): ?>
        <p class="alert success">Teams updated.</p>
      <?php elseif (isset($_GET['error']) && $_GET['mode'] === 'page-access'): ?>
        <p class="alert error">Error saving teams.</p>
      <?php endif; ?>
  <table>
    <thead><tr><th>Name</th><th>Delete?</th></tr></thead>
    <tbody>
    <?php foreach ($teams as $t): ?>
      <tr>
        <td><input type="text" name="teams[<?= $t['id'] ?>][name]" value="<?= htmlspecialchars($t['name']) ?>" style="max-width:300px; width:100%; box-sizing:border-box;"></td>
        <td><button type="submit" name="delete" value="<?= $t['id'] ?>">Delete</button></td>
      </tr>
    <?php endforeach; ?>
      <tr>
        <td><input type="text" name="teams[new][name]" placeholder="New Team Name" style="max-width:300px; width:100%; box-sizing:border-box;"></td>
        <td></td>
      </tr>
    </tbody>
  </table>
  <button type="submit">Save Teams</button>
</form>

