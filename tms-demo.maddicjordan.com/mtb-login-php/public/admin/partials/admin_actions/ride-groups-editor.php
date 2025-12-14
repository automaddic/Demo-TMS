<?php
require_once  '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed
require_once  '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);
?>
<style>
    .add-new-form input[type="text"] {
        max-width: 300px;
        width: 100%;
        box-sizing: border-box;
    }

    .add-new-form select {
        max-width: 200px;
    }
</style>

<form method="POST" action="../router-api.php?path=admin/actions/ride-groups-handler.php">
    <?php if (isset($_GET['saved']) && $_GET['mode'] === 'ride-groups'): ?>
        <p class="alert success">Ride groups updated.</p>
      <?php elseif (isset($_GET['error']) && $_GET['mode'] === 'page-access'): ?>
        <p class="alert error">Error saving Ride Groups.</p>
      <?php endif; ?>
    <input type="hidden" name="action" value="update-existing">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Color</th>
                <th>Delete?</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rideGroups as $rg):
                $rgColor = $rg['color'] ?? '';
                ?>
                <tr>
                    <td><input type="text" name="rg_name[<?= $rg['id'] ?>]" value="<?= htmlspecialchars($rg['name']) ?>"
                            style="max-width:300px; width:100%; box-sizing:border-box;"></td>
                    <td>
                        <select name="rg_color_id[<?= $rg['id'] ?>]" style="max-width:200px;">
                            <option value="">-- None --</option>
                            <?php foreach ($colors as $c):
                                $selected = ($rgColor !== '' && $c['name'] === $rgColor) ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($rgColor):
                            $hex = null;
                            foreach ($colors as $c) {
                                if ($c['name'] === $rgColor) {
                                    $hex = $c['hex'] ?: null;
                                    break;
                                }
                            }
                            $badgeColor = $hex ?: '#fff';
                            ?>
                            <span class="ride-group-badge"
                                style="background-color: <?= htmlspecialchars($badgeColor) ?>;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="submit" name="rg_delete[<?= $rg['id'] ?>]" value="1"
                            class="btn-delete">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit">Update / Delete Selected</button>
</form>

<form method="POST" action="../router-api.php?path=admin/actions/ride-groups-handler.php"
    class="add-new-form" style="margin-top:1.5rem;">
    <input type="hidden" name="action" value="add-new">
    <h4>Add New Ride Group</h4>
    <input type="text" name="new_name" placeholder="Group Name">
    <select name="new_color_id">
        <option value="">-- None --</option>
        <?php foreach ($colors as $c): ?>
            <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Add Ride Group</button>
</form>
