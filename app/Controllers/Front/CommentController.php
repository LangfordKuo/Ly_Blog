<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Core\Config;
use LyBlog\Core\Mailer;
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
}
