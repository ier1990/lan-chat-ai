<?php
/**
 * admin.php — Settings and administration panel. Admin role required.
 */
require_once __DIR__ . '/lib/bootstrap.php';

if (!file_exists(AI_INSTALLED_FLAG)) {
    Util::redirect('/ai/install.php');
}

Auth::requireLogin();
Auth::requireAdmin();

_ensureDebugModeSetting();
_ensureAiUserInfra();

$tab           = Util::get('tab', 'app');
$activeSection = Util::get('section', 'settings');
$standalone    = Util::get('standalone', '0') === '1';
$hideAdminNav  = Util::get('hide_nav', '0') === '1';
$flash         = '';
$flashType     = 'success';

// Handle settings form submit.
if (Util::isPost() && isset($_POST['_save_settings'])) {
    Util::requireCsrf();
    $saved = 0;
    foreach ($_POST['settings'] ?? [] as $key => $value) {
        if (Settings::set((string) $key, (string) $value)) {
            $saved++;
        }
    }
    Settings::flush();
    $flash = "Saved {$saved} setting(s).";
}

if (Util::isPost() && isset($_POST['_users_action'])) {
    Util::requireCsrf();

    $action = Util::post('_users_action');
    $targetUserId = (int) Util::post('user_id');

    if ($action === 'create_user') {
        $username        = Util::post('username');
        $displayName     = Util::post('display_name');
        $password        = Util::post('password');
        $passwordConfirm = Util::post('password_confirm');
        $role            = Util::post('role', 'member');
        $roleKeys        = array_column(Users::getRoles(), 'role_key');

        if ($username === '' || $displayName === '' || $password === '') {
            $flash = 'Username, display name, and password are required.';
            $flashType = 'error';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            $flash = 'Username may only contain letters, numbers, dot, underscore, and dash.';
            $flashType = 'error';
        } elseif (Users::getByUsername($username)) {
            $flash = 'That username already exists.';
            $flashType = 'error';
        } elseif (!in_array($role, $roleKeys, true)) {
            $flash = 'Invalid role selected.';
            $flashType = 'error';
        } elseif (strlen($password) < 8) {
            $flash = 'Password must be at least 8 characters.';
            $flashType = 'error';
        } elseif ($password !== $passwordConfirm) {
            $flash = 'Password confirmation does not match.';
            $flashType = 'error';
        } else {
            Users::create($username, $displayName, $password, $role);
            $flash = 'User created.';
        }
    }

    if ($action === 'change_role') {
        $role = Util::post('role');
        $user = Users::getById($targetUserId);

        if (!$user) {
            $flash = 'User not found.';
            $flashType = 'error';
        } elseif ($targetUserId === Auth::id()) {
            $flash = 'Change your own role from the database or another admin account.';
            $flashType = 'error';
        } elseif ($user['role_key'] === 'admin' && $role !== 'admin' && Users::countByRole('admin') <= 1) {
            $flash = 'You cannot demote the last admin.';
            $flashType = 'error';
        } else {
            Users::setRole($targetUserId, $role);
            $flash = 'Role updated.';
        }
    }

    if ($action === 'reset_password') {
        $user = Users::getById($targetUserId);
        $password = Util::post('new_password');
        $passwordConfirm = Util::post('new_password_confirm');

        if (!$user) {
            $flash = 'User not found.';
            $flashType = 'error';
        } elseif (strlen($password) < 8) {
            $flash = 'New password must be at least 8 characters.';
            $flashType = 'error';
        } elseif ($password !== $passwordConfirm) {
            $flash = 'New password confirmation does not match.';
            $flashType = 'error';
        } else {
            Users::setPassword($targetUserId, $password);
            $flash = 'Password reset.';
        }
    }

    if ($action === 'delete_user') {
        $user = Users::getById($targetUserId);

        if (!$user) {
            $flash = 'User not found.';
            $flashType = 'error';
        } elseif ($targetUserId === Auth::id()) {
            $flash = 'You cannot delete your own account.';
            $flashType = 'error';
        } elseif ($user['role_key'] === 'admin' && Users::countByRole('admin') <= 1) {
            $flash = 'You cannot delete the last admin.';
            $flashType = 'error';
        } else {
            Users::delete($targetUserId);
            $flash = 'User deleted.';
        }
    }
}

if (Util::isPost() && isset($_POST['_provider_action'])) {
    Util::requireCsrf();

    $action = Util::post('_provider_action');

    if ($action === 'refresh_models') {
        $providerId = (int) Util::post('provider_id');
        $provider = DB::fetch('SELECT * FROM ai_providers WHERE id = ?', [$providerId]);

        if (!$provider) {
            $flash = 'Provider not found.';
            $flashType = 'error';
        } else {
            try {
                $sync = AiProvider::syncProviderModels($provider);
                $flash = 'Models refreshed from provider URL. Found '
                    . $sync['total']
                    . ', added '
                    . $sync['added']
                    . '.';
                if (!empty($sync['default_model'])) {
                    $flash .= ' Default set to ' . $sync['default_model'] . '.';
                }
            } catch (Throwable $e) {
                $flash = 'Model refresh failed: ' . $e->getMessage();
                $flashType = 'error';
            }
        }
    }

    if ($action === 'set_default_model') {
        $providerId = (int) Util::post('provider_id');
        $modelKey   = Util::post('model_key');

        $provider = DB::fetch('SELECT * FROM ai_providers WHERE id = ?', [$providerId]);
        if (!$provider) {
            $flash = 'Provider not found.';
            $flashType = 'error';
        } else {
            $model = DB::fetch(
                'SELECT id FROM ai_models WHERE provider_id = ? AND model_key = ?',
                [$providerId, $modelKey]
            );
            if (!$model) {
                $flash = 'Selected model does not belong to this provider.';
                $flashType = 'error';
            } else {
                DB::update('ai_providers', ['model_default' => $modelKey], 'id = ?', [$providerId]);
                $flash = 'Default model updated.';
            }
        }
    }
}

if (Util::isPost() && isset($_POST['_ai_users_action'])) {
    Util::requireCsrf();

    $action = Util::post('_ai_users_action');
    $targetUserId = (int) Util::post('user_id');

    if ($action === 'create_ai_user') {
        $username    = Util::post('username');
        $displayName = Util::post('display_name');
        $baseUrl     = Util::post('base_url');
        $modelKey    = Util::post('model_default');
        $apiKey      = Util::post('api_key');
        $providerKey = Util::post('provider_key', 'openai_compat');
        $personaId   = (int) Util::post('persona_id');
        $httpReferer = Util::post('http_referer');
        $xTitle      = Util::post('x_title');
        $headersJson = Util::post('headers_json');
        $headersData = AiUsers::decodeHeaders($headersJson);

        if ($httpReferer !== '') {
            $headersData['HTTP-Referer'] = $httpReferer;
        }
        if ($xTitle !== '') {
            $headersData['X-Title'] = $xTitle;
        }

        if ($username === '' || $displayName === '' || $baseUrl === '' || $modelKey === '' || $apiKey === '') {
            $flash = 'Username, display name, endpoint URL, model, and API key are required.';
            $flashType = 'error';
        } elseif ($headersJson !== '' && Util::jsonDecode($headersJson) === null && strtolower(trim($headersJson)) !== 'null') {
            $flash = 'Extra headers must be valid JSON.';
            $flashType = 'error';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            $flash = 'Username may only contain letters, numbers, dot, underscore, and dash.';
            $flashType = 'error';
        } elseif (Users::getByUsername($username)) {
            $flash = 'That username already exists.';
            $flashType = 'error';
        } else {
            $userId = Users::create($username, $displayName, $apiKey, 'ai');
            AiUsers::upsertConfig($userId, [
                'provider_key'  => $providerKey,
                'base_url'      => $baseUrl,
                'api_key'       => $apiKey,
                'model_default' => $modelKey,
                'persona_id'    => $personaId ?: null,
                'headers_json'  => AiUsers::encodeHeaders($headersData),
                'is_enabled'    => 1,
            ]);
            $flash = 'AI user created.';
        }
    }

    if ($action === 'save_ai_user') {
        $user = Users::getById($targetUserId);
        $baseUrl     = Util::post('base_url');
        $modelKey    = Util::post('model_default');
        $apiKey      = Util::post('api_key');
        $providerKey = Util::post('provider_key', 'openai_compat');
        $personaId   = (int) Util::post('persona_id');
        $isEnabled   = Util::post('is_enabled', '0') === '1' ? 1 : 0;
        $httpReferer = Util::post('http_referer');
        $xTitle      = Util::post('x_title');
        $headersJson = Util::post('headers_json');
        $headersData = AiUsers::decodeHeaders($headersJson);

        if ($httpReferer !== '') {
            $headersData['HTTP-Referer'] = $httpReferer;
        }
        if ($xTitle !== '') {
            $headersData['X-Title'] = $xTitle;
        }

        if (!$user || $user['role_key'] !== 'ai') {
            $flash = 'AI user not found.';
            $flashType = 'error';
        } elseif ($baseUrl === '' || $modelKey === '') {
            $flash = 'Endpoint URL and model are required.';
            $flashType = 'error';
        } elseif ($headersJson !== '' && Util::jsonDecode($headersJson) === null && strtolower(trim($headersJson)) !== 'null') {
            $flash = 'Extra headers must be valid JSON.';
            $flashType = 'error';
        } else {
            $cfgData = [
                'provider_key'  => $providerKey,
                'base_url'      => $baseUrl,
                'model_default' => $modelKey,
                'persona_id'    => $personaId ?: null,
                'headers_json'  => AiUsers::encodeHeaders($headersData),
                'is_enabled'    => $isEnabled,
            ];
            if ($apiKey !== '') {
                $cfgData['api_key'] = $apiKey;
                Users::setPassword($targetUserId, $apiKey);
            }
            AiUsers::upsertConfig($targetUserId, $cfgData);

            $flash = 'AI user config updated.';
        }
    }
}

if (Util::isPost() && isset($_POST['_rooms_action'])) {
    Util::requireCsrf();

    $action = Util::post('_rooms_action');
    $roomId = (int) Util::post('room_id');

    if ($action === 'create_dm_room') {
        $targetType = Util::post('dm_target_type', 'user');
        $targetId = (int) Util::post('dm_target_id');

        if (!in_array($targetType, ['user', 'persona'], true) || $targetId <= 0) {
            $flash = 'Select a valid DM target.';
            $flashType = 'error';
        } elseif ($targetType === 'user' && !Users::getById($targetId)) {
            $flash = 'Selected user not found.';
            $flashType = 'error';
        } elseif ($targetType === 'persona' && !Personas::getById($targetId)) {
            $flash = 'Selected persona not found.';
            $flashType = 'error';
        } else {
            $newRoomId = Rooms::createDm((int) Auth::id(), $targetType, $targetId);
            $newRoom = Rooms::getById($newRoomId);
            $flash = 'DM room ready: ' . ($newRoom['name'] ?? ('Room #' . $newRoomId));
        }
    }

    if ($action === 'create_channel') {
        $name = Util::post('name');
        $slugInput = Util::post('slug');
        $isPrivate = Util::post('is_private', '0') === '1' ? 1 : 0;
        $aiEnabled = Util::post('ai_enabled', '0') === '1';
        $aiTrigger = Util::post('ai_trigger_mode', 'mention');

        if ($name === '') {
            $flash = 'Room name is required.';
            $flashType = 'error';
        } else {
            $slug = $slugInput !== '' ? Util::slug($slugInput) : Util::slug($name);
            if ($slug === '') {
                $flash = 'Invalid slug.';
                $flashType = 'error';
            } elseif (Rooms::getBySlug($slug)) {
                $flash = 'That slug is already in use.';
                $flashType = 'error';
            } else {
                $settings = [
                    'ai_enabled' => $aiEnabled,
                    'ai_trigger_mode' => in_array($aiTrigger, ['off', 'mention', 'always'], true) ? $aiTrigger : 'mention',
                ];

                Rooms::create([
                    'room_type' => 'channel',
                    'name' => $name,
                    'slug' => $slug,
                    'is_private' => $isPrivate,
                    'created_by' => (int) Auth::id(),
                    'settings_json' => Util::jsonEncode($settings),
                ]);

                $flash = 'Channel created.';
            }
        }
    }

    if ($action === 'save_room') {
        $room = Rooms::getById($roomId);
        $name = Util::post('name');
        $slugInput = Util::post('slug');
        $isPrivate = Util::post('is_private', '0') === '1' ? 1 : 0;
        $aiEnabled = Util::post('ai_enabled', '0') === '1';
        $aiTrigger = Util::post('ai_trigger_mode', 'mention');

        if (!$room) {
            $flash = 'Room not found.';
            $flashType = 'error';
        } elseif ($name === '' || $slugInput === '') {
            $flash = 'Name and slug are required.';
            $flashType = 'error';
        } else {
            $slug = Util::slug($slugInput);
            $slugOwner = DB::fetch('SELECT id FROM rooms WHERE slug = ? AND id <> ?', [$slug, $roomId]);
            if ($slug === '') {
                $flash = 'Invalid slug.';
                $flashType = 'error';
            } elseif ($slugOwner) {
                $flash = 'That slug is already in use.';
                $flashType = 'error';
            } else {
                $settings = Rooms::settings($roomId);
                $settings['ai_enabled'] = $aiEnabled;
                $settings['ai_trigger_mode'] = in_array($aiTrigger, ['off', 'mention', 'always'], true) ? $aiTrigger : 'mention';

                DB::update('rooms', [
                    'name' => $name,
                    'slug' => $slug,
                    'is_private' => $room['room_type'] === 'dm' ? 1 : $isPrivate,
                    'settings_json' => Util::jsonEncode($settings),
                ], 'id = ?', [$roomId]);

                $flash = 'Room updated.';
            }
        }
    }

    if ($action === 'create_or_rotate_webhook') {
        $room = Rooms::getById($roomId);
        $webhookName = Util::post('webhook_name');
        $sourceType = Util::post('source_type', 'generic');
        $isEnabled = Util::post('webhook_enabled', '1') === '1' ? 1 : 0;

        if (!$room) {
            $flash = 'Room not found.';
            $flashType = 'error';
        } else {
            $key = Util::token(24);
            $existing = DB::fetch(
                'SELECT * FROM webhook_sources WHERE target_room_id = ? ORDER BY id ASC LIMIT 1',
                [$roomId]
            );

            if ($existing) {
                DB::update('webhook_sources', [
                    'name' => $webhookName !== '' ? $webhookName : ('Room ' . $room['name'] . ' Hook'),
                    'source_type' => $sourceType !== '' ? $sourceType : 'generic',
                    'webhook_key' => $key,
                    'is_enabled' => $isEnabled,
                ], 'id = ?', [(int) $existing['id']]);
                $flash = 'Webhook key rotated.';
            } else {
                DB::insert('webhook_sources', [
                    'name' => $webhookName !== '' ? $webhookName : ('Room ' . $room['name'] . ' Hook'),
                    'source_type' => $sourceType !== '' ? $sourceType : 'generic',
                    'webhook_key' => $key,
                    'target_room_id' => $roomId,
                    'is_enabled' => $isEnabled,
                ]);
                $flash = 'Webhook key created.';
            }
        }
    }

    if ($action === 'delete_webhook') {
        $hookId = (int) Util::post('webhook_id');
        $hook = DB::fetch('SELECT id FROM webhook_sources WHERE id = ? AND target_room_id = ?', [$hookId, $roomId]);
        if (!$hook) {
            $flash = 'Webhook not found.';
            $flashType = 'error';
        } else {
            DB::query('DELETE FROM webhook_sources WHERE id = ?', [$hookId]);
            $flash = 'Webhook deleted.';
        }
    }
}

if (Util::isPost() && isset($_POST['_persona_action'])) {
    Util::requireCsrf();

    $action = Util::post('_persona_action');
    $personaId = (int) Util::post('persona_id');

    if ($action === 'create_persona' || $action === 'save_persona') {
        $personaKey   = strtolower(Util::post('persona_key'));
        $name         = Util::post('name');
        $systemPrompt = trim((string) ($_POST['system_prompt'] ?? ''));
        $styleNotes   = trim((string) ($_POST['style_notes'] ?? ''));
        $settingsJson = trim((string) ($_POST['settings_json'] ?? ''));
        $isEnabled    = Util::post('is_enabled', '0') === '1' ? 1 : 0;
        $isDefault    = Util::post('is_default', '0') === '1' ? 1 : 0;

        if ($personaKey === '' || $name === '' || $systemPrompt === '') {
            $flash = 'Persona key, name, and system prompt are required.';
            $flashType = 'error';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $personaKey)) {
            $flash = 'Persona key must start with a letter/number and use only lowercase letters, numbers, dot, underscore, or dash.';
            $flashType = 'error';
        } elseif ($settingsJson !== '' && Util::jsonDecode($settingsJson) === null && strtolower($settingsJson) !== 'null') {
            $flash = 'Settings JSON must be valid JSON.';
            $flashType = 'error';
        } else {
            if ($action === 'create_persona') {
                if (Personas::getByKey($personaKey)) {
                    $flash = 'That persona key already exists.';
                    $flashType = 'error';
                } else {
                    if ($isDefault) {
                        DB::query('UPDATE personas SET is_default = 0 WHERE is_default = 1');
                    }

                    DB::insert('personas', [
                        'persona_key'   => $personaKey,
                        'name'          => $name,
                        'system_prompt' => $systemPrompt,
                        'style_notes'   => $styleNotes !== '' ? $styleNotes : null,
                        'is_enabled'    => $isEnabled,
                        'is_default'    => $isDefault,
                        'settings_json' => $settingsJson !== '' ? $settingsJson : null,
                        'updated_at'    => Util::now(),
                    ]);
                    $flash = 'Persona created.';
                }
            }

            if ($action === 'save_persona') {
                $existing = Personas::getById($personaId);
                if (!$existing) {
                    $flash = 'Persona not found.';
                    $flashType = 'error';
                } else {
                    $conflict = DB::fetch(
                        'SELECT id FROM personas WHERE persona_key = ? AND id <> ?',
                        [$personaKey, $personaId]
                    );

                    if ($conflict) {
                        $flash = 'That persona key is already in use.';
                        $flashType = 'error';
                    } else {
                        if ($isDefault) {
                            DB::query('UPDATE personas SET is_default = 0 WHERE is_default = 1 AND id <> ?', [$personaId]);
                        }

                        DB::update('personas', [
                            'persona_key'   => $personaKey,
                            'name'          => $name,
                            'system_prompt' => $systemPrompt,
                            'style_notes'   => $styleNotes !== '' ? $styleNotes : null,
                            'is_enabled'    => $isEnabled,
                            'is_default'    => $isDefault,
                            'settings_json' => $settingsJson !== '' ? $settingsJson : null,
                            'updated_at'    => Util::now(),
                        ], 'id = ?', [$personaId]);
                        $flash = 'Persona updated.';
                    }
                }
            }
        }
    }

    if ($action === 'delete_persona') {
        $existing = Personas::getById($personaId);
        if (!$existing) {
            $flash = 'Persona not found.';
            $flashType = 'error';
        } else {
            DB::query('DELETE FROM personas WHERE id = ?', [$personaId]);
            $flash = 'Persona deleted.';
        }
    }

    if ($action === 'create_persona_example') {
        $exampleKey = Util::post('example_key');
        $examples = _personaExamples();
        $example = $examples[$exampleKey] ?? null;

        if (!$example) {
            $flash = 'Example not found.';
            $flashType = 'error';
        } else {
            $baseKey = strtolower((string) $example['persona_key']);
            $candidateKey = $baseKey;
            $n = 2;
            while (Personas::getByKey($candidateKey)) {
                $candidateKey = $baseKey . '-' . $n;
                $n++;
            }

            DB::insert('personas', [
                'persona_key'   => $candidateKey,
                'name'          => (string) $example['name'],
                'system_prompt' => (string) $example['system_prompt'],
                'style_notes'   => (string) ($example['style_notes'] ?? ''),
                'is_enabled'    => 1,
                'is_default'    => 0,
                'settings_json' => isset($example['settings_json']) ? Util::jsonEncode($example['settings_json']) : null,
                'updated_at'    => Util::now(),
            ]);

            $flash = 'Example persona "' . $example['name'] . '" created as key ' . $candidateKey . '.';
        }
    }

    if ($action === 'import_personas_json') {
        $payload = trim((string) ($_POST['import_json'] ?? ''));
        $replaceExisting = Util::post('replace_existing', '0') === '1';

        if ($payload === '') {
            $flash = 'Import JSON is required.';
            $flashType = 'error';
        } else {
            $decoded = Util::jsonDecode($payload);
            if ($decoded === null) {
                $flash = 'Import JSON is invalid.';
                $flashType = 'error';
            } else {
                try {
                    $result = _importPersonasPayload($decoded, $replaceExisting);
                    $flash = 'Persona import complete. Added '
                        . $result['created']
                        . ', updated '
                        . $result['updated']
                        . ', skipped '
                        . $result['skipped']
                        . '.';
                } catch (RuntimeException $e) {
                    $flash = 'Persona import failed: ' . $e->getMessage();
                    $flashType = 'error';
                }
            }
        }
    }
}

$personaExport = Util::get('persona_export');
if ($activeSection === 'personas' && $personaExport !== '') {
    $exportPayload = null;
    $filename = 'personas-export.json';

    if ($personaExport === 'all') {
        $rows = DB::fetchAll('SELECT * FROM personas ORDER BY name ASC');
        $exportPayload = [
            'version' => 1,
            'exported_at' => gmdate('c'),
            'personas' => array_map('_normalizePersonaRow', $rows),
        ];
    } else {
        $persona = Personas::getById((int) $personaExport);
        if ($persona) {
            $exportPayload = _normalizePersonaRow($persona);
            $filename = 'persona-' . $persona['persona_key'] . '.json';
        }
    }

    if ($exportPayload === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Persona export not found.';
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
    echo json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rooms    = Rooms::getAll();
$roomWebhooksRows = DB::fetchAll('SELECT * FROM webhook_sources ORDER BY id DESC');
$roomWebhooks = [];
foreach ($roomWebhooksRows as $hook) {
    $rid = (int) $hook['target_room_id'];
    if (!isset($roomWebhooks[$rid])) {
        $roomWebhooks[$rid] = $hook;
    }
}
$roomDmMeta = _buildDmRoomMeta($rooms);
$webhookBaseUrl = _detectAdminBaseUrl() . '/webhook.php?key=';
$users    = Users::getAll(false);
$aiUsers  = DB::fetchAll(
    'SELECT u.id, u.username, u.display_name, u.is_active,
            COALESCE(r.role_key, "member") AS role_key,
            auc.provider_key, auc.base_url, auc.model_default, auc.api_key,
            auc.persona_id, auc.headers_json, auc.is_enabled,
            p.name AS persona_name
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     LEFT JOIN ai_user_configs auc ON auc.user_id = u.id
     LEFT JOIN personas p ON p.id = auc.persona_id
     WHERE COALESCE(r.role_key, "member") = "ai"
     ORDER BY u.display_name'
);
$roles    = Users::getRoles();
$personas = Personas::getAll(false);
$personasAdmin = DB::fetchAll(
    'SELECT p.*, COUNT(auc.user_id) AS ai_user_count
     FROM personas p
     LEFT JOIN ai_user_configs auc ON auc.persona_id = p.id
     GROUP BY p.id
     ORDER BY p.name ASC'
);
$personaExamples = _personaExamples();
$providers = DB::fetchAll('SELECT * FROM ai_providers ORDER BY priority DESC');
$models    = DB::fetchAll('SELECT m.*, p.name AS provider_name FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id ORDER BY p.priority DESC, m.label');

$settingsTabs = SettingsMeta::getTabs();
$tabFields    = $tab && in_array($tab, $settingsTabs, true) ? SettingsMeta::getByTab($tab) : [];

$title = 'Admin — ' . Settings::get('app.site_name', 'AI Chat');
$view  = 'admin';

require __DIR__ . '/view/layout.php';

function _ensureDebugModeSetting(): void
{
    DB::query(
        'INSERT IGNORE INTO settings
         (category, section, setting_key, setting_value, setting_type, description, is_public, is_editable, is_sensitive)
         VALUES (?,?,?,?,?,?,?,?,?)',
        ['app', 'general', 'app.debug_mode', '0', 'bool', 'Log request routing/debug details to #log room.', 0, 1, 0]
    );

    DB::query(
        'INSERT IGNORE INTO settings_meta (setting_key, label, input_type, options_json, help_text, sort_order, tab_name)
         VALUES (?,?,?,?,?,?,?)',
        ['app.debug_mode', 'Debug Mode', 'checkbox', null, 'When enabled, request GET/POST and routing info is logged to #log.', 11, 'app']
    );
}

function _ensureAiUserInfra(): void
{
    DB::query(
        'INSERT IGNORE INTO roles (role_key, name, description) VALUES (?, ?, ?)',
        ['ai', 'AI User', 'Provider-backed bot identity for DM automation.']
    );

    DB::query(
        "CREATE TABLE IF NOT EXISTS ai_user_configs (
            user_id       INT UNSIGNED PRIMARY KEY,
            provider_key  VARCHAR(60)  NOT NULL DEFAULT 'openai_compat',
            base_url      VARCHAR(255) NOT NULL,
            api_key       TEXT,
            model_default VARCHAR(120) NOT NULL,
            persona_id    INT UNSIGNED,
            headers_json  TEXT,
            is_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
            settings_json TEXT,
            updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    AiUsers::resetTableCache();
}

function _personaExamples(): array
{
    return [
        'support_pro' => [
            'persona_key' => 'support-pro',
            'name' => 'Support Pro',
            'system_prompt' => 'You are a senior customer support engineer. Diagnose clearly, ask concise clarifying questions, and always provide step-by-step remediation options with likely root cause first.',
            'style_notes' => 'Calm, practical, no fluff, checklists preferred.',
            'settings_json' => ['temperature' => 0.2, 'max_tokens' => 1000],
        ],
        'php_mentor' => [
            'persona_key' => 'php-mentor',
            'name' => 'PHP Mentor',
            'system_prompt' => 'You are a pragmatic PHP mentor. Prefer simple, readable PHP 8.2 code, explain tradeoffs, and include safe defaults for security, validation, and error handling.',
            'style_notes' => 'Teaching tone with short examples and before/after snippets.',
            'settings_json' => ['temperature' => 0.35, 'max_tokens' => 1400],
        ],
        'bug_hunter' => [
            'persona_key' => 'bug-hunter',
            'name' => 'Bug Hunter',
            'system_prompt' => 'You are a ruthless debugging specialist. Start from symptoms, isolate variables, reproduce quickly, then propose minimal high-confidence fixes and verification steps.',
            'style_notes' => 'Direct and investigative. Prioritize likely causes by probability.',
            'settings_json' => ['temperature' => 0.15, 'max_tokens' => 900],
        ],
        'release_manager' => [
            'persona_key' => 'release-manager',
            'name' => 'Release Manager',
            'system_prompt' => 'You manage release readiness. Enforce risk checks, rollback plans, migration safety, and release notes quality. Highlight blockers and go/no-go decisions.',
            'style_notes' => 'Structured, checklist-heavy, risk-first communication.',
            'settings_json' => ['temperature' => 0.25, 'max_tokens' => 1200],
        ],
        'docs_writer' => [
            'persona_key' => 'docs-writer',
            'name' => 'Docs Writer',
            'system_prompt' => 'You are a technical documentation specialist. Convert implementation details into crisp docs with quick-start, prerequisites, examples, and troubleshooting sections.',
            'style_notes' => 'Readable, skimmable, headings + examples + caveats.',
            'settings_json' => ['temperature' => 0.45, 'max_tokens' => 1600],
        ],
        'security_guard' => [
            'persona_key' => 'security-guard',
            'name' => 'Security Guard',
            'system_prompt' => 'You are an application security reviewer. Identify input validation gaps, auth/authz weaknesses, sensitive data exposure, and recommend practical mitigations.',
            'style_notes' => 'Defensive mindset, severity labels, concise mitigations.',
            'settings_json' => ['temperature' => 0.2, 'max_tokens' => 1100],
        ],
        'product_copilot' => [
            'persona_key' => 'product-copilot',
            'name' => 'Product Copilot',
            'system_prompt' => 'You are a product-thinking assistant for engineering teams. Translate requests into scope, user impact, edge cases, and measurable acceptance criteria.',
            'style_notes' => 'Outcome-focused, clear assumptions, concise specs.',
            'settings_json' => ['temperature' => 0.5, 'max_tokens' => 1300],
        ],
        'creative_brainstorm' => [
            'persona_key' => 'creative-brainstorm',
            'name' => 'Creative Brainstorm',
            'system_prompt' => 'You are a creative ideation partner. Generate diverse, actionable ideas with pros/cons, bold options, and fast experiments to validate each direction.',
            'style_notes' => 'Energetic and varied, but always actionable.',
            'settings_json' => ['temperature' => 0.8, 'max_tokens' => 1400],
        ],
    ];
}

function _normalizePersonaRow(array $row): array
{
    return [
        'persona_key'   => (string) ($row['persona_key'] ?? ''),
        'name'          => (string) ($row['name'] ?? ''),
        'system_prompt' => (string) ($row['system_prompt'] ?? ''),
        'style_notes'   => $row['style_notes'] !== null ? (string) $row['style_notes'] : '',
        'is_enabled'    => !empty($row['is_enabled']) ? 1 : 0,
        'is_default'    => !empty($row['is_default']) ? 1 : 0,
        'settings_json' => Util::jsonDecode((string) ($row['settings_json'] ?? '')),
    ];
}

function _importPersonasPayload(mixed $payload, bool $replaceExisting): array
{
    if (is_array($payload) && array_key_exists('personas', $payload) && is_array($payload['personas'])) {
        $items = $payload['personas'];
    } elseif (is_array($payload) && array_is_list($payload)) {
        $items = $payload;
    } elseif (is_array($payload)) {
        $items = [$payload];
    } else {
        throw new RuntimeException('Expected a persona object or personas array.');
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $defaultPersonaId = null;

    foreach ($items as $item) {
        if (!is_array($item)) {
            $skipped++;
            continue;
        }

        $personaKey = strtolower(trim((string) ($item['persona_key'] ?? '')));
        $name = trim((string) ($item['name'] ?? ''));
        $systemPrompt = trim((string) ($item['system_prompt'] ?? ''));

        if ($personaKey === '' || $name === '' || $systemPrompt === '') {
            $skipped++;
            continue;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $personaKey)) {
            $skipped++;
            continue;
        }

        $row = [
            'persona_key'   => $personaKey,
            'name'          => $name,
            'system_prompt' => $systemPrompt,
            'style_notes'   => trim((string) ($item['style_notes'] ?? '')) ?: null,
            'is_enabled'    => !empty($item['is_enabled']) ? 1 : 0,
            'is_default'    => !empty($item['is_default']) ? 1 : 0,
            'settings_json' => array_key_exists('settings_json', $item)
                ? Util::jsonEncode($item['settings_json'])
                : null,
            'updated_at'    => Util::now(),
        ];

        $existing = Personas::getByKey($personaKey);
        if ($existing) {
            if (!$replaceExisting) {
                $skipped++;
                continue;
            }

            DB::update('personas', $row, 'id = ?', [(int) $existing['id']]);
            $updated++;
            if ($row['is_default']) {
                $defaultPersonaId = (int) $existing['id'];
            }
            continue;
        }

        DB::insert('personas', $row);
        $created++;
        if ($row['is_default']) {
            $defaultPersonaId = (int) DB::fetchColumn('SELECT id FROM personas WHERE persona_key = ?', [$personaKey]);
        }
    }

    if ($defaultPersonaId) {
        DB::query('UPDATE personas SET is_default = 0 WHERE id <> ?', [$defaultPersonaId]);
        DB::query('UPDATE personas SET is_default = 1 WHERE id = ?', [$defaultPersonaId]);
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
    ];
}

function _buildDmRoomMeta(array $rooms): array
{
    $dmRoomIds = [];
    foreach ($rooms as $room) {
        if (($room['room_type'] ?? '') === 'dm') {
            $dmRoomIds[] = (int) $room['id'];
        }
    }
    if (!$dmRoomIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($dmRoomIds), '?'));
    $rows = DB::fetchAll(
        "SELECT rp.room_id, rp.participant_type, rp.participant_id,
                u.display_name AS user_name,
                p.name AS persona_name
         FROM room_participants rp
         LEFT JOIN users u
           ON rp.participant_type = 'user' AND u.id = rp.participant_id
         LEFT JOIN personas p
           ON rp.participant_type = 'persona' AND p.id = rp.participant_id
         WHERE rp.room_id IN ($placeholders)",
        $dmRoomIds
    );

    $out = [];
    foreach ($dmRoomIds as $id) {
        $out[$id] = [
            'label' => 'DM',
            'detail' => '',
        ];
    }

    $usersByRoom = [];
    $personasByRoom = [];
    foreach ($rows as $row) {
        $rid = (int) $row['room_id'];
        if ($row['participant_type'] === 'user') {
            $usersByRoom[$rid][] = [
                'id' => (int) $row['participant_id'],
                'name' => (string) ($row['user_name'] ?? ('User ' . $row['participant_id'])),
            ];
        }
        if ($row['participant_type'] === 'persona') {
            $personasByRoom[$rid][] = (string) ($row['persona_name'] ?? ('Persona ' . $row['participant_id']));
        }
    }

    foreach ($dmRoomIds as $rid) {
        $users = $usersByRoom[$rid] ?? [];
        $personas = $personasByRoom[$rid] ?? [];

        if ($personas) {
            $out[$rid]['label'] = 'DM AI';
            $out[$rid]['detail'] = implode(', ', array_unique($personas));
            continue;
        }

        if ($users) {
            $userIds = array_values(array_unique(array_map(fn($u) => (int) $u['id'], $users)));
            $userNames = array_values(array_unique(array_map(fn($u) => (string) $u['name'], $users)));

            if (count($userIds) === 1) {
                $out[$rid]['label'] = 'DM Self';
                $out[$rid]['detail'] = $userNames[0] ?? '';
            } else {
                $out[$rid]['label'] = 'DM';
                $out[$rid]['detail'] = implode(' ↔ ', $userNames);
            }
        }
    }

    return $out;
}

function _detectAdminBaseUrl(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '/ai';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/ai/admin.php')));
    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir === '' || $scriptDir === '.') {
        $scriptDir = '/ai';
    }

    return rtrim($scheme . '://' . $host . $scriptDir, '/');
}
