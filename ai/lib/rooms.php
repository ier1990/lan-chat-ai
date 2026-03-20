<?php
/**
 * Rooms — room creation, participant management, and room queries.
 */
class Rooms
{
    /** All rooms, optionally filtered by type. */
    public static function getAll(?string $type = null): array
    {
        if ($type !== null) {
            return DB::fetchAll(
                'SELECT * FROM rooms WHERE room_type = ? ORDER BY name',
                [$type]
            );
        }
        return DB::fetchAll('SELECT * FROM rooms ORDER BY room_type, name');
    }

    public static function getById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM rooms WHERE id = ?', [$id]);
    }

    public static function getBySlug(string $slug): ?array
    {
        return DB::fetch('SELECT * FROM rooms WHERE slug = ?', [$slug]);
    }

    /** Rooms visible to a user: public rooms + private rooms they're in. */
    public static function forUser(int $userId): array
    {
        return DB::fetchAll(
            'SELECT r.* FROM rooms r
             LEFT JOIN room_participants rp
                    ON rp.room_id = r.id
                   AND rp.participant_type = "user"
                   AND rp.participant_id = ?
             WHERE r.is_private = 0 OR rp.id IS NOT NULL
             ORDER BY r.room_type, r.name',
            [$userId]
        );
    }

    /** Create a channel or group room. Returns new room id. */
    public static function create(array $data): int
    {
        $slug = Util::slug((string) ($data['slug'] ?? $data['name']));
        if ($slug === '') {
            $slug = Util::slug((string) $data['name']);
        }
        // Ensure unique slug.
        $n = 1;
        $base = $slug;
        while (DB::exists('SELECT id FROM rooms WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $n++;
        }
        $data['slug']     = $slug;
        $data['room_key'] = $slug . '_' . substr(Util::token(4), 0, 8);
        return DB::insert('rooms', $data);
    }

    /**
     * Create or retrieve a DM room between a user and any other participant.
     * Works for user↔user and user↔persona DMs.
     */
    public static function createDm(int $userId, string $otherType, int $otherId): int
    {
        // Canonical key so user↔user does not create duplicates in opposite order.
        if ($otherType === 'user') {
            $a = min($userId, $otherId);
            $b = max($userId, $otherId);
            $key = 'dm_user_' . $a . '_user_' . $b;
        } else {
            $key = 'dm_user_' . $userId . '_' . $otherType . '_' . $otherId;
        }

        $existing = DB::fetch('SELECT id FROM rooms WHERE room_key = ?', [$key]);
        if ($existing) {
            $roomId = (int) $existing['id'];
            // Ensure persona DMs always have AI enabled, even if created before this fix.
            if ($otherType === 'persona') {
                $settings = self::settings($roomId);
                $settings['ai_enabled'] = true;
                $settings['ai_persona_id'] = $otherId;
                $settings['ai_trigger_mode'] = $settings['ai_trigger_mode'] ?? 'always';
                self::updateSettings($roomId, $settings);
            }
            return $roomId;
        }

        $name = 'DM';
        if ($otherType === 'persona') {
            $personaName = DB::fetchColumn('SELECT name FROM personas WHERE id = ?', [$otherId]);
            $name = 'AI · ' . ($personaName ?: 'Assistant');
        }
        if ($otherType === 'user') {
            $otherName = DB::fetchColumn('SELECT display_name FROM users WHERE id = ?', [$otherId]);
            $name = 'DM · ' . ($otherName ?: ('User ' . $otherId));
        }

        $slug = 'dm-' . $userId . '-' . substr(Util::token(3), 0, 6);

        $roomId = DB::insert('rooms', [
            'room_key'   => $key,
            'room_type'  => 'dm',
            'name'       => $name,
            'slug'       => $slug,
            'is_private' => 1,
            'created_by' => $userId,
        ]);
        self::addParticipant($roomId, 'user', $userId);
        self::addParticipant($roomId, $otherType, $otherId);

        // Persona DMs should reply automatically without requiring @mentions.
        if ($otherType === 'persona') {
            self::updateSettings($roomId, [
                'ai_enabled'      => true,
                'ai_persona_id'   => $otherId,
                'ai_trigger_mode' => 'always',
            ]);
        }

        return $roomId;
    }

    public static function addParticipant(
        int    $roomId,
        string $type,
        int    $participantId,
        bool   $canPost   = true,
        bool   $canInvite = false
    ): void {
        DB::query(
            'INSERT IGNORE INTO room_participants
             (room_id, participant_type, participant_id, can_post, can_invite)
             VALUES (?, ?, ?, ?, ?)',
            [$roomId, $type, $participantId, $canPost ? 1 : 0, $canInvite ? 1 : 0]
        );
    }

    public static function getParticipants(int $roomId): array
    {
        return DB::fetchAll(
            'SELECT * FROM room_participants WHERE room_id = ?',
            [$roomId]
        );
    }

    /** Return decoded room settings_json as an array. */
    public static function settings(int $roomId): array
    {
        $room = self::getById($roomId);
        return Util::jsonDecode($room['settings_json'] ?? '') ?? [];
    }

    public static function updateSettings(int $roomId, array $settings): void
    {
        DB::update(
            'rooms',
            ['settings_json' => Util::jsonEncode($settings)],
            'id = ?',
            [$roomId]
        );
    }
}
