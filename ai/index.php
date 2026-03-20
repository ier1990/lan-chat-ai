<?php
/**
 * index.php — Main entry point.
 * Shows login form or the chat app depending on auth state.
 */
require_once __DIR__ . '/lib/bootstrap.php';

// Enforce install.
if (!file_exists(AI_INSTALLED_FLAG)) {
    Util::redirect('/ai/install.php');
}

// Show login view if not authenticated.
if (!Auth::check()) {
    $loginError = '';
    if (Util::isPost() && isset($_POST['_login'])) {
        if (!Util::csrfVerify(Util::post('csrf'))) {
            $loginError = 'Invalid request.';
        } elseif (!Auth::attempt(Util::post('username'), Util::post('password'))) {
            $loginError = 'Invalid username or password.';
        } else {
            Util::redirect('/ai/');
        }
    }
    require __DIR__ . '/view/login.php';
    exit;
}

// Load rooms visible to the current user.
$rooms = Rooms::forUser(Auth::id());

// Pick the active room (from query string, session, or first available).
$currentRoomSlug = Util::get('room', (string) ($_SESSION['last_room'] ?? ''));
$currentRoom     = null;

if ($currentRoomSlug) {
    $currentRoom = Rooms::getBySlug($currentRoomSlug);
}
if (!$currentRoom && $rooms) {
    $currentRoom = $rooms[0];
}

if ($currentRoom) {
    $_SESSION['last_room'] = $currentRoom['slug'];
    $messages              = Messages::forRoom(
        (int) $currentRoom['id'],
        (int) Settings::get('chat.max_history', 50)
    );
    $roomSettings = Rooms::settings((int) $currentRoom['id']);
    $dmMeta       = ($currentRoom['room_type'] === 'dm')
        ? AiUsers::dmMeta((int) $currentRoom['id'])
        : null;
} else {
    $messages     = [];
    $roomSettings = [];
    $dmMeta       = null;
}

$title    = Settings::get('app.site_name', 'AI Chat');
$personas = Personas::getAll();
$users    = Users::getAll(false);
$view     = 'chat';

require __DIR__ . '/view/layout.php';
