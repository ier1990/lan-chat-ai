<?php
/**
 * Messages — post and retrieve messages from rooms.
 */
class Messages
{
    /**
     * Post a message to a room. Returns the new message id.
     */
    public static function post(
        int     $roomId,
        string  $senderType,
        int     $senderId,
        string  $text,
        string  $msgType = 'text',
        array   $meta    = [],
        ?int    $replyTo = null
    ): int {
        return DB::insert('messages', [
            'room_id'      => $roomId,
            'sender_type'  => $senderType,
            'sender_id'    => $senderId,
            'message_text' => $text,
            'message_type' => $msgType,
            'reply_to_id'  => $replyTo,
            'status'       => 'sent',
            'meta_json'    => $meta ? Util::jsonEncode($meta) : null,
            'created_at'   => Util::now(),
        ]);
    }

    /**
     * Load the most recent $limit messages for a room, optionally paging backwards.
     * Returns rows newest-first (caller reverses for display).
     */
    public static function load(int $roomId, int $limit = 50, int $beforeId = 0): array
    {
        $where  = 'room_id = ?';
        $params = [$roomId];
        if ($beforeId > 0) {
            $where   .= ' AND id < ?';
            $params[] = $beforeId;
        }
        return DB::fetchAll(
            "SELECT * FROM messages WHERE {$where} ORDER BY created_at DESC LIMIT " . (int) $limit,
            $params
        );
    }

    /** Load for display — chronological order, with sender names resolved. */
    public static function forRoom(int $roomId, int $limit = 50, int $beforeId = 0): array
    {
        $rows = array_reverse(self::load($roomId, $limit, $beforeId));
        return self::withSenderInfo($rows);
    }

    /** Load messages posted after $afterId (for polling). */
    public static function since(int $roomId, int $afterId): array
    {
        $rows = DB::fetchAll(
            'SELECT * FROM messages WHERE room_id = ? AND id > ? ORDER BY created_at ASC',
            [$roomId, $afterId]
        );
        return self::withSenderInfo($rows);
    }

    /** Annotate each message row with sender_name and sender_avatar_initial. */
    public static function withSenderInfo(array $messages): array
    {
        foreach ($messages as &$msg) {
            $msg['sender_name']           = self::resolveSenderName($msg['sender_type'], (int) $msg['sender_id']);
            $msg['sender_avatar_initial'] = mb_strtoupper(mb_substr($msg['sender_name'], 0, 1));
            $msg['meta']                  = Util::jsonDecode($msg['meta_json'] ?? '') ?? [];
        }
        unset($msg);
        return $messages;
    }

    private static function resolveSenderName(string $type, int $id): string
    {
        return match ($type) {
            'user'    => (string) (DB::fetchColumn('SELECT display_name FROM users   WHERE id = ?', [$id]) ?? 'Unknown'),
            'persona' => (string) (DB::fetchColumn('SELECT name          FROM personas WHERE id = ?', [$id]) ?? 'AI'),
            'webhook' => (string) (DB::fetchColumn('SELECT name          FROM webhook_sources WHERE id = ?', [$id]) ?? 'Webhook'),
            default   => 'System',
        };
    }
}
