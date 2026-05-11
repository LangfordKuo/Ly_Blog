<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Core\Config;
use LyBlog\Core\Session;
use LyBlog\Models\Article;
use LyBlog\Models\Category;
use LyBlog\Models\Tag;
use LyBlog\Models\Comment;
use LyBlog\Models\Like;
use LyBlog\Helpers\Str;

class ArticleController extends BaseController
{
    public function show(Request $request, $slug = null)
    {
        if (!$slug) { $this->notFound(); return; }

        $article = Article::findBySlug($slug);
        if (!$article || ($article['status'] !== 'published' && $article['published_at'] > date('Y-m-d H:i:s'))) {
            $this->notFound(); return;
        }

        if ($article['status'] === 'private' && !Session::isLoggedIn()) {
            $this->notFound(); return;
        }

        if (!empty($article['password'])) {
            $inputPass = $request->getPost('article_password', '');
            if ($inputPass !== $article['password']) {
                $this->display('password', ['article' => $article]);
                return;
            }
        }

        $this->incrementViewOnce($article['id']);

        // Apply shortcodes
        $article['content'] = Str::shortcodes($article['content']);

        $comments = Comment::getNested($article['id']);
        $tags = Article::getTags($article['id']);
        $category = Category::find($article['category_id']);
        $liked = Like::isLiked($article['id'], $request->getIp());
        $commentCount = Comment::getArticleCommentCount($article['id']);

        // Reading time estimate (avg 300 chars/min for Chinese)
        $plainContent = strip_tags($article['content'] ?? '');
        $article['read_time'] = max(1, (int)ceil(mb_strlen($plainContent) / 300));

        // Author info
        $author = \LyBlog\Models\User::find($article['author_id']);
        if ($author) {
            $author['avatar_url'] = Str::gravatar($author['email'] ?? '', 64);
        }

        // Related articles (same category or tags)
        $related = [];
        if ($category) {
            $relatedData = Article::query(
                "id != ? AND category_id = ? AND status = 'published' AND published_at <= NOW()",
                [$article['id'], $category['id']],
                'RAND()'
            );
            $related = array_slice($relatedData, 0, 3);
        }

        // Pre-render comments HTML
        $commentsHtml = $this->renderCommentsHtml($comments);

        // Breadcrumb
        $bc = [['🏠 ' . Config::get('site_name', '首页'), $this->siteUrl()]];
        if ($category) {
            $bc[] = [$category['name'], $this->siteUrl('category/' . $category['slug'])];
        }
        $bc[] = [$article['title'], null];
        $breadcrumb = $this->breadcrumb($bc);
        $jsonLd = $this->jsonLdArticle($article, $author, $category);

        $this->display('article', [
            'article'       => $article,
            'comments'      => $comments,
            'comments_html' => $commentsHtml,
            'comment_count' => $commentCount,
            'tags'          => $tags,
            'category'      => $category,
            'liked'         => $liked,
            'author'        => $author,
            'related'       => $related,
            'prev'          => Article::getPrev($article['id']),
            'next'          => Article::getNext($article['id']),
            'breadcrumb'    => $breadcrumb,
            'canonical_url' => $this->siteUrl('article/' . $article['slug']),
            'json_ld'       => $jsonLd,
            'csrf_field'    => Session::csrfField(),
            'csrf_token'    => Session::csrfToken(),
        ]);
    }

    private function renderCommentsHtml(array $comments, int $depth = 0): string
    {
        $html = '';
        foreach ($comments as $comment) {
            $html .= '<div class="comment" style="margin-left:' . ($depth * 20) . 'px">';
            $gravatarUrl = '';
            if (!empty($comment['author_email'])) {
                $gravatarUrl = Str::gravatar($comment['author_email'], 44);
            }
            $html .= '<div class="comment-avatar">';
            if ($gravatarUrl) {
                $html .= '<img src="' . $gravatarUrl . '" width="44" height="44" style="border-radius:50%" alt="">';
            } else {
                $html .= strtoupper(mb_substr($comment['author_name'], 0, 1));
            }
            $html .= '</div>';
            $html .= '<div class="comment-body">';
            $html .= '<div class="comment-meta"><strong>' . htmlspecialchars($comment['author_name']) . '</strong>';
            $html .= '<span>' . date('Y-m-d H:i', strtotime($comment['created_at'])) . '</span></div>';
            $html .= '<div class="comment-content">' . nl2br(htmlspecialchars($comment['content'])) . '</div>';
            $html .= '</div></div>';

            if (!empty($comment['children'])) {
                $html .= $this->renderCommentsHtml($comment['children'], $depth + 1);
            }
        }
        return $html;
    }

    private function incrementViewOnce(int $articleId): void
    {
        $key = 'viewed_articles';
        $viewed = Session::get($key, []);
        $now = time();

        // Clean expire views older than 1 hour
        foreach ($viewed as $id => $timestamp) {
            if ($now - $timestamp > 3600) {
                unset($viewed[$id]);
            }
        }

        if (!isset($viewed[$articleId])) {
            Article::incrementViews($articleId);
            $viewed[$articleId] = $now;
            Session::set($key, $viewed);
        }
    }
}
