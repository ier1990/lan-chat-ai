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
