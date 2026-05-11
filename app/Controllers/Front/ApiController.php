<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Models\Article;
use LyBlog\Models\Category;
use LyBlog\Models\Tag;

class ApiController extends BaseController
{
    /**
     * GET /api/articles — List published articles.
     * Params: page, per_page, category_id, tag
     */
    public function articles(Request $request)
    {
        $page = (int) ($request->getQuery('page', 1));
        $perPage = min((int) ($request->getQuery('per_page', 10)), 50);
        $categoryId = (int) ($request->getQuery('category_id', 0));
        $tagSlug = $request->getQuery('tag', '');

        if ($tagSlug) {
            $data = Article::getByTag($tagSlug, $page, $perPage);
        } else {
            $data = Article::getPublished($page, $perPage, $categoryId);
        }

        $items = array_map(function ($a) {
            return $this->formatArticle($a);
        }, $data['items']);

        $this->json([
            'code' => 200,
            'data' => [
                'items'      => $items,
                'total'      => $data['total'],
                'page'       => $data['current_page'],
                'per_page'   => $data['per_page'],
                'last_page'  => $data['last_page'],
            ],
        ]);
    }

    /**
     * GET /api/article/{slug} — Single article detail.
     */
    public function article(Request $request, $slug = null)
    {
        if (!$slug) {
            $this->json(['code' => 404, 'message' => 'Article not found'], 404);
            return;
        }

        $article = Article::findBySlug($slug);
        if (!$article || $article['status'] !== 'published') {
            $this->json(['code' => 404, 'message' => 'Article not found'], 404);
            return;
        }

        $article['tags'] = Article::getTags($article['id']);
        $category = Category::find($article['category_id']);

        Article::incrementViews($article['id']);

        $this->json([
            'code' => 200,
            'data' => [
                'article'  => $this->formatArticle($article),
                'category' => $category ? $this->formatCategory($category) : null,
                'tags'     => array_map([$this, 'formatTag'], $article['tags']),
            ],
        ]);
    }

    /**
     * GET /api/search — Search articles.
     * Params: q
     */
    public function search(Request $request)
    {
        $query = trim($request->getQuery('q', ''));
        $page  = (int) ($request->getQuery('page', 1));
        $perPage = min((int) ($request->getQuery('per_page', 10)), 50);

        if (empty($query)) {
            $this->json(['code' => 400, 'message' => 'Query parameter required'], 400);
            return;
        }

        $data = Article::search($query, $page, $perPage);

        $items = array_map(function ($a) {
            return $this->formatArticle($a);
        }, $data['items']);

        $this->json([
            'code' => 200,
            'data' => [
                'query'     => $query,
                'items'     => $items,
                'total'     => $data['total'],
                'page'      => $data['current_page'],
                'per_page'  => $data['per_page'],
                'last_page' => $data['last_page'],
            ],
        ]);
    }

    /**
     * GET /api/categories — List categories.
     */
    public function categories(Request $request)
    {
        $categories = Category::all('sort_order ASC');

        $this->json([
            'code' => 200,
            'data' => array_map([$this, 'formatCategory'], $categories),
        ]);
    }

    /**
     * GET /api/tags — List tags cloud.
     */
    public function tags(Request $request)
    {
        $tags = Tag::getCloud(100);

        $this->json([
            'code' => 200,
            'data' => array_map([$this, 'formatTag'], $tags),
        ]);
    }

    /**
     * GET /api/archives — Archive by month.
     */
    public function archives(Request $request)
    {
        $this->json([
            'code' => 200,
            'data' => Article::getArchives(),
        ]);
    }

    private function formatArticle(array $a): array
    {
        return [
            'id'           => (int) $a['id'],
            'title'        => $a['title'],
            'slug'         => $a['slug'],
            'excerpt'      => $a['excerpt'] ?? '',
            'content'      => $a['content'] ?? '',
            'cover_image'  => $a['cover_image'] ?? null,
            'status'       => $a['status'],
            'views'        => (int) ($a['views'] ?? 0),
            'likes'        => (int) ($a['likes'] ?? 0),
            'category_id'  => (int) ($a['category_id'] ?? 0),
            'is_pinned'    => (bool) ($a['is_pinned'] ?? false),
            'read_time'    => (int) ($a['read_time'] ?? 0),
            'published_at' => $a['published_at'] ?? null,
            'created_at'   => $a['created_at'] ?? null,
            'updated_at'   => $a['updated_at'] ?? null,
        ];
    }

    private function formatCategory(array $c): array
    {
        return [
            'id'   => (int) $c['id'],
            'name' => $c['name'],
            'slug' => $c['slug'],
            'description' => $c['description'] ?? '',
            'article_count' => $c['article_count'] ?? 0,
        ];
    }

    private function formatTag(array $t): array
    {
        return [
            'id'   => (int) $t['id'],
            'name' => $t['name'],
            'slug' => $t['slug'],
            'article_count' => (int) ($t['article_count'] ?? 0),
        ];
    }
}
