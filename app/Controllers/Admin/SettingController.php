<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Config;
use LyBlog\Core\Database;
use LyBlog\Core\Auth;
use LyBlog\Core\Mailer;

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

        // Notification section — super_admin only
        if (\LyBlog\Core\Auth::hasRole('super_admin')) {
            echo '<h3 style="font-size:16px;font-weight:500;margin:24px 0 16px;padding-bottom:8px;border-bottom:1px solid var(--border)">🔔 邮件通知 (仅超级管理员可见)</h3>';

            echo '<div class="form-row"><div class="form-group"><label>评论通知</label><select name="comment_notification">';
            echo '<option value="0" ' . ($settings['comment_notification'] == '0' ? 'selected' : '') . '>关闭</option>';
            echo '<option value="1" ' . ($settings['comment_notification'] == '1' ? 'selected' : '') . '>开启</option>';
            echo '</select><p class="help-text" style="font-size:12px;color:var(--text-2)">有新评论时发送邮件通知</p></div>';

            echo '<div class="form-group"><label>评论审核</label><select name="comment_moderation">';
            echo '<option value="approved" ' . (($settings['comment_moderation'] ?? 'approved') === 'approved' ? 'selected' : '') . '>直接发布</option>';
            echo '<option value="pending" ' . (($settings['comment_moderation'] ?? '') === 'pending' ? 'selected' : '') . '>先审后发</option>';
            echo '</select></div></div>';

            echo '<h4 style="font-size:14px;font-weight:500;margin:16px 0 12px;color:var(--text-2)">SMTP 服务器配置</h4>';
            echo '<div class="form-row"><div class="form-group"><label>SMTP 主机</label><input type="text" name="smtp_host" value="' . htmlspecialchars($settings['smtp_host'] ?? '') . '" placeholder="smtp.example.com"></div>';
            echo '<div class="form-group"><label>端口</label><input type="number" name="smtp_port" value="' . htmlspecialchars($settings['smtp_port'] ?? '587') . '"></div></div>';
            echo '<div class="form-row"><div class="form-group"><label>发件邮箱</label><input type="email" name="smtp_user" value="' . htmlspecialchars($settings['smtp_user'] ?? '') . '" placeholder="user@example.com"></div>';
            echo '<div class="form-group"><label>SMTP 密码 / 授权码</label><input type="password" name="smtp_pass" value="' . htmlspecialchars($settings['smtp_pass'] ?? '') . '" placeholder="留空不修改"></div></div>';
            echo '<div class="form-group"><label>发件人地址</label><input type="email" name="smtp_from" value="' . htmlspecialchars($settings['smtp_from'] ?? '') . '" placeholder="默认使用 SMTP 邮箱"><p class="help-text" style="font-size:12px;color:var(--text-2)">接收通知的邮箱地址</p></div>';

            echo '<div style="display:flex;align-items:center;gap:12px;margin-top:16px">';
            echo '<button type="button" class="btn btn-secondary" id="test-mail-btn" onclick="testMail()">📧 发送测试邮件</button>';
            echo '<span id="test-mail-result" style="font-size:13px"></span>';
            echo '</div>';

            echo '<script>
                function testMail() {
                    var btn = document.getElementById("test-mail-btn");
                    var result = document.getElementById("test-mail-result");
                    btn.disabled = true;
                    btn.textContent = "发送中...";
                    result.textContent = "";
                    result.style.color = "";

                    var form = btn.closest("form");
                    var data = new FormData();
                    data.append("_csrf_token", form.querySelector("[name=_csrf_token]").value);
                    data.append("smtp_host", form.querySelector("[name=smtp_host]").value);
                    data.append("smtp_port", form.querySelector("[name=smtp_port]").value);
                    data.append("smtp_user", form.querySelector("[name=smtp_user]").value);
                    data.append("smtp_pass", form.querySelector("[name=smtp_pass]").value);
                    data.append("smtp_from", form.querySelector("[name=smtp_from]").value);

                    fetch("' . $this->adminUrl('settings/test-mail') . '", {
                        method: "POST",
                        body: data,
                        headers: {"X-Requested-With": "XMLHttpRequest"}
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        result.textContent = d.message;
                        result.style.color = d.success ? "#34c759" : "#ff3b30";
                    })
                    .catch(function(e) {
                        result.textContent = "请求失败: " + e.message;
                        result.style.color = "#ff3b30";
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.textContent = "📧 发送测试邮件";
                    });
                }
            </script>';
        }

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
            'log_level', 'cache_driver', 'registration',
            'footer_text',
        ];

        // Only super_admin can change notification/SMTP settings
        if (\LyBlog\Core\Auth::hasRole('super_admin')) {
            $fields = array_merge($fields, [
                'comment_moderation', 'comment_notification',
                'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from',
            ]);

            // Don't overwrite SMTP password with empty
            $smtpPass = $request->getPost('smtp_pass', '');
            if ($smtpPass === '') {
                unset($_POST['smtp_pass']);
            }
        }

        $db = Database::getInstance();
        foreach ($fields as $field) {
            $value = $request->getPost($field, '');

            // Skip empty SMTP password (keep existing)
            if ($field === 'smtp_pass' && $value === '') {
                continue;
            }

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

    public function testMail(Request $request)
    {
        if (!Auth::hasRole('super_admin')) {
            $this->json(['success' => false, 'message' => '权限不足'], 403);
            return;
        }

        if (!Session::validateCsrf()) {
            $this->json(['success' => false, 'message' => '安全令牌无效'], 403);
            return;
        }

        $host = $request->getPost('smtp_host', '');
        $port = $request->getPost('smtp_port', '587');
        $user = $request->getPost('smtp_user', '');
        $pass = $request->getPost('smtp_pass', '');
        $from = $request->getPost('smtp_from', '');

        // If password field is empty, use saved value
        if (empty($pass)) {
            $pass = Config::get('smtp_pass', '');
        }

        if (empty($host) || empty($user) || empty($pass)) {
            $this->json(['success' => false, 'message' => 'SMTP 配置不完整，请填写主机、邮箱和密码'], 400);
            return;
        }

        $to = $from ?: $user;

        $mailer = new Mailer([
            'smtp_host' => $host,
            'smtp_port' => $port,
            'smtp_user' => $user,
            'smtp_pass' => $pass,
            'smtp_from' => $from,
        ]);

        $siteName = Config::get('site_name', 'LyBlog');
        $body = '<div style="font-family:sans-serif;padding:20px">';
        $body .= '<h2 style="color:#1d1d1f">✅ SMTP 配置成功</h2>';
        $body .= '<p>来自 <strong>' . htmlspecialchars($siteName) . '</strong> 的测试邮件。</p>';
        $body .= '<p style="color:#6e6e73">如果你收到此邮件，说明 SMTP 配置正确无误。</p>';
        $body .= '<p style="color:#6e6e73;font-size:12px">发送时间: ' . date('Y-m-d H:i:s') . '</p>';
        $body .= '</div>';

        $result = $mailer->send($to, "[{$siteName}] SMTP 测试邮件", $body);

        if ($result) {
            $this->json(['success' => true, 'message' => "测试邮件已发送至 {$to}，请检查收件箱"]);
        } else {
            $this->json(['success' => false, 'message' => '发送失败: ' . $mailer->getLastError()]);
        }
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
