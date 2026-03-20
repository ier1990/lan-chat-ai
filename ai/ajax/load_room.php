<?php
/**
 * ajax/load_room.php — Return messages for a room.
 *
 * GET /ai/ajax/load_room.php?room_id=1[&before_id=0]
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
Auth::requireLogin();

DebugLog::request('ajax.load_room', null, [
    'room_id'   => (int) Util::get('room_id'),
    'before_id' => (int) Util::get('before_id', '0'),
]);

$roomId   = (int) Util::get('room_id');
$beforeId = (int) Util::get('before_id', '0');

if (!$roomId) {
    DebugLog::event('debug.error', ['route' => 'ajax.load_room', 'error' => 'room_id required']);
    Util::jsonResponse(['error' => 'room_id required'], 400);
}

$room = Rooms::getById($roomId);
if (!$room) {
    DebugLog::event('debug.error', ['route' => 'ajax.load_room', 'error' => 'room not found', 'room_id' => $roomId]);
    Util::jsonResponse(['error' => 'Room not found'], 404);
}

// Access check: user must be able to see this room.
$accessible = Rooms::forUser(Auth::id());
$roomIds    = array_column($accessible, 'id');
if (!in_array($room['id'], $roomIds, false)) {
    DebugLog::event('debug.error', ['route' => 'ajax.load_room', 'error' => 'access denied', 'room_id' => $roomId, 'user_id' => Auth::id()]);
    Util::jsonResponse(['error' => 'Access denied'], 403);
}

$limit    = (int) Settings::get('chat.max_history', 50);
$messages = Messages::forRoom($roomId, $limit, $beforeId);

DebugLog::event('debug.response', [
    'route'         => 'ajax.load_room',
    'ok'            => true,
    'room_id'       => $roomId,
    'message_count' => count($messages),
]);

Util::jsonResponse([
    'ok'       => true,
    'room'     => $room,
    'messages' => $messages,
]);
