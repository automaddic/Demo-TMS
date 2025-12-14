<?php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);
?>

<form method="POST" action="<?= htmlspecialchars($baseUrl) ?>/server/admin/actions/save-schools.php">
  <?php if (isset($_GET['saved']) && $_GET['mode']==='schools'): ?>
  <div class="alert success">Schools saved successfully.</div>
<?php elseif (isset($_GET['error']) && $_GET['mode']==='schools'): ?>
  <div class="alert error">Error saving schools.</div>
<?php endif; ?>
  <table>
    <thead><tr><th>Name</th><th>Delete?</th></tr></thead>
    <tbody>
    <?php foreach ($schools as $s): ?>
      <tr>
        <td><input type="text" name="schools[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['name']) ?>" style="max-width:300px; width:100%; box-sizing:border-box;"></td>
        <td><button type="submit" name="delete" value="<?= $s['id'] ?>">Delete</button></td>
      </tr>
    <?php endforeach; ?>
      <tr>
        <td colspan="2"><input type="text" name="schools[new]" placeholder="New School" style="max-width:300px; width:100%; box-sizing:border-box;"></td>
      </tr>
    </tbody>
  </table>
  <button type="submit">Save Schools</button>
</form>

