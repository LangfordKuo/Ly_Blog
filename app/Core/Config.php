<?php

namespace LyBlog\Core;

class Config
{
    private static $items = [];
    private static $loaded = false;
    private static $dbSettings = null;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $appConfigFile      = ROOT_DIR . '/config/app.php';
        $databaseConfigFile = ROOT_DIR . '/config/database.php';

        $appConfig      = file_exists($appConfigFile) ? require $appConfigFile : [];
        $databaseConfig = file_exists($databaseConfigFile) ? require $databaseConfigFile : [];

        self::$items = array_merge($appConfig, $databaseConfig);
        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        if (array_key_exists($key, self::$items)) {
            return self::$items[$key];
        }

        $segments = explode('.', $key);
        $value    = self::$items;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                // Not found in file config, try database
                $dbValue = self::getDb($key);
                if ($dbValue !== null) {
                    return $dbValue;
                }
                return $default;
            }
        }

        return $value;
    }

    private static function getDb(string $key): ?string
    {
        if (self::$dbSettings === null) {
            self::loadDb();
        }

        return self::$dbSettings[$key] ?? null;
    }

    private static function loadDb(): void
    {
        self::$dbSettings = [];

        try {
            $db = Database::getInstance();
            if ($db === null) return;

            $rows = $db->fetchAll("SELECT `key`, `value` FROM {settings}");
            foreach ($rows as $row) {
                self::$dbSettings[$row['key']] = $row['value'];
            }
        } catch (\Exception $e) {
            // DB not available yet
        }
    }

    public static function clearDbCache(): void
    {
        self::$dbSettings = null;
    }

    public static function set(string $key, $value): void
    {
        if (!self::$loaded) {
            self::load();
        }

        $segments = explode('.', $key);
        $current  = &self::$items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    public static function all(): array
    {
        if (!self::$loaded) {
            self::load();
        }
        return self::$items;
    }

    public static function isInstalled(): bool
    {
        $appConfigFile      = ROOT_DIR . '/config/app.php';
        $databaseConfigFile = ROOT_DIR . '/config/database.php';
        return file_exists($appConfigFile) && file_exists($databaseConfigFile);
    }

    public static function writeAppConfig(array $data): bool
    {
        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        return file_put_contents(ROOT_DIR . '/config/app.php', $content, LOCK_EX) !== false;
    }

    public static function writeDatabaseConfig(array $data): bool
    {
        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        return file_put_contents(ROOT_DIR . '/config/database.php', $content, LOCK_EX) !== false;
    }
}
