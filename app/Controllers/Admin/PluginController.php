<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Database;

class PluginController extends BaseAdminController
{
    public function index(Request $request)
    {
        $plugins = $this->scanPlugins();
        $db = Database::getInstance();

        // Get active status from DB
        $activePlugins = [];
        if ($db) {
            $rows = $db->fetchAll("SELECT slug, status FROM {plugins}");
            foreach ($rows as $r) $activePlugins[$r['slug']] = (int) $r['status'];
        }

        $this->adminHeader('插件管理', 'plugins');
        $this->pageHeader('插件管理');
        $this->flashMessages();

        if (empty($plugins)) {
            echo '<div class="card"><div class="empty"><div class="empty-icon">🔌</div><p>暂无插件</p><p style="font-size:13px;margin-top:8px">将插件放入 plugins/ 目录即可</p></div></div>';
        } else {
            foreach ($plugins as $plugin) {
                $isActive = ($activePlugins[$plugin['slug']] ?? 1) === 1;
                $actionUrl = $isActive ? $this->adminUrl('plugins/deactivate/' . $plugin['slug']) : $this->adminUrl('plugins/activate/' . $plugin['slug']);
                $actionLabel = $isActive ? '停用' : '启用';

                echo '<div class="card" style="display:flex;align-items:center;justify-content:space-between">';
                echo '<div>';
                echo '<h3 style="font-size:16px;font-weight:500;margin-bottom:4px">' . htmlspecialchars($plugin['name']) . ' <span class="badge badge-' . ($isActive ? 'success' : 'warning') . '">' . ($isActive ? '已启用' : '已停用') . '</span></h3>';
                echo '<p style="color:var(--text-2);font-size:13px">v' . htmlspecialchars($plugin['version']) . ' by ' . htmlspecialchars($plugin['author'] ?? '') . '</p>';
                echo '<p style="color:var(--text-2);font-size:13px">' . htmlspecialchars($plugin['description'] ?? '') . '</p>';
                echo '</div>';
                echo '<a href="' . $actionUrl . '" class="btn btn-sm ' . ($isActive ? 'btn-secondary' : 'btn-primary') . '">' . $actionLabel . '</a>';
                echo '</div>';
            }
        }
        $this->adminFooter();
    }

    public function activate(Request $request, $slug)
    {
        $db = Database::getInstance();
        $existing = $db->fetch("SELECT * FROM {plugins} WHERE slug = ?", [$slug]);
        if ($existing) {
            $db->update('plugins', ['status' => 1], 'slug = ?', [$slug]);
        } else {
            $db->insert('plugins', ['name' => $slug, 'slug' => $slug, 'version' => '1.0', 'status' => 1]);
        }
        Session::flash('success', '插件已启用');
        $this->redirect($this->adminUrl('plugins'));
    }

    public function deactivate(Request $request, $slug)
    {
        $db = Database::getInstance();
        $db->update('plugins', ['status' => 0], 'slug = ?', [$slug]);
        Session::flash('success', '插件已停用');
        $this->redirect($this->adminUrl('plugins'));
    }

    private function scanPlugins(): array
    {
        $plugins = [];
        $dir = PLUGINS_DIR;
        if (!is_dir($dir)) return $plugins;

        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            $pluginDir = $dir . '/' . $name;
            if (!is_dir($pluginDir)) continue;

            $jsonFile = $pluginDir . '/plugin.json';
            if (file_exists($jsonFile)) {
                $info = json_decode(file_get_contents($jsonFile), true);
                $plugins[] = [
                    'slug'        => $info['slug'] ?? $name,
                    'name'        => $info['name'] ?? $name,
                    'version'     => $info['version'] ?? '1.0',
                    'author'      => $info['author'] ?? '',
                    'description' => $info['description'] ?? '',
                ];
            }
        }
        return $plugins;
    }
}
