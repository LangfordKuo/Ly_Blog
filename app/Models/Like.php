<?php

namespace LyBlog\Models;

use LyBlog\Core\Database;

class Like extends Model
{
    protected static $table = 'likes';
    protected static $primaryKey = 'id';
    protected static $fillable = ['article_id', 'user_id', 'ip'];

    public static function toggle(int $articleId, ?int $userId, string $ip): bool
    {
        $db = Database::getInstance();
        if ($db === null) return false;

        $existing = $db->fetch(
            "SELECT * FROM {likes} WHERE article_id = ? AND ip = ?",
            [$articleId, $ip]
        );

        if ($existing) {
            $db->delete('likes', 'id = ?', [$existing['id']]);
            $db->query("UPDATE {articles} SET likes = GREATEST(likes - 1, 0) WHERE id = ?", [$articleId]);
            return false;
        }

        $db->insert('likes', [
            'article_id' => $articleId,
            'user_id'    => $userId,
            'ip'         => $ip,
        ]);
        $db->query("UPDATE {articles} SET likes = likes + 1 WHERE id = ?", [$articleId]);
        return true;
    }

    public static function isLiked(int $articleId, string $ip): bool
    {
        $db = Database::getInstance();
        if ($db === null) return false;

        return $db->exists('likes', 'article_id = ? AND ip = ?', [$articleId, $ip]);
    }

    public static function getCount(int $articleId): int
    {
        return static::count('article_id = ?', [$articleId]);
    }

    public static function getByArticle(int $articleId): array
    {
        return static::getBy('article_id', $articleId);
    }
}
