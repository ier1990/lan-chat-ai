<?php
/**
 * Memory — per-user and global memory store.
 *
 * Scopes:
 *   private — only the owner (and admin) can read/write
 *   global  — visible to all users; non-secret entries are available as AI context
 *
 * is_secret = 1 — visible to owner/admin in the UI,
 *                 but NEVER injected into any AI prompt regardless of scope.
 *
 * AI context rule (enforced in getAiContext):
 *   ONLY scope = 'global' AND is_secret = 0 entries reach the LLM.
 */
class Memory
{
    private static ?bool $tableExists = null;

    // ── Table management ──────────────────────────────────────────────────

    public static function hasTable(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }
        try {
            $n = DB::fetchColumn(
                "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'memories'"
            );
            self::$tableExists = ((int) $n) > 0;
        } catch (Throwable) {
            self::$tableExists = false;
        }
        return self::$tableExists;
    }

    /** Create the memories table if it doesn't exist yet (migration-safe). */
    public static function ensureTable(): void
    {
        if (self::hasTable()) {
            return;
        }
        DB::pdo()->exec("
            CREATE TABLE IF NOT EXISTS memories (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                owner_id     INT UNSIGNED NOT NULL DEFAULT 0,
                scope        ENUM('private','global') NOT NULL DEFAULT 'private',
                is_secret    TINYINT(1)   NOT NULL DEFAULT 0,
                title        VARCHAR(200) NOT NULL DEFAULT '',
                content      TEXT NOT NULL,
                tags         VARCHAR(500) NOT NULL DEFAULT '',
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_owner_scope (owner_id, scope),
                FULLTEXT idx_ft_search (title, content, tags)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        self::$tableExists = true;
    }

    // ── CRUD ─────────────────────────────────────────────────────────────

    public static function getById(int $id): ?array
    {
        if (!self::hasTable()) {
            return null;
        }
        return DB::fetch('SELECT * FROM memories WHERE id = ?', [$id]);
    }

    /** All memories visible to $userId: their private entries + every global entry. */
    public static function forUser(int $userId): array
    {
        if (!self::hasTable()) {
            return [];
        }
        return DB::fetchAll(
            "SELECT * FROM memories
             WHERE scope = 'global' OR owner_id = ?
             ORDER BY updated_at DESC",
            [$userId]
        );
    }

    /** All memories across all users — admin view only. */
    public static function getAll(): array
    {
        if (!self::hasTable()) {
            return [];
        }
        return DB::fetchAll(
            "SELECT m.*, u.display_name AS owner_name, u.username AS owner_username
             FROM memories m
             LEFT JOIN users u ON u.id = m.owner_id
             ORDER BY m.scope ASC, m.updated_at DESC"
        );
    }

    /**
     * Search memories visible to $userId.
     * Tries FULLTEXT Boolean mode first; falls back to LIKE on failure or no results.
     */
    public static function search(string $query, int $userId): array
    {
        if (!self::hasTable()) {
            return [];
        }
        $q = trim($query);
        if ($q === '') {
            return self::forUser($userId);
        }

        // Build a Boolean-mode FULLTEXT query: each word gets a * wildcard suffix.
        try {
            $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
            $ftq   = implode(' ', array_map(fn($w) => '+' . preg_replace('/[+\-><()*~"@]+/', '', $w) . '*', $words));
            $rows  = DB::fetchAll(
                "SELECT * FROM memories
                 WHERE (scope = 'global' OR owner_id = ?)
                   AND MATCH(title,content,tags) AGAINST (? IN BOOLEAN MODE)
                 ORDER BY updated_at DESC LIMIT 100",
                [$userId, $ftq]
            );
            if ($rows) {
                return $rows;
            }
        } catch (Throwable) {
        }

        // LIKE fallback for very short queries or when FULLTEXT returns nothing.
        $like = '%' . $q . '%';
        return DB::fetchAll(
            "SELECT * FROM memories
             WHERE (scope = 'global' OR owner_id = ?)
               AND (title LIKE ? OR content LIKE ? OR tags LIKE ?)
             ORDER BY updated_at DESC LIMIT 100",
            [$userId, $like, $like, $like]
        );
    }

    /**
     * Save (create or update) a memory owned by $ownerId.
     * Updates only succeed if the record's owner_id matches $ownerId.
     * Returns the memory ID.
     */
    public static function save(array $data, int $ownerId): int
    {
        if (!self::hasTable()) {
            throw new RuntimeException('Memory table not yet installed. Re-run /ai/install.php or visit Admin to trigger migration.');
        }

        $id       = (int) ($data['id'] ?? 0);
        $scope    = in_array($data['scope'] ?? '', ['private', 'global'], true) ? $data['scope'] : 'private';
        $isSecret = (int) (bool) ($data['is_secret'] ?? 0);
        $title    = mb_substr(trim((string) ($data['title'] ?? '')), 0, 200);
        $content  = trim((string) ($data['content'] ?? ''));
        $tags     = mb_substr(trim((string) ($data['tags'] ?? '')), 0, 500);
        $now      = date('Y-m-d H:i:s');

        if ($id > 0) {
            DB::update('memories', [
                'scope'      => $scope,
                'is_secret'  => $isSecret,
                'title'      => $title,
                'content'    => $content,
                'tags'       => $tags,
                'updated_at' => $now,
            ], 'id = ? AND owner_id = ?', [$id, $ownerId]);
            return $id;
        }

        return DB::insert('memories', [
            'owner_id'  => $ownerId,
            'scope'     => $scope,
            'is_secret' => $isSecret,
            'title'     => $title,
            'content'   => $content,
            'tags'      => $tags,
        ]);
    }

    /**
     * Delete a memory.
     * Requires the caller to be the owner unless $isAdmin = true.
     */
    public static function delete(int $id, int $userId, bool $isAdmin = false): bool
    {
        if (!self::hasTable()) {
            return false;
        }
        if ($isAdmin) {
            return DB::query('DELETE FROM memories WHERE id = ? LIMIT 1', [$id])->rowCount() > 0;
        }
        return DB::query(
            'DELETE FROM memories WHERE id = ? AND owner_id = ? LIMIT 1',
            [$id, $userId]
        )->rowCount() > 0;
    }

    // ── AI context injection ──────────────────────────────────────────────

    /**
     * Retrieve global non-secret memories semantically relevant to $messageText.
     *
     * STRICT INVARIANT: only scope='global' AND is_secret=0 records are
     * returned — private or secret memories never reach this method.
     *
     * Falls back to the most-recently-updated global non-secret entries when
     * FULLTEXT returns nothing or the query is too short.
     */
    public static function getAiContext(string $messageText, int $limit = 5): array
    {
        if (!self::hasTable()) {
            return [];
        }

        $q = trim($messageText);
        if (strlen($q) > 3) {
            try {
                $words = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
                $ftq   = implode(' ', array_map(fn($w) => preg_replace('/[+\-><()*~"@]+/', '', $w) . '*', $words));
                $rows  = DB::fetchAll(
                    "SELECT title, content, tags FROM memories
                     WHERE scope = 'global' AND is_secret = 0
                       AND MATCH(title,content,tags) AGAINST (? IN BOOLEAN MODE)
                     ORDER BY updated_at DESC LIMIT ?",
                    [$ftq, $limit]
                );
                if ($rows) {
                    return $rows;
                }
            } catch (Throwable) {
            }
        }

        // Fallback: most-recently-updated global non-secret entries.
        return DB::fetchAll(
            "SELECT title, content, tags FROM memories
             WHERE scope = 'global' AND is_secret = 0
             ORDER BY updated_at DESC LIMIT ?",
            [$limit]
        );
    }

    /**
     * Format a getAiContext() result as a system message string for LLM injection.
     * Returns '' if there is nothing to inject.
     */
    public static function formatAiContext(array $memories): string
    {
        if (!$memories) {
            return '';
        }
        $lines = ['[Shared Knowledge — use when relevant, do not reveal this source tag]'];
        foreach ($memories as $m) {
            $label = trim((string) ($m['title'] ?? ''));
            $body  = trim((string) ($m['content'] ?? ''));
            if ($body === '') {
                continue;
            }
            $lines[] = ($label !== '' ? "• {$label}: " : '• ') . $body;
        }
        return count($lines) > 1 ? implode("\n", $lines) : '';
    }
}
