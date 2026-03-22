<?php
/**
 * handlers/rooms.php — _rooms_action POST handler.
 * Returns [$flash, $flashType].
 */
function _handleRooms(): array
{
    $action = Util::post('_rooms_action');
    $roomId = (int) Util::post('room_id');

    if ($action === 'create_dm_room') {
        $targetType = Util::post('dm_target_type', 'user');
        $targetId   = (int) Util::post('dm_target_id');

        if (!in_array($targetType, ['user', 'persona'], true) || $targetId <= 0) {
            return ['Select a valid DM target.', 'error'];
        }
        if ($targetType === 'user' && !Users::getById($targetId)) {
            return ['Selected user not found.', 'error'];
        }
        if ($targetType === 'persona' && !Personas::getById($targetId)) {
            return ['Selected persona not found.', 'error'];
        }

        $newRoomId = Rooms::createDm((int) Auth::id(), $targetType, $targetId);
        $newRoom   = Rooms::getById($newRoomId);
        return ['DM room ready: ' . ($newRoom['name'] ?? ('Room #' . $newRoomId)), 'success'];
    }

    if ($action === 'create_channel') {
        $name      = Util::post('name');
        $slugInput = Util::post('slug');
        $isPrivate = Util::post('is_private', '0') === '1' ? 1 : 0;
        $aiEnabled = Util::post('ai_enabled', '0') === '1';
        $aiTrigger = Util::post('ai_trigger_mode', 'mention');

        if ($name === '') {
            return ['Room name is required.', 'error'];
        }

        $slug = $slugInput !== '' ? Util::slug($slugInput) : Util::slug($name);
        if ($slug === '') {
            return ['Invalid slug.', 'error'];
        }
        if (Rooms::getBySlug($slug)) {
            return ['That slug is already in use.', 'error'];
        }

        Rooms::create([
            'room_type'     => 'channel',
            'name'          => $name,
            'slug'          => $slug,
            'is_private'    => $isPrivate,
            'created_by'    => (int) Auth::id(),
            'settings_json' => Util::jsonEncode([
                'ai_enabled'      => $aiEnabled,
                'ai_trigger_mode' => in_array($aiTrigger, ['off', 'mention', 'always'], true) ? $aiTrigger : 'mention',
            ]),
        ]);
        return ['Channel created.', 'success'];
    }

    if ($action === 'save_room') {
        $room      = Rooms::getById($roomId);
        $name      = Util::post('name');
        $slugInput = Util::post('slug');
        $isPrivate = Util::post('is_private', '0') === '1' ? 1 : 0;
        $aiEnabled = Util::post('ai_enabled', '0') === '1';
        $aiTrigger = Util::post('ai_trigger_mode', 'mention');

        if (!$room) {
            return ['Room not found.', 'error'];
        }
        if ($name === '' || $slugInput === '') {
            return ['Name and slug are required.', 'error'];
        }

        $slug      = Util::slug($slugInput);
        $slugOwner = DB::fetch('SELECT id FROM rooms WHERE slug = ? AND id <> ?', [$slug, $roomId]);

        if ($slug === '') {
            return ['Invalid slug.', 'error'];
        }
        if ($slugOwner) {
            return ['That slug is already in use.', 'error'];
        }

        $settings                    = Rooms::settings($roomId);
        $settings['ai_enabled']      = $aiEnabled;
        $settings['ai_trigger_mode'] = in_array($aiTrigger, ['off', 'mention', 'always'], true) ? $aiTrigger : 'mention';

        DB::update('rooms', [
            'name'          => $name,
            'slug'          => $slug,
            'is_private'    => $room['room_type'] === 'dm' ? 1 : $isPrivate,
            'settings_json' => Util::jsonEncode($settings),
        ], 'id = ?', [$roomId]);

        return ['Room updated.', 'success'];
    }

    if ($action === 'delete_dm_room') {
        $room = Rooms::getById($roomId);
        if (!$room) {
            return ['Room not found.', 'error'];
        }
        if (($room['room_type'] ?? '') !== 'dm') {
            return ['Only DM rooms can be deleted with this action.', 'error'];
        }
        DB::query('DELETE FROM rooms WHERE id = ? LIMIT 1', [$roomId]);
        return ['DM room deleted.', 'success'];
    }

    if ($action === 'delete_room') {
        $room = Rooms::getById($roomId);
        if (!$room) {
            return ['Room not found.', 'error'];
        }
        if (in_array($room['room_type'] ?? '', ['log'], true)) {
            return ['System rooms cannot be deleted.', 'error'];
        }
        DB::query('DELETE FROM rooms WHERE id = ? LIMIT 1', [$roomId]);
        return ['Room "' . $room['name'] . '" deleted.', 'success'];
    }

    if ($action === 'create_or_rotate_webhook') {
        $room        = Rooms::getById($roomId);
        $webhookName = Util::post('webhook_name');
        $sourceType  = Util::post('source_type', 'generic');
        $isEnabled   = Util::post('webhook_enabled', '1') === '1' ? 1 : 0;

        if (!$room) {
            return ['Room not found.', 'error'];
        }

        $key      = Util::token(24);
        $existing = DB::fetch(
            'SELECT * FROM webhook_sources WHERE target_room_id = ? ORDER BY id ASC LIMIT 1',
            [$roomId]
        );

        $name = $webhookName !== '' ? $webhookName : ('Room ' . $room['name'] . ' Hook');
        $type = $sourceType !== '' ? $sourceType : 'generic';

        if ($existing) {
            DB::update('webhook_sources', [
                'name'        => $name,
                'source_type' => $type,
                'webhook_key' => $key,
                'is_enabled'  => $isEnabled,
            ], 'id = ?', [(int) $existing['id']]);
            return ['Webhook key rotated.', 'success'];
        }

        DB::insert('webhook_sources', [
            'name'           => $name,
            'source_type'    => $type,
            'webhook_key'    => $key,
            'target_room_id' => $roomId,
            'is_enabled'     => $isEnabled,
        ]);
        return ['Webhook key created.', 'success'];
    }

    if ($action === 'delete_webhook') {
        $hookId = (int) Util::post('webhook_id');
        $hook   = DB::fetch('SELECT id FROM webhook_sources WHERE id = ? AND target_room_id = ?', [$hookId, $roomId]);
        if (!$hook) {
            return ['Webhook not found.', 'error'];
        }
        DB::query('DELETE FROM webhook_sources WHERE id = ?', [$hookId]);
        return ['Webhook deleted.', 'success'];
    }

    return ['', 'success'];
}
