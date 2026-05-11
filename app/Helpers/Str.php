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

    /**
     * Generate Gravatar URL from email address.
     * @param string $email  User email
     * @param int    $size   Image size in pixels (default 80)
     * @param string $default Default image: mp, identicon, monsterid, wavatar, retro, robohash, 404
     * @param string $rating  Rating: g, pg, r, x
     */
    public static function gravatar(string $email, int $size = 80, string $default = 'mp', string $rating = 'g'): string
    {
        $hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}&r={$rating}";
    }

    /**
     * Parse shortcodes in content.
     * [video src] → embedded video
     * [audio src] → audio player
     * [alert type]content[/alert] → alert box
     * [collapse title]content[/collapse] → collapsible section
     */
    public static function shortcodes(string $content): string
    {
        // [video url]
        $content = preg_replace('/\[video\s+([^\]]+)\]/i', '<div style="position:relative;padding-bottom:56.25%;height:0;margin:16px 0"><iframe src="$1" style="position:absolute;top:0;left:0;width:100%;height:100%;border-radius:8px" frameborder="0" allowfullscreen></iframe></div>', $content);

        // [audio url]
        $content = preg_replace('/\[audio\s+([^\]]+)\]/i', '<audio controls src="$1" style="width:100%;margin:12px 0"></audio>', $content);

        // [alert type]...[/alert]
        $content = preg_replace('/\[alert\s+(info|success|warning|error)\](.*?)\[\/alert\]/is', '<div class="alert alert-$1">$2</div>', $content);

        // [collapse title]...[/collapse]
        $content = preg_replace('/\[collapse\s+([^\]]+)\](.*?)\[\/collapse\]/is', '<details style="margin:12px 0;border:1px solid var(--border);border-radius:8px;padding:12px 16px"><summary style="cursor:pointer;font-weight:600">$1</summary><div style="padding-top:8px">$2</div></details>', $content);

        return $content;
    }
}
