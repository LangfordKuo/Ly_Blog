<?php

namespace LyBlog\Core;

class Hook
{
    private static $actions = [];
    private static $filters = [];
    private static $executed = [];

    public static function addAction(string $hook, $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
        if (isset(self::$actions[$hook])) {
            ksort(self::$actions[$hook]);
        }
    }

    public static function addFilter(string $hook, $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
        if (isset(self::$filters[$hook])) {
            ksort(self::$filters[$hook]);
        }
    }

    public static function doAction(string $hook, ...$args): void
    {
        self::$executed[] = $hook;

        if (!isset(self::$actions[$hook])) {
            return;
        }

        foreach (self::$actions[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    call_user_func_array($callback, $args);
                }
            }
        }
    }

    public static function applyFilters(string $hook, $value, ...$args)
    {
        if (!isset(self::$filters[$hook])) {
            return $value;
        }

        array_unshift($args, $value);

        foreach (self::$filters[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    $args[0] = call_user_func_array($callback, $args);
                }
            }
        }

        return $args[0];
    }

    public static function removeAction(string $hook, $callback = null, int $priority = 10): bool
    {
        if (!isset(self::$actions[$hook][$priority])) {
            return false;
        }

        if ($callback === null) {
            unset(self::$actions[$hook][$priority]);
            return true;
        }

        foreach (self::$actions[$hook][$priority] as $key => $cb) {
            if ($cb === $callback) {
                unset(self::$actions[$hook][$priority][$key]);
                return true;
            }
        }

        return false;
    }

    public static function removeFilter(string $hook, $callback = null, int $priority = 10): bool
    {
        if (!isset(self::$filters[$hook][$priority])) {
            return false;
        }

        if ($callback === null) {
            unset(self::$filters[$hook][$priority]);
            return true;
        }

        foreach (self::$filters[$hook][$priority] as $key => $cb) {
            if ($cb === $callback) {
                unset(self::$filters[$hook][$priority][$key]);
                return true;
            }
        }

        return false;
    }

    public static function hasAction(string $hook): bool
    {
        return !empty(self::$actions[$hook]);
    }

    public static function hasFilter(string $hook): bool
    {
        return !empty(self::$filters[$hook]);
    }

    public static function didAction(string $hook): bool
    {
        return in_array($hook, self::$executed, true);
    }

    public static function removeAllActions(string $hook = null): void
    {
        if ($hook === null) {
            self::$actions = [];
        } else {
            unset(self::$actions[$hook]);
        }
    }

    public static function removeAllFilters(string $hook = null): void
    {
        if ($hook === null) {
            self::$filters = [];
        } else {
            unset(self::$filters[$hook]);
        }
    }

    public static function getActions(): array
    {
        return self::$actions;
    }

    public static function getFilters(): array
    {
        return self::$filters;
    }
}
