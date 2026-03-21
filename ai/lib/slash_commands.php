<?php
/**
 * SlashCommands — private, non-persistent command handling for the chat composer.
 *
 * Commands are executed server-side and return an ephemeral response to the caller.
 * They do NOT post the raw slash command into the room.
 */
class SlashCommands
{
    public static function dispatch(int $roomId, int $userId, string $text): ?array
    {
        $raw = ltrim($text);
        if ($raw === '' || $raw[0] !== '/') {
            return null;
        }

        Memory::ensureTable();

        [$command, $rest] = self::splitCommand($raw);

        return match ($command) {
            '/help', '/slash', '/commands' => self::helpResult(),
            '/new'                         => self::handleNew($userId, $rest),
            '/dm'                          => self::handleDm($roomId, $userId, $rest),
            '/mem', '/memory'              => self::handleMemory($roomId, $userId, $rest),
            default                        => self::result('error', "Unknown slash command: {$command}\nUse /help to see available commands."),
        };
    }

    private static function splitCommand(string $text): array
    {
        $text = trim($text);
        $pos = strpos($text, ' ');
        if ($pos === false) {
            return [mb_strtolower($text), ''];
        }

        return [
            mb_strtolower(substr($text, 0, $pos)),
            trim(substr($text, $pos + 1)),
        ];
    }

    private static function helpResult(): array
    {
        return self::result('info', implode("\n", [
            'Slash commands',
            '/help',
            '/new channel My Room',
            '/new private Team Notes',
            '/new group Incident Bridge',
            '/dm @username',
            '/dm persona:assistant',
            '/mem help',
            '/mem list',
            '/mem find api key',
            '/mem add private Title | Content | tags,optional',
            '/mem add global Title | Content | tags,optional',
            '/mem add secret private Title | Content | tags,optional',
            '/mem show 12',
            '/mem delete 12',
            '/mem ai-list     (only in an AI DM)',
            '/mem ai-add Title | Content | tags,optional',
            '/mem ai-show 34',
            '/mem ai-delete 34',
        ]));
    }

    private static function handleNew(int $userId, string $rest): array
    {
        $input = trim($rest);
        if ($input === '') {
            return self::result('error', 'Usage: /new channel My Room\nUsage: /new private Team Notes\nUsage: /new group Incident Bridge');
        }

        $type = 'channel';
        $isPrivate = 0;
        $lower = mb_strtolower($input);

        foreach (['channel', 'private', 'group'] as $prefix) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '\b/i', $input)) {
                $input = trim(substr($input, strlen($prefix)));
                if ($prefix === 'private') {
                    $type = 'channel';
                    $isPrivate = 1;
                } elseif ($prefix === 'group') {
                    $type = 'group';
                    $isPrivate = 1;
                } else {
                    $type = 'channel';
                }
                break;
            }
        }

        $name = trim($input);
        if ($name === '') {
            return self::result('error', 'Room name required. Example: /new channel Dev Updates');
        }

        $roomId = Rooms::create([
            'room_type'  => $type,
            'name'       => $name,
            'is_private' => $isPrivate,
            'created_by' => $userId,
        ]);
        Rooms::addParticipant($roomId, 'user', $userId, true, true);
        $room = Rooms::getById($roomId);

        return [
            'handled' => true,
            'type' => 'success',
            'notice' => 'Created ' . self::roomLabel($room) . '. Switching now.',
            'switch_room' => [
                'id' => (int) ($room['id'] ?? 0),
                'slug' => (string) ($room['slug'] ?? ''),
                'name' => (string) ($room['name'] ?? ''),
            ],
        ];
    }

    private static function handleDm(int $roomId, int $userId, string $rest): array
    {
        $target = trim($rest);
        if ($target === '') {
            return self::result('error', 'Usage: /dm @username\nUsage: /dm persona:assistant');
        }

        $target = ltrim($target, '@');
        $otherType = 'user';
        $other = null;

        if (str_starts_with(mb_strtolower($target), 'persona:')) {
            $otherType = 'persona';
            $other = Personas::getByKey(trim(substr($target, 8)));
        } elseif (str_starts_with(mb_strtolower($target), 'ai:')) {
            $otherType = 'persona';
            $other = Personas::getByKey(trim(substr($target, 3)));
        } else {
            $other = Users::getByUsername($target);
            if (!$other) {
                $otherType = 'persona';
                $other = Personas::getByKey($target);
            }
        }

        if (!$other) {
            return self::result('error', 'DM target not found. Use /dm @username or /dm persona:persona-key.');
        }

        if ($otherType === 'user' && (int) $other['id'] === $userId) {
            return self::result('error', 'You already have yourself locally. Pick another user or a persona.');
        }

        $dmRoomId = Rooms::createDm($userId, $otherType, (int) $other['id']);
        $room = Rooms::getById($dmRoomId);

        return [
            'handled' => true,
            'type' => 'success',
            'notice' => 'Opened ' . self::roomLabel($room) . '. Switching now.',
            'switch_room' => [
                'id' => (int) ($room['id'] ?? 0),
                'slug' => (string) ($room['slug'] ?? ''),
                'name' => (string) ($room['name'] ?? ''),
            ],
        ];
    }

    private static function handleMemory(int $roomId, int $userId, string $rest): array
    {
        [$subcommand, $args] = self::splitCommand('/' . ltrim($rest === '' ? 'help' : $rest));
        $subcommand = ltrim($subcommand, '/');

        return match ($subcommand) {
            'help'      => self::memoryHelpResult(),
            'list'      => self::handleMemoryList($userId),
            'find'      => self::handleMemoryFind($userId, $args),
            'show'      => self::handleMemoryShow($userId, $args),
            'delete'    => self::handleMemoryDelete($userId, $args),
            'add'       => self::handleMemoryAdd($userId, $args),
            'ai-list'   => self::handleAiMemoryList($roomId, $userId),
            'ai-find'   => self::handleAiMemoryFind($roomId, $userId, $args),
            'ai-show'   => self::handleAiMemoryShow($roomId, $userId, $args),
            'ai-delete' => self::handleAiMemoryDelete($roomId, $userId, $args),
            'ai-add'    => self::handleAiMemoryAdd($roomId, $userId, $args),
            default     => self::result('error', 'Unknown /mem subcommand. Use /mem help.'),
        };
    }

    private static function memoryHelpResult(): array
    {
        return self::result('info', implode("\n", [
            'Memory commands',
            '/mem list',
            '/mem find query words',
            '/mem show 12',
            '/mem add private Title | Content | tags,optional',
            '/mem add global Title | Content | tags,optional',
            '/mem add secret private Title | Content | tags,optional',
            '/mem delete 12',
            '',
            'AI-private memory in an AI DM',
            '/mem ai-list',
            '/mem ai-find ssh key',
            '/mem ai-add Title | Content | tags,optional',
            '/mem ai-show 34',
            '/mem ai-delete 34',
        ]));
    }

    private static function handleMemoryList(int $userId): array
    {
        $rows = array_slice(Memory::forUser($userId), 0, 10);
        if (!$rows) {
            return self::result('info', 'No memories yet. Use /mem add private Title | Content | tags');
        }

        return self::result('info', self::formatMemoryList('Your visible memories', $rows));
    }

    private static function handleMemoryFind(int $userId, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return self::result('error', 'Usage: /mem find query words');
        }

        $rows = array_slice(Memory::search($query, $userId), 0, 10);
        if (!$rows) {
            return self::result('info', 'No memories matched: ' . $query);
        }

        return self::result('info', self::formatMemoryList('Matches for: ' . $query, $rows));
    }

    private static function handleMemoryShow(int $userId, string $args): array
    {
        $memory = Memory::getById((int) trim($args));
        if (!$memory || !self::canViewMemory($memory, $userId)) {
            return self::result('error', 'Memory not found.');
        }

        return self::result('info', self::formatMemoryDetail($memory));
    }

    private static function handleMemoryDelete(int $userId, string $args): array
    {
        $memory = Memory::getById((int) trim($args));
        if (!$memory || !self::canDeleteMemory($memory, $userId)) {
            return self::result('error', 'Memory not found or not owned by you.');
        }

        Memory::delete((int) $memory['id'], $userId, Auth::isAdmin());
        return self::result('success', 'Deleted memory #' . (int) $memory['id'] . '.');
    }

    private static function handleMemoryAdd(int $userId, string $args): array
    {
        $parsed = self::parseMemoryAddArgs($args);
        if (!empty($parsed['error'])) {
            return self::result('error', (string) $parsed['error']);
        }

        $memoryId = Memory::save([
            'title' => $parsed['title'],
            'content' => $parsed['content'],
            'tags' => $parsed['tags'],
            'scope' => $parsed['scope'],
            'is_secret' => $parsed['is_secret'],
        ], $userId);

        return self::result('success', 'Saved memory #' . $memoryId . ' [' . $parsed['scope'] . ($parsed['is_secret'] ? ', secret' : '') . '].');
    }

    private static function handleAiMemoryList(int $roomId, int $userId): array
    {
        $aiOwner = self::resolveAiOwner($roomId, $userId);
        if (!$aiOwner) {
            return self::result('error', 'AI memory commands only work inside a DM with an AI user.');
        }

        $rows = array_values(array_filter(Memory::forUser((int) $aiOwner['id']), function (array $row) use ($aiOwner): bool {
            return (int) $row['owner_id'] === (int) $aiOwner['id'];
        }));
        $rows = array_slice($rows, 0, 10);
        if (!$rows) {
            return self::result('info', 'This AI does not have private memories yet. Use /mem ai-add Title | Content | tags');
        }

        return self::result('info', self::formatMemoryList('AI memories for @' . $aiOwner['username'], $rows));
    }

    private static function handleAiMemoryFind(int $roomId, int $userId, string $query): array
    {
        $aiOwner = self::resolveAiOwner($roomId, $userId);
        if (!$aiOwner) {
            return self::result('error', 'AI memory commands only work inside a DM with an AI user.');
        }
        $query = trim($query);
        if ($query === '') {
            return self::result('error', 'Usage: /mem ai-find query words');
        }

        $rows = array_values(array_filter(Memory::search($query, (int) $aiOwner['id']), function (array $row) use ($aiOwner): bool {
            return (int) $row['owner_id'] === (int) $aiOwner['id'];
        }));
        $rows = array_slice($rows, 0, 10);
        if (!$rows) {
            return self::result('info', 'No AI memories matched: ' . $query);
        }

        return self::result('info', self::formatMemoryList('AI matches for: ' . $query, $rows));
    }

    private static function handleAiMemoryShow(int $roomId, int $userId, string $args): array
    {
        $aiOwner = self::resolveAiOwner($roomId, $userId);
        if (!$aiOwner) {
            return self::result('error', 'AI memory commands only work inside a DM with an AI user.');
        }

        $memory = Memory::getById((int) trim($args));
        if (!$memory || (int) $memory['owner_id'] !== (int) $aiOwner['id']) {
            return self::result('error', 'AI memory not found.');
        }

        return self::result('info', self::formatMemoryDetail($memory));
    }

    private static function handleAiMemoryDelete(int $roomId, int $userId, string $args): array
    {
        $aiOwner = self::resolveAiOwner($roomId, $userId);
        if (!$aiOwner) {
            return self::result('error', 'AI memory commands only work inside a DM with an AI user.');
        }

        $memory = Memory::getById((int) trim($args));
        if (!$memory || (int) $memory['owner_id'] !== (int) $aiOwner['id']) {
            return self::result('error', 'AI memory not found.');
        }

        Memory::delete((int) $memory['id'], (int) $aiOwner['id'], true);
        return self::result('success', 'Deleted AI memory #' . (int) $memory['id'] . '.');
    }

    private static function handleAiMemoryAdd(int $roomId, int $userId, string $args): array
    {
        $aiOwner = self::resolveAiOwner($roomId, $userId);
        if (!$aiOwner) {
            return self::result('error', 'AI memory commands only work inside a DM with an AI user.');
        }

        $parsed = self::parseMemoryAddArgs('private ' . $args);
        if (!empty($parsed['error'])) {
            return self::result('error', 'Usage: /mem ai-add Title | Content | tags');
        }

        $memoryId = Memory::save([
            'title' => $parsed['title'],
            'content' => $parsed['content'],
            'tags' => $parsed['tags'],
            'scope' => 'private',
            'is_secret' => 0,
        ], (int) $aiOwner['id']);

        return self::result('success', 'Saved AI memory #' . $memoryId . ' for @' . $aiOwner['username'] . '.');
    }

    private static function parseMemoryAddArgs(string $args): array
    {
        $input = trim($args);
        if ($input === '') {
            return ['error' => 'Usage: /mem add private Title | Content | tags'];
        }

        $segments = array_map('trim', explode('|', $input));
        $head = array_shift($segments) ?? '';
        $tokens = preg_split('/\s+/', trim($head), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $scope = 'private';
        $isSecret = 0;
        $consumed = 0;
        while (isset($tokens[$consumed])) {
            $token = mb_strtolower($tokens[$consumed]);
            if ($token === 'private' || $token === 'global') {
                $scope = $token;
                $consumed++;
                continue;
            }
            if ($token === 'secret') {
                $isSecret = 1;
                $consumed++;
                continue;
            }
            break;
        }

        $title = trim(implode(' ', array_slice($tokens, $consumed)));
        $content = trim($segments[0] ?? '');
        $tags = trim($segments[1] ?? '');

        if ($title === '' || $content === '') {
            return ['error' => 'Usage: /mem add private Title | Content | tags'];
        }

        return [
            'scope' => $scope,
            'is_secret' => $isSecret,
            'title' => $title,
            'content' => $content,
            'tags' => $tags,
        ];
    }

    private static function resolveAiOwner(int $roomId, int $userId): ?array
    {
        return AiUsers::getDmAiUserForRoom($roomId, $userId);
    }

    private static function canViewMemory(array $memory, int $userId): bool
    {
        return Auth::isAdmin()
            || (string) ($memory['scope'] ?? 'private') === 'global'
            || (int) ($memory['owner_id'] ?? 0) === $userId;
    }

    private static function canDeleteMemory(array $memory, int $userId): bool
    {
        return Auth::isAdmin() || (int) ($memory['owner_id'] ?? 0) === $userId;
    }

    private static function formatMemoryList(string $title, array $rows): string
    {
        $lines = [$title];
        foreach ($rows as $row) {
            $lines[] = self::formatMemoryRow($row);
        }
        return implode("\n", $lines);
    }

    private static function formatMemoryRow(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        $scope = (string) ($row['scope'] ?? 'private');
        $secret = !empty($row['is_secret']) ? ', secret' : '';
        $title = trim((string) ($row['title'] ?? 'Untitled'));
        $preview = trim((string) ($row['content'] ?? ''));
        $preview = preg_replace('/\s+/', ' ', $preview);
        $preview = mb_strimwidth($preview, 0, 90, '...');
        return '#' . $id . ' [' . $scope . $secret . '] ' . $title . ' — ' . $preview;
    }

    private static function formatMemoryDetail(array $row): string
    {
        $tags = trim((string) ($row['tags'] ?? ''));
        $lines = [
            'Memory #' . (int) ($row['id'] ?? 0),
            'Title: ' . trim((string) ($row['title'] ?? 'Untitled')),
            'Scope: ' . (string) ($row['scope'] ?? 'private') . (!empty($row['is_secret']) ? ' (secret)' : ''),
            'Content: ' . trim((string) ($row['content'] ?? '')),
        ];
        if ($tags !== '') {
            $lines[] = 'Tags: ' . $tags;
        }
        return implode("\n", $lines);
    }

    private static function roomLabel(?array $room): string
    {
        if (!$room) {
            return 'room';
        }
        $prefix = match ((string) ($room['room_type'] ?? 'channel')) {
            'dm' => '@',
            'group' => 'group ',
            default => '#',
        };
        return $prefix . (string) ($room['name'] ?? 'room');
    }

    private static function result(string $type, string $notice): array
    {
        return [
            'handled' => true,
            'type' => $type,
            'notice' => $notice,
        ];
    }
}
