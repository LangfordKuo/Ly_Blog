<?php

namespace LyBlog\Models;

use LyBlog\Core\Database;
use LyBlog\Helpers\Str;

class Category extends Model
{
    protected static $table = 'categories';
    protected static $primaryKey = 'id';
    protected static $fillable = ['name', 'slug', 'description', 'parent_id', 'sort_order'];

    public static function createCategory(array $data): ?int
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return static::create($data);
    }

    public static function updateCategory($id, array $data): int
    {
        if (!empty($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return static::update($id, $data);
    }

    public static function findBySlug(string $slug): ?array
    {
        return static::where('slug', $slug);
    }

    public static function getTree(): array
    {
        $categories = static::all('sort_order ASC, id ASC');
        return static::buildTree($categories);
    }

    public static function buildTree(array $categories, int $parentId = null): array
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = static::buildTree($categories, $category['id']);
                $category['children'] = $children;
                $tree[] = $category;
            }
        }
        return $tree;
    }

    public static function getFlatList(int $parentId = null, int $depth = 0): array
    {
        $categories = static::all('sort_order ASC');
        return static::flattenTree($categories, $parentId, $depth);
    }

    private static function flattenTree(array $categories, int $parentId = null, int $depth = 0): array
    {
        $result = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $category['depth'] = $depth;
                $category['display'] = str_repeat('— ', $depth) . $category['name'];
                $result[] = $category;
                $result = array_merge($result, static::flattenTree($categories, $category['id'], $depth + 1));
            }
        }
        return $result;
    }

    public static function getChildren(int $parentId): array
    {
        return static::getBy('parent_id', $parentId);
    }

    public static function getArticleCount(int $categoryId): int
    {
        $db = Database::getInstance();
        if ($db === null) return 0;

        return $db->count('articles', "category_id = ? AND status = 'published'", [$categoryId]);
    }

    public static function deleteCategory($id): int
    {
        $children = static::getChildren($id);
        foreach ($children as $child) {
            static::deleteCategory($child['id']);
        }

        $db = Database::getInstance();
        if ($db) {
            $db->update('articles', ['category_id' => null], 'category_id = ?', [$id]);
        }

        return static::delete($id);
    }
}
