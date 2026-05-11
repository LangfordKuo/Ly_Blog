<?php

namespace LyBlog\Controllers\User;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Auth;
use LyBlog\Core\Config;
use LyBlog\Core\Session;
use LyBlog\Models\Role;

class BaseUserController extends BaseController
{
    protected $currentUser;
    protected $currentRole;

    public function __construct()
    {
        parent::__construct();

        $this->currentUser = Auth::user();

        if (!Auth::check()) {
            $this->redirect($this->siteUrl('login'));
        }

        $this->currentRole = Role::find($this->currentUser['role_id']);
    }

    protected function userHeader(string $title, string $activeMenu = 'dashboard'): void
    {
        $siteName = Config::get('site_name', 'LyBlog');
        $user = $this->currentUser;
        $isAdmin = $this->currentRole && Role::canAccessAdmin($this->currentRole);

        echo '<!DOCTYPE html><html lang="zh-CN"><head>';
        echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
        echo '<title>' . htmlspecialchars($title) . ' - ' . htmlspecialchars($siteName) . '</title>';
        echo '<style>'; include THEMES_DIR . '/Default/assets/css/admin.css'; echo '</style>';
        echo '</head><body>';

        echo '<aside class="sidebar">';
        echo '<div class="sidebar-brand">控制面板</div>';
        echo '<nav class="sidebar-nav">';

        $this->menuItem('dashboard', '概览', '📊', $activeMenu);
        $this->menuItem('articles', '我的文章', '📝', $activeMenu);

        if ($this->hasPerm('media.upload')) {
            $this->menuItem('media', '媒体', '🖼️', $activeMenu);
        }

        $this->menuItem('profile', '个人资料', '👤', $activeMenu);

        echo '<div class="sidebar-divider">导航</div>';

        if ($isAdmin) {
            echo '<a href="' . $this->adminUrl() . '" class="" style="padding:10px 20px;color:var(--text-2);font-size:14px;display:flex;align-items:center;gap:10px"><span class="nav-icon">⚙️</span> 管理后台</a>';
        }
        echo '<a href="' . $this->siteUrl() . '" class="" style="padding:10px 20px;color:var(--text-2);font-size:14px;display:flex;align-items:center;gap:10px"><span class="nav-icon">🏠</span> 返回前台</a>';

        echo '</nav>';
        echo '<div class="sidebar-footer">';
        echo '<div class="user-info"><div class="user-name">' . htmlspecialchars($user['display_name'] ?? $user['username']) . '</div>';
        echo '<div class="user-role">' . htmlspecialchars($this->currentRole['name'] ?? '') . '</div></div>';
        echo '<a href="' . $this->siteUrl('logout') . '">退出</a>';
        echo '</div></aside>';

        echo '<main class="main">';
    }

    protected function userFooter(): void
    {
        echo '</main>';
        echo '<script>
            document.querySelectorAll(".delete-btn").forEach(function(btn){
                btn.addEventListener("click",function(e){if(!confirm("确定要删除吗？")){e.preventDefault()}})
            });
        </script>';
        echo '</body></html>';
    }

    private function menuItem(string $slug, string $label, string $icon, string $active): void
    {
        $url = $this->siteUrl('user/' . ($slug === 'dashboard' ? '' : $slug));
        $cls = $active === $slug ? 'active' : '';
        echo '<a href="' . $url . '" class="' . $cls . '"><span class="nav-icon">' . $icon . '</span> ' . $label . '</a>';
    }

    protected function hasPerm(string $perm): bool
    {
        return Auth::can($perm);
    }

    protected function pageHeader(string $title, string $actionHtml = ''): void
    {
        echo '<div class="page-header"><h1>' . htmlspecialchars($title) . '</h1>';
        if ($actionHtml) echo '<div class="actions">' . $actionHtml . '</div>';
        echo '</div>';
    }

    protected function alert(string $message, string $type = 'info'): void
    {
        echo '<div class="alert alert-' . $type . '">' . htmlspecialchars($message) . '</div>';
    }

    protected function success(string $message): void
    {
        echo '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
    }

    protected function error(string $message): void
    {
        echo '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>';
    }

    protected function flashMessages(): void
    {
        $error = Session::flash('error');
        $success = Session::flash('success');
        if ($error) $this->alert($error, 'danger');
        if ($success) $this->alert($success, 'success');
    }

    protected function statusBadge(string $status): string
    {
        $map = [
            'published' => ['已发布', 'success'],
            'draft'     => ['草稿', 'warning'],
            'pending'   => ['待审', 'warning'],
            'private'   => ['私密', 'info'],
        ];
        if (isset($map[$status])) {
            return '<span class="badge badge-' . $map[$status][1] . '">' . $map[$status][0] . '</span>';
        }
        return '<span class="badge badge-info">' . htmlspecialchars($status) . '</span>';
    }

    protected function actionButtons(string $editUrl, string $deleteUrl): string
    {
        return '<div class="table-actions"><a href="' . $editUrl . '">编辑</a><a href="' . $deleteUrl . '" class="delete-btn">删除</a></div>';
    }
}
