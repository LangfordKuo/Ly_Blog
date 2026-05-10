<?php

namespace LyBlog\Controllers\Admin;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Auth;
use LyBlog\Core\Config;
use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Sanitizer;
use LyBlog\Core\Validator;

class AuthController extends BaseController
{
    public function loginForm(Request $request)
    {
        if (Auth::check()) {
            $this->redirect($this->adminUrl());
        }

        $error = Session::flash('error');
        $remaining = Auth::getRateLimitRemaining();

        $this->renderLoginPage($error, $remaining);
    }

    public function login(Request $request)
    {
        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效，请重试');
            $this->redirect($this->adminUrl('login'));
            return;
        }

        $username = $request->getPost('username', '');
        $password = $request->getPost('password', '');

        $validator = Validator::quick(
            ['username' => $username, 'password' => $password],
            ['username' => 'required', 'password' => 'required'],
            ['username' => '用户名', 'password' => '密码']
        );

        if ($validator->fails()) {
            Session::flash('error', $validator->first());
            $this->redirect($this->adminUrl('login'));
            return;
        }

        if (Auth::isRateLimited()) {
            Session::flash('error', '登录尝试次数过多，请15分钟后再试');
            $this->redirect($this->adminUrl('login'));
            return;
        }

        $user = Auth::attempt($username, $password);

        if (!$user) {
            Session::flash('error', '用户名或密码错误');
            $this->redirect($this->adminUrl('login'));
            return;
        }

        if (!Auth::canAccessAdmin()) {
            Auth::logout();
            Session::flash('error', '无权访问管理后台');
            $this->redirect($this->adminUrl('login'));
            return;
        }

        $this->redirect($this->adminUrl());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $this->redirect($this->adminUrl('login'));
    }

    private function renderLoginPage(?string $error, int $remaining): void
    {
        $siteName = Config::get('site_name', 'LyBlog');
        $csrfField = Session::csrfField();

        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>登录 - ' . htmlspecialchars($siteName) . '</title>';
        echo '<style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            body{font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f5f7;color:#1d1d1f;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .container{width:100%;max-width:400px}
            .card{background:rgba(255,255,255,0.72);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.06);padding:40px}
            h1{font-size:24px;font-weight:500;margin-bottom:4px}
            .subtitle{color:#6e6e73;font-size:14px;margin-bottom:32px}
            .alert{background:rgba(255,59,48,0.08);color:#ff3b30;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;border:1px solid rgba(255,59,48,0.12)}
            .form-group{margin-bottom:20px}
            label{display:block;font-size:13px;font-weight:500;color:#6e6e73;margin-bottom:6px}
            input[type="text"],input[type="password"]{width:100%;padding:12px 16px;border:1px solid rgba(0,0,0,0.08);border-radius:8px;font-size:15px;font-family:inherit;color:#1d1d1f;background:rgba(255,255,255,0.6);transition:all 0.2s;outline:none}
            input:focus{border-color:#1d1d1f;box-shadow:0 0 0 3px rgba(0,0,0,0.06);background:#fff}
            .btn{width:100%;padding:12px;border:none;border-radius:8px;font-size:15px;font-weight:500;font-family:inherit;cursor:pointer;transition:all 0.2s}
            .btn-primary{background:#1d1d1f;color:#fff}
            .btn-primary:hover{background:#333;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.15)}
            .btn:disabled{opacity:0.5;cursor:not-allowed;transform:none}
            .back-link{text-align:center;margin-top:24px;font-size:13px}
            .back-link a{color:#6e6e73;text-decoration:none}
            .back-link a:hover{color:#1d1d1f}
            .attempts{font-size:12px;color:#6e6e73;margin-top:8px}
        </style>';
        echo '</head><body><div class="container"><div class="card">';
        echo '<h1>后台登录</h1>';
        echo '<p class="subtitle">' . htmlspecialchars($siteName) . ' 管理后台</p>';

        if ($error) {
            echo '<div class="alert">' . htmlspecialchars($error) . '</div>';
        }

        if ($remaining <= 2) {
            echo '<div class="alert" style="background:rgba(255,149,0,0.08);color:#ff9500;border-color:rgba(255,149,0,0.12);">剩余尝试次数: ' . $remaining . '</div>';
        }

        echo '<form method="post" action="' . $this->adminUrl('login') . '">';
        echo $csrfField;
        echo '<div class="form-group"><label>用户名或邮箱</label><input type="text" name="username" autocomplete="username" autofocus required></div>';
        echo '<div class="form-group"><label>密码</label><input type="password" name="password" autocomplete="current-password" required></div>';
        echo '<button type="submit" class="btn btn-primary" ' . ($remaining <= 0 ? 'disabled' : '') . '>登 录</button>';
        echo '<p class="attempts">剩余尝试: ' . $remaining . '/5 次</p>';
        echo '</form>';
        echo '<div class="back-link"><a href="' . $this->siteUrl() . '">← 返回前台</a></div>';
        echo '</div></div></body></html>';
    }
}
