<?php
/**
 * Settings — typed DB-backed key/value store.
 *
 * All values live in the `settings` table.
 * Sensitive keys (is_sensitive=1) can only be written by admins.
 */
class Settings
{
    private static array $cache  = [];
    private static bool  $loaded = false;

    /** Eager-load all settings into cache. */
    public static function loadAll(): void
    {
        if (self::$loaded) {
            return;
        }
        foreach (DB::fetchAll('SELECT * FROM settings') as $row) {
            self::$cache[$row['setting_key']] = $row;
        }
        self::$loaded = true;
    }

    /** Get a value by key, with optional default. */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadAll();
        if (!isset(self::$cache[$key])) {
            return $default;
        }
        return self::cast(
            self::$cache[$key]['setting_value'],
            self::$cache[$key]['setting_type']
        );
    }

    /**
     * Set a value.
     *
     * @param bool $byAi  Pass true when an AI wants to write — blocks sensitive keys.
     */
    public static function set(string $key, mixed $value, bool $byAi = false): bool
    {
        self::loadAll();
        $row = DB::fetch('SELECT * FROM settings WHERE setting_key = ?', [$key]);
        if (!$row || !$row['is_editable']) {
            return false;
        }
        if ($row['is_sensitive'] && ($byAi || !Auth::isAdmin())) {
            return false;
        }

        $strVal = is_array($value) || is_object($value)
            ? Util::jsonEncode($value)
            : (string) $value;

        DB::update('settings', [
            'setting_value' => $strVal,
            'updated_by'    => Auth::id(),
            'updated_at'    => Util::now(),
        ], 'setting_key = ?', [$key]);

        self::$cache[$key]['setting_value'] = $strVal;
        return true;
    }

    /** Return all rows for a given category prefix (e.g. 'ui'). */
    public static function getByCategory(string $category): array
    {
        self::loadAll();
        return array_filter(
            self::$cache,
            fn($row) => str_starts_with($row['setting_key'], $category . '.')
        );
    }

    /** Flush the in-memory cache (useful after bulk updates). */
    public static function flush(): void
    {
        self::$cache  = [];
        self::$loaded = false;
    }

    private static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int'   => (int) $value,
            'float' => (float) $value,
            'bool'  => in_array($value, ['1', 'true', 'yes'], true),
            'json'  => Util::jsonDecode((string) $value),
            default => $value,
        };
    }
}
