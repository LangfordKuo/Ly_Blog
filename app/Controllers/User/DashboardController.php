<?php

namespace LyBlog\Controllers\User;

use LyBlog\Core\Request;
use LyBlog\Models\Article;

class DashboardController extends BaseUserController
{
    public function index(Request $request)
    {
        $userId = $this->currentUser['id'];

        $totalArticles = Article::count('author_id = ?', [$userId]);
        $publishedArticles = Article::count("author_id = ? AND status = 'published'", [$userId]);
        $draftArticles = Article::count("author_id = ? AND status = 'draft'", [$userId]);
        $totalViews = \LyBlog\Core\Database::getInstance()->fetchColumn(
            "SELECT COALESCE(SUM(views), 0) FROM {articles} WHERE author_id = ?", [$userId]
        );

        $this->userHeader('概览', 'dashboard');
        $this->pageHeader('控制面板');
        echo '<p style="color:var(--text-2);margin-bottom:24px">欢迎回来，' . htmlspecialchars($this->currentUser['display_name'] ?? $this->currentUser['username']) . '</p>';

        echo '<div class="stats">';
        echo '<div class="stat-card"><div class="stat-value">' . $totalArticles . '</div><div class="stat-label">总文章</div></div>';
        echo '<div class="stat-card"><div class="stat-value">' . $publishedArticles . '</div><div class="stat-label">已发布</div></div>';
        echo '<div class="stat-card"><div class="stat-value">' . $draftArticles . '</div><div class="stat-label">草稿</div></div>';
        echo '<div class="stat-card"><div class="stat-value">' . $totalViews . '</div><div class="stat-label">总阅读量</div></div>';
        echo '</div>';

        $recentArticles = Article::query('author_id = ?', [$userId], 'created_at DESC, id ASC');
        echo '<div class="card"><div class="card-header"><h2>最近文章</h2>';
        echo '<a href="' . $this->siteUrl('user/articles/create') . '" class="btn btn-sm btn-primary">写文章</a></div>';

        if (empty($recentArticles)) {
            echo '<div class="empty"><p>还没有文章，点击上方按钮开始写第一篇</p></div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>标题</th><th>状态</th><th>阅读</th><th>日期</th><th>操作</th></tr></thead><tbody>';
            foreach (array_slice($recentArticles, 0, 10) as $a) {
                echo '<tr>';
                echo '<td><a href="' . $this->siteUrl('article/' . $a['slug']) . '" target="_blank">' . htmlspecialchars($a['title']) . '</a></td>';
                echo '<td>' . $this->statusBadge($a['status']) . '</td>';
                echo '<td>' . $a['views'] . '</td>';
                $date = $a['status'] === 'published' ? ($a['published_at'] ?? $a['created_at']) : $a['created_at'];
                echo '<td>' . date('Y-m-d', strtotime($date)) . '</td>';
                echo '<td>' . $this->actionButtons($this->siteUrl('user/articles/' . $a['id'] . '/edit'), $this->siteUrl('user/articles/' . $a['id'] . '/delete')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';

        $this->userFooter();
    }
}
