<?php

namespace LyBlog\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;
    private $prefix;

    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['dbname'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        $this->pdo    = new PDO($dsn, $config['user'], $config['pass'], $options);
        $this->prefix = $config['prefix'] ?? '';
    }

    public static function getInstance(array $config = null): ?self
    {
        if (self::$instance === null) {
            if ($config === null) {
                return null;
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public static function connect(array $config): self
    {
        $instance = new self($config);
        self::$instance = $instance;
        return $instance;
    }

    public static function testConnection(array $config): bool
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $config['host'],
                $config['port'] ?? 3306,
                $config['charset'] ?? 'utf8mb4'
            );
            new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function testDatabase(array $config): bool
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'] ?? 3306,
                $config['dbname'],
                $config['charset'] ?? 'utf8mb4'
            );
            new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $sql = $this->replacePrefix($sql);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$this->prefix}{$table}` (`{$columns}`) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "`{$col}` = ?";
            $params[] = $val;
        }
        $setStr = implode(', ', $sets);
        $sql = "UPDATE `{$this->prefix}{$table}` SET {$setStr} WHERE {$where}";
        $stmt = $this->query($sql, array_merge($params, $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `{$this->prefix}{$table}` WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0)
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }

    public function count(string $table, string $where = '1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->prefix}{$table}` WHERE {$where}";
        return (int) $this->fetchColumn($sql, $params);
    }

    public function exists(string $table, string $where, array $params = []): bool
    {
        return $this->count($table, $where, $params) > 0;
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    private function replacePrefix(string $sql): string
    {
        $sql = str_replace('{prefix}', $this->prefix, $sql);
        return preg_replace('/\{([a-zA-Z_]\w*)\}/', $this->prefix . '$1', $sql);
    }
}
