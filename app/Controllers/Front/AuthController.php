<?php

namespace LyBlog\Controllers\Front;

use LyBlog\Controllers\BaseController;
use LyBlog\Core\Auth;
use LyBlog\Core\Config;
use LyBlog\Core\Request;
use LyBlog\Core\Session;
use LyBlog\Core\Validator;
use LyBlog\Models\User;

class AuthController extends BaseController
{
    public function loginForm(Request $request)
    {
        if (Auth::check()) {
            $this->redirect($this->siteUrl());
            return;
        }

        $error = Session::flash('error');
        $registrationOpen = Config::get('registration') == '1';

        $siteName = Config::get('site_name', 'LyBlog');
        $csrfField = Session::csrfField();

        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
        echo '<title>登录 - ' . htmlspecialchars($siteName) . '</title>';
        echo '<style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            body{font-family:-apple-system,sans-serif;background:#f5f5f7;color:#1d1d1f;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{background:rgba(255,255,255,0.72);backdrop-filter:blur(20px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.06);padding:40px;max-width:400px;width:100%}
            h1{font-size:22px;font-weight:500;margin-bottom:24px}
            .alert{background:rgba(255,59,48,0.08);color:#ff3b30;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
            .form-group{margin-bottom:18px}
            label{display:block;font-size:13px;color:#6e6e73;margin-bottom:6px}
            input[type="text"],input[type="email"],input[type="password"]{width:100%;padding:12px 16px;border:1px solid rgba(0,0,0,0.08);border-radius:8px;font-size:15px;font-family:inherit;outline:none;background:rgba(255,255,255,0.6);transition:all 0.2s}
            input:focus{border-color:#1d1d1f;box-shadow:0 0 0 3px rgba(0,0,0,0.06);background:#fff}
            .btn{width:100%;padding:12px;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;transition:all 0.2s;font-family:inherit}
            .btn-primary{background:#1d1d1f;color:#fff}
            .btn-primary:hover{background:#333;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.15)}
            .links{text-align:center;margin-top:20px;font-size:13px}
            .links a{color:#6e6e73;text-decoration:none;margin:0 8px}
            .links a:hover{color:#1d1d1f}
        </style>';
        echo '</head><body><div class="card"><h1>登录</h1>';
        if ($error) echo '<div class="alert">' . htmlspecialchars($error) . '</div>';
        echo '<form method="post" action="' . $this->siteUrl('login') . '">' . $csrfField;
        echo '<div class="form-group"><label>用户名或邮箱</label><input type="text" name="username" required autofocus></div>';
        echo '<div class="form-group"><label>密码</label><input type="password" name="password" required></div>';
        echo '<button type="submit" class="btn btn-primary">登 录</button></form>';
        echo '<div class="links"><a href="' . $this->siteUrl() . '">← 首页</a>';
        if ($registrationOpen) echo ' · <a href="' . $this->siteUrl('register') . '">注册账号</a>';
        echo '</div></div></body></html>';
    }

    public function login(Request $request)
    {
        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->siteUrl('login'));
            return;
        }

        $username = $request->getPost('username', '');
        $password = $request->getPost('password', '');

        $v = Validator::quick(
            ['username' => $username, 'password' => $password],
            ['username' => 'required', 'password' => 'required'],
            ['username' => '用户名', 'password' => '密码']
        );

        if ($v->fails()) {
            Session::flash('error', $v->first());
            $this->redirect($this->siteUrl('login'));
            return;
        }

        $user = Auth::attempt($username, $password);

        if (!$user) {
            Session::flash('error', '用户名或密码错误');
            $this->redirect($this->siteUrl('login'));
            return;
        }

        if (Auth::canAccessAdmin()) {
            $this->redirect($this->adminUrl());
        } else {
            $this->redirect($this->siteUrl('user'));
        }
    }

    public function registerForm(Request $request)
    {
        if (Auth::check()) {
            $this->redirect($this->siteUrl());
            return;
        }

        if (Config::get('registration') != '1') {
            $this->notFound();
            return;
        }

        $error = Session::flash('error');
        $siteName = Config::get('site_name', 'LyBlog');
        $csrfField = Session::csrfField();

        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">';
        echo '<title>注册 - ' . htmlspecialchars($siteName) . '</title>';
        echo '<style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            body{font-family:-apple-system,sans-serif;background:#f5f5f7;color:#1d1d1f;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
            .card{background:rgba(255,255,255,0.72);backdrop-filter:blur(20px);border:1px solid rgba(0,0,0,0.06);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,0.06);padding:40px;max-width:400px;width:100%}
            h1{font-size:22px;font-weight:500;margin-bottom:24px}
            .alert{background:rgba(255,59,48,0.08);color:#ff3b30;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px}
            .form-group{margin-bottom:18px}
            label{display:block;font-size:13px;color:#6e6e73;margin-bottom:6px}
            input[type="text"],input[type="email"],input[type="password"]{width:100%;padding:12px 16px;border:1px solid rgba(0,0,0,0.08);border-radius:8px;font-size:15px;font-family:inherit;outline:none;background:rgba(255,255,255,0.6);transition:all 0.2s}
            input:focus{border-color:#1d1d1f;box-shadow:0 0 0 3px rgba(0,0,0,0.06);background:#fff}
            .btn{width:100%;padding:12px;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;transition:all 0.2s;font-family:inherit}
            .btn-primary{background:#1d1d1f;color:#fff}
            .btn-primary:hover{background:#333;transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.15)}
            .links{text-align:center;margin-top:20px;font-size:13px}
            .links a{color:#6e6e73;text-decoration:none;margin:0 8px}
            .links a:hover{color:#1d1d1f}
            .help{font-size:12px;color:#6e6e73;margin-top:4px}
        </style>';
        echo '</head><body><div class="card"><h1>注册</h1>';
        if ($error) echo '<div class="alert">' . htmlspecialchars($error) . '</div>';
        echo '<form method="post" action="' . $this->siteUrl('register') . '">' . $csrfField;
        echo '<div class="form-group"><label>用户名</label><input type="text" name="username" required autofocus><p class="help">3-50个字符，字母数字下划线</p></div>';
        echo '<div class="form-group"><label>邮箱</label><input type="email" name="email" required></div>';
        echo '<div class="form-group"><label>密码</label><input type="password" name="password" required minlength="6"><p class="help">至少6个字符</p></div>';
        echo '<div class="form-group"><label>确认密码</label><input type="password" name="password_confirm" required minlength="6"></div>';
        echo '<button type="submit" class="btn btn-primary">注 册</button></form>';
        echo '<div class="links"><a href="' . $this->siteUrl() . '">← 首页</a> · <a href="' . $this->siteUrl('login') . '">登录</a></div>';
        echo '</div></body></html>';
    }

    public function register(Request $request)
    {
        if (Config::get('registration') != '1') {
            $this->notFound();
            return;
        }

        if (!Session::validateCsrf()) {
            Session::flash('error', '安全令牌无效');
            $this->redirect($this->siteUrl('register'));
            return;
        }

        $data = [
            'username' => $request->getPost('username', ''),
            'email'    => $request->getPost('email', ''),
            'password' => $request->getPost('password', ''),
        ];
        $passwordConfirm = $request->getPost('password_confirm', '');

        $v = Validator::quick($data, [
            'username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'email'    => 'required|email|max:100',
            'password' => 'required|min:6|max:100',
        ], ['username' => '用户名', 'email' => '邮箱', 'password' => '密码']);

        if ($v->fails()) {
            Session::flash('error', $v->first());
            $this->redirect($this->siteUrl('register'));
            return;
        }

        if ($data['password'] !== $passwordConfirm) {
            Session::flash('error', '两次输入的密码不一致');
            $this->redirect($this->siteUrl('register'));
            return;
        }

        if (User::findByUsername($data['username'])) {
            Session::flash('error', '用户名已存在');
            $this->redirect($this->siteUrl('register'));
            return;
        }

        if (User::findByEmail($data['email'])) {
            Session::flash('error', '邮箱已被注册');
            $this->redirect($this->siteUrl('register'));
            return;
        }

        $id = User::createUser($data);
        if ($id) {
            Auth::attempt($data['username'], $data['password']);
            $this->redirect($this->siteUrl());
        } else {
            Session::flash('error', '注册失败，请重试');
            $this->redirect($this->siteUrl('register'));
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $this->redirect($this->siteUrl());
    }
}
