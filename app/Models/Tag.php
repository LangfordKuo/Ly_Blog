<?php

namespace LyBlog\Models;

use LyBlog\Helpers\Str;

class Tag extends Model
{
    protected static $table = 'tags';
    protected static $primaryKey = 'id';
    protected static $fillable = ['name', 'slug'];

    public static function createTag(string $name): ?int
    {
        $slug = Str::slug($name);

        $existing = static::findBySlug($slug);
        if ($existing) return $existing['id'];

        return static::create(['name' => $name, 'slug' => $slug]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::where('slug', $slug);
    }

    public static function updateTag($id, array $data): int
    {
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return static::update($id, $data);
    }

    public static function getCloud(int $limit = 50): array
    {
        $db = \LyBlog\Core\Database::getInstance();
        if ($db === null) return [];

        return $db->fetchAll(
            "SELECT t.*, COUNT(at.article_id) as article_count 
             FROM {tags} t 
             LEFT JOIN {article_tag} at ON t.id = at.tag_id 
             GROUP BY t.id 
             HAVING article_count > 0 
             ORDER BY article_count DESC 
             LIMIT {$limit}"
        );
    }

    public static function getByArticle(int $articleId): array
    {
        $db = \LyBlog\Core\Database::getInstance();
        if ($db === null) return [];

        return $db->fetchAll(
            "SELECT t.* FROM {tags} t 
             INNER JOIN {article_tag} at ON t.id = at.tag_id 
             WHERE at.article_id = ? 
             ORDER BY t.name",
            [$articleId]
        );
    }

    public static function getArticleCount(int $tagId): int
    {
        $db = \LyBlog\Core\Database::getInstance();
        if ($db === null) return 0;

        return $db->fetchColumn(
            "SELECT COUNT(*) FROM {article_tag} at 
             INNER JOIN {articles} a ON a.id = at.article_id 
             WHERE at.tag_id = ? AND a.status = 'published'",
            [$tagId]
        );
    }

    public static function findOrCreate(string $name): array
    {
        $slug = Str::slug($name);
        $existing = static::findBySlug($slug);
        if ($existing) return $existing;

        $id = static::create(['name' => $name, 'slug' => $slug]);
        return static::find($id);
    }
}
