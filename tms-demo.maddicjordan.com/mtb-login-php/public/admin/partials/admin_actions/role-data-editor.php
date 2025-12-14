<?php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<style>
#role-data-container {
  padding: 1rem;
  background-color: #3D3939;
  border-radius: 8px;
  margin-bottom: 1.5rem;
  overflow-x: auto;
}
#role-data-table {
  width: 100%;
  border-collapse: collapse;
}
#role-data-table th, #role-data-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #555;
}
#role-data-table tr.protected-role {
  background-color: #4B4848;
}
#role-data-table tr.protected-role input[readonly] {
  background-color: #4B4848;
  color: #ccc;
}
#role-data-table tr.new-role {
  background-color: #3D3A3A;
}
/* Grab handle styling */
.grab-handle {
  width: 24px;
  text-align: center;
}
</style>

<div id="role-data-container">
  <!-- Feedback messages -->
  <?php if (isset($_GET['saved']) && $_GET['mode']==='role-data'): ?>
    <div class="alert success">Roles saved successfully.</div>
  <?php elseif (isset($_GET['error']) && $_GET['mode']==='role-data'): ?>
    <div class="alert error">Error saving roles.</div>
  <?php endif; ?>

  <form id="role-data-form" method="POST" action="../router-api.php?path=admin/actions/role-data-handler.php">
    <button type="submit" style="margin-bottom:1rem; background-color:#28a745; color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer;">
      Save Roles
    </button>
    <table id="role-data-table">
      <thead>
        <tr><th></th><th>Order</th><th>Role Name</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rolesList as $level => $roleName):
            $isProtected = ($level >= $currentUserLevel);
        ?>
        <tr data-level="<?= $level ?>" class="<?= $isProtected ? 'protected-role' : '' ?>">
          <td class="grab-handle" style="cursor: <?= $isProtected ? 'not-allowed' : 'grab' ?>;">
            <?php if (!$isProtected): ?>
              &#x2630;
            <?php endif; ?>
          </td>
          <td class="role-order"><?= $level ?></td>
          <td>
            <input type="text" name="roles[<?= $level ?>]" value="<?= htmlspecialchars($roleName) ?>"
              <?= $isProtected ? 'readonly style="opacity:0.6; background:#4B4848; color:#ccc;"' : '' ?>>
          </td>
        </tr>
        <?php endforeach; ?>
        <!-- New role row: not draggable, but editable -->
        <tr data-level="new" class="new-role">
          <td class="grab-handle" style="cursor: not-allowed;"></td>
          <td class="role-order">New</td>
          <td>
            <input type="text" name="roles[new]" placeholder="New Role Name">
          </td>
        </tr>
      </tbody>
    </table>
  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.querySelector("#role-data-table tbody");
  Sortable.create(tbody, {
    handle: ".grab-handle",
    animation: 150,
    // Filter both protected and new-role rows
    filter: ".protected-role, .new-role",
    preventOnFilter: true,
    onEnd: () => {
      updateOrderDisplay();
    }
  });

  function updateOrderDisplay() {
    // Remove existing hidden input if any
    const existing = document.getElementById("new-role-ordering");
    if (existing) existing.remove();

    let order = 1;
    const ordering = [];
    document.querySelectorAll("#role-data-table tbody tr").forEach(row => {
      const lvl = row.dataset.level;
      if (lvl === "new") {
        row.querySelector(".role-order").textContent = "New";
      } else if (!row.classList.contains("protected-role")) {
        row.querySelector(".role-order").textContent = order;
        ordering.push(lvl);
        order++;
      } else {
        // protected: keep original displayed level
      }
    });
    // Append hidden input
    const input = document.createElement("input");
    input.type = "hidden";
    input.id = "new-role-ordering";
    input.name = "new_order";
    input.value = ordering.join(",");
    document.getElementById("role-data-form").appendChild(input);
  }

  // Initial ordering
  updateOrderDisplay();
});
</script>
