<?php

namespace LyBlog\Controllers;

use LyBlog\Core\Config;
use LyBlog\Core\Session;
use LyBlog\Core\View;
use LyBlog\Core\Theme;

class BaseController
{
    protected $view;

    public function __construct()
    {
        $theme = Config::get('theme', 'Default');
        $themePath = THEMES_DIR . '/' . $theme . '/templates';
        $this->view = new View($themePath);

        $this->view->addGlobal('site_name', Config::get('site_name', 'LyBlog'));
        $this->view->addGlobal('site_url', rtrim(Config::get('site_url', '/'), '/'));
        $this->view->addGlobal('site_description', Config::get('site_description', ''));
        $this->view->addGlobal('site_keywords', Config::get('site_keywords', ''));
        $this->view->addGlobal('footer_text', Config::get('footer_text', ''));
        $this->view->addGlobal('now_year', date('Y'));
        $this->view->addGlobal('is_logged_in', Session::isLoggedIn());

        // Theme settings
        $themeConfig = Theme::config();
        $this->view->addGlobal('theme_css_vars', Theme::cssVars());
        $this->view->addGlobal('theme_body_class', Theme::bodyClass());
        $this->view->addGlobal('theme', $themeConfig);
        $this->view->addGlobal('t_dark_mode', $themeConfig['dark_mode'] ?? 'light');
        $this->view->addGlobal('t_card_style', $themeConfig['card_style'] ?? 'glass');
        $this->view->addGlobal('t_show_cover', $themeConfig['show_cover'] ?? '1');
        $this->view->addGlobal('t_primary_color', $themeConfig['primary_color'] ?? '');
    }

    protected function render(string $template, array $data = []): string
    {
        return $this->view->render($template, $data);
    }

    protected function display(string $template, array $data = []): void
    {
        $this->view->display($template, $data);
    }

    protected function redirect(string $url, int $code = 302): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Location: ' . $url);
        }
        echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></head><body></body></html>';
        exit;
    }

    protected function siteUrl(string $path = ''): string
    {
        return rtrim(Config::get('site_url', ''), '/') . '/' . ltrim($path, '/');
    }

    protected function adminUrl(string $path = ''): string
    {
        $adminPath = Config::get('admin_path', 'admin');
        return $this->siteUrl($adminPath . '/' . ltrim($path, '/'));
    }

    protected function json(array $data, int $code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function isLoggedIn(): bool
    {
        return Session::isLoggedIn();
    }

    protected function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect($this->siteUrl('login'));
        }
    }

    protected function notFound(): void
    {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>404</title></head>';
        echo '<body style="font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f7;">';
        echo '<div style="text-align:center;"><h1 style="font-size:96px;font-weight:200;margin:0;color:#1d1d1f;">404</h1><p style="color:#6e6e73;">页面未找到</p><a href="' . $this->siteUrl() . '" style="color:#1d1d1f;">← 返回首页</a></div>';
        echo '</body></html>';
        exit;
    }

    protected function csrfField(): string
    {
        return Session::csrfField();
    }

    protected function validateCsrf(): bool
    {
        return Session::validateCsrf();
    }
}
