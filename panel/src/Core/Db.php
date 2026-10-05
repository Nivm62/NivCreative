<?php
declare(strict_types=1);

namespace Nivc\Core;

use PDO;

/** Thin PDO wrapper. Every value goes through prepared statements. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function connect(?array $cfg = null): PDO
    {
        if (self::$pdo === null) {
            $c   = $cfg ?? Config::get('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int) $c['port'], $c['name'], $c['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
            self::$pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::connect()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::connect()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $st = self::connect()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::connect()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** @param array<string,mixed> $data column => value */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        self::assertIdent($table);
        foreach ($cols as $c) {
            self::assertIdent($c);
        }
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $st  = self::connect()->prepare($sql);
        $st->execute(array_values($data));
        return (int) self::connect()->lastInsertId();
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $where equality conditions */
    public static function update(string $table, array $data, array $where): int
    {
        self::assertIdent($table);
        $set = [];
        $params = [];
        foreach ($data as $c => $v) {
            self::assertIdent($c);
            $set[] = '`' . $c . '` = ?';
            $params[] = $v;
        }
        $cond = [];
        foreach ($where as $c => $v) {
            self::assertIdent($c);
            $cond[] = '`' . $c . '` = ?';
            $params[] = $v;
        }
        return self::exec('UPDATE `' . $table . '` SET ' . implode(',', $set) . ' WHERE ' . implode(' AND ', $cond), $params);
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::connect();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Identifiers can't be bound, so they are validated against a strict pattern. */
    private static function assertIdent(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid identifier');
        }
    }
}
