<?php
/**
 * Auth — session-based authentication and role-checking.
 * Call Auth::init() once after session_start().
 */
class Auth
{
    private static ?array $user  = null;
    private static array  $roles = [];

    /** Reload current user from session, or clear state if session is stale. */
    public static function init(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }
        $user = DB::fetch(
            'SELECT * FROM users WHERE id = ? AND is_active = 1',
            [(int) $_SESSION['user_id']]
        );
        if (!$user) {
            self::logout();
            return;
        }
        self::$user  = $user;
        self::$roles = self::loadRoles((int) $user['id']);
    }

    /** Attempt login. Returns true on success. */
    public static function attempt(string $username, string $password): bool
    {
        $user = DB::fetch(
            'SELECT * FROM users WHERE username = ? AND is_active = 1',
            [trim($username)]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        self::$user  = $user;
        self::$roles = self::loadRoles((int) $user['id']);
        return true;
    }

    public static function logout(): void
    {
        self::$user  = null;
        self::$roles = [];
        unset($_SESSION['user_id'], $_SESSION['csrf_token']);
        session_regenerate_id(true);
    }

    public static function user(): ?array  { return self::$user; }
    public static function id(): ?int      { return self::$user ? (int) self::$user['id'] : null; }
    public static function check(): bool   { return self::$user !== null; }
    public static function roles(): array  { return self::$roles; }

    public static function hasRole(string $role): bool
    {
        return in_array($role, self::$roles, true);
    }

    public static function isAdmin(): bool { return self::hasRole('admin'); }

    /** Redirect to login page if not authenticated. */
    public static function requireLogin(string $redirect = '/ai/'): void
    {
        if (!self::check()) {
            Util::redirect('/ai/index.php?login=1');
        }
    }

    /** Die with 403 if the current user is not an admin. */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Forbidden');
        }
    }

    private static function loadRoles(int $userId): array
    {
        $rows = DB::fetchAll(
            'SELECT r.role_key FROM roles r
             JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ?',
            [$userId]
        );
        return array_column($rows, 'role_key');
    }
}
