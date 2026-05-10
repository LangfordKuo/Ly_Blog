<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Models\Comment;
use LyBlog\Models\Article;

class CommentController extends BaseAdminController
{
    public function index(Request $request)
    {
        $page = (int) ($request->getQuery('page', 1));
        $status = $request->getQuery('status', '');
        $search = $request->getQuery('search', '');

        $where = '1=1';
        $params = [];
        if ($status) { $where .= ' AND status = ?'; $params[] = $status; }
        if ($search) { $where .= ' AND (author_name LIKE ? OR content LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }

        $data = Comment::paginate($page, 20, $where, $params, 'created_at DESC');

        $this->adminHeader('评论管理', 'comments');
        $this->pageHeader('评论管理');
        $this->flashMessages();

        echo '<div class="card"><form method="get" class="filter-bar">';
        echo '<select name="status" style="width:auto"><option value="">全部</option>';
        foreach (['pending' => '待审', 'approved' => '通过', 'spam' => '垃圾'] as $k => $v) {
            echo '<option value="' . $k . '" ' . ($status === $k ? 'selected' : '') . '>' . $v . '</option>';
        }
        echo '</select>';
        echo '<input type="text" name="search" value="' . htmlspecialchars($search) . '" placeholder="搜索..." style="width:200px">';
        echo '<button type="submit" class="btn btn-secondary btn-sm">筛选</button>';
        echo '</form></div>';

        $headers = ['ID', '文章', '作者', '内容', '状态', '时间', '操作'];
        $rows = [];
        foreach ($data['items'] as $c) {
            $article = Article::find($c['article_id']);
            $actions = '<div class="table-actions">';
            if ($c['status'] === 'pending') {
                $actions .= '<a href="' . $this->adminUrl('comments/' . $c['id'] . '/approve') . '">通过</a>';
                $actions .= '<a href="' . $this->adminUrl('comments/' . $c['id'] . '/spam') . '">垃圾</a>';
            }
            if ($c['status'] === 'approved') {
                $actions .= '<a href="' . $this->adminUrl('comments/' . $c['id'] . '/spam') . '">垃圾</a>';
            }
            if ($c['status'] === 'spam') {
                $actions .= '<a href="' . $this->adminUrl('comments/' . $c['id'] . '/approve') . '">恢复</a>';
            }
            $actions .= '<a href="' . $this->adminUrl('comments/' . $c['id'] . '/delete') . '" class="delete-btn">删除</a>';
            $actions .= '</div>';

            $rows[] = [
                $c['id'],
                $article ? htmlspecialchars($article['title']) : '[' . $c['article_id'] . ']',
                htmlspecialchars($c['author_name']),
                mb_substr(strip_tags($c['content']), 0, 60),
                $this->statusBadge($c['status']),
                date('Y-m-d H:i', strtotime($c['created_at'])),
                $actions,
            ];
        }
        $this->table($headers, $rows, '暂无评论');
        $this->pagination($data, $this->adminUrl('comments'));
        $this->adminFooter();
    }

    public function approve(Request $request, $id)
    {
        Comment::approve((int) $id);
        Session::flash('success', '评论已通过');
        $this->redirect($this->adminUrl('comments'));
    }

    public function spam(Request $request, $id)
    {
        Comment::markAsSpam((int) $id);
        Session::flash('success', '已标记为垃圾');
        $this->redirect($this->adminUrl('comments'));
    }

    public function delete(Request $request, $id)
    {
        Comment::deleteComment((int) $id);
        Session::flash('success', '评论已删除');
        $this->redirect($this->adminUrl('comments'));
    }
}
