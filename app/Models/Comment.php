<?php

namespace LyBlog\Models;

use LyBlog\Core\Database;
use LyBlog\Core\Sanitizer;

class Comment extends Model
{
    protected static $table = 'comments';
    protected static $primaryKey = 'id';
    protected static $fillable = [
        'article_id', 'parent_id', 'user_id', 'author_name',
        'author_email', 'author_url', 'content',
        'status', 'ip', 'user_agent',
    ];

    public static function createComment(array $data): ?int
    {
        if (!isset($data['status'])) {
            $data['status'] = 'approved';
        }

        if (!isset($data['ip'])) {
            $data['ip'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        if (!isset($data['user_agent'])) {
            $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        $data['content'] = Sanitizer::text($data['content'] ?? '');

        return static::create($data);
    }

    public static function getByArticle(int $articleId, string $status = 'approved', string $orderBy = 'created_at ASC'): array
    {
        return static::query("article_id = ? AND status = ?", [$articleId, $status], $orderBy);
    }

    public static function getNested(int $articleId, string $status = 'approved'): array
    {
        $comments = static::getByArticle($articleId, $status, 'created_at ASC');
        return static::buildNested($comments);
    }

    public static function buildNested(array $comments, int $parentId = null): array
    {
        $branch = [];
        foreach ($comments as $comment) {
            if ($comment['parent_id'] == $parentId) {
                $children = static::buildNested($comments, $comment['id']);
                $comment['children'] = $children;
                $branch[] = $comment;
            }
        }
        return $branch;
    }

    public static function approve(int $commentId): int
    {
        return static::update($commentId, ['status' => 'approved']);
    }

    public static function markAsSpam(int $commentId): int
    {
        return static::update($commentId, ['status' => 'spam']);
    }

    public static function markAsPending(int $commentId): int
    {
        return static::update($commentId, ['status' => 'pending']);
    }

    public static function deleteComment(int $commentId): int
    {
        $comment = static::find($commentId);
        if ($comment) {
            $children = static::getBy('parent_id', $commentId);
            foreach ($children as $child) {
                static::deleteComment($child['id']);
            }
        }
        return static::delete($commentId);
    }

    public static function getRecent(int $limit = 10, string $status = 'approved'): array
    {
        return static::query("status = ?", [$status], 'created_at DESC');
    }

    public static function getPending(int $page = 1, int $perPage = 20): array
    {
        return static::paginate($page, $perPage, "status = 'pending'", [], 'created_at DESC');
    }

    public static function getArticleCommentCount(int $articleId): int
    {
        return static::count("article_id = ? AND status = 'approved'", [$articleId]);
    }
}
