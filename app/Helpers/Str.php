<?php

namespace LyBlog\Helpers;

class Str
{
    public static function slug(string $text, string $separator = '-'): string
    {
        $text = preg_replace('~[^\pL\d]+~u', $separator, $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, $separator);
        $text = preg_replace('~-+~', $separator, $text);
        $text = strtolower($text);
        return $text ?: 'n-a';
    }

    public static function excerpt(string $text, int $length = 200, string $suffix = '...'): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $excerpt = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($excerpt, ' ');

        if ($lastSpace !== false) {
            $excerpt = mb_substr($excerpt, 0, $lastSpace);
        }

        return $excerpt . $suffix;
    }

    public static function random(int $length = 32): string
    {
        $bytes = random_bytes(ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    public static function limit(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $suffix;
    }
}
