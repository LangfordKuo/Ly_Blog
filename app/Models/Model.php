<?php

namespace LyBlog\Models;

use LyBlog\Core\Database;

abstract class Model
{
    protected static $table;
    protected static $primaryKey = 'id';
    protected static $fillable = [];
    protected static $timestamps = true;

    public static function table(): string
    {
        if (static::$table === null) {
            $class = (new \ReflectionClass(static::class))->getShortName();
            static::$table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
        }
        return static::$table;
    }

    public static function tableRaw(): string
    {
        $db = Database::getInstance();
        return $db->getPrefix() . static::table();
    }

    public static function find($id): ?array
    {
        $db = Database::getInstance();
        if ($db === null) return null;

        return $db->fetch(
            "SELECT * FROM `{" . static::table() . "}` WHERE `" . static::$primaryKey . "` = ? LIMIT 1",
            [$id]
        );
    }

    public static function all(string $orderBy = ''): array
    {
        $db = Database::getInstance();
        if ($db === null) return [];

        $sql = "SELECT * FROM `{" . static::table() . "}`";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        return $db->fetchAll($sql);
    }

    public static function where(string $column, $value): ?array
    {
        $db = Database::getInstance();
        if ($db === null) return null;

        return $db->fetch(
            "SELECT * FROM `{" . static::table() . "}` WHERE `{$column}` = ? LIMIT 1",
            [$value]
        );
    }

    public static function getBy(string $column, $value): array
    {
        $db = Database::getInstance();
        if ($db === null) return [];

        return $db->fetchAll(
            "SELECT * FROM `{" . static::table() . "}` WHERE `{$column}` = ?",
            [$value]
        );
    }

    public static function query(string $where, array $params = [], string $orderBy = ''): array
    {
        $db = Database::getInstance();
        if ($db === null) return [];

        $sql = "SELECT * FROM `{" . static::table() . "}` WHERE {$where}";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        return $db->fetchAll($sql, $params);
    }

    public static function create(array $data): ?int
    {
        $db = Database::getInstance();
        if ($db === null) return null;

        $filtered = [];
        foreach (static::$fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }

        if (empty($filtered) && !empty($data)) {
            $filtered = $data;
        }

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!isset($filtered['created_at'])) {
                $filtered['created_at'] = $now;
            }
            if (!isset($filtered['updated_at'])) {
                $filtered['updated_at'] = $now;
            }
        }

        return $db->insert(static::table(), $filtered);
    }

    public static function update($id, array $data): int
    {
        $db = Database::getInstance();
        if ($db === null) return 0;

        $filtered = [];
        foreach (static::$fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }

        if (empty($filtered) && !empty($data)) {
            $filtered = $data;
        }

        if (static::$timestamps && !isset($filtered['updated_at'])) {
            $filtered['updated_at'] = date('Y-m-d H:i:s');
        }

        return $db->update(static::table(), $filtered, "`" . static::$primaryKey . "` = ?", [$id]);
    }

    public static function delete($id): int
    {
        $db = Database::getInstance();
        if ($db === null) return 0;

        return $db->delete(static::table(), "`" . static::$primaryKey . "` = ?", [$id]);
    }

    public static function count(string $where = '1', array $params = []): int
    {
        $db = Database::getInstance();
        if ($db === null) return 0;

        return $db->count(static::table(), $where, $params);
    }

    public static function exists(string $where, array $params = []): bool
    {
        $db = Database::getInstance();
        if ($db === null) return false;

        return $db->exists(static::table(), $where, $params);
    }

    public static function paginate(int $page = 1, int $perPage = 20, string $where = '1', array $params = [], string $orderBy = ''): array
    {
        $db = Database::getInstance();
        if ($db === null) return [];

        $total = static::count($where, $params);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM `{" . static::table() . "}` WHERE {$where}";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $items = $db->fetchAll($sql, $params);

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'items'      => $items,
            'total'      => $total,
            'per_page'   => $perPage,
            'current_page' => $page,
            'last_page'  => $lastPage,
            'has_more'   => $page < $lastPage,
        ];
    }
}
