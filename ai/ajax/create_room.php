<?php
/**
 * ajax/create_room.php — Create a new channel or start a DM.
 *
 * POST body: type (channel|dm|group), name, [persona_id or user_id for DMs]
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireLogin();

if (!Util::isPost()) {
    Util::jsonResponse(['error' => 'POST required'], 405);
}
Util::requireCsrf();

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

DebugLog::request('ajax.create_room', is_array($data) ? $data : null);

$type = trim((string) ($data['type'] ?? 'channel'));
$name = trim((string) ($data['name'] ?? ''));

if (!in_array($type, ['channel', 'group', 'dm'], true)) {
    DebugLog::event('debug.error', ['route' => 'ajax.create_room', 'error' => 'invalid room type', 'type' => $type]);
    Util::jsonResponse(['error' => 'Invalid room type'], 400);
}

// DM creation.
if ($type === 'dm') {
    $otherType = (string) ($data['other_type'] ?? 'user');
    $otherId   = (int)   ($data['other_id']   ?? 0);

    if (!in_array($otherType, ['user', 'persona'], true) || !$otherId) {
        DebugLog::event('debug.error', ['route' => 'ajax.create_room', 'error' => 'invalid dm target', 'other_type' => $otherType, 'other_id' => $otherId]);
        Util::jsonResponse(['error' => 'other_type and other_id required for DMs'], 400);
    }

    if ($otherType === 'user' && !Users::getById($otherId)) {
        DebugLog::event('debug.error', ['route' => 'ajax.create_room', 'error' => 'target user not found', 'other_id' => $otherId]);
        Util::jsonResponse(['error' => 'Target user not found'], 404);
    }
    if ($otherType === 'persona' && !Personas::getById($otherId)) {
        DebugLog::event('debug.error', ['route' => 'ajax.create_room', 'error' => 'target persona not found', 'other_id' => $otherId]);
        Util::jsonResponse(['error' => 'Target persona not found'], 404);
    }

    $roomId = Rooms::createDm(Auth::id(), $otherType, $otherId);
    $room   = Rooms::getById($roomId);
    DebugLog::event('debug.response', ['route' => 'ajax.create_room', 'ok' => true, 'type' => 'dm', 'room_id' => $roomId, 'room_slug' => $room['slug'] ?? '']);
    Util::jsonResponse(['ok' => true, 'room' => $room]);
}

// Channel / group creation.
if (!$name) {
    DebugLog::event('debug.error', ['route' => 'ajax.create_room', 'error' => 'name required']);
    Util::jsonResponse(['error' => 'name required'], 400);
}

$roomId = Rooms::create([
    'room_type'  => $type,
    'name'       => $name,
    'is_private' => (int) ($data['is_private'] ?? 0),
    'created_by' => Auth::id(),
]);

// Add creator as a participant.
Rooms::addParticipant($roomId, 'user', Auth::id(), true, true);

$room = Rooms::getById($roomId);
DebugLog::event('debug.response', ['route' => 'ajax.create_room', 'ok' => true, 'type' => $type, 'room_id' => $roomId, 'room_slug' => $room['slug'] ?? '']);
Util::jsonResponse(['ok' => true, 'room' => $room]);
