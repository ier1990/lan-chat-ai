<?php
/**
 * DebugLog — lightweight request/event logging into the #log room.
 */
class DebugLog
{
    public static function enabled(): bool
    {
        return (bool) Settings::get('app.debug_mode', 0);
    }

    public static function request(string $route, ?array $body = null, array $extra = []): void
    {
        if (!self::enabled()) {
            return;
        }

        $payload = [
            'route'   => $route,
            'method'  => (string) ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'),
            'uri'     => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'user_id' => Auth::id(),
            'get'     => self::sanitize($_GET),
            'post'    => $body !== null ? self::sanitize($body) : self::sanitize($_POST),
            'time'    => Util::now(),
        ];

        foreach ($extra as $k => $v) {
            $payload[$k] = self::sanitizeValue($k, $v);
        }

        self::event('debug.request', $payload);
    }

    public static function event(string $event, array $payload = []): void
    {
        if (!self::enabled()) {
            return;
        }

        try {
            $roomId = self::resolveLogRoomId();
            if (!$roomId) {
                return;
            }

            $text = '[' . $event . '] ' . Util::jsonEncode(self::sanitize($payload));
            Messages::post($roomId, 'system', 0, $text, 'log');
        } catch (Throwable $e) {
            error_log('DebugLog failed: ' . $e->getMessage());
        }
    }

    private static function resolveLogRoomId(): int
    {
        $slug = (string) Settings::get('webhook.log_room_default', 'log');
        $id   = (int) DB::fetchColumn('SELECT id FROM rooms WHERE slug = ? LIMIT 1', [$slug]);
        if ($id > 0) {
            return $id;
        }

        $id = (int) DB::fetchColumn('SELECT id FROM rooms WHERE room_type = "log" ORDER BY id ASC LIMIT 1');
        if ($id > 0) {
            return $id;
        }

        $roomId = DB::insert('rooms', [
            'room_key'   => 'log-' . substr(Util::token(4), 0, 8),
            'room_type'  => 'log',
            'name'       => 'log',
            'slug'       => 'log',
            'is_private' => 0,
            'created_by' => Auth::id() ?: null,
        ]);

        return (int) $roomId;
    }

    private static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            $i = 0;
            foreach ($value as $k => $v) {
                $out[$k] = self::sanitizeValue((string) $k, $v);
                $i++;
                if ($i >= 50) {
                    $out['_truncated'] = 'array limited to 50 items';
                    break;
                }
            }
            return $out;
        }

        return self::sanitizeValue('', $value);
    }

    private static function sanitizeValue(string $key, mixed $value): mixed
    {
        if (preg_match('/(^|_)(password|pass|api_key|token|csrf|secret)$/i', $key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            return self::sanitize($value);
        }

        if (is_object($value)) {
            return '[object]';
        }

        if (is_string($value) && mb_strlen($value) > 500) {
            return mb_substr($value, 0, 500) . '...[truncated]';
        }

        return $value;
    }
}
