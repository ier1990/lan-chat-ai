      <h2>AI Setup</h2>

      <?php foreach ($providers as $pv):
        $providerModels = array_values(array_filter(
          $models, fn($m) => (int) $m['provider_id'] === (int) $pv['id']
        ));
        $hasModels = !empty($providerModels);
      ?>
      <div class="provider-card" style="margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
          <strong><?= Util::e($pv['name']) ?></strong>
          <span class="badge <?= $pv['is_enabled'] ? 'badge-ok' : 'badge-off' ?>">
            <?= $pv['is_enabled'] ? 'enabled' : 'disabled' ?>
          </span>
          <?php if ($hasModels): ?>
            <span class="muted" style="font-size:.8rem;"><?= count($providerModels) ?> model<?= count($providerModels) !== 1 ? 's' : '' ?> loaded</span>
          <?php endif; ?>
        </div>

        <form method="post" class="settings-form" style="margin-bottom:0;">
          <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">
          <input type="hidden" name="_provider_action" value="save_provider">
          <input type="hidden" name="provider_id" value="<?= (int) $pv['id'] ?>">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem;align-items:start;">
            <div class="field" style="margin:0;">
              <label>Endpoint URL</label>
              <input type="text" name="base_url" value="<?= Util::e((string) $pv['base_url']) ?>" placeholder="http://localhost:11434/v1" required>
              <small class="field-help">OpenAI-compatible /v1 base URL</small>
            </div>

            <div class="field" style="margin:0;">
              <label>API Key<?= !empty($pv['api_key']) ? ' <span class="muted" style="font-weight:normal;">(set)</span>' : '' ?></label>
              <input type="password" name="api_key" placeholder="<?= !empty($pv['api_key']) ? '(leave blank to keep current)' : 'sk-... or blank for Ollama' ?>" autocomplete="new-password">
            </div>

            <div class="field" style="margin:0;">
              <label>Model<?= $pv['model_default'] ? ' <span class="muted" style="font-weight:normal;">— current: ' . Util::e((string) $pv['model_default']) . '</span>' : '' ?></label>
              <?php if ($hasModels): ?>
                <select name="model_default">
                  <option value="">— keep current —</option>
                  <?php foreach ($providerModels as $m): ?>
                    <option value="<?= Util::e($m['model_key']) ?>"<?= $pv['model_default'] === $m['model_key'] ? ' selected' : '' ?>>
                      <?= Util::e($m['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="text" name="model_default" value="<?= Util::e((string) ($pv['model_default'] ?? '')) ?>" placeholder="e.g. llama3 or gpt-4o-mini">
                <small class="field-help">No models loaded yet — enter manually or use "Load from endpoint" below.</small>
              <?php endif; ?>
            </div>

            <div class="field" style="margin:0;display:flex;flex-direction:column;justify-content:flex-end;gap:.35rem;">
              <label style="display:flex;gap:.5rem;align-items:center;cursor:pointer;font-weight:normal;">
                <input type="hidden" name="refresh_models" value="0">
                <input type="checkbox" name="refresh_models" value="1"<?= !$hasModels ? ' checked' : '' ?>>
                Load models from endpoint on save
              </label>
              <?php if ($hasModels): ?>
                <span class="muted" style="font-size:.8rem;">Uncheck to skip re-fetching on save.</span>
              <?php endif; ?>
            </div>
          </div>

          <div style="margin-top:.85rem;">
            <button type="submit" class="btn btn-primary btn-small">Save</button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>

      <?php if (!$providers): ?>
        <p class="muted">No providers configured. Run the installer to seed defaults.</p>
      <?php endif; ?>

      <hr style="border:none;border-top:1px solid var(--border);margin:1.75rem 0 1.25rem;">

      <h3 style="margin-bottom:.75rem;">AI Behavior</h3>
      <form method="post" class="settings-form">
        <input type="hidden" name="_save_settings" value="1">
        <input type="hidden" name="csrf" value="<?= Util::e(Util::csrfToken()) ?>">

        <div class="field">
          <label for="ai_dm_enabled_chk">AI in DMs</label>
          <input type="hidden" name="settings[ai.dm_enabled]" value="0">
          <input type="checkbox" id="ai_dm_enabled_chk" name="settings[ai.dm_enabled]" value="1"<?= Settings::get('ai.dm_enabled', '1') === '1' ? ' checked' : '' ?>>
          <small class="field-help">Allow AI auto-reply in DM rooms targeting an AI user.</small>
        </div>

        <div class="field">
          <label for="ai_trigger_mode_sel">Default AI Trigger</label>
          <select id="ai_trigger_mode_sel" name="settings[ai.default_trigger_mode]">
            <?php foreach (['off' => 'Off', 'manual' => 'Manual (@mention)', 'always' => 'Always'] as $val => $label): ?>
              <option value="<?= $val ?>"<?= Settings::get('ai.default_trigger_mode', 'manual') === $val ? ' selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <small class="field-help">Applied to new rooms by default. Per-room override available in Rooms.</small>
        </div>

        <button type="submit" class="btn btn-primary btn-small">Save AI Settings</button>
      </form>
