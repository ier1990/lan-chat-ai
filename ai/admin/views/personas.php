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
