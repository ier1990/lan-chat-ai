<?php
/**
 * Minimal PDO wrapper.
 * Call DB::connect($cfg) once from bootstrap, then use static helpers everywhere.
 */
class DB
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): void
    {
        if (self::$pdo !== null) {
            return;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port']    ?? 3306,
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );
        self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new RuntimeException('DB not connected. Call DB::connect() first.');
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row !== false ? $row : null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $val = self::query($sql, $params)->fetchColumn();
        return $val !== false ? $val : null;
    }

    public static function insert(string $table, array $data): int
    {
        $cols  = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $slots = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($slots)", array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set  = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $stmt = self::query(
            "UPDATE `$table` SET $set WHERE $where",
            [...array_values($data), ...$whereParams]
        );
        return $stmt->rowCount();
    }

    public static function exists(string $sql, array $params = []): bool
    {
        return self::fetchColumn($sql, $params) !== null;
    }

    public static function beginTransaction(): void { self::pdo()->beginTransaction(); }
    public static function commit(): void           { self::pdo()->commit(); }
    public static function rollBack(): void         { self::pdo()->rollBack(); }
}
