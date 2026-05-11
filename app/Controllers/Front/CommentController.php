<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Core\Config;
use LyBlog\Core\Mailer;
use LyBlog\Core\Database;
use LyBlog\Models\Article;
use LyBlog\Models\Comment;

class CommentController extends BaseController
{
    public function store(Request $request, $articleId = null)
    {
        $articleId = (int) $request->getPost('article_id', $articleId);

        if (!$articleId) {
            $this->redirect($this->siteUrl());
            return;
        }

        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->siteUrl('article/' . $articleId));
            return;
        }

        // Rate limit: prevent comment spam
        if ($reason = $this->isCommentRateLimited($request)) {
            Session::flash('error', $reason);
            $this->redirect($this->siteUrl('article/' . $articleId) . '#comments');
            return;
        }

        $data = [
            'article_id'   => $articleId,
            'parent_id'    => $request->getPost('parent_id') ? (int) $request->getPost('parent_id') : null,
            'author_name'  => $request->getPost('author_name', ''),
            'author_email' => $request->getPost('author_email', ''),
            'author_url'   => $request->getPost('author_url', ''),
            'content'      => $request->getPost('content', ''),
        ];

        $v = Validator::quick(
            $data,
            [
                'author_name' => 'required|min:1|max:50',
                'content'     => 'required|min:2|max:5000',
            ],
            ['author_name' => '昵称', 'content' => '评论内容']
        );

        if ($v->fails()) {
            Session::flash('error', $v->first());
            $this->redirect($this->siteUrl('article/' . $articleId) . '#comments');
            return;
        }

        Comment::createComment($data);

        // Send notification email if enabled
        if (Config::get('comment_notification') == '1') {
            try {
                $article = Article::find($articleId);
                if ($article) {
                    Mailer::sendCommentNotification($data, $article);
                }
            } catch (\Throwable $e) {
                // Silently ignore notification failures
            }
        }

        $redirectUrl = $this->siteUrl('article/' . $articleId) . '#comments';
        $this->redirect($redirectUrl);
    }

    /**
     * Check if current IP/user is posting too fast.
     * Returns error message string if rate-limited, or false if allowed.
     */
    private function isCommentRateLimited(Request $request)
    {
        $ip = $request->getIp();
        $db = Database::getInstance();
        if (!$db) return false;

        // Configurable limits
        $perMinute  = 3;   // Max comments per minute
        $perHour    = 15;   // Max comments per hour
        $perDay     = 50;   // Max comments per day
        $rapidSecs  = 10;   // Minimum seconds between comments

        // Rapid fire: any comment in last N seconds?
        $cutoff = date('Y-m-d H:i:s', time() - $rapidSecs);
        if ($db->exists('comments', 'ip = ? AND created_at > ?', [$ip, $cutoff])) {
            return "评论太频繁，请 {$rapidSecs} 秒后再试";
        }

        // Per-minute limit
        $cutoff = date('Y-m-d H:i:s', time() - 60);
        $count = $db->count('comments', 'ip = ? AND created_at > ?', [$ip, $cutoff]);
        if ($count >= $perMinute) {
            return '评论次数过多，请 1 分钟后再试';
        }

        // Per-hour limit
        $cutoff = date('Y-m-d H:i:s', time() - 3600);
        $count = $db->count('comments', 'ip = ? AND created_at > ?', [$ip, $cutoff]);
        if ($count >= $perHour) {
            return '评论次数过多，请 1 小时后再试';
        }

        // Per-day limit
        $cutoff = date('Y-m-d', time()) . ' 00:00:00';
        $count = $db->count('comments', 'ip = ? AND created_at >= ?', [$ip, $cutoff]);
        if ($count >= $perDay) {
            return '今日评论次数已达上限，请明天再来';
        }

        // Duplicate content check (same text from same IP in last hour)
        $cutoff = date('Y-m-d H:i:s', time() - 3600);
        $content = trim($request->getPost('content', ''));
        if ($content && $db->exists('comments', 'ip = ? AND content = ? AND created_at > ?', [$ip, $content, $cutoff])) {
            return '请勿重复提交相同内容';
        }

        return false;
    }
}
