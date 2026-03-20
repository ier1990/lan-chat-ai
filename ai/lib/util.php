<?php
/**
 * General-purpose utilities: slugging, tokens, CSRF, output helpers.
 */
class Util
{
    public static function slug(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** HTML-escape for safe output. */
    public static function e(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function jsonEncode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function jsonDecode(?string $json, bool $assoc = true): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }
        return json_decode($json, $assoc);
    }

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    public static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /** Send a JSON response and exit. */
    public static function jsonResponse(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Return or generate the session CSRF token. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::token(32);
        }
        return $_SESSION['csrf_token'];
    }

    /** Verify a submitted CSRF token against the session token. */
    public static function csrfVerify(?string $token): bool
    {
        return $token !== null
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Abort an AJAX request if the CSRF token is missing or invalid. */
    public static function requireCsrf(): void
    {
        $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!self::csrfVerify($token)) {
            self::jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }
    }

    public static function post(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $default));
    }

    public static function get(string $key, string $default = ''): string
    {
        return trim((string) ($_GET[$key] ?? $default));
    }
}
