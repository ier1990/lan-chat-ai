      <h2>Users</h2>
      <div class="user-admin-grid">
        <form method="post" class="settings-form compact-card">
          <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
          <input type="hidden" name="_users_action" value="create_user">
          <h3>Create User</h3>
          <div class="field">
            <label for="new_username">Username</label>
            <input type="text" id="new_username" name="username" required>
          </div>
          <div class="field">
            <label for="new_display_name">Display Name</label>
            <input type="text" id="new_display_name" name="display_name" required>
          </div>
          <div class="field">
            <label for="new_role">Role</label>
            <select id="new_role" name="role">
              <?php foreach ($roles as $role): ?>
                <option value="<?= Util::e($role['role_key']) ?>"><?= Util::e($role['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="new_password">Password</label>
            <input type="password" id="new_password" name="password" required>
          </div>
          <div class="field">
            <label for="new_password_confirm">Confirm Password</label>
            <input type="password" id="new_password_confirm" name="password_confirm" required>
          </div>
          <button type="submit" class="btn btn-primary">Create User</button>
        </form>

        <div class="compact-card muted-card">
          <h3>User Actions</h3>
          <p class="muted">From the table below you can:</p>
          <ul class="admin-help-list">
            <li>change any user's role</li>
            <li>reset a user's password</li>
            <li>delete users you no longer need</li>
          </ul>
          <p class="muted">Safety rules:</p>
          <ul class="admin-help-list">
            <li>you cannot delete yourself</li>
            <li>you cannot demote or delete the last admin</li>
          </ul>
        </div>
      </div>

      <table class="data-table">
        <thead><tr><th>Username</th><th>Display Name</th><th>Role</th><th>Password</th><th>Active</th><th>Delete</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= Util::e($u['username']) ?></td>
              <td><?= Util::e($u['display_name']) ?></td>
              <td>
                <form method="post" class="inline-form">
                  <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                  <input type="hidden" name="_users_action" value="change_role">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <select name="role"<?= (int) $u['id'] === (int) Auth::id() ? ' disabled' : '' ?>>
                    <?php foreach ($roles as $role): ?>
                      <option value="<?= Util::e($role['role_key']) ?>"<?= $u['role_key'] === $role['role_key'] ? ' selected' : '' ?>>
                        <?= Util::e($role['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ((int) $u['id'] !== (int) Auth::id()): ?>
                    <button type="submit" class="btn btn-small">Save</button>
                  <?php else: ?>
                    <span class="muted">self</span>
                  <?php endif; ?>
                </form>
              </td>
              <td>
                <form method="post" class="inline-form inline-form-password">
                  <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                  <input type="hidden" name="_users_action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <input type="password" name="new_password" placeholder="new password" class="compact-input" required>
                  <input type="password" name="new_password_confirm" placeholder="confirm" class="compact-input" required>
                  <button type="submit" class="btn btn-small">Reset</button>
                </form>
              </td>
              <td><?= $u['is_active'] ? '✓' : '—' ?></td>
              <td>
                <?php if ((int) $u['id'] === (int) Auth::id()): ?>
                  <span class="muted">self</span>
                <?php else: ?>
                  <form method="post" class="inline-form" onsubmit="return confirm('Delete user <?= Util::e($u['username']) ?>?');">
                    <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                    <input type="hidden" name="_users_action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
