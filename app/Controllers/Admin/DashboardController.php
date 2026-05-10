<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Config;
use LyBlog\Core\Auth;
use LyBlog\Core\Session;
use LyBlog\Models\User;
use LyBlog\Models\Role;

class DashboardController extends BaseAdminController
{
    public function index(Request $request)
    {
        $this->adminHeader('仪表盘', 'dashboard');

        $currentUser = Auth::user();
        echo '<div class="page-header"><h1>仪表盘</h1><p>欢迎回来，' . htmlspecialchars($currentUser['display_name'] ?? $currentUser['username']) . '</p></div>';

        echo '<div class="stats">';
        $articleCount = class_exists('\LyBlog\Models\Article') ? \LyBlog\Models\Article::count() : 0;
        $pageCount = class_exists('\LyBlog\Models\Page') ? \LyBlog\Models\Page::count() : 0;
        $commentCount = class_exists('\LyBlog\Models\Comment') ? \LyBlog\Models\Comment::count() : 0;
        $userCount = User::count();
        echo '<div class="stat-card"><div class="stat-value">' . $articleCount . '</div><div class="stat-label">文章</div></div>';
        echo '<div class="stat-card"><div class="stat-value">' . $pageCount . '</div><div class="stat-label">页面</div></div>';
        echo '<div class="stat-card"><div class="stat-value">' . $commentCount . '</div><div class="stat-label">评论</div></div>';
        echo '<div class="stat-card"><div class="stat-value">' . $userCount . '</div><div class="stat-label">用户</div></div>';
        echo '</div>';

        echo '<div class="card"><h2>系统信息</h2>';
        echo '<table><tr><td>PHP 版本</td><td>' . PHP_VERSION . '</td></tr>';
        echo '<tr><td>LyBlog 版本</td><td>1.0.0</td></tr>';
        echo '<tr><td>当前主题</td><td>' . htmlspecialchars(Config::get('theme', 'Default')) . '</td></tr>';
        echo '<tr><td>时区</td><td>' . htmlspecialchars(Config::get('timezone', 'Asia/Shanghai')) . '</td></tr>';
        echo '</table></div>';

        $this->adminFooter();
    }
}
