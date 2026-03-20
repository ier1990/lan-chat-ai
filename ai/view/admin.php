<?php
/**
 * view/admin.php — Admin panel (included by layout.php when $view === 'admin').
 *
 * Variables provided by admin.php:
 *   $tab, $flash, $settingsTabs, $tabFields,
 *   $rooms, $users, $roles, $personas, $providers, $models
 */
$adminTabs = ['settings', 'rooms', 'users', 'ai_users', 'personas', 'providers', 'webhooks', 'memories'];
$activeTab = Util::get('section', 'settings');
$queryBase = [];
if (!empty($standalone)) {
    $queryBase['standalone'] = '1';
}
if (!empty($hideAdminNav)) {
    $queryBase['hide_nav'] = '1';
}

$buildAdminUrl = static function (array $extra = []) use ($queryBase): string {
    $params = array_merge($queryBase, $extra);
    return '/ai/admin.php' . ($params ? ('?' . http_build_query($params)) : '');
};
?>
<div class="admin-panel<?= !empty($hideAdminNav) ? ' no-nav' : '' ?>">

  <?php if (empty($hideAdminNav)): ?>
  <aside class="admin-sidebar">
    <h2 class="admin-title">Admin</h2>
    <?php foreach ($adminTabs as $at): ?>
      <a href="<?= Util::e($buildAdminUrl(['section' => $at])) ?>"
         class="admin-nav-item<?= UI::activeClass($activeTab === $at) ?>">
        <?= Util::e($at === 'ai_users' ? 'AI Users' : ucfirst($at)) ?>
      </a>
    <?php endforeach; ?>
    <a href="<?= Util::e($buildAdminUrl(['section' => $activeTab, 'tab' => $tab, 'hide_nav' => '1'])) ?>" class="admin-nav-item">⇤ Hide Menu</a>
    <a href="/ai/" class="admin-nav-item">← Back to Chat</a>
  </aside>
  <?php endif; ?>

  <div class="admin-content">
    <div class="tab-bar" style="margin-bottom:1rem;">
      <?php if (!empty($hideAdminNav)): ?>
        <a href="<?= Util::e($buildAdminUrl(['section' => $activeTab, 'tab' => $tab, 'hide_nav' => '0'])) ?>" class="tab">Show Menu</a>
      <?php else: ?>
        <a href="<?= Util::e($buildAdminUrl(['section' => $activeTab, 'tab' => $tab, 'hide_nav' => '1'])) ?>" class="tab">Hide Menu</a>
      <?php endif; ?>
      <?php if (empty($standalone)): ?>
        <a href="<?= Util::e($buildAdminUrl(['section' => $activeTab, 'tab' => $tab, 'standalone' => '1'])) ?>" class="tab" target="_blank" rel="noopener">Open Standalone</a>
      <?php endif; ?>
    </div>

    <?php if (!empty($flash)): ?>
      <?= UI::flash($flashType ?? 'success', $flash) ?>
    <?php endif; ?>

    <!-- ── Settings ───────────────────────────────────────────────── -->
    <?php if ($activeTab === 'settings'): ?>
      <h2>Settings</h2>
      <div class="tab-bar">
        <?php foreach ($settingsTabs as $st): ?>
          <a href="<?= Util::e($buildAdminUrl(['section' => 'settings', 'tab' => $st])) ?>"
             class="tab<?= UI::activeClass($tab === $st) ?>">
            <?= Util::e(ucfirst($st)) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($tabFields): ?>
        <form method="post" class="settings-form">
          <input type="hidden" name="_save_settings" value="1">
          <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
          <?php foreach ($tabFields as $meta): ?>
            <?= SettingsMeta::renderField($meta) ?>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
      <?php else: ?>
        <p class="muted">Select a tab to edit settings.</p>
      <?php endif; ?>

    <!-- ── Rooms ──────────────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'rooms'): ?>
      <h2>Rooms</h2>
      <div class="user-admin-grid" style="margin-bottom:1rem;">
        <form method="post" class="settings-form compact-card">
          <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
          <input type="hidden" name="_rooms_action" value="create_channel">
          <h3>Create Channel</h3>
          <div class="field">
            <label for="room_new_name">Name</label>
            <input type="text" id="room_new_name" name="name" placeholder="dev-updates" required>
          </div>
          <div class="field">
            <label for="room_new_slug">Slug (optional)</label>
            <input type="text" id="room_new_slug" name="slug" placeholder="dev-updates">
          </div>
          <label class="muted" style="display:flex;gap:.45rem;align-items:center;margin-bottom:.4rem;">
            <input type="hidden" name="is_private" value="0">
            <input type="checkbox" name="is_private" value="1"> private
          </label>
          <label class="muted" style="display:flex;gap:.45rem;align-items:center;margin-bottom:.4rem;">
            <input type="hidden" name="ai_enabled" value="0">
            <input type="checkbox" name="ai_enabled" value="1"> AI enabled
          </label>
          <div class="field">
            <label for="room_new_trigger">AI Trigger</label>
            <select id="room_new_trigger" name="ai_trigger_mode">
              <option value="off">off</option>
              <option value="mention" selected>mention</option>
              <option value="always">always</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Create Channel</button>
        </form>

        <form method="post" class="settings-form compact-card">
          <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
          <input type="hidden" name="_rooms_action" value="create_dm_room">
          <h3>Create DM Room</h3>
          <div class="field">
            <label for="dm_target_type">Target Type</label>
            <select id="dm_target_type" name="dm_target_type">
              <option value="user">User</option>
              <option value="persona">Persona</option>
            </select>
          </div>
          <div class="field" id="dm_target_user_wrap">
            <label for="dm_target_user_id">Target User</label>
            <select id="dm_target_user_id" name="dm_target_id_user">
              <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>"><?= Util::e($u['display_name']) ?> (@<?= Util::e($u['username']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" id="dm_target_persona_wrap" style="display:none;">
            <label for="dm_target_persona_id">Target Persona</label>
            <select id="dm_target_persona_id" name="dm_target_id_persona">
              <?php foreach ($personas as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= Util::e($p['name']) ?> (<?= Util::e($p['persona_key']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="hidden" name="dm_target_id" id="dm_target_id" value="<?= !empty($users) ? (int) $users[0]['id'] : 0 ?>">
          <button type="submit" class="btn btn-primary">Create / Open DM</button>
        </form>

        <div class="compact-card muted-card">
          <h3>Webhook Keys Per Room</h3>
          <ul class="admin-help-list">
            <li>Each room can have a webhook key for POST ingestion.</li>
            <li>Use "Create/Rotate Key" to generate a fresh secret.</li>
            <li>"Delete Key" revokes webhook posting immediately.</li>
          </ul>
          <p class="muted">Endpoint format: <code><?= Util::e($webhookBaseUrl) ?>YOUR_KEY</code></p>
          <p class="muted">DM labels now show as DM Self, DM AI, or DM with participant names.</p>
        </div>
      </div>

      <table class="data-table">
        <thead><tr><th>Name</th><th>Type</th><th>Slug</th><th>Private</th><th>AI</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($rooms as $r): ?>
            <?php
              $rs = Util::jsonDecode($r['settings_json'] ?? '') ?? [];
              $rid = (int) $r['id'];
              $dmMeta = $roomDmMeta[$rid] ?? null;
              $displayName = (string) $r['name'];
              if ($r['room_type'] === 'dm' && $dmMeta) {
                  $displayName = $dmMeta['label'] . ($dmMeta['detail'] !== '' ? (' · ' . $dmMeta['detail']) : '');
              }
              $hook = $roomWebhooks[$rid] ?? null;
              $hookUrl = $hook ? ($webhookBaseUrl . $hook['webhook_key']) : '';
                $curlExample = $hook
                  ? 'curl -X POST "' . $hookUrl . '" -H "Content-Type: application/json" -d "{\\"text\\":\\"hello from $(hostname)\\"}"'
                  : '';
            ?>
            <tr>
              <td>
                <div><strong><?= Util::e($displayName) ?></strong></div>
                <?php if ($r['room_type'] !== 'dm'): ?>
                  <div class="muted" style="font-size:.82rem;">editable room name below</div>
                <?php endif; ?>
              </td>
              <td><?= Util::e($r['room_type']) ?></td>
              <td><code><?= Util::e($r['slug']) ?></code></td>
              <td><?= !empty($r['is_private']) ? '✓' : '—' ?></td>
              <td><?= !empty($rs['ai_enabled']) ? Util::e($rs['ai_trigger_mode'] ?? 'on') : '—' ?></td>
              <td style="min-width:350px;">
                <form method="post" class="inline-form" style="display:grid;grid-template-columns:1fr 1fr auto;gap:.4rem;align-items:center;">
                  <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                  <input type="hidden" name="_rooms_action" value="save_room">
                  <input type="hidden" name="room_id" value="<?= $rid ?>">
                  <input type="text" name="name" value="<?= Util::e((string) $r['name']) ?>"<?= $r['room_type'] === 'dm' ? ' readonly' : '' ?> placeholder="room name">
                  <input type="text" name="slug" value="<?= Util::e((string) $r['slug']) ?>"<?= $r['room_type'] === 'dm' ? ' readonly' : '' ?> placeholder="slug">
                  <button type="submit" class="btn btn-small">Save</button>

                  <label class="muted" style="display:flex;gap:.35rem;align-items:center;">
                    <input type="hidden" name="is_private" value="0">
                    <input type="checkbox" name="is_private" value="1"<?= !empty($r['is_private']) ? ' checked' : '' ?><?= $r['room_type'] === 'dm' ? ' disabled' : '' ?>> private
                  </label>
                  <label class="muted" style="display:flex;gap:.35rem;align-items:center;">
                    <input type="hidden" name="ai_enabled" value="0">
                    <input type="checkbox" name="ai_enabled" value="1"<?= !empty($rs['ai_enabled']) ? ' checked' : '' ?>> AI
                  </label>
                  <select name="ai_trigger_mode">
                    <option value="off"<?= ($rs['ai_trigger_mode'] ?? '') === 'off' ? ' selected' : '' ?>>off</option>
                    <option value="mention"<?= ($rs['ai_trigger_mode'] ?? '') === 'mention' ? ' selected' : '' ?>>mention</option>
                    <option value="always"<?= ($rs['ai_trigger_mode'] ?? '') === 'always' ? ' selected' : '' ?>>always</option>
                  </select>
                </form>

                <div style="margin-top:.45rem;padding-top:.45rem;border-top:1px dashed var(--border);">
                  <form method="post" class="inline-form" style="display:grid;grid-template-columns:1fr 1fr auto;gap:.35rem;align-items:center;">
                    <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                    <input type="hidden" name="_rooms_action" value="create_or_rotate_webhook">
                    <input type="hidden" name="room_id" value="<?= $rid ?>">
                    <input type="text" name="webhook_name" value="<?= Util::e((string) ($hook['name'] ?? ('Room ' . $r['name'] . ' Hook'))) ?>" placeholder="Webhook name">
                    <input type="text" name="source_type" value="<?= Util::e((string) ($hook['source_type'] ?? 'generic')) ?>" placeholder="source type">
                    <button type="submit" class="btn btn-small"><?= $hook ? 'Rotate Key' : 'Create Key' ?></button>

                    <label class="muted" style="display:flex;gap:.35rem;align-items:center;grid-column:1 / span 2;">
                      <input type="hidden" name="webhook_enabled" value="0">
                      <input type="checkbox" name="webhook_enabled" value="1"<?= empty($hook) || !empty($hook['is_enabled']) ? ' checked' : '' ?>> webhook enabled
                    </label>
                  </form>

                  <?php if ($hook): ?>
                    <form method="post" style="margin-top:.35rem;display:inline-block;" onsubmit="return confirm('Delete webhook key for <?= Util::e($r['name']) ?>?');">
                      <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                      <input type="hidden" name="_rooms_action" value="delete_webhook">
                      <input type="hidden" name="room_id" value="<?= $rid ?>">
                      <input type="hidden" name="webhook_id" value="<?= (int) $hook['id'] ?>">
                      <button type="submit" class="btn btn-small" style="background:#7d3030;border-color:#7d3030;">Delete Key</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($hook): ?>
                    <div class="muted" style="font-size:.78rem;margin-top:.25rem;word-break:break-all;">POST URL: <?= Util::e($hookUrl) ?></div>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.35rem;">
                      <button type="button" class="btn btn-small js-copy-text" data-copy="<?= Util::e($hookUrl) ?>">Copy URL</button>
                      <button type="button" class="btn btn-small js-copy-text" data-copy="<?= Util::e($curlExample) ?>">Copy curl</button>
                    </div>
                    <textarea readonly rows="3" style="margin-top:.35rem;font-size:.8rem;"><?= Util::e($curlExample) ?></textarea>
                  <?php endif; ?>

                  <?php if ($r['room_type'] === 'dm'): ?>
                    <form method="post" style="margin-top:.45rem;display:inline-block;" onsubmit="return confirm('Delete DM room <?= Util::e($displayName) ?>? This removes its message history.');">
                      <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                      <input type="hidden" name="_rooms_action" value="delete_dm_room">
                      <input type="hidden" name="room_id" value="<?= $rid ?>">
                      <button type="submit" class="btn btn-small" style="background:#7d3030;border-color:#7d3030;">Delete DM</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rooms): ?>
            <tr><td colspan="6" class="muted">No rooms yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <script>
      (function () {
        var typeEl = document.getElementById('dm_target_type');
        var userWrap = document.getElementById('dm_target_user_wrap');
        var personaWrap = document.getElementById('dm_target_persona_wrap');
        var userSelect = document.getElementById('dm_target_user_id');
        var personaSelect = document.getElementById('dm_target_persona_id');
        var targetIdEl = document.getElementById('dm_target_id');

        function syncDmTarget() {
          if (!typeEl || !targetIdEl) return;
          var isPersona = typeEl.value === 'persona';
          if (userWrap) userWrap.style.display = isPersona ? 'none' : '';
          if (personaWrap) personaWrap.style.display = isPersona ? '' : 'none';
          targetIdEl.value = isPersona
            ? ((personaSelect && personaSelect.value) || '0')
            : ((userSelect && userSelect.value) || '0');
        }

        if (typeEl) typeEl.addEventListener('change', syncDmTarget);
        if (userSelect) userSelect.addEventListener('change', syncDmTarget);
        if (personaSelect) personaSelect.addEventListener('change', syncDmTarget);
        syncDmTarget();

        document.querySelectorAll('.js-copy-text').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy') || '';
            if (!text) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(text).then(function () {
                var old = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = old; }, 1100);
              });
              return;
            }

            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (_e) {}
            document.body.removeChild(ta);
          });
        });
      })();
      </script>

    <!-- ── Users ──────────────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'users'): ?>
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

    <!-- ── AI Users ──────────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'ai_users'): ?>
      <h2>AI Users</h2>

      <div class="user-admin-grid">
        <form method="post" class="settings-form compact-card">
          <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
          <input type="hidden" name="_ai_users_action" value="create_ai_user">
          <h3>Create AI User</h3>

          <div class="field">
            <label for="ai_username">Username</label>
            <input type="text" id="ai_username" name="username" placeholder="openai" required>
          </div>
          <div class="field">
            <label for="ai_display_name">Display Name</label>
            <input type="text" id="ai_display_name" name="display_name" placeholder="OpenAI" required>
          </div>
          <div class="field">
            <label for="ai_provider_key">Provider key</label>
            <input type="text" id="ai_provider_key" name="provider_key" value="openai_compat" required>
            <small class="field-help">Use <code>openrouter</code> for OpenRouter-specific defaults.</small>
          </div>
          <div class="field">
            <label for="ai_base_url">User Endpoint URL</label>
            <input type="text" id="ai_base_url" name="base_url" placeholder="https://api.openai.com/v1" required>
            <small class="field-help">For OpenRouter use <code>https://openrouter.ai/api/v1</code>.</small>
          </div>
          <div class="field">
            <label for="ai_model_default">Default model</label>
            <input type="text" id="ai_model_default" name="model_default" placeholder="gpt-4o-mini" required>
          </div>
          <div class="field">
            <label for="ai_api_key">API Key (also login password)</label>
            <input type="password" id="ai_api_key" name="api_key" required>
          </div>
          <div class="field">
            <label for="ai_persona_id">AI Persona</label>
            <select id="ai_persona_id" name="persona_id">
              <option value="0">None</option>
              <?php foreach ($personas as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= Util::e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="ai_http_referer">HTTP-Referer</label>
            <input type="text" id="ai_http_referer" name="http_referer" placeholder="https://your-app.example/">
          </div>
          <div class="field">
            <label for="ai_x_title">X-Title</label>
            <input type="text" id="ai_x_title" name="x_title" value="<?= Util::e(Settings::get('app.site_name', 'AI Chat')) ?>">
          </div>
          <div class="field" style="grid-column:1 / -1;">
            <label for="ai_headers_json">Extra headers JSON</label>
            <textarea id="ai_headers_json" name="headers_json" rows="3" placeholder='{"X-Custom-Header":"value"}'></textarea>
            <small class="field-help">Optional. OpenRouter-friendly headers above are merged into this JSON.</small>
          </div>

          <button type="submit" class="btn btn-primary">Create AI User</button>
        </form>

        <div class="compact-card muted-card">
          <h3>What this does</h3>
          <ul class="admin-help-list">
            <li>creates a normal account with role <strong>ai</strong></li>
            <li>stores provider endpoint/model/key per AI user</li>
            <li>lets DMs to that account auto-call its provider</li>
            <li>persona is optional and can shape the AI voice later</li>
            <li>OpenRouter users can set HTTP-Referer and X-Title directly here</li>
          </ul>
        </div>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>User</th><th>Endpoint</th><th>Model</th><th>Provider</th><th>Persona</th><th>Enabled</th><th>Save</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aiUsers as $au): ?>
            <?php
              $auHeaders = AiUsers::decodeHeaders($au['headers_json'] ?? null);
              $auReferer = (string) ($auHeaders['HTTP-Referer'] ?? $auHeaders['http-referer'] ?? '');
              $auTitle = (string) ($auHeaders['X-Title'] ?? $auHeaders['x-title'] ?? '');
              unset($auHeaders['HTTP-Referer'], $auHeaders['http-referer'], $auHeaders['X-Title'], $auHeaders['x-title']);
              $auExtraHeaders = $auHeaders ? Util::jsonEncode($auHeaders) : '';
            ?>
            <tr>
              <td>
                <strong>@<?= Util::e($au['username']) ?></strong><br>
                <span class="muted"><?= Util::e($au['display_name']) ?></span>
              </td>
              <td colspan="6">
                <form method="post" class="inline-form" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto auto;gap:.5rem;align-items:center;">
                  <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                  <input type="hidden" name="_ai_users_action" value="save_ai_user">
                  <input type="hidden" name="user_id" value="<?= (int) $au['id'] ?>">

                  <input type="text" name="base_url" value="<?= Util::e((string) ($au['base_url'] ?? '')) ?>" placeholder="https://.../v1" required>
                  <input type="text" name="model_default" value="<?= Util::e((string) ($au['model_default'] ?? '')) ?>" placeholder="model" required>
                  <input type="text" name="provider_key" value="<?= Util::e((string) ($au['provider_key'] ?? 'openai_compat')) ?>" placeholder="provider key" required>

                  <select name="persona_id">
                    <option value="0">None</option>
                    <?php foreach ($personas as $p): ?>
                      <option value="<?= (int) $p['id'] ?>"<?= (int) ($au['persona_id'] ?? 0) === (int) $p['id'] ? ' selected' : '' ?>>
                        <?= Util::e($p['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>

                  <label class="muted" style="display:flex;gap:.35rem;align-items:center;">
                    <input type="checkbox" name="is_enabled" value="1"<?= !empty($au['is_enabled']) ? ' checked' : '' ?>> on
                  </label>

                  <input type="password" name="api_key" placeholder="API key (blank keeps current)">
                  <input type="text" name="http_referer" value="<?= Util::e($auReferer) ?>" placeholder="HTTP-Referer" style="grid-column:1 / span 2;">
                  <input type="text" name="x_title" value="<?= Util::e($auTitle) ?>" placeholder="X-Title" style="grid-column:3 / span 2;">
                  <textarea name="headers_json" rows="2" placeholder='{"X-Custom-Header":"value"}' style="grid-column:1 / span 5;"><?= Util::e($auExtraHeaders) ?></textarea>
                  <button type="submit" class="btn btn-small">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$aiUsers): ?>
            <tr><td colspan="7" class="muted">No AI users yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

    <!-- ── Personas ───────────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'personas'): ?>
      <h2>AI Personas</h2>
      <div class="user-admin-grid">
        <form method="post" class="settings-form compact-card" id="persona-create-form">
          <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
          <input type="hidden" name="_persona_action" value="create_persona">
          <h3>Create Persona</h3>

          <div class="field">
            <label for="persona_new_key">Persona Key</label>
            <input type="text" id="persona_new_key" name="persona_key" placeholder="support-pro" required>
            <small class="field-help">Lowercase letters, numbers, dot, underscore, dash.</small>
          </div>
          <div class="field">
            <label for="persona_new_name">Name</label>
            <input type="text" id="persona_new_name" name="name" placeholder="Support Pro" required>
          </div>
          <div class="field">
            <label for="persona_new_prompt">System Prompt</label>
            <textarea id="persona_new_prompt" name="system_prompt" rows="6" placeholder="You are..." required></textarea>
          </div>
          <div class="field">
            <label for="persona_new_style">Style Notes</label>
            <textarea id="persona_new_style" name="style_notes" rows="3" placeholder="Tone and formatting guidance"></textarea>
          </div>
          <div class="field">
            <label for="persona_new_settings">Settings JSON</label>
            <textarea id="persona_new_settings" name="settings_json" rows="3" placeholder='{"temperature":0.3,"max_tokens":1200}'></textarea>
          </div>

          <label class="muted" style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem;">
            <input type="hidden" name="is_enabled" value="0">
            <input type="checkbox" name="is_enabled" value="1" checked> enabled
          </label>
          <label class="muted" style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" value="1"> make default persona
          </label>

          <button type="submit" class="btn btn-primary">Create Persona</button>
        </form>

        <div class="compact-card muted-card">
          <h3>Export / Import</h3>
          <p class="muted" style="margin-bottom:.75rem;">Share personas across installs with plain JSON.</p>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
            <a class="btn btn-small" href="<?= Util::e($buildAdminUrl(['section' => 'personas', 'persona_export' => 'all'])) ?>">Export All JSON</a>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
            <input type="hidden" name="_persona_action" value="import_personas_json">
            <div class="field">
              <label for="persona_import_json">Import JSON</label>
              <textarea id="persona_import_json" name="import_json" rows="8" placeholder='{"personas":[{"persona_key":"support-pro","name":"Support Pro","system_prompt":"You are..."}]}'></textarea>
              <small class="field-help">Accepts a single persona object, an array, or an object with a <code>personas</code> array.</small>
            </div>
            <label class="muted" style="display:flex;gap:.5rem;align-items:center;margin-bottom:1rem;">
              <input type="hidden" name="replace_existing" value="0">
              <input type="checkbox" name="replace_existing" value="1"> replace existing personas with matching keys
            </label>
            <button type="submit" class="btn btn-small">Import JSON</button>
          </form>
        </div>

        <div class="compact-card muted-card">
          <h3>Awesome Examples</h3>
          <p class="muted" style="margin-bottom:.75rem;">Quick insert or use as starter template in the create form.</p>
          <div style="display:grid;gap:.6rem;">
            <?php foreach ($personaExamples as $ek => $ex): ?>
              <div style="border:1px solid var(--border);border-radius:var(--radius);padding:.6rem;background:var(--bg-card);">
                <strong><?= Util::e($ex['name']) ?></strong>
                <div class="muted" style="font-size:.82rem;margin:.2rem 0 .5rem;">key: <code><?= Util::e($ex['persona_key']) ?></code></div>
                <div class="muted" style="font-size:.82rem;margin-bottom:.5rem;"><?= Util::e($ex['style_notes'] ?? '') ?></div>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                    <input type="hidden" name="_persona_action" value="create_persona_example">
                    <input type="hidden" name="example_key" value="<?= Util::e($ek) ?>">
                    <button class="btn btn-small" type="submit">Add Example</button>
                  </form>
                  <button type="button"
                          class="btn btn-small js-persona-template"
                          data-key="<?= Util::e($ex['persona_key']) ?>"
                          data-name="<?= Util::e($ex['name']) ?>"
                          data-prompt="<?= Util::e($ex['system_prompt']) ?>"
                          data-style="<?= Util::e($ex['style_notes'] ?? '') ?>"
                          data-settings="<?= Util::e(isset($ex['settings_json']) ? Util::jsonEncode($ex['settings_json']) : '') ?>">Use Template</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <h3 style="margin:1rem 0;">Existing Personas</h3>
      <table class="data-table">
        <thead><tr><th>Persona</th><th>Details</th></tr></thead>
        <tbody>
          <?php foreach ($personasAdmin as $p): ?>
            <tr>
              <td style="min-width:220px;vertical-align:top;">
                <div><strong><?= Util::e($p['name']) ?></strong></div>
                <div class="muted" style="font-size:.82rem;">key: <code><?= Util::e($p['persona_key']) ?></code></div>
                <div class="muted" style="font-size:.82rem;">Used by <?= (int) ($p['ai_user_count'] ?? 0) ?> AI user(s)</div>
                <div style="margin-top:.5rem;">
                  <a class="btn btn-small" href="<?= Util::e($buildAdminUrl(['section' => 'personas', 'persona_export' => (string) $p['id']])) ?>">Export JSON</a>
                </div>
              </td>
              <td>
                <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;align-items:start;">
                  <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                  <input type="hidden" name="_persona_action" value="save_persona">
                  <input type="hidden" name="persona_id" value="<?= (int) $p['id'] ?>">

                  <input type="text" name="persona_key" value="<?= Util::e($p['persona_key']) ?>" required>
                  <input type="text" name="name" value="<?= Util::e($p['name']) ?>" required>

                  <textarea name="system_prompt" rows="6" required style="grid-column:1 / -1;"><?= Util::e((string) ($p['system_prompt'] ?? '')) ?></textarea>
                  <textarea name="style_notes" rows="3" placeholder="Style notes" style="grid-column:1 / -1;"><?= Util::e((string) ($p['style_notes'] ?? '')) ?></textarea>
                  <textarea name="settings_json" rows="3" placeholder='{"temperature":0.3}' style="grid-column:1 / -1;"><?= Util::e((string) ($p['settings_json'] ?? '')) ?></textarea>

                  <label class="muted" style="display:flex;gap:.4rem;align-items:center;">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1"<?= !empty($p['is_enabled']) ? ' checked' : '' ?>> enabled
                  </label>
                  <label class="muted" style="display:flex;gap:.4rem;align-items:center;">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1"<?= !empty($p['is_default']) ? ' checked' : '' ?>> default
                  </label>

                  <div style="grid-column:1 / -1;">
                    <button type="submit" class="btn btn-small">Save</button>
                  </div>

                </form>
                <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                  <form method="post" onsubmit="return confirm('Delete persona <?= Util::e($p['name']) ?>?');" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                    <input type="hidden" name="_persona_action" value="delete_persona">
                    <input type="hidden" name="persona_id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn btn-small" style="background:#7d3030;border-color:#7d3030;">Delete</button>
                  </form>
                  </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$personasAdmin): ?>
            <tr><td colspan="2" class="muted">No personas yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <script>
      (function () {
        var form = document.getElementById('persona-create-form');
        if (!form) return;
        var keyEl = form.querySelector('[name="persona_key"]');
        var nameEl = form.querySelector('[name="name"]');
        var promptEl = form.querySelector('[name="system_prompt"]');
        var styleEl = form.querySelector('[name="style_notes"]');
        var settingsEl = form.querySelector('[name="settings_json"]');

        document.querySelectorAll('.js-persona-template').forEach(function (btn) {
          btn.addEventListener('click', function () {
            if (keyEl) keyEl.value = btn.getAttribute('data-key') || '';
            if (nameEl) nameEl.value = btn.getAttribute('data-name') || '';
            if (promptEl) promptEl.value = btn.getAttribute('data-prompt') || '';
            if (styleEl) styleEl.value = btn.getAttribute('data-style') || '';
            if (settingsEl) settingsEl.value = btn.getAttribute('data-settings') || '';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
          });
        });
      })();
      </script>

    <!-- ── Providers ──────────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'providers'): ?>
      <h2>AI Providers &amp; Models</h2>
      <?php foreach ($providers as $pv): ?>
        <div class="provider-card">
          <strong><?= Util::e($pv['name']) ?></strong>
          <code class="muted"><?= Util::e($pv['base_url']) ?></code>
          <span class="badge <?= $pv['is_enabled'] ? 'badge-ok' : 'badge-off' ?>">
            <?= $pv['is_enabled'] ? 'enabled' : 'disabled' ?>
          </span>

          <?php
            $providerModels = array_values(array_filter(
              $models,
              fn($m) => (int) $m['provider_id'] === (int) $pv['id']
            ));
          ?>

          <form method="post" class="inline-form provider-inline-form">
            <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
            <input type="hidden" name="_provider_action" value="refresh_models">
            <input type="hidden" name="provider_id" value="<?= (int) $pv['id'] ?>">
            <button type="submit" class="btn btn-small">Refresh Models from URL</button>
          </form>

          <form method="post" class="inline-form provider-inline-form">
            <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
            <input type="hidden" name="_provider_action" value="set_default_model">
            <input type="hidden" name="provider_id" value="<?= (int) $pv['id'] ?>">
            <label for="provider_model_<?= (int) $pv['id'] ?>" class="muted">Default model</label>
            <select id="provider_model_<?= (int) $pv['id'] ?>" name="model_key">
              <?php foreach ($providerModels as $m): ?>
                <option value="<?= Util::e($m['model_key']) ?>"<?= $pv['model_default'] === $m['model_key'] ? ' selected' : '' ?>>
                  <?= Util::e($m['label']) ?> (<?= Util::e($m['model_key']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-small">Save</button>
          </form>

          <ul class="model-list">
            <?php foreach ($models as $m): ?>
              <?php if ((int) $m['provider_id'] === (int) $pv['id']): ?>
                <li><?= Util::e($m['label']) ?> <code><?= Util::e($m['model_key']) ?></code></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>

    <!-- ── Webhooks ───────────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'webhooks'): ?>
      <h2>Webhook Sources</h2>
      <?php $webhooks = DB::fetchAll('SELECT ws.*, r.name AS room_name FROM webhook_sources ws JOIN rooms r ON r.id = ws.target_room_id'); ?>
      <?php if ($webhooks): ?>
        <table class="data-table">
          <thead><tr><th>Name</th><th>Key</th><th>Target Room</th><th>Enabled</th></tr></thead>
          <tbody>
            <?php foreach ($webhooks as $wh): ?>
              <tr>
                <td><?= Util::e($wh['name']) ?></td>
                <td><code><?= Util::e($wh['webhook_key']) ?></code></td>
                <td><?= Util::e($wh['room_name']) ?></td>
                <td><?= $wh['is_enabled'] ? '✓' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted">No webhooks yet. Use <code>webhook_sources</code> table to add one.</p>
        <p class="muted">POST to <code>/ai/webhook.php?key=YOUR_KEY</code> with a JSON body containing <code>text</code>.</p>
      <?php endif; ?>

    <!-- ── Memories ──────────────────────────────────────────────────── -->
    <?php elseif ($activeTab === 'memories'): ?>
      <h2>Memory Store</h2>
      <p class="muted">All user memories. Admins can see and delete any entry. Only <strong>Global + non-secret</strong> entries are injected as AI context.</p>

      <!-- Create global memory as admin -->
      <details class="mem-admin-create">
        <summary class="btn btn-small btn-secondary" style="display:inline-block;cursor:pointer;">+ Add Global Memory</summary>
        <form method="post" action="/ai/admin.php?standalone=<?= $standalone ? '1' : '0' ?>&amp;section=memories" class="memory-form" style="margin-top:.75rem">
          <input type="hidden" name="csrf"           value="<?= Util::e(Util::csrfToken()) ?>">
          <input type="hidden" name="_memory_action" value="save">
          <input type="hidden" name="mem_id"         value="0">
          <input type="hidden" name="scope"          value="global">
          <div class="form-row">
            <label>Title</label>
            <input type="text" name="title" class="input" maxlength="200" placeholder="Descriptive label…">
          </div>
          <div class="form-row">
            <label>Content <span class="required">*</span></label>
            <textarea name="content" class="input mem-content-area" rows="3" required placeholder="Fact or note to share with all users and the AI…"></textarea>
          </div>
          <div class="form-row">
            <label>Tags</label>
            <input type="text" name="tags" class="input" maxlength="500" placeholder="comma, separated">
          </div>
          <div class="form-row">
            <label class="checkbox-label">
              <input type="checkbox" name="is_secret" value="1"> Secret (hidden from AI)
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Save Global Memory</button>
        </form>
      </details>

      <?php if (!$allMemories): ?>
        <p class="muted" style="margin-top:1rem">No memories stored yet.</p>
      <?php else: ?>
        <table class="data-table" style="margin-top:1rem">
          <thead>
            <tr>
              <th>Owner</th>
              <th>Scope</th>
              <th>Secret</th>
              <th>Title</th>
              <th>Content preview</th>
              <th>Tags</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allMemories as $mem): ?>
              <tr>
                <td class="muted"><?= Util::e($mem['owner_username'] ?? '—') ?></td>
                <td>
                  <span class="badge <?= $mem['scope'] === 'global' ? 'badge-accent' : 'badge-neutral' ?>">
                    <?= Util::e($mem['scope']) ?>
                  </span>
                </td>
                <td><?= $mem['is_secret'] ? '🔒' : '' ?></td>
                <td><?= Util::e($mem['title'] ?: '—') ?></td>
                <td class="muted"><?= Util::e(mb_strimwidth($mem['content'], 0, 80, '…')) ?></td>
                <td class="muted"><?= Util::e($mem['tags']) ?></td>
                <td class="muted"><?= Util::e($mem['updated_at']) ?></td>
                <td>
                  <form method="post" action="/ai/admin.php?standalone=<?= $standalone ? '1' : '0' ?>&amp;section=memories"
                        onsubmit="return confirm('Delete this memory?')" class="inline-form">
                    <input type="hidden" name="csrf"           value="<?= Util::e(Util::csrfToken()) ?>">
                    <input type="hidden" name="_memory_action" value="delete">
                    <input type="hidden" name="mem_id"         value="<?= (int) $mem['id'] ?>">
                    <button class="btn btn-small btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    <?php endif; ?>

  </div><!-- .admin-content -->
</div><!-- .admin-panel -->
