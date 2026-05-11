<?php

namespace LyBlog\Core;

class Theme
{
    private static $configs = [];

    /**
     * Get all theme config (merged: theme.json defaults → DB values)
     */
    public static function config(string $themeName = null): array
    {
        $themeName = $themeName ?? Config::get('theme', 'Default');
        $cacheKey = 'config_' . $themeName;

        if (isset(self::$configs[$cacheKey])) {
            return self::$configs[$cacheKey];
        }

        // Load theme.json
        $themeDir = THEMES_DIR . '/' . $themeName;
        $jsonFile = $themeDir . '/theme.json';
        $schema = [];

        if (file_exists($jsonFile)) {
            $info = json_decode(file_get_contents($jsonFile), true);
            $schema = $info['config'] ?? [];
        }

        if (empty($schema)) {
            self::$configs[$cacheKey] = [];
            return [];
        }

        $result = [];
        foreach ($schema as $key => $def) {
            $result[$key] = $def['default'] ?? '';
        }

        // Override with DB values
        $db = Database::getInstance();
        if ($db) {
            $dbKey = 'theme_' . $themeName . '_config';
            $row = $db->fetch("SELECT `value` FROM {settings} WHERE `key` = ?", [$dbKey]);
            if ($row && !empty($row['value'])) {
                $saved = json_decode($row['value'], true);
                if (is_array($saved)) {
                    foreach ($saved as $key => $val) {
                        if (isset($result[$key])) {
                            $result[$key] = $val;
                        }
                    }
                }
            }
        }

        self::$configs[$cacheKey] = $result;
        return $result;
    }

    /**
     * Get a single config value.
     */
    public static function get(string $key, $default = null, string $themeName = null)
    {
        $config = self::config($themeName);
        return $config[$key] ?? $default;
    }

    /**
     * Get the config schema from theme.json
     */
    public static function schema(string $themeName = null): array
    {
        $themeName = $themeName ?? Config::get('theme', 'Default');
        $jsonFile = THEMES_DIR . '/' . $themeName . '/theme.json';

        if (!file_exists($jsonFile)) return [];

        $info = json_decode(file_get_contents($jsonFile), true);
        return $info['config'] ?? [];
    }

    /**
     * Save theme config to database.
     */
    public static function save(array $values, string $themeName = null): void
    {
        $themeName = $themeName ?? Config::get('theme', 'Default');
        $db = Database::getInstance();
        if (!$db) return;

        // Clear cache
        unset(self::$configs['config_' . $themeName]);

        $dbKey = 'theme_' . $themeName . '_config';
        $json = json_encode($values, JSON_UNESCAPED_UNICODE);

        $existing = $db->fetch("SELECT id FROM {settings} WHERE `key` = ?", [$dbKey]);
        if ($existing) {
            $db->update('settings', ['value' => $json], '`key` = ?', [$dbKey]);
        } else {
            $db->insert('settings', ['key' => $dbKey, 'value' => $json]);
        }

        Config::clearDbCache();
    }

    /**
     * Render inline CSS variables from theme config.
     */
    public static function cssVars(string $themeName = null): string
    {
        $config = self::config($themeName);

        $vars = '';

        // Primary color
        if (!empty($config['primary_color'])) {
            $vars .= '--accent:' . $config['primary_color'] . ';';
        }

        // Dark mode
        if (($config['dark_mode'] ?? 'light') === 'dark') {
            $vars .= '--bg:#1c1c1e;--card-bg:rgba(44,44,46,0.85);--text:#f5f5f7;--text-2:#aeaeb2;--border:rgba(255,255,255,0.08);';
            $vars .= '--shadow:0 4px 24px rgba(0,0,0,0.2);--accent:#f5f5f7;';
        }

        if (!empty($vars)) {
            $vars = ':root{' . $vars . '}';
        }

        return $vars;
    }

    /**
     * Get layout class based on theme config.
     */
    public static function bodyClass(string $themeName = null): string
    {
        $config = self::config($themeName);
        $classes = [];

        $layout = $config['layout'] ?? 'standard';
        if ($layout !== 'standard') {
            $classes[] = 'layout-' . $layout;
        }

        $dark = $config['dark_mode'] ?? 'light';
        if ($dark === 'dark') {
            $classes[] = 'dark-mode';
        } elseif ($dark === 'auto') {
            $classes[] = 'auto-dark';
        }

        return implode(' ', $classes);
    }
}
