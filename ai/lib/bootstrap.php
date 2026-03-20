<?php
/**
 * App bootstrap — loaded by every front-facing page.
 * Connects to DB, starts session, loads all service classes, initialises auth.
 */

define('AI_ROOT', dirname(__DIR__) . '/');
define('AI_LIB',  __DIR__);
define('AI_INSTALLED_FLAG', AI_ROOT . '/.installed');

// Load core utilities first (no dependencies).
require_once AI_LIB . '/db.php';
require_once AI_LIB . '/util.php';

// Load all service classes.
require_once AI_LIB . '/auth.php';
require_once AI_LIB . '/settings.php';
require_once AI_LIB . '/settings_meta.php';
require_once AI_LIB . '/rooms.php';
require_once AI_LIB . '/messages.php';
require_once AI_LIB . '/users.php';
require_once AI_LIB . '/ai_users.php';
require_once AI_LIB . '/personas.php';
require_once AI_LIB . '/ai_provider.php';
require_once AI_LIB . '/permissions.php';
require_once AI_LIB . '/ui.php';
require_once AI_LIB . '/debug_log.php';
require_once AI_LIB . '/memory.php';

// Start session with safe defaults.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Connect to the database.
$_ai_cfg = require AI_ROOT . '/config.php';
try {
    DB::connect($_ai_cfg['db']);
} catch (PDOException $e) {
    // install.php defines AI_INSTALL_MODE to allow graceful failure here.
    if (!defined('AI_INSTALL_MODE')) {
        http_response_code(503);
        die('<h2>Database connection failed.</h2><p>Check config.php then run <a href="/ai/install.php">install.php</a>.</p>');
    }
}
unset($_ai_cfg);

Auth::init();
