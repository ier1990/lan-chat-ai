<?php
/**
 * Permissions — centralised access-control checks.
 */
class Permissions
{
    /**
     * Can the given participant post to this room?
     * Explicit participant rows take precedence; public rooms allow all members.
     */
    public static function canPostToRoom(int $roomId, string $participantType, int $participantId): bool
    {
        $row = DB::fetch(
            'SELECT can_post FROM room_participants
             WHERE room_id = ? AND participant_type = ? AND participant_id = ?',
            [$roomId, $participantType, $participantId]
        );
        if ($row !== null) {
            return (bool) $row['can_post'];
        }
        // Not explicitly listed — allowed if the room is public.
        $room = Rooms::getById($roomId);
        return $room !== null && !$room['is_private'];
    }

    /**
     * Can the current authenticated user administer this room?
     * Admins can always; the room creator can too.
     */
    public static function canAdminRoom(int $roomId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }
        $room = Rooms::getById($roomId);
        return $room !== null && (int) $room['created_by'] === Auth::id();
    }

    /**
     * Can the current authenticated user edit the given setting key?
     * Sensitive settings require admin role.
     */
    public static function canEditSetting(string $settingKey): bool
    {
        $row = DB::fetch(
            'SELECT is_sensitive, is_editable FROM settings WHERE setting_key = ?',
            [$settingKey]
        );
        if (!$row || !$row['is_editable']) {
            return false;
        }
        if ($row['is_sensitive']) {
            return Auth::isAdmin();
        }
        return Auth::check();
    }

    /** Can a persona reply to a message in this room given room AI settings? */
    public static function personaShouldReply(int $roomId, string $messageText): bool
    {
        $settings    = Rooms::settings($roomId);
        $aiEnabled   = (bool) ($settings['ai_enabled']      ?? false);
        $triggerMode = (string) ($settings['ai_trigger_mode'] ?? 'off');

        if (!$aiEnabled || $triggerMode === 'off') {
            return false;
        }
        if ($triggerMode === 'always') {
            return true;
        }

        // manual — allow explicit assistant mentions.
        $text = mb_strtolower($messageText);
        if (str_contains($text, '@assistant') || str_contains($text, '@ai')) {
            return true;
        }

        // Backward-compatible fallback: any @mention triggers a reply.
        return (bool) preg_match('/@\w+/', $messageText);
    }
}
