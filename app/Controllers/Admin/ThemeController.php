<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Config;
use LyBlog\Core\Database;
use LyBlog\Core\Theme;

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
            echo '<div style="margin-top:8px;display:flex;gap:8px">';
            if (!$isActive) {
                echo '<a href="' . $this->adminUrl('themes/activate/' . $theme['slug']) . '" class="btn btn-sm btn-primary">启用</a>';
            }
            if ($isActive && $theme['has_settings']) {
                echo '<a href="' . $this->adminUrl('themes/settings/' . $theme['slug']) . '" class="btn btn-sm btn-secondary">⚙ 设置</a>';
            }
            echo '</div></div></div>';
        }
        $this->adminFooter();
    }

    public function settings(Request $request, $slug = null)
    {
        $slug = $slug ?? Config::get('theme', 'Default');
        $schema = Theme::schema($slug);

        if (empty($schema)) {
            Session::flash('error', '该主题没有可配置项');
            $this->redirect($this->adminUrl('themes'));
            return;
        }

        $config = Theme::config($slug);
        $info = $this->getThemeInfo($slug);

        $this->adminHeader('主题设置 - ' . $info['name'], 'themes');
        $this->pageHeader($info['name'] . ' 设置', '<a href="' . $this->adminUrl('themes') . '" class="btn btn-secondary">← 返回主题</a>');
        $this->flashMessages();

        echo '<div class="card"><form method="post" action="' . $this->adminUrl('themes/settings/' . $slug . '/save') . '">';
        echo Session::csrfField();

        foreach ($schema as $key => $def) {
            $value = $config[$key] ?? $def['default'] ?? '';
            $label = $def['label'] ?? $key;
            $type  = $def['type'] ?? 'text';
            $help  = $def['help'] ?? '';

            echo '<div class="form-group">';
            echo '<label>' . htmlspecialchars($label) . '</label>';

            switch ($type) {
                case 'color':
                    echo '<div style="display:flex;align-items:center;gap:10px">';
                    echo '<input type="color" name="config[' . $key . ']" value="' . htmlspecialchars($value) . '" style="width:48px;height:36px;padding:2px;border-radius:6px;cursor:pointer">';
                    echo '<input type="text" value="' . htmlspecialchars($value) . '" onchange="this.previousElementSibling.value=this.value" style="flex:1">';
                    echo '</div>';
                    break;

                case 'select':
                    echo '<select name="config[' . $key . ']">';
                    foreach (($def['options'] ?? []) as $optVal => $optLabel) {
                        $sel = $value == $optVal ? 'selected' : '';
                        echo '<option value="' . $optVal . '" ' . $sel . '>' . htmlspecialchars($optLabel) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'toggle':
                    echo '<select name="config[' . $key . ']">';
                    echo '<option value="1" ' . ($value == '1' ? 'selected' : '') . '>启用</option>';
                    echo '<option value="0" ' . ($value == '0' ? 'selected' : '') . '>禁用</option>';
                    echo '</select>';
                    break;

                case 'textarea':
                    echo '<textarea name="config[' . $key . ']" rows="6" style="font-family:monospace;font-size:13px">' . htmlspecialchars($value) . '</textarea>';
                    break;

                default:
                    echo '<input type="text" name="config[' . $key . ']" value="' . htmlspecialchars($value) . '">';
            }

            if ($help) {
                echo '<p class="help-text" style="font-size:12px;color:var(--text-2);margin-top:4px">' . htmlspecialchars($help) . '</p>';
            }
            echo '</div>';
        }

        echo '<button type="submit" class="btn btn-primary">保存设置</button>';
        echo '</form></div>';

        $this->adminFooter();
    }

    public function saveSettings(Request $request, $slug = null)
    {
        $slug = $slug ?? Config::get('theme', 'Default');

        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->adminUrl('themes/settings/' . $slug));
            return;
        }

        $config = $request->getPost('config', []);
        if (is_array($config)) {
            Theme::save($config, $slug);
        }

        Session::flash('success', '主题设置已保存');
        $this->redirect($this->adminUrl('themes/settings/' . $slug));
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
                    'slug'         => $name,
                    'name'         => $info['name'] ?? $name,
                    'version'      => $info['version'] ?? '1.0',
                    'author'       => $info['author'] ?? '',
                    'description'  => $info['description'] ?? '',
                    'has_settings' => !empty($info['config']),
                    'screenshot'   => file_exists($themeDir . '/screenshot.png') ? Config::get('site_url') . '/themes/' . $name . '/screenshot.png' : null,
                ];
            }
        }
        return $themes;
    }

    private function getThemeInfo(string $slug): array
    {
        $jsonFile = THEMES_DIR . '/' . $slug . '/theme.json';
        if (file_exists($jsonFile)) {
            $info = json_decode(file_get_contents($jsonFile), true);
            return ['name' => $info['name'] ?? $slug];
        }
        return ['name' => $slug];
    }
}
