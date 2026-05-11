<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Auth;
use LyBlog\Core\Config;
use LyBlog\Core\Session;
use LyBlog\Models\User;

class BaseAdminController extends BaseController
{
    protected $currentUser;

    public function __construct()
    {
        parent::__construct();

        $this->currentUser = Auth::user();

        if (!Auth::check()) {
            $this->redirect($this->adminUrl('login'));
        }

        if (!Auth::canAccessAdmin()) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title></head>';
            echo '<body style="font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f7;">';
            echo '<div style="text-align:center;"><h1 style="font-size:72px;font-weight:200;margin:0;">403</h1><p>无权访问</p></div></body></html>';
            exit;
        }
    }

    protected function adminHeader(string $title, string $activeMenu = 'dashboard'): void
    {
        $siteName = Config::get('site_name', 'LyBlog');
        $user = $this->currentUser;
        $role = \LyBlog\Models\Role::find($user['role_id']);

        echo '<!DOCTYPE html><html lang="zh-CN"><head>';
        echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
        echo '<title>' . htmlspecialchars($title) . ' - ' . htmlspecialchars($siteName) . '</title>';
        echo '<style>'; include THEMES_DIR . '/Default/assets/css/admin.css'; echo '</style>';
        echo '</head><body>';

        echo '<aside class="sidebar">';
        echo '<div class="sidebar-brand">' . htmlspecialchars($siteName) . '</div>';
        echo '<nav class="sidebar-nav">';

        $this->menuItem('dashboard', '仪表盘', '📊', $activeMenu);
        $this->menuItem('articles', '文章', '📝', $activeMenu);
        $this->menuItem('pages', '页面', '📄', $activeMenu);
        $this->menuItem('comments', '评论', '💬', $activeMenu, $this->pendingCommentCount());
        $this->menuItem('categories', '分类', '📁', $activeMenu);
        $this->menuItem('tags', '标签', '🏷️', $activeMenu);
        $this->menuDivider('用户');
        $this->menuItem('users', '用户管理', '👥', $activeMenu);
        $this->menuItem('roles', '角色管理', '🔐', $activeMenu);
        $this->menuDivider('扩展');
        $this->menuItem('menus', '菜单', '📋', $activeMenu);
        $this->menuItem('links', '友情链接', '🔗', $activeMenu);
        $this->menuItem('media', '媒体', '🖼️', $activeMenu);
        $this->menuItem('themes', '主题', '🎨', $activeMenu);
        $this->menuItem('plugins', '插件', '🔌', $activeMenu);
        $this->menuDivider('系统');
        $this->menuItem('settings', '设置', '⚙️', $activeMenu);

        echo '</nav>';
        echo '<div class="sidebar-footer">';
        echo '<div class="user-info"><div class="user-name">' . htmlspecialchars($user['display_name'] ?? $user['username']) . '</div>';
        echo '<div class="user-role">' . htmlspecialchars($role['name'] ?? '') . '</div></div>';
        echo '<a href="' . $this->adminUrl('logout') . '">退出</a>';
        echo '</div></aside>';

        echo '<main class="main">';
    }

    protected function adminFooter(): void
    {
        echo '</main>';
        echo '<script>
            // Confirm delete
            document.querySelectorAll(".delete-btn").forEach(function(btn){
                btn.addEventListener("click",function(e){
                    if(!confirm("确定要删除吗？此操作不可恢复。")){e.preventDefault()}
                })
            });
            // Auto-hide alerts
            setTimeout(function(){
                document.querySelectorAll(".alert").forEach(function(a){a.style.opacity="0";a.style.transition="opacity 0.5s";setTimeout(function(){a.remove()},500)})
            },3000);
        </script>';
        echo '</body></html>';
    }

    private function menuItem(string $slug, string $label, string $icon, string $active, int $badge = 0): void
    {
        $url = $this->adminUrl($slug === 'dashboard' ? '' : $slug);
        $cls = $active === $slug ? 'active' : '';
        $badgeHtml = $badge > 0 ? '<span style="margin-left:auto;background:#ff3b30;color:#fff;font-size:11px;padding:1px 7px;border-radius:10px;font-weight:600;line-height:1.4">' . $badge . '</span>' : '';
        echo '<a href="' . $url . '" class="' . $cls . '"><span class="nav-icon">' . $icon . '</span> ' . $label . $badgeHtml . '</a>';
    }

    private function menuDivider(string $label): void
    {
        echo '<div class="sidebar-divider">' . htmlspecialchars($label) . '</div>';
    }

    private function pendingCommentCount(): int
    {
        try {
            $db = \LyBlog\Core\Database::getInstance();
            if ($db) return $db->count('comments', "status = 'pending'");
        } catch (\Exception $e) {}
        return 0;
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

    protected function table(array $headers, array $rows, string $empty = '暂无数据'): void
    {
        if (empty($rows)) {
            echo '<div class="card"><div class="empty"><div class="empty-icon">📭</div><p>' . $empty . '</p></div></div>';
            return;
        }
        echo '<div class="card"><div class="table-wrap"><table><thead><tr>';
        foreach ($headers as $h) echo '<th>' . htmlspecialchars($h) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) echo '<td>' . $cell . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
    }

    protected function pagination(array $data, string $baseUrl, string $query = ''): void
    {
        if ($data['last_page'] <= 1) return;

        $current = $data['current_page'];
        $last = $data['last_page'];
        $sep = strpos($baseUrl, '?') !== false ? '&' : '?';

        echo '<div class="pagination">';
        if ($current > 1) {
            echo '<a href="' . $baseUrl . $sep . 'page=' . ($current - 1) . $query . '">← 上一页</a>';
        }
        for ($i = max(1, $current - 2); $i <= min($last, $current + 2); $i++) {
            $cls = $i === $current ? 'active' : '';
            echo '<a href="' . $baseUrl . $sep . 'page=' . $i . $query . '" class="' . $cls . '">' . $i . '</a>';
        }
        if ($current < $last) {
            echo '<a href="' . $baseUrl . $sep . 'page=' . ($current + 1) . $query . '">下一页 →</a>';
        }
        echo '</div>';
    }

    protected function statusBadge(string $status): string
    {
        $map = [
            'published' => ['发布', 'success'],
            'draft'     => ['草稿', 'warning'],
            'pending'   => ['待审', 'warning'],
            'approved'  => ['通过', 'success'],
            'spam'      => ['垃圾', 'danger'],
            'private'   => ['私密', 'info'],
            'active'    => ['启用', 'success'],
            'disabled'  => ['禁用', 'danger'],
        ];
        if (isset($map[$status])) {
            return '<span class="badge badge-' . $map[$status][1] . '">' . $map[$status][0] . '</span>';
        }
        return '<span class="badge badge-info">' . htmlspecialchars($status) . '</span>';
    }

    protected function actionButtons(string $editUrl, string $deleteUrl): string
    {
        return '<div class="table-actions">'
            . '<a href="' . $editUrl . '">编辑</a>'
            . '<a href="' . $deleteUrl . '" class="delete-btn">删除</a>'
            . '</div>';
    }

    protected function flashMessages(): void
    {
        $error = Session::flash('error');
        $success = Session::flash('success');
        if ($error) $this->alert($error, 'danger');
        if ($success) $this->alert($success, 'success');
    }
}
