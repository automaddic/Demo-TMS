<?php
require_once '/home/automaddic/mtb/server/config/bootstrap.php';  // Adjust path as needed
require_once '/home/automaddic/mtb/server/auth/check-role-access.php';
enforceAccessOrDie('admin-dashboard.php', $pdo);
?>
<style>
  /* Ensure search bar doesn’t overextend: max-width inside container */
  .role-editor-container {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow: visible;
    /* For example, limit input width: */
  }

  .role-editor-container input[name="search_user"] {
    max-width: 300px;
    width: 100%;
    box-sizing: border-box;
    margin-bottom: 0.5rem;
  }

  .role-editor-container .sort-buttons button {
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .role-editor-container table {
    width: 100%;
    table-layout: fixed;
    /* ensures it respects container width */
    word-wrap: break-word;
    border-collapse: collapse;
  }

  .role-editor-container th,
  .role-editor-container td {
    padding: 8px 12px;
    border-bottom: 1px solid #555;
    overflow-wrap: break-word;
    word-break: break-word;
  }

  .role-editor-container button.save-btn-top {
    background-color: #28a745;
    margin-bottom: 1rem;
  }

  .role-editor-container button.save-btn-top:hover {
    background-color: #218838;
  }

  .role-editor-container select {
    max-width: 100%;
    width: 100%;
    box-sizing: border-box;
  }
</style>


<div class="role-editor-container">
  <!-- Feedback message -->
  <?php if (isset($_GET['saved']) && $_GET['mode'] === 'role-editor'): ?>
    <div class="alert success">User roles updated successfully.</div>
  <?php elseif (isset($_GET['error']) && $_GET['mode'] === 'role-editor'): ?>
    <div class="alert error">Error updating some user roles.</div>
  <?php endif; ?>

  <!-- Save button at top -->
  <button type="button" class="save-btn-top" id="save-user-roles-btn">Save User Roles</button>

  <!-- Search & Sort -->
  <div id="user-role-search-sort">
    <input type="text" id="search-user-input" placeholder="Search users..."
      value="<?= htmlspecialchars($search_user) ?>">
    <div>
      <?php
      $cols = [
        'username' => 'Username',
        'email' => 'Email',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'role_level' => 'Role Level'
      ];
      foreach ($cols as $colKey => $label) {
        $isActive = ($sort_user === $colKey);
        $btnClass = 'user-sort-btn' . ($isActive ? ' active' : '');
        echo '<button type="button" class="' . $btnClass . '" data-sort="' . htmlspecialchars($colKey) . '">'
          . htmlspecialchars($label)
          . ($isActive
            ? ($order_user === 'asc' ? ' ↑' : ($order_user === 'desc' ? ' ↓' : ''))
            : '')
          . '</button> ';
      }
      ?>
    </div>
  </div>

  <!-- Table of users and role selects -->
  <form method="POST" action="../router-api.php?path=admin/actions/role-editor-handler.php"
    id="user-role-form">
    <table>
      <thead>
        <tr>
          <th>Username</th>
          <th>Email</th>
          <th>First Name</th>
          <th>Last Name</th>
          <th>Role</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['first_name'] ?? '') ?></td>
            <td><?= htmlspecialchars($u['last_name'] ?? '') ?></td>
            <td>
              <?php
              $userRoleLevel = $u['role_level'];
              $isEditable = $u['id'] != $currentUserId && $userRoleLevel <= $currentUserLevel;
              ?>
              <select name="role[<?= $u['id'] ?>]" <?= !$isEditable ? 'disabled' : '' ?>>
                <?php
                for ($lvl = 0; $lvl <= $currentUserLevel; $lvl++):
                  $label = isset($rolesList[$lvl]) ? $rolesList[$lvl] : 'ADMIN';
                  ?>
                  <option value="<?= $lvl ?>" <?= $lvl == $userRoleLevel ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endfor; ?>
              </select>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </form>
</div>

<script>
  // Immediate search & sort behavior:
  document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById('search-user-input');
    const saveBtn = document.getElementById('save-user-roles-btn');

    function debounce(fn, delay) {
      let timer = null;
      return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
      };
    }

    // Search: update URL after debounce
    searchInput.addEventListener('input', debounce(ev => {
      const v = ev.target.value.trim();
      const params = new URLSearchParams(window.location.search);
      params.set('mode', 'role-editor');
      if (v !== '') {
        params.set('search_user', v);
      } else {
        params.delete('search_user');
      }
      // Preserve sort_user/order_user if present
      window.location.search = params.toString();
    }, 300));

    // Sort buttons
    document.querySelectorAll('.user-sort-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const sortField = btn.dataset.sort;
        const params = new URLSearchParams(window.location.search);
        params.set('mode', 'role-editor');
        const currSort = params.get('sort_user');
        const currOrder = params.get('order_user');
        if (currSort === sortField) {
          // toggle asc -> desc -> remove
          if (currOrder === 'asc') {
            params.set('order_user', 'desc');
          } else if (currOrder === 'desc') {
            params.delete('sort_user');
            params.delete('order_user');
          } else {
            params.set('order_user', 'asc');
          }
        } else {
          params.set('sort_user', sortField);
          params.set('order_user', 'asc');
        }
        // preserve search_user
        window.location.search = params.toString();
      });
    });

    // Save button triggers form submit
    saveBtn.addEventListener('click', () => {
      document.getElementById('user-role-form').submit();
    });
  });

</script>
