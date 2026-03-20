<?php
/**
 * AiUsers — AI-role user configuration and chat routing helpers.
 */
class AiUsers
{
    private static ?bool $tableExists = null;

    public static function decodeHeaders(?string $headersJson): array
    {
        $headers = Util::jsonDecode($headersJson ?? '');
        if (!is_array($headers)) {
            return [];
        }

        $clean = [];
        foreach ($headers as $key => $value) {
            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }
            $clean[$name] = trim((string) $value);
        }

        return $clean;
    }

    public static function encodeHeaders(array $headers): ?string
    {
        $clean = [];
        foreach ($headers as $key => $value) {
            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }

            $headerValue = trim((string) $value);
            if ($headerValue === '') {
                continue;
            }

            $clean[$name] = $headerValue;
        }

        return $clean ? Util::jsonEncode($clean) : null;
    }

    public static function getByUserId(int $userId): ?array
    {
        if (!self::hasTable()) {
            return null;
        }
        return DB::fetch(
            'SELECT auc.*, u.username, u.display_name
             FROM ai_user_configs auc
             JOIN users u ON u.id = auc.user_id
             WHERE auc.user_id = ?',
            [$userId]
        );
    }

    public static function getAllConfigs(): array
    {
        if (!self::hasTable()) {
            return [];
        }
        return DB::fetchAll(
            'SELECT auc.*, u.username, u.display_name, u.is_active,
                    p.name AS persona_name
             FROM ai_user_configs auc
             JOIN users u ON u.id = auc.user_id
             LEFT JOIN personas p ON p.id = auc.persona_id
             ORDER BY u.display_name ASC'
        );
    }

    public static function upsertConfig(int $userId, array $config): void
    {
        if (!self::hasTable()) {
            throw new RuntimeException('ai_user_configs table is missing. Open Admin once to initialize AI user infra.');
        }

        $existing = DB::fetchColumn('SELECT user_id FROM ai_user_configs WHERE user_id = ?', [$userId]);
        $row = [
            'provider_key' => (string) ($config['provider_key'] ?? 'openai_compat'),
            'base_url'     => (string) ($config['base_url'] ?? ''),
            'api_key'      => (string) ($config['api_key'] ?? ''),
            'model_default'=> (string) ($config['model_default'] ?? ''),
            'persona_id'   => !empty($config['persona_id']) ? (int) $config['persona_id'] : null,
            'headers_json' => !empty($config['headers_json']) ? (string) $config['headers_json'] : null,
            'is_enabled'   => (int) ($config['is_enabled'] ?? 1),
            'updated_at'   => Util::now(),
        ];

        if ($existing) {
            DB::update('ai_user_configs', $row, 'user_id = ?', [$userId]);
            return;
        }

        DB::insert('ai_user_configs', ['user_id' => $userId, ...$row]);
    }

    /**
     * Return AI-user target for a DM room, excluding the sender.
     */
    public static function getDmAiUserForRoom(int $roomId, int $senderUserId): ?array
    {
        if (!self::hasTable()) {
            return null;
        }

        $room = Rooms::getById($roomId);
        if (!$room || $room['room_type'] !== 'dm') {
            return null;
        }

        $aiUser = DB::fetch(
            'SELECT u.id, u.username, u.display_name
             FROM room_participants rp
             JOIN users u ON u.id = rp.participant_id
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE rp.room_id = ?
               AND rp.participant_type = "user"
               AND rp.participant_id <> ?
               AND u.is_active = 1
               AND r.role_key = "ai"
             LIMIT 1',
            [$roomId, $senderUserId]
        );

        if (!$aiUser) {
            return null;
        }

        $config = self::getByUserId((int) $aiUser['id']);
        if (!$config || !(int) ($config['is_enabled'] ?? 0)) {
            return null;
        }

        $aiUser['config'] = $config;
        return $aiUser;
    }

    public static function chat(array $aiConfig, array $messages): array
    {
        $url = rtrim((string) $aiConfig['base_url'], '/') . '/chat/completions';
        $apiKey = (string) ($aiConfig['api_key'] ?? '');
        $model = (string) ($aiConfig['model_default'] ?? '');

        if ($url === '/chat/completions' || $model === '') {
            throw new RuntimeException('AI user is missing base URL or model.');
        }

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 2048,
        ];

        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $extra = self::decodeHeaders((string) ($aiConfig['headers_json'] ?? ''));
        if ((string) ($aiConfig['provider_key'] ?? '') === 'openrouter') {
            if (!self::hasHeader($extra, 'HTTP-Referer')) {
                $referer = self::detectAppUrl();
                if ($referer !== null) {
                    $extra['HTTP-Referer'] = $referer;
                }
            }
            if (!self::hasHeader($extra, 'X-Title')) {
                $extra['X-Title'] = (string) Settings::get('app.site_name', 'AI Chat');
            }
        }

        foreach ($extra as $key => $value) {
            if ($value === '') {
                continue;
            }
            $headers[] = $key . ': ' . $value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('AI user request failed: ' . $curlErr);
        }
        if ($httpCode !== 200) {
            throw new RuntimeException('AI user provider returned HTTP ' . $httpCode . ': ' . $response);
        }

        $data = json_decode((string) $response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from AI user provider.');
        }

        return [
            'text'       => trim((string) ($data['choices'][0]['message']['content'] ?? '')),
            'model'      => (string) ($data['model'] ?? $model),
            'tokens_in'  => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($data['usage']['completion_tokens'] ?? 0),
        ];
    }

    public static function resetTableCache(): void
    {
        self::$tableExists = null;
    }

    private static function hasTable(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        try {
            $count = DB::fetchColumn(
                "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_user_configs'"
            );
            self::$tableExists = ((int) $count) > 0;
        } catch (Throwable) {
            self::$tableExists = false;
        }

        return self::$tableExists;
    }

    private static function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $key => $_value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function detectAppUrl(): ?string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return null;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/ai')));
        $scriptDir = rtrim($scriptDir, '/');
        if ($scriptDir === '' || $scriptDir === '.') {
            $scriptDir = '/';
        }

        return rtrim($scheme . '://' . $host . $scriptDir, '/') . '/';
    }
}
