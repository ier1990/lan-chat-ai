<?php
/**
 * admin/index.php — Admin panel entry point.
 *
 * URL: /ai/admin/
 * All POST actions are dispatched to handlers/, all views live in views/.
 * Password-protect this directory with .htaccess + .htpasswd as needed.
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if (!file_exists(AI_INSTALLED_FLAG)) {
    Util::redirect('/ai/install.php');
}

Auth::requireLogin();
Auth::requireAdmin();

require_once __DIR__ . '/helpers.php';

_ensureDebugModeSetting();
_ensureAiUserInfra();
_ensureDefaultPersonas();
Memory::ensureTable();

$tab           = Util::get('tab', 'app');
$activeSection = Util::get('section', 'settings');
$standalone    = true; // admin/ is always its own page
$flash         = '';
$flashType     = 'success';

// ── POST dispatch ─────────────────────────────────────────────────────────────

if (Util::isPost()) {
    Util::requireCsrf();

    if (isset($_POST['_save_settings'])) {
        require_once __DIR__ . '/handlers/settings.php';
        [$flash, $flashType] = _handleSettings();
    } elseif (isset($_POST['_users_action'])) {
        require_once __DIR__ . '/handlers/users.php';
        [$flash, $flashType] = _handleUsers();
    } elseif (isset($_POST['_provider_action'])) {
        require_once __DIR__ . '/handlers/providers.php';
        [$flash, $flashType] = _handleProviders();
    } elseif (isset($_POST['_ai_users_action'])) {
        require_once __DIR__ . '/handlers/ai_users.php';
        [$flash, $flashType] = _handleAiUsers();
    } elseif (isset($_POST['_rooms_action'])) {
        require_once __DIR__ . '/handlers/rooms.php';
        [$flash, $flashType] = _handleRooms();
    } elseif (isset($_POST['_persona_action'])) {
        require_once __DIR__ . '/handlers/personas.php';
        [$flash, $flashType] = _handlePersonas();
    } elseif (isset($_POST['_memory_action'])) {
        require_once __DIR__ . '/handlers/memory.php';
        [$flash, $flashType] = _handleMemory();
    }
}

// ── Persona export (GET) ──────────────────────────────────────────────────────

$personaExport = Util::get('persona_export');
if ($activeSection === 'personas' && $personaExport !== '') {
    require_once __DIR__ . '/handlers/personas.php';
    _handlePersonaExport($personaExport);
    // exits inside
}

// ── Data loading ──────────────────────────────────────────────────────────────

$rooms            = Rooms::getAll();
$roomWebhooksRows = DB::fetchAll('SELECT * FROM webhook_sources ORDER BY id DESC');
$roomWebhooks     = [];
foreach ($roomWebhooksRows as $hook) {
    $rid = (int) $hook['target_room_id'];
    if (!isset($roomWebhooks[$rid])) {
        $roomWebhooks[$rid] = $hook;
    }
}
$roomDmMeta     = _buildDmRoomMeta($rooms);
$webhookBaseUrl = _detectWebhookBaseUrl();

$users   = Users::getAll(false);
$aiUsers = DB::fetchAll(
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

$roles         = Users::getRoles();
$personas      = Personas::getAll(false);
$personasAdmin = DB::fetchAll(
    'SELECT p.*, COUNT(auc.user_id) AS ai_user_count
     FROM personas p
     LEFT JOIN ai_user_configs auc ON auc.persona_id = p.id
     GROUP BY p.id
     ORDER BY p.name ASC'
);
$personaExamples = _personaExamples();

$providers = DB::fetchAll('SELECT * FROM ai_providers ORDER BY priority DESC');
$models    = DB::fetchAll(
    'SELECT m.*, p.name AS provider_name
     FROM ai_models m
     JOIN ai_providers p ON p.id = m.provider_id
     ORDER BY p.priority DESC, m.label'
);

$settingsTabs = SettingsMeta::getTabs();
$tabFields    = ($tab && in_array($tab, $settingsTabs, true)) ? SettingsMeta::getByTab($tab) : [];
$allMemories  = ($activeSection === 'memories') ? Memory::getAll() : [];

// ── URL builder available to all views ───────────────────────────────────────

$buildAdminUrl = static function (array $extra = []) use ($tab): string {
    $params = $extra;
    // Preserve tab when staying in settings section.
    if (!isset($params['tab']) && isset($params['section']) && $params['section'] === 'settings') {
        $params['tab'] = $tab;
    }
    return '/ai/admin/?' . http_build_query($params);
};

// ── Render ────────────────────────────────────────────────────────────────────

$title = 'Admin — ' . Settings::get('app.site_name', 'AI Chat');

require __DIR__ . '/views/layout.php';
