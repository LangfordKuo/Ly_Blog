<?php

namespace LyBlog\Core;

class Plugin
{
    private static $loaded = [];
    private static $instances = [];

    public static function loadAll(): void
    {
        $pluginDir = PLUGINS_DIR;
        if (!is_dir($pluginDir)) return;

        $db = Database::getInstance();
        $activeSlugs = [];

        if ($db) {
            $rows = $db->fetchAll("SELECT slug FROM {plugins} WHERE status = 1");
            foreach ($rows as $row) $activeSlugs[] = $row['slug'];
        }

        foreach (scandir($pluginDir) as $name) {
            if ($name === '.' || $name === '..') continue;
            $dir = $pluginDir . '/' . $name;
            if (!is_dir($dir)) continue;

            $jsonFile = $dir . '/plugin.json';
            if (!file_exists($jsonFile)) continue;

            $info = json_decode(file_get_contents($jsonFile), true);
            $slug = $info['slug'] ?? $name;

            if (!in_array($slug, $activeSlugs)) continue;

            $mainFile = $dir . '/' . ($info['main'] ?? 'Plugin.php');
            if (!file_exists($mainFile)) continue;

            require_once $mainFile;

            $className = $info['class'] ?? null;
            if ($className && class_exists($className)) {
                self::$instances[$slug] = new $className();
            }

            self::$loaded[] = $slug;
        }
    }

    public static function getLoaded(): array
    {
        return self::$loaded;
    }

    public static function isLoaded(string $slug): bool
    {
        return in_array($slug, self::$loaded);
    }

    public static function getInstance(string $slug)
    {
        return self::$instances[$slug] ?? null;
    }
}
