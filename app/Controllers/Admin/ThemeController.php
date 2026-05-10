<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Config;
use LyBlog\Core\Database;

class ThemeController extends BaseAdminController
{
    public function index(Request $request)
    {
        $currentTheme = Config::get('theme', 'Default');
        $themes = $this->scanThemes();

        $this->adminHeader('主题管理', 'themes');
        $this->pageHeader('主题管理');
        $this->flashMessages();

        foreach ($themes as $theme) {
            $isActive = $theme['slug'] === $currentTheme;
            echo '<div class="card" style="display:flex;align-items:center;gap:20px">';
            if ($theme['screenshot']) {
                echo '<img src="' . $theme['screenshot'] . '" style="width:200px;height:140px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">';
            } else {
                echo '<div style="width:200px;height:140px;background:rgba(0,0,0,0.04);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text-2);font-size:32px">🎨</div>';
            }
            echo '<div style="flex:1">';
            echo '<h3 style="font-size:18px;font-weight:500;margin-bottom:4px">' . htmlspecialchars($theme['name']) . ($isActive ? ' <span class="badge badge-success">当前</span>' : '') . '</h3>';
            echo '<p style="color:var(--text-2);font-size:14px;margin-bottom:4px">v' . htmlspecialchars($theme['version']) . ' by ' . htmlspecialchars($theme['author'] ?? 'Unknown') . '</p>';
            echo '<p style="color:var(--text-2);font-size:13px">' . htmlspecialchars($theme['description'] ?? '') . '</p>';
            if (!$isActive) {
                echo '<a href="' . $this->adminUrl('themes/activate/' . $theme['slug']) . '" class="btn btn-sm btn-primary" style="margin-top:8px">启用</a>';
            }
            echo '</div></div>';
        }
        $this->adminFooter();
    }

    public function activate(Request $request, $slug)
    {
        $db = Database::getInstance();
        $existing = $db->fetch("SELECT * FROM {settings} WHERE `key` = 'theme'");
        if ($existing) {
            $db->update('settings', ['value' => $slug], '`key` = ?', ['theme']);
        } else {
            $db->insert('settings', ['key' => 'theme', 'value' => $slug]);
        }
        Session::flash('success', '主题已切换为: ' . $slug);
        $this->redirect($this->adminUrl('themes'));
    }

    private function scanThemes(): array
    {
        $themes = [];
        $dir = THEMES_DIR;
        if (!is_dir($dir)) return $themes;

        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..') continue;
            $themeDir = $dir . '/' . $name;
            if (!is_dir($themeDir)) continue;

            $jsonFile = $themeDir . '/theme.json';
            if (file_exists($jsonFile)) {
                $info = json_decode(file_get_contents($jsonFile), true);
                $themes[] = [
                    'slug'        => $name,
                    'name'        => $info['name'] ?? $name,
                    'version'     => $info['version'] ?? '1.0',
                    'author'      => $info['author'] ?? '',
                    'description' => $info['description'] ?? '',
                    'screenshot'  => file_exists($themeDir . '/screenshot.png') ? Config::get('site_url') . '/themes/' . $name . '/screenshot.png' : null,
                ];
            }
        }
        return $themes;
    }
}
