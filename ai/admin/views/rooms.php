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
                  <?php elseif (!in_array($r['room_type'], ['log'], true)): ?>
                    <form method="post" style="margin-top:.45rem;display:inline-block;" onsubmit="return confirm('Delete room &quot;<?= Util::e($displayName) ?>&quot;? This permanently removes it and all its messages.');">
                      <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
                      <input type="hidden" name="_rooms_action" value="delete_room">
                      <input type="hidden" name="room_id" value="<?= $rid ?>">
                      <button type="submit" class="btn btn-small" style="background:#7d3030;border-color:#7d3030;">Delete</button>
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
