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

        $comments = Comment::getNested($article['id']);
        $tags = Article::getTags($article['id']);
        $category = Category::find($article['category_id']);
        $liked = Like::isLiked($article['id'], $request->getIp());
        $commentCount = Comment::getArticleCommentCount($article['id']);

        // Pre-render comments HTML
        $commentsHtml = $this->renderCommentsHtml($comments);

        $this->display('article', [
            'article'       => $article,
            'comments'      => $comments,
            'comments_html' => $commentsHtml,
            'comment_count' => $commentCount,
            'tags'          => $tags,
            'category'      => $category,
            'liked'         => $liked,
            'csrf_field'    => Session::csrfField(),
            'csrf_token'    => Session::csrfToken(),
        ]);
    }

    private function renderCommentsHtml(array $comments, int $depth = 0): string
    {
        $html = '';
        foreach ($comments as $comment) {
            $html .= '<div class="comment" style="margin-left:' . ($depth * 20) . 'px">';
            $html .= '<div class="comment-avatar">' . strtoupper(mb_substr($comment['author_name'], 0, 1)) . '</div>';
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
