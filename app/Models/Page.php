<?php

namespace LyBlog\Models;

use LyBlog\Helpers\Str;

class Page extends Model
{
    protected static $table = 'pages';
    protected static $primaryKey = 'id';
    protected static $fillable = ['title', 'slug', 'content', 'status', 'author_id', 'template', 'sort_order'];

    public static function createPage(array $data): ?int
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        $data['slug'] = static::uniqueSlug($data['slug']);

        return static::create($data);
    }

    public static function updatePage($id, array $data): int
    {
        if (!empty($data['title']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        if (!empty($data['slug'])) {
            $data['slug'] = static::uniqueSlug($data['slug'], $id);
        }
        return static::update($id, $data);
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::where('slug', $slug);
    }

    public static function getPublished(string $orderBy = 'sort_order ASC'): array
    {
        return static::query("status = 'published'", [], $orderBy);
    }

    private static function uniqueSlug(string $slug, int $exceptId = null): string
    {
        $original = $slug;
        $counter = 1;

        $db = \LyBlog\Core\Database::getInstance();
        if ($db === null) return $slug;

        while (true) {
            $where = 'slug = ?';
            $params = [$slug];
            if ($exceptId) {
                $where .= ' AND id != ?';
                $params[] = $exceptId;
            }
            if (!$db->exists('pages', $where, $params)) {
                break;
            }
            $slug = $original . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
}
