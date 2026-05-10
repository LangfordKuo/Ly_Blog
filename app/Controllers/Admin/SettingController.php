<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Config;
use LyBlog\Core\Database;
use LyBlog\Core\Cache;

class SettingController extends BaseAdminController
{
    public function index(Request $request)
    {
        $db = Database::getInstance();
        $settings = [];

        // Load all settings
        $defaults = [
            'site_name'      => 'LyBlog',
            'site_description' => '',
            'site_keywords'  => '',
            'admin_path'     => 'admin',
            'timezone'       => 'Asia/Shanghai',
            'log_level'      => 'error',
            'cache_driver'   => 'file',
            'registration'   => '0',
            'comment_moderation' => 'approved',
            'comment_notification' => '0',
            'smtp_host'      => '',
            'smtp_port'      => '587',
            'smtp_user'      => '',
            'smtp_pass'      => '',
            'smtp_from'      => '',
            'footer_text'    => '',
        ];

        if ($db) {
            $rows = $db->fetchAll("SELECT `key`, `value` FROM {settings}");
            foreach ($rows as $r) {
                $defaults[$r['key']] = $r['value'];
            }
        }
        $settings = $defaults;

        $this->adminHeader('系统设置', 'settings');
        $this->pageHeader('系统设置');
        $this->flashMessages();

        echo '<div class="card">';
        echo '<form method="post" action="' . $this->adminUrl('settings') . '">';
        echo Session::csrfField();

        echo '<h3 style="font-size:16px;font-weight:500;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)">基本设置</h3>';
        echo '<div class="form-row"><div class="form-group"><label>网站名称</label><input type="text" name="site_name" value="' . htmlspecialchars($settings['site_name']) . '"></div>';
        echo '<div class="form-group"><label>时区</label><select name="timezone">';
        foreach (['Asia/Shanghai', 'Asia/Tokyo', 'America/New_York', 'Europe/London'] as $tz) {
            echo '<option value="' . $tz . '" ' . ($settings['timezone'] === $tz ? 'selected' : '') . '>' . $tz . '</option>';
        }
        echo '</select></div></div>';
        echo '<div class="form-group"><label>网站描述</label><input type="text" name="site_description" value="' . htmlspecialchars($settings['site_description']) . '"></div>';
        echo '<div class="form-group"><label>关键词 (逗号分隔)</label><input type="text" name="site_keywords" value="' . htmlspecialchars($settings['site_keywords']) . '"></div>';
        echo '<div class="form-group"><label>页脚文本</label><input type="text" name="footer_text" value="' . htmlspecialchars($settings['footer_text']) . '"></div>';

        echo '<h3 style="font-size:16px;font-weight:500;margin:24px 0 16px;padding-bottom:8px;border-bottom:1px solid var(--border)">安全与注册</h3>';
        echo '<div class="form-row">';
        echo '<div class="form-group"><label>开放注册</label><select name="registration"><option value="1" ' . ($settings['registration'] == '1' ? 'selected' : '') . '>开启</option><option value="0" ' . ($settings['registration'] == '0' ? 'selected' : '') . '>关闭</option></select></div>';
        echo '<div class="form-group"><label>后台路径 (修改后需重新登录)</label><input type="text" name="admin_path" value="' . htmlspecialchars(Config::get('admin_path', 'admin')) . '"></div>';
        echo '</div>';

        echo '<h3 style="font-size:16px;font-weight:500;margin:24px 0 16px;padding-bottom:8px;border-bottom:1px solid var(--border)">缓存</h3>';
        echo '<div class="form-row"><div class="form-group"><label>缓存驱动</label><select name="cache_driver">';
        foreach (['none' => '不缓存', 'file' => '文件缓存', 'redis' => 'Redis'] as $k => $v) {
            echo '<option value="' . $k . '" ' . ($settings['cache_driver'] === $k ? 'selected' : '') . '>' . $v . '</option>';
        }
        echo '</select></div><div class="form-group"><label>日志等级</label><select name="log_level">';
        foreach (['none' => '关闭', 'error' => '错误', 'warning' => '警告', 'info' => '信息', 'debug' => '调试'] as $k => $v) {
            echo '<option value="' . $k . '" ' . ($settings['log_level'] === $k ? 'selected' : '') . '>' . $v . '</option>';
        }
        echo '</select></div></div>';

        echo '<button type="submit" class="btn btn-primary" style="margin-top:8px">保存设置</button>';
        echo '</form></div>';

        // Cache clear button
        echo '<div class="card" style="margin-top:20px"><div class="card-header"><h2>缓存管理</h2></div>';
        echo '<p style="color:var(--text-2);margin-bottom:16px">清除所有缓存文件（包括模板编译缓存、数据缓存）</p>';
        echo '<a href="' . $this->adminUrl('settings/clear-cache') . '" class="btn btn-secondary" onclick="return confirm(\'确定要清除缓存吗？\')">清除缓存</a>';
        echo '</div>';

        $this->adminFooter();
    }

    public function update(Request $request)
    {
        if (!Session::validateCsrf()) { Session::flash('error', '安全令牌无效'); $this->redirect($this->adminUrl('settings')); return; }

        $fields = [
            'site_name', 'site_description', 'site_keywords', 'timezone',
            'log_level', 'cache_driver', 'registration', 'comment_moderation',
            'comment_notification', 'footer_text',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
        ];

        $db = Database::getInstance();
        foreach ($fields as $field) {
            $value = $request->getPost($field, '');
            $existing = $db->fetch("SELECT id FROM {settings} WHERE `key` = ?", [$field]);
            if ($existing) {
                $db->update('settings', ['value' => $value], '`key` = ?', [$field]);
            } else {
                $db->insert('settings', ['key' => $field, 'value' => $value]);
            }
        }

        \LyBlog\Core\Config::clearDbCache();

        Session::flash('success', '设置已保存');
        $this->redirect($this->adminUrl('settings'));
    }

    public function clearCache(Request $request)
    {
        $cacheDir = STORAGE_DIR . '/cache';
        $this->clearDir($cacheDir);
        Session::flash('success', '缓存已清除');
        $this->redirect($this->adminUrl('settings'));
    }

    private function clearDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->clearDir($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}
