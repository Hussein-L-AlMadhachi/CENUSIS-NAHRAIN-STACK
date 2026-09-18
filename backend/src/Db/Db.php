<?php

declare(strict_types=1);

namespace Cenusis\Db;

use PDO;
use PDOStatement;
use Throwable;

/**
 * PDO MySQL singleton. Configuration comes from environment variables:
 *   DB_HOST (default: mysql), DB_PORT (default: 3306),
 *   DB_NAME (default: cenusis_ops), DB_USER (default: dev),
 *   DB_PASSWORD (default: dev123456)
 */
final class Db
{
    private static ?Db $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'cenusis_ops';
        $user = getenv('DB_USER') ?: 'dev';
        $password = getenv('DB_PASSWORD') ?: 'dev123456';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function pdo(): PDO
    {
        return self::instance()->pdo;
    }

    /**
     * Normalize bind parameters for MySQL. postgres.js accepted PHP-like
     * booleans natively; PDO would bind `false` as '' and fail strict-mode
     * inserts, so convert bools to 0/1 ints globally.
     *
     * @param array<int, mixed> $params
     * @return array<int, mixed>
     */
    private static function normalizeParams(array $params): array
    {
        foreach ($params as $i => $param) {
            if (is_bool($param)) {
                $params[$i] = $param ? 1 : 0;
            }
        }
        return $params;
    }

    /**
     * Run a SELECT (or any statement that produces rows).
     *
     * @return array<int, array<string, mixed>> list of assoc rows
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Run a SELECT and return the first row or null.
     *
     * @return array<string, mixed>|null
     */
    public static function first(string $sql, array $params = []): ?array
    {
        $rows = self::query($sql, $params);
        return $rows[0] ?? null;
    }

    /**
     * Run an INSERT/UPDATE/DELETE.
     *
     * @return array{stmt: PDOStatement, lastInsertId: string}
     */
    public static function execute(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute(self::normalizeParams($params));
        return [
            'stmt' => $stmt,
            'lastInsertId' => self::pdo()->lastInsertId(),
        ];
    }

    /**
     * Run $fn inside a transaction. The Db instance is passed to $fn.
     * Commits on success, rolls back and re-throws on any error.
     *
     * $isolation (e.g. 'SERIALIZABLE') is applied before BEGIN, mirroring
     * postgres.js's sql.begin({ isolation }) which sets the level before
     * starting the transaction. MySQL rejects SET TRANSACTION ISOLATION
     * inside an active transaction.
     */
    public static function transaction(callable $fn, ?string $isolation = null): mixed
    {
        $pdo = self::pdo();
        if ($isolation !== null) {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL ' . $isolation);
        }
        $pdo->beginTransaction();
        try {
            $result = $fn(self::instance());
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
