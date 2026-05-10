<?php

namespace LyBlog\Core;

class Sanitizer
{
    public static function text($input): string
    {
        if (is_array($input)) {
            return '';
        }
        return htmlspecialchars((string) $input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function html($input): string
    {
        if (is_array($input)) {
            return '';
        }

        $allowedTags = '<p><br><strong><b><em><i><u><s><del><a><img><ul><ol><li><blockquote><pre><code><h1><h2><h3><h4><h5><h6><hr><table><thead><tbody><tr><th><td><span><div><sub><sup><dl><dt><dd>';
        $allowedAttrs = ['href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel', 'width', 'height', 'colspan', 'rowspan', 'start', 'type', 'lang', 'dir'];

        $html = strip_tags((string) $input, $allowedTags);

        // Remove event handlers
        $html = preg_replace('/\s+on\w+\s*=\s*(["\']).*?(\1)/is', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/is', '', $html);

        // Remove javascript: URLs
        $html = preg_replace('/href\s*=\s*(["\'])javascript:[^"\']*(\1)/is', 'href="#"', $html);
        $html = preg_replace('/src\s*=\s*(["\'])javascript:[^"\']*(\1)/is', 'src="#"', $html);

        // Filter remaining attributes
        $html = preg_replace_callback('/<[^>]+>/', function ($m) use ($allowedAttrs) {
            $tag = $m[0];
            $tag = preg_replace_callback('/\s+(\w+)\s*=\s*(["\'])(.*?)(\2)/is', function ($am) use ($allowedAttrs) {
                $attr = strtolower($am[1]);
                if (!in_array($attr, $allowedAttrs)) {
                    return '';
                }
                $value = $am[3];
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                return ' ' . $attr . '="' . $value . '"';
            }, $tag);
            return $tag;
        }, $html);

        return $html;
    }

    public static function markdownSafe($input): string
    {
        if (is_array($input)) {
            return '';
        }
        return self::html((string) $input);
    }

    public static function int($input): int
    {
        return (int) $input;
    }

    public static function float($input): float
    {
        return (float) $input;
    }

    public static function email($input): string
    {
        return filter_var((string) $input, FILTER_SANITIZE_EMAIL);
    }

    public static function url($input): string
    {
        return filter_var((string) $input, FILTER_SANITIZE_URL);
    }

    public static function slug($input): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '-', (string) $input);
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug);
        return strtolower($slug);
    }

    public static function filename($input): string
    {
        $name = (string) $input;
        $name = preg_replace('/[^\p{L}\p{N}\._\-]+/u', '-', $name);
        $name = trim($name, '-_.');
        return $name ?: 'file';
    }

    public static function alpha($input): string
    {
        return preg_replace('/[^a-zA-Z]+/', '', (string) $input);
    }

    public static function alphanumeric($input): string
    {
        return preg_replace('/[^a-zA-Z0-9]+/', '', (string) $input);
    }

    public static function digits($input): string
    {
        return preg_replace('/[^0-9]+/', '', (string) $input);
    }

    public static function trim($input): string
    {
        return trim((string) $input);
    }

    public static function stripTags($input): string
    {
        return strip_tags((string) $input);
    }

    public static function array(array $input): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return self::text($value);
            }
            if (is_array($value)) {
                return self::array($value);
            }
            return $value;
        }, $input);
    }

    public static function sqlLike(string $input): string
    {
        return addcslashes($input, '%_\\');
    }

    public static function ip(string $input): string
    {
        return preg_replace('/[^0-9a-fA-F.:]/', '', (string) $input);
    }

    public static function clean($input)
    {
        if (is_array($input)) {
            return self::array($input);
        }
        if (is_numeric($input)) {
            return $input + 0;
        }
        return self::text((string) $input);
    }
}
