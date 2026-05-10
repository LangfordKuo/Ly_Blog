<?php

namespace LyBlog\Models;

use LyBlog\Core\Database;
use LyBlog\Helpers\Str;

class Article extends Model
{
    protected static $table = 'articles';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'title', 'slug', 'content', 'excerpt', 'cover_image',
        'status', 'author_id', 'category_id', 'views', 'likes',
        'password', 'published_at',
    ];

    public static function createArticle(array $data): ?int
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        $data['slug'] = static::uniqueSlug($data['slug']);

        if (empty($data['excerpt'])) {
            $data['excerpt'] = Str::excerpt(strip_tags($data['content'] ?? ''), 200);
        }

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        if ($data['status'] === 'scheduled') {
            if (empty($data['published_at'])) {
                $data['published_at'] = date('Y-m-d H:i:s', strtotime('+1 hour'));
            }
            $data['status'] = 'draft';
        }

        if (empty($data['category_id'])) {
            $data['category_id'] = null;
        }

        $id = static::create($data);

        if ($id && !empty($data['tags'])) {
            static::syncTags($id, $data['tags']);
        }

        return $id;
    }

    public static function updateArticle($id, array $data): int
    {
        if (!empty($data['title']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        if (!empty($data['slug'])) {
            $data['slug'] = static::uniqueSlug($data['slug'], $id);
        }

        if (!empty($data['content']) && empty($data['excerpt'])) {
            $data['excerpt'] = Str::excerpt(strip_tags($data['content']), 200);
        }

        if (isset($data['status']) && $data['status'] === 'published') {
            $existing = static::find($id);
            if ($existing && $existing['status'] !== 'published') {
                $data['published_at'] = $data['published_at'] ?? date('Y-m-d H:i:s');
            }
        }

        if (array_key_exists('category_id', $data) && empty($data['category_id'])) {
            $data['category_id'] = null;
        }

        $affected = static::update($id, $data);

        if (isset($data['tags'])) {
            static::syncTags($id, $data['tags']);
        }

        return $affected;
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::where('slug', $slug);
    }

    public static function getPublished(int $page = 1, int $perPage = 10, int $categoryId = 0, string $orderBy = 'published_at DESC'): array
    {
        $where = "status = 'published' AND published_at <= NOW()";
        $params = [];

        if ($categoryId > 0) {
            $where .= " AND category_id = ?";
            $params[] = $categoryId;
        }

        return static::paginate($page, $perPage, $where, $params, $orderBy);
    }

    public static function getByTag(string $tagSlug, int $page = 1, int $perPage = 10): array
    {
        $db = Database::getInstance();
        if ($db === null) return static::emptyPaginate($perPage);

        $tag = Tag::findBySlug($tagSlug);
        if (!$tag) return static::emptyPaginate($perPage);

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {articles} a 
             INNER JOIN {article_tag} at ON a.id = at.article_id 
             WHERE at.tag_id = ? AND a.status = 'published' AND a.published_at <= NOW()",
            [$tag['id']]
        );

        $offset = ($page - 1) * $perPage;
        $items = $db->fetchAll(
            "SELECT a.* FROM {articles} a 
             INNER JOIN {article_tag} at ON a.id = at.article_id 
             WHERE at.tag_id = ? AND a.status = 'published' AND a.published_at <= NOW() 
             ORDER BY a.published_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$tag['id']]
        );

        $lastPage = max(1, (int) ceil($total / $perPage));
        return [
            'items'        => $items,
            'total'        => (int) $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'has_more'     => $page < $lastPage,
        ];
    }

    public static function search(string $query, int $page = 1, int $perPage = 10): array
    {
        $db = Database::getInstance();
        if ($db === null) return static::emptyPaginate($perPage);

        $q = '%' . $query . '%';
        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM {articles} WHERE status = 'published' AND published_at <= NOW() AND (title LIKE ? OR content LIKE ?)",
            [$q, $q]
        );

        $offset = ($page - 1) * $perPage;
        $items = $db->fetchAll(
            "SELECT * FROM {articles} WHERE status = 'published' AND published_at <= NOW() AND (title LIKE ? OR content LIKE ?) ORDER BY published_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$q, $q]
        );

        $lastPage = max(1, (int) ceil($total / $perPage));
        return [
            'items'        => $items,
            'total'        => (int) $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'has_more'     => $page < $lastPage,
        ];
    }

    public static function getArchives(): array
    {
        $db = Database::getInstance();
        if ($db === null) return [];

        return $db->fetchAll(
            "SELECT DATE_FORMAT(published_at, '%Y') as year, DATE_FORMAT(published_at, '%m') as month, COUNT(*) as count 
             FROM {articles} 
             WHERE status = 'published' AND published_at <= NOW() 
             GROUP BY year, month 
             ORDER BY year DESC, month DESC"
        );
    }

    public static function getByArchive(string $year, string $month = null, int $page = 1, int $perPage = 10): array
    {
        $where = "status = 'published' AND published_at <= NOW() AND YEAR(published_at) = ?";
        $params = [$year];

        if ($month) {
            $where .= " AND MONTH(published_at) = ?";
            $params[] = $month;
        }

        return static::paginate($page, $perPage, $where, $params, 'published_at DESC');
    }

    public static function incrementViews(int $articleId): void
    {
        $db = Database::getInstance();
        if ($db === null) return;

        $db->query("UPDATE {articles} SET views = views + 1 WHERE id = ?", [$articleId]);
    }

    public static function getTags(int $articleId): array
    {
        $db = Database::getInstance();
        if ($db === null) return [];

        return $db->fetchAll(
            "SELECT t.* FROM {tags} t INNER JOIN {article_tag} at ON t.id = at.tag_id WHERE at.article_id = ? ORDER BY t.name",
            [$articleId]
        );
    }

    public static function getMeta(int $articleId, string $key = null)
    {
        $db = Database::getInstance();
        if ($db === null) return $key ? null : [];

        if ($key) {
            $row = $db->fetch(
                "SELECT meta_value FROM {article_meta} WHERE article_id = ? AND meta_key = ?",
                [$articleId, $key]
            );
            return $row ? $row['meta_value'] : null;
        }

        $rows = $db->fetchAll("SELECT meta_key, meta_value FROM {article_meta} WHERE article_id = ?", [$articleId]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['meta_key']] = $row['meta_value'];
        }
        return $result;
    }

    public static function setMeta(int $articleId, string $key, $value): void
    {
        $db = Database::getInstance();
        if ($db === null) return;

        $exists = $db->fetch("SELECT id FROM {article_meta} WHERE article_id = ? AND meta_key = ?", [$articleId, $key]);
        if ($exists) {
            $db->update('article_meta', ['meta_value' => $value], 'id = ?', [$exists['id']]);
        } else {
            $db->insert('article_meta', ['article_id' => $articleId, 'meta_key' => $key, 'meta_value' => $value]);
        }
    }

    public static function deleteMeta(int $articleId, string $key = null): void
    {
        $db = Database::getInstance();
        if ($db === null) return;

        if ($key) {
            $db->delete('article_meta', 'article_id = ? AND meta_key = ?', [$articleId, $key]);
        } else {
            $db->delete('article_meta', 'article_id = ?', [$articleId]);
        }
    }

    private static function syncTags(int $articleId, $tags): void
    {
        $db = Database::getInstance();
        if ($db === null) return;

        $db->delete('article_tag', 'article_id = ?', [$articleId]);

        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        if (!is_array($tags)) return;

        foreach ($tags as $tag) {
            if (empty(trim($tag))) continue;

            if (is_numeric($tag)) {
                $tagId = (int) $tag;
            } else {
                $tagName = trim($tag);
                $tagSlug = Str::slug($tagName);

                $existing = Tag::findBySlug($tagSlug);
                if ($existing) {
                    $tagId = $existing['id'];
                } else {
                    $tagId = Tag::createTag($tagName);
                }
            }

            if ($tagId) {
                try {
                    $db->insert('article_tag', ['article_id' => $articleId, 'tag_id' => $tagId]);
                } catch (\Exception $e) {
                    // Skip duplicates
                }
            }
        }
    }

    private static function uniqueSlug(string $slug, int $exceptId = null): string
    {
        $original = $slug;
        $counter = 1;

        $db = Database::getInstance();
        if ($db === null) return $slug;

        while (true) {
            $where = 'slug = ?';
            $params = [$slug];
            if ($exceptId) {
                $where .= ' AND id != ?';
                $params[] = $exceptId;
            }
            if (!$db->exists('articles', $where, $params)) {
                break;
            }
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private static function emptyPaginate(int $perPage): array
    {
        return [
            'items'        => [],
            'total'        => 0,
            'per_page'     => $perPage,
            'current_page' => 1,
            'last_page'    => 1,
            'has_more'     => false,
        ];
    }
}
