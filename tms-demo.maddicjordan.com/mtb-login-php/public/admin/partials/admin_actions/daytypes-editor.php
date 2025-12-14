<?php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);
?>

<form method="POST" action="../../../router-api.php?path=admin/actions/save-daytypes.php">
    <?php if (isset($_GET['saved']) && $_GET['mode'] === 'day-types'): ?>
        <p class="alert success">Day types updated.</p>
      <?php elseif (isset($_GET['error']) && $_GET['mode'] === 'page-access'): ?>
        <p class="alert error">Error saving day types.</p>
      <?php endif; ?>
  <table>
    <thead><tr><th>Name</th><th>Delete?</th></tr></thead>
    <tbody>
    <?php foreach ($dayTypes as $d): ?>
      <tr>
        <td><input type="text" name="daytypes[<?= $d['id'] ?>]" value="<?= htmlspecialchars($d['name']) ?>" style="max-width:300px; width:100%; box-sizing:border-box;"></td>
        <td><button type="submit" name="delete" value="<?= $d['id'] ?>">Delete</button></td>
      </tr>
    <?php endforeach; ?>
      <tr>
        <td colspan="2"><input type="text" name="daytypes[new]" placeholder="New Day Type" style="max-width:300px; width:100%; box-sizing:border-box;"></td>
      </tr>
    </tbody>
  </table>
  <button type="submit">Save Day Types</button>
</form>
