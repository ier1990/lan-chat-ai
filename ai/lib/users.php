<?php
/**
 * Users — user creation, retrieval, and role management.
 */
class Users
{
    public static function getAll(bool $activeOnly = true): array
    {
        return $activeOnly
            ? DB::fetchAll(
                'SELECT u.id, u.username, u.display_name, u.is_active, u.created_at,
                        COALESCE(r.role_key, "member") AS role_key
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 WHERE u.is_active = 1
                 ORDER BY u.display_name'
              )
            : DB::fetchAll(
                'SELECT u.id, u.username, u.display_name, u.is_active, u.created_at,
                        COALESCE(r.role_key, "member") AS role_key
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 ORDER BY u.display_name'
              );
    }

    public static function getById(int $id): ?array
    {
        return DB::fetch(
            'SELECT u.id, u.username, u.display_name, u.is_active, u.created_at,
                    COALESCE(r.role_key, "member") AS role_key
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ?',
            [$id]
        );
    }

    public static function getByUsername(string $username): ?array
    {
        return DB::fetch(
            'SELECT id, username, display_name, is_active FROM users WHERE username = ?',
            [trim($username)]
        );
    }

    /**
     * Create a user and assign a role. Returns new user id.
     * Password is hashed with bcrypt.
     */
    public static function create(
        string $username,
        string $displayName,
        string $password,
        string $role = 'member'
    ): int {
        $userId = DB::insert('users', [
            'username'      => trim($username),
            'display_name'  => trim($displayName),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'is_active'     => 1,
        ]);
        self::setRole($userId, $role);
        return $userId;
    }

    public static function setPassword(int $userId, string $password): void
    {
        DB::update(
            'users',
            ['password_hash' => password_hash($password, PASSWORD_BCRYPT)],
            'id = ?',
            [$userId]
        );
    }

    /** Replace all existing roles for this user with the given role. */
    public static function setRole(int $userId, string $role): void
    {
        $roleRow = DB::fetch('SELECT id FROM roles WHERE role_key = ?', [$role]);
        if (!$roleRow) {
            return;
        }
        DB::query('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
        DB::insert('user_roles', ['user_id' => $userId, 'role_id' => $roleRow['id']]);
    }

    public static function getRoleKey(int $userId): string
    {
        return (string) (DB::fetchColumn(
            'SELECT r.role_key FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ? LIMIT 1',
            [$userId]
        ) ?? 'member');
    }

    public static function getRoles(): array
    {
        return DB::fetchAll('SELECT role_key, name FROM roles ORDER BY id');
    }

    public static function countByRole(string $role): int
    {
        return (int) (DB::fetchColumn(
            'SELECT COUNT(*) FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE u.is_active = 1 AND r.role_key = ?',
            [$role]
        ) ?? 0);
    }

    public static function delete(int $userId): void
    {
        DB::query('DELETE FROM users WHERE id = ?', [$userId]);
    }
}
