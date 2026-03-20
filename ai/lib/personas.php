<?php
/**
 * Personas — AI persona management and retrieval.
 */
class Personas
{
    public static function getAll(bool $enabledOnly = true): array
    {
        return $enabledOnly
            ? DB::fetchAll('SELECT * FROM personas WHERE is_enabled = 1 ORDER BY name')
            : DB::fetchAll('SELECT * FROM personas ORDER BY name');
    }

    public static function getById(int $id): ?array
    {
        return DB::fetch('SELECT * FROM personas WHERE id = ?', [$id]);
    }

    public static function getByKey(string $key): ?array
    {
        return DB::fetch('SELECT * FROM personas WHERE persona_key = ?', [$key]);
    }

    public static function getDefault(): ?array
    {
        return DB::fetch(
            'SELECT * FROM personas WHERE is_default = 1 AND is_enabled = 1 LIMIT 1'
        );
    }

    /** Decoded settings_json for a persona, or empty array. */
    public static function settings(int $personaId): array
    {
        $persona = self::getById($personaId);
        return Util::jsonDecode($persona['settings_json'] ?? '') ?? [];
    }
}
