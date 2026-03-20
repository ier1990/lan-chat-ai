<?php
/**
 * install.php — First-time setup wizard.
 *
 * Creates all DB tables, seeds default data, creates the admin user,
 * then writes a .installed lock file so this page can't be rerun.
 */
define('AI_INSTALL_MODE', true);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/util.php';

$lockFile = __DIR__ . '/.installed';

// Redirect away if already installed.
if (file_exists($lockFile)) {
    Util::redirect('/ai/');
}

$error   = '';
$success = false;

if (Util::isPost()) {
    $adminUser  = Util::post('admin_user');
    $adminName  = Util::post('admin_name');
    $adminPass  = Util::post('admin_pass');
    $adminPass2 = Util::post('admin_pass2');

    if (!$adminUser || !$adminName || !$adminPass) {
        $error = 'All fields are required.';
    } elseif ($adminPass !== $adminPass2) {
        $error = 'Passwords do not match.';
    } elseif (strlen($adminPass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        try {
            $cfg = require __DIR__ . '/config.php';
            _ensureDatabaseExists($cfg['db']);
            DB::connect($cfg['db']);
            _runInstall($adminUser, $adminName, $adminPass);
            file_put_contents($lockFile, date('c'));
            $success = true;
        } catch (Throwable $e) {
            $error = 'Install failed: ' . $e->getMessage();
        }
    }
}

// ─── Installer logic ─────────────────────────────────────────────────────────

/**
 * Create the target database if it does not already exist.
 */
function _ensureDatabaseExists(array $dbCfg): void
{
    $dbName = (string) ($dbCfg['name'] ?? '');
    if ($dbName === '') {
        throw new RuntimeException('Database name is missing in config.php');
    }

    // Restrict to common safe MySQL identifier characters.
    if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
        throw new RuntimeException('Invalid database name in config.php. Use letters, numbers, and underscore only.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $dbCfg['host'],
        $dbCfg['port'] ?? 3306,
        $dbCfg['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

function _runInstall(string $adminUser, string $adminName, string $adminPass): void
{
    $schema = file_get_contents(__DIR__ . '/db/schema.sql');
    // Split on statement boundaries and run each CREATE TABLE.
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
        DB::pdo()->exec($stmt);
    }

    _seedRoles();
    _seedSettings();
    _seedSettingsMeta();
    _seedRooms();
    _seedPersona();
    _seedProvider();

    // Admin user.
    $userId = DB::insert('users', [
        'username'      => $adminUser,
        'display_name'  => $adminName,
        'password_hash' => password_hash($adminPass, PASSWORD_BCRYPT),
        'is_active'     => 1,
    ]);
    $roleId = DB::fetchColumn('SELECT id FROM roles WHERE role_key = "admin"');
    DB::insert('user_roles', ['user_id' => $userId, 'role_id' => $roleId]);
}

function _seedRoles(): void
{
    $roles = [
        ['admin',  'Administrator', 'Full access to all settings and rooms.'],
        ['member', 'Member',        'Can chat and create rooms.'],
        ['viewer', 'Viewer',        'Read-only access.'],
        ['ai',     'AI User',       'Provider-backed bot identity for DM automation.'],
    ];
    foreach ($roles as [$key, $name, $desc]) {
        DB::query(
            'INSERT IGNORE INTO roles (role_key, name, description) VALUES (?, ?, ?)',
            [$key, $name, $desc]
        );
    }
}

function _seedSettings(): void
{
    $rows = [
        // category,  section,    key,                        value,                   type,     description,                                             public, editable, sensitive
        ['app',  'general',  'app.site_name',            'AI Chat',               'string', 'Application display name.',                              1, 1, 0],
        ['app',  'general',  'app.debug_mode',           '0',                     'bool',   'Enable verbose request logging to #log room.',            0, 1, 0],
        ['app',  'general',  'app.version',              '0.1.0',                 'string', 'App version (read-only).',                               0, 0, 0],
        ['ui',   'display',  'ui.theme',                 'dark',                  'string', 'UI colour theme.',                                       1, 1, 0],
        ['ui',   'display',  'ui.font_size',             'large',                 'string', 'Base font size.',                                        1, 1, 0],
        ['auth', 'session',  'auth.remember_me_enabled', '1',                     'bool',   'Allow remember-me cookies.',                             0, 1, 0],
        ['auth', 'session',  'auth.session_lifetime',    '86400',                 'int',    'Session lifetime in seconds.',                           0, 1, 0],
        ['chat', 'behavior', 'chat.default_persona',     'assistant',             'string', 'Default AI persona key.',                                0, 1, 0],
        ['chat', 'behavior', 'chat.max_history',         '50',                    'int',    'Number of messages to load per room.',                   0, 1, 0],
        ['ai',   'provider', 'ai.default_provider',      'local',                 'string', 'Default provider key.',                                  0, 1, 0],
        ['ai',   'provider', 'ai.default_model',         '',                      'string', 'Default model key (blank = provider default).',         0, 1, 0],
        ['ai',   'features', 'ai.dm_enabled',            '1',                     'bool',   'Allow AI in DM rooms.',                                  0, 1, 0],
        ['ai',   'features', 'ai.default_trigger_mode',  'manual',                'string', 'Default AI trigger mode for new rooms.',                 0, 1, 0],
        ['room', 'defaults', 'room.message_page_size',   '50',                    'int',    'Messages loaded per room page.',                         0, 1, 0],
        ['room', 'defaults', 'room.poll_interval_ms',    '3000',                  'int',    'Client polling interval in milliseconds.',               0, 1, 0],
        ['webhook', 'core',  'webhook.enabled',          '1',                     'bool',   'Enable incoming webhook endpoint.',                      0, 1, 0],
        ['webhook', 'core',  'webhook.log_room_default', 'log',                   'string', 'Default room slug for webhook messages.',                0, 1, 0],
        ['memory', 'core',   'memory.enabled',           '0',                     'bool',   'Enable AI memory system (post-MVP).',                    0, 1, 0],
        ['log',  'core',     'log.level',                'error',                 'string', 'Application log level.',                                 0, 1, 0],
        ['provider', 'local', 'provider.local.base_url', 'http://localhost:11434/v1', 'string', 'Base URL for the local provider (Ollama/LM Studio).', 0, 1, 1],
        ['provider', 'local', 'provider.local.api_key',  '',                      'string', 'API key for the local provider (leave blank for Ollama).', 0, 1, 1],
    ];

    foreach ($rows as $r) {
        DB::query(
            'INSERT IGNORE INTO settings
             (category, section, setting_key, setting_value, setting_type, description, is_public, is_editable, is_sensitive)
             VALUES (?,?,?,?,?,?,?,?,?)',
            $r
        );
    }
}

function _seedSettingsMeta(): void
{
    $rows = [
        // key,                          label,                     input_type,  options_json,                                                   help_text,                         sort, tab
        ['app.site_name',           'Site Name',               'text',      null,                                                            null,                              10, 'app'],
        ['app.debug_mode',          'Debug Mode',              'checkbox',  null,                                                            'Logs request GET/POST and routing details to #log.', 11, 'app'],
        ['ui.theme',                'Theme',                   'select',    '{"dark":"Dark","light":"Light"}',                               'Colour theme for all users.',     10, 'ui'],
        ['ui.font_size',            'Font Size',               'select',    '{"small":"Small","medium":"Medium","large":"Large"}',           'Base font size.',                 20, 'ui'],
        ['auth.remember_me_enabled','Allow Remember Me',       'checkbox',  null,                                                            null,                              10, 'auth'],
        ['auth.session_lifetime',   'Session Lifetime (s)',    'text',      null,                                                            'Default 86400 = 1 day.',          20, 'auth'],
        ['chat.default_persona',    'Default Persona Key',     'text',      null,                                                            null,                              10, 'chat'],
        ['chat.max_history',        'Messages Per Room',       'text',      null,                                                            null,                              20, 'chat'],
        ['ai.dm_enabled',           'AI in DMs',               'checkbox',  null,                                                            null,                              10, 'ai'],
        ['ai.default_trigger_mode', 'Default AI Trigger',      'select',    '{"off":"Off","manual":"Manual (@mention)","always":"Always"}',  null,                              20, 'ai'],
        ['room.poll_interval_ms',   'Poll Interval (ms)',      'text',      null,                                                            'How often client polls. 3000=3s.',30, 'chat'],
        ['webhook.enabled',         'Enable Webhooks',         'checkbox',  null,                                                            null,                              10, 'webhook'],
        ['provider.local.base_url', 'Local Provider URL',      'text',      null,                                                            'e.g. http://localhost:11434/v1',  10, 'providers'],
        ['provider.local.api_key',  'Local Provider API Key',  'password',  null,                                                            'Leave blank for Ollama.',         20, 'providers'],
    ];

    foreach ($rows as $r) {
        DB::query(
            'INSERT IGNORE INTO settings_meta (setting_key, label, input_type, options_json, help_text, sort_order, tab_name)
             VALUES (?,?,?,?,?,?,?)',
            $r
        );
    }
}

function _seedRooms(): void
{
    $rooms = [
        ['room_key' => 'general', 'room_type' => 'channel', 'name' => 'general', 'slug' => 'general', 'is_private' => 0],
        ['room_key' => 'log',     'room_type' => 'log',     'name' => 'log',     'slug' => 'log',     'is_private' => 0],
    ];
    foreach ($rooms as $room) {
        DB::query(
            'INSERT IGNORE INTO rooms (room_key, room_type, name, slug, is_private) VALUES (?,?,?,?,?)',
            array_values($room)
        );
    }
}

function _seedPersona(): void
{
    DB::query(
        'INSERT IGNORE INTO personas (persona_key, name, system_prompt, is_enabled, is_default) VALUES (?,?,?,1,1)',
        [
            'assistant',
            'Assistant',
            'You are a helpful, concise assistant running on a local LAN chat system. '
          . 'Be direct and useful. Format code in markdown code blocks.',
        ]
    );

    $extra = [
        [
            'windows-helper',
            'Windows Helper',
            'You are a Windows Helper. Explain Windows troubleshooting in plain English with UI-first directions and minimal jargon. Assume non-technical users may be present and include easy verification steps after each fix.',
        ],
        [
            'sysadmin-light',
            'Sysadmin Light',
            'You are Sysadmin Light. Be server-minded and practical: commands first, minimal theory, clear rollback/safety notes, and concise troubleshooting sequences.',
        ],
        [
            'sales-assistant',
            'Sales Assistant',
            'You are a Sales Assistant. Be friendly, concise, and customer-aware. Help draft polished replies, summarize leads, extract next actions, and keep tone professional.',
        ],
        [
            'log-analyst',
            'Log Analyst',
            'You are a Log Analyst. Read logs carefully, group related errors, surface probable root causes, and provide prioritized next checks with concrete commands/queries.',
        ],
        [
            'teacher-simple',
            'Teacher Simple',
            'You are Teacher Simple. Explain things slowly and clearly with low assumptions, larger step-by-step structure, and short checkpoints to confirm understanding.',
        ],
    ];

    foreach ($extra as [$key, $name, $prompt]) {
        DB::query(
            'INSERT IGNORE INTO personas (persona_key, name, system_prompt, is_enabled, is_default) VALUES (?,?,?,1,0)',
            [$key, $name, $prompt]
        );
    }
}

function _seedProvider(): void
{
    DB::query(
        'INSERT IGNORE INTO ai_providers (provider_key, name, driver, base_url, api_key, model_default, is_enabled, priority)
         VALUES (?,?,?,?,?,?,1,10)',
        ['local', 'Local (Ollama / LM Studio)', 'openai_compat', 'http://localhost:11434/v1', '', 'llama3']
    );
    $providerId = DB::fetchColumn('SELECT id FROM ai_providers WHERE provider_key = "local"');
    DB::query(
        'INSERT IGNORE INTO ai_models (provider_id, model_key, label, context_window, max_tokens, temperature_default, is_enabled)
         VALUES (?,?,?,?,?,?,1)',
        [(int) $providerId, 'llama3', 'Llama 3 (default)', 8192, 2048, 0.70]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AI Chat — Installer</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:#1a1d21;color:#d1d2d3;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;font-size:16px}
  .card{background:#222529;border:1px solid #333;border-radius:8px;padding:2rem;width:100%;max-width:480px}
  h1{font-size:1.5rem;margin-bottom:.25rem;color:#fff}
  p.sub{font-size:.875rem;color:#888;margin-bottom:1.5rem}
  .field{margin-bottom:1rem}
  label{display:block;font-size:.85rem;margin-bottom:.35rem;color:#aaa}
  input{width:100%;padding:.6rem .75rem;background:#1a1d21;border:1px solid #444;border-radius:4px;color:#d1d2d3;font-size:1rem}
  input:focus{outline:none;border-color:#4a9eff}
  .btn{width:100%;padding:.75rem;background:#4a9eff;color:#fff;border:none;border-radius:4px;font-size:1rem;cursor:pointer;margin-top:.5rem}
  .btn:hover{background:#37f}
  .error{background:#3d1a1a;border:1px solid #a33;color:#f88;padding:.75rem;border-radius:4px;margin-bottom:1rem;font-size:.9rem}
  .success{background:#1a3d1a;border:1px solid #3a3;color:#8f8;padding:.75rem;border-radius:4px;margin-bottom:1rem;font-size:.9rem}
  .note{font-size:.8rem;color:#666;margin-top:1.5rem;line-height:1.5}
</style>
</head>
<body>
<div class="card">
  <h1>AI Chat</h1>
  <p class="sub">First-time installer — sets up your database and creates an admin account.</p>

  <?php if ($success): ?>
    <div class="success">
      ✓ Installed successfully! <a href="/ai/" style="color:#8f8">Open the app →</a>
    </div>
  <?php elseif ($error): ?>
    <div class="error"><?= Util::e($error) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="post">
    <div class="field">
      <label>Admin Username</label>
      <input type="text" name="admin_user" value="<?= Util::e(Util::post('admin_user')) ?>" required autocomplete="username">
    </div>
    <div class="field">
      <label>Display Name</label>
      <input type="text" name="admin_name" value="<?= Util::e(Util::post('admin_name')) ?>" required>
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="admin_pass" required autocomplete="new-password">
    </div>
    <div class="field">
      <label>Confirm Password</label>
      <input type="password" name="admin_pass2" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Install →</button>
  </form>
  <p class="note">
    DB settings are read from <code>config.php</code>. After install, that file and
    <code>.installed</code> should both be outside web root in production.
  </p>
  <?php endif; ?>
</div>
</body>
</html>
